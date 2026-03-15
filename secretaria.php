<?php
/**
 * JMM SYSTEM - SECRETARIA & MARKETING PRO (VERSÃO COM GESTÃO DE PROJETOS)
 */
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once 'config.php';

$lib_carregada = file_exists('SimpleXLSX.php');
if ($lib_carregada) { require_once 'SimpleXLSX.php'; }

if (!isset($_SESSION['user_id']) || ($_SESSION['nivel'] !== 'admin' && $_SESSION['nivel'] !== 'secretaria')) {
    header("Location: sistema_dashboard.php"); exit;
}

$user_nome = $_SESSION['user_nome'];

// Funções auxiliares de data
function dataBR($data) { return ($data && $data != '0000-00-00') ? date('d/m/Y', strtotime($data)) : ''; }
function dataSQL($data) { 
    if(strpos($data, '/')) {
        $p = explode('/', $data);
        return $p[2].'-'.$p[1].'-'.$p[0];
    }
    return $data;
}

// --- AÇÕES GET (VCF) ---
if (isset($_GET['gerar_vcf'])) {
    $proj_id = $_GET['gerar_vcf'];
    $stmt = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
    $stmt->execute([$proj_id]);
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/vcard');
    header('Content-Disposition: attachment; filename="Agenda_JMM_Proj'.$proj_id.'.vcf"');
    foreach ($contatos as $c) {
        echo "BEGIN:VCARD\nVERSION:3.0\nFN:JMM " . $c['nome'] . "\nTEL;TYPE=CELL:+55" . $c['telefone'] . "\nEND:VCARD\n";
    }
    exit;
}

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    // 1. SALVAR / ATUALIZAR PROJETO
    if ($acao == 'novo_projeto') {
        $id_p = $_POST['id_projeto_edit'] ?? '';
        $nome = trim($_POST['nome_p']);
        $ini  = dataSQL($_POST['data_i']);
        $fim  = dataSQL($_POST['data_f']);
        $msg_txt = $_POST['mensagem'];

        if (!empty($id_p)) {
            // UPDATE
            $pdo->prepare("UPDATE projetos SET nome_projeto=?, data_inicio=?, data_fim=?, mensagem=? WHERE id=?")
                ->execute([$nome, $ini, $fim, $msg_txt, $id_p]);
            $res = "atualizado";
        } else {
            // INSERT
            $pdo->prepare("INSERT INTO projetos (nome_projeto, data_inicio, data_fim, mensagem) VALUES (?, ?, ?, ?)")
                ->execute([$nome, $ini, $fim, $msg_txt]);
            $res = "criado";
        }
        header("Location: secretaria.php?tab=projetos&projeto_ok=$res"); exit;
    }

    // 2. EXCLUIR PROJETO
    if ($acao == 'deletar_projeto') {
        $id_p = $_POST['id_p'];
        // Deleta contatos vinculados primeiro (ou deixa o ON DELETE CASCADE do banco agir)
        $pdo->prepare("DELETE FROM marketing_contatos WHERE projeto_id = ?")->execute([$id_p]);
        $pdo->prepare("DELETE FROM projetos WHERE id = ?")->execute([$id_p]);
        header("Location: secretaria.php?tab=projetos&del_ok=1"); exit;
    }

    // 3. IMPORTAR EXCEL
    if ($acao == 'importar_marketing' && $lib_carregada) {
        $proj_id = $_POST['projeto_id'];
        if (isset($_FILES['arquivo_excel']) && $_FILES['arquivo_excel']['error'] == 0) {
            $classe = class_exists('\Shuchkin\SimpleXLSX') ? '\Shuchkin\SimpleXLSX' : 'SimpleXLSX';
            if ($xlsx = $classe::parse($_FILES['arquivo_excel']['tmp_name'])) {
                $stmt_ex = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
                $stmt_ex->execute([$proj_id]);
                $db = $stmt_ex->fetchAll(PDO::FETCH_ASSOC);
                $nomes_db = array_column($db, 'nome'); $tels_db = array_column($db, 'telefone');
                
                $novos = 0; $dups = 0;
                foreach ($xlsx->rows() as $i => $linha) {
                    if ($i == 0) continue;
                    $nome = trim($linha[0] ?? '');
                    $tel  = preg_replace('/\D/', '', $linha[1] ?? '');
                    if (!empty($nome) && !empty($tel)) {
                        if (in_array($nome, $nomes_db) || in_array($tel, $tels_db)) { $dups++; }
                        else {
                            $pdo->prepare("INSERT INTO marketing_contatos (projeto_id, nome, telefone) VALUES (?, ?, ?)")->execute([$proj_id, $nome, $tel]);
                            $nomes_db[] = $nome; $tels_db[] = $tel; $novos++;
                        }
                    }
                }
                header("Location: secretaria.php?tab=marketing&n=$novos&d=$dups&f_projeto=$proj_id"); exit;
            }
        }
    }

    // 4. AÇÃO EM MASSA (SETAR COMO ENVIADO)
    if ($acao == 'massa_enviado') {
        $ids = $_POST['ids_contatos'] ?? [];
        if(!empty($ids)){
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id IN ($inQuery)")->execute($ids);
        }
        header("Location: secretaria.php?tab=marketing&massa_ok=".count($ids)."&f_projeto=".$_POST['f_projeto']); exit;
    }

    // 5. MARCAR INDIVIDUAL (AJAX)
    if ($acao == 'marcar_enviado') {
        $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id = ?")->execute([$_POST['contato_id']]);
        echo json_encode(['status' => 'ok']); exit;
    }
}

// CONSULTAS
$projetos_all = $pdo->query("SELECT * FROM projetos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$id_filtro = $_GET['f_projeto'] ?? ($projetos_all[0]['id'] ?? 0);

$projeto_ativo = null;
$contatos_mkt = [];
if($id_filtro > 0){
    foreach($projetos_all as $p) if($p['id'] == $id_filtro) $projeto_ativo = $p;
    $stmt = $pdo->prepare("SELECT * FROM marketing_contatos WHERE projeto_id = ? ORDER BY status ASC, nome ASC");
    $stmt->execute([$id_filtro]);
    $contatos_mkt = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>JMM Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 80px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.75rem; color: #555; background: #fff; border: 1px solid #eee; margin: 2px; }
        .nav-pills .nav-link.active { background-color: #6f42c1 !important; color: #fff !important; }
        .btn-whatsapp { background-color: #25d366; color: white; border-radius: 50px; padding: 5px 12px; font-weight: bold; text-decoration: none; font-size: 0.8rem; }
        .table-small { font-size: 0.8rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container d-flex justify-content-between">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-arrow-left fs-4"></i></a>
        <h6 class="m-0 fw-bold">SECRETARIA / MKT</h6>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
    </div>
</nav>

<div class="container">

    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded-pill shadow-sm">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-projetos">PROJETOS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-marketing">GESTÃO DE LISTA</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- ABA PROJETOS -->
        <div class="tab-pane fade show active" id="tab-projetos">
            <div class="card p-4 border-top border-5 border-primary shadow">
                <h6 class="fw-bold mb-3" id="titulo_form_p">Novo Projeto & Mensagem</h6>
                <form method="POST" id="form_projeto">
                    <input type="hidden" name="form_acao" value="novo_projeto">
                    <input type="hidden" name="id_projeto_edit" id="id_projeto_edit">
                    
                    <div class="mb-3"><label class="small fw-bold">NOME DO PROJETO</label><input type="text" name="nome_p" id="p_nome" class="form-control rounded-3" placeholder="Ex: Rifa 2026" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="small fw-bold">DATA INÍCIO</label><input type="text" name="data_i" id="p_ini" class="form-control" placeholder="00/00/0000" onkeyup="maskData(this)" maxlength="10"></div>
                        <div class="col-6"><label class="small fw-bold">DATA FIM</label><input type="text" name="data_f" id="p_fim" class="form-control" placeholder="00/00/0000" onkeyup="maskData(this)" maxlength="10"></div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">MENSAGEM DO DISPARO</label>
                        <textarea name="mensagem" id="p_msg" class="form-control rounded-3" rows="3" placeholder="Olá [NOME]..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" id="btn_salvar_p" class="btn btn-primary w-100 fw-bold py-2 shadow">SALVAR PROJETO</button>
                        <button type="button" id="btn_cancelar_p" class="btn btn-light border d-none" onclick="location.reload()">CANCELAR</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0 table-small">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Projeto</th>
                            <th>Período</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($projetos_all as $p): ?>
                        <tr>
                            <td class="ps-3"><b><?=$p['nome_projeto']?></b></td>
                            <td><?=dataBR($p['data_inicio'])?> <i class="bi bi-arrow-right"></i> <?=dataBR($p['data_fim'])?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <button class="btn btn-link text-primary p-0 me-2" onclick='povoarEdicaoProjeto(<?=json_encode($p)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Excluir projeto e todos os contatos?')">
                                    <input type="hidden" name="form_acao" value="deletar_projeto">
                                    <input type="hidden" name="id_p" value="<?=$p['id']?>">
                                    <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($projetos_all)): ?><tr><td colspan="3" class="text-center p-3">Nenhum projeto cadastrado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ABA MARKETING -->
        <div class="tab-pane fade" id="tab-marketing">
            <!-- Formulário de Importação Simplificado -->
            <div class="card p-4 border-top border-5 border-success text-center shadow-sm">
                <h6 class="fw-bold"><i class="bi bi-file-earmark-excel"></i> Importar Excel</h6>
                <form method="POST" enctype="multipart/form-data" class="mt-2">
                    <input type="hidden" name="form_acao" value="importar_marketing">
                    <select name="projeto_id" class="form-select mb-2" required>
                        <option value="">Para qual projeto?</option>
                        <?php foreach($projetos_all as $p): ?><option value="<?=$p['id']?>" <?=($id_filtro==$p['id']?'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?>
                    </select>
                    <input type="file" name="arquivo_excel" class="form-control mb-2" accept=".xlsx" required>
                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">SUBIR LISTA</button>
                </form>
            </div>

            <!-- Filtro e Exportação -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <form method="GET">
                    <input type="hidden" name="tab" value="marketing">
                    <select name="f_projeto" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary" onchange="this.form.submit()">
                        <?php foreach($projetos_all as $p): ?>
                            <option value="<?=$p['id']?>" <?=($id_filtro == $p['id']?'selected':'')?>><?=$p['nome_projeto']?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if($id_filtro): ?>
                <a href="?gerar_vcf=<?=$id_filtro?>" class="btn btn-dark btn-sm rounded-pill px-3 fw-bold"><i class="bi bi-download"></i> AGENDA</a>
                <?php endif; ?>
            </div>

            <!-- Tabela de Marketing -->
            <form method="POST">
                <input type="hidden" name="form_acao" value="massa_enviado">
                <input type="hidden" name="f_projeto" value="<?=$id_filtro?>">
                
                <div class="card overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-small">
                            <thead class="table-dark">
                                <tr>
                                    <th class="ps-3"><input type="checkbox" id="check_todos" class="form-check-input"></th>
                                    <th>Nome</th>
                                    <th>Status</th>
                                    <th class="text-center">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($contatos_mkt as $c): 
                                    $msg_final = str_replace('[NOME]', $c['nome'], ($projeto_ativo['mensagem'] ?? 'Olá [NOME]!'));
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <?php if($c['status'] == 'Pendente'): ?>
                                            <input type="checkbox" name="ids_contatos[]" value="<?=$c['id']?>" class="form-check-input check-item">
                                        <?php endif; ?>
                                    </td>
                                    <td><b><?=$c['nome']?></b><br><small class="text-muted"><?=$c['telefone']?></small></td>
                                    <td><span id="st-<?=$c['id']?>" class="badge <?=($c['status']=='Pendente'?'bg-warning text-dark':'bg-success')?>"><?=$c['status']?></span></td>
                                    <td class="text-center">
                                        <a href="https://api.whatsapp.com/send?phone=55<?=$c['telefone']?>&text=<?=urlencode($msg_final)?>" 
                                           target="_blank" onclick="marcarEnviado(<?=$c['id']?>)" class="btn-whatsapp">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="sticky-massa shadow-lg border text-center" id="barra_massa" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 400px; display: none; background: #fff; padding: 10px; border-radius: 50px;">
                    <div class="d-flex justify-content-between align-items-center px-3">
                        <span class="small fw-bold"><span id="count_selecionados">0</span> selecionados</span>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">SETAR ENVIADO</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function maskData(i) {
        let v = i.value.replace(/\D/g,'');
        if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
        if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9);
        i.value = v;
    }

    function povoarEdicaoProjeto(p) {
        document.getElementById('id_projeto_edit').value = p.id;
        document.getElementById('p_nome').value = p.nome_projeto;
        
        // Formatar datas para o input BR
        if(p.data_inicio && p.data_inicio !== '0000-00-00'){
            let d = p.data_inicio.split('-');
            document.getElementById('p_ini').value = d[2]+'/'+d[1]+'/'+d[0];
        }
        if(p.data_fim && p.data_fim !== '0000-00-00'){
            let d = p.data_fim.split('-');
            document.getElementById('p_fim').value = d[2]+'/'+d[1]+'/'+d[0];
        }
        
        document.getElementById('p_msg').value = p.mensagem;
        document.getElementById('titulo_form_p').innerText = "Editar: " + p.nome_projeto;
        document.getElementById('btn_salvar_p').innerText = "ATUALIZAR PROJETO";
        document.getElementById('btn_salvar_p').classList.replace('btn-primary', 'btn-warning');
        document.getElementById('btn_cancelar_p').classList.remove('d-none');
        window.scrollTo(0,0);
    }

    function marcarEnviado(id) {
        const fd = new FormData();
        fd.append('form_acao', 'marcar_enviado');
        fd.append('contato_id', id);
        fetch('secretaria.php', { method: 'POST', body: fd });
        const badge = document.getElementById('st-'+id);
        badge.innerText = 'Enviado';
        badge.className = 'badge bg-success';
    }

    // Seleção em Massa
    const checkTodos = document.getElementById('check_todos');
    const items = document.querySelectorAll('.check-item');
    const barraMassa = document.getElementById('barra_massa');
    const countTxt = document.getElementById('count_selecionados');

    function atualizarBarra() {
        const selecionados = document.querySelectorAll('.check-item:checked').length;
        countTxt.innerText = selecionados;
        barraMassa.style.display = selecionados > 0 ? 'block' : 'none';
    }

    if(checkTodos){
        checkTodos.addEventListener('change', function() {
            items.forEach(i => i.checked = this.checked);
            atualizarBarra();
        });
    }
    items.forEach(i => i.addEventListener('change', atualizarBarra));

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if(urlParams.get('projeto_ok')) {
            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Projeto salvo com sucesso.' });
        }
        if(urlParams.get('del_ok')) {
            Swal.fire({ icon: 'info', title: 'Excluído!', text: 'Projeto e contatos removidos.' });
        }
        if(urlParams.get('n')) {
            Swal.fire({ icon: 'success', title: 'Importado!', html: `<b>${urlParams.get('n')}</b> novos contatos.` });
        }

        const tab = urlParams.get('tab');
        if(tab) {
            const btn = document.querySelector(`[data-bs-target="#tab-${tab}"]`);
            if(btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
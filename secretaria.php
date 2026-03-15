<?php
/**
 * JMM SYSTEM - MÓDULO SECRETARIA & MARKETING PRO
 * Versão Completa: Projetos, Importação XLSX, VCF e Ações em Massa.
 */
error_reporting(E_ALL); 
ini_set('display_errors', 1);
require_once 'config.php';

// Verifica se a biblioteca de leitura de Excel existe
$lib_carregada = file_exists('SimpleXLSX.php');
if ($lib_carregada) { 
    require_once 'SimpleXLSX.php'; 
}

// 1. SEGURANÇA E SINCRONIZAÇÃO DE NÍVEL
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); 
    exit;
}

// Sincroniza o nível com o banco de dados para evitar erros de sessão vazia
$stmt_nivel = $pdo->prepare("SELECT nivel, nome FROM usuarios WHERE id = ?");
$stmt_nivel->execute([$_SESSION['user_id']]);
$dados_sessao = $stmt_nivel->fetch(PDO::FETCH_ASSOC);

if ($dados_sessao) {
    $_SESSION['nivel'] = !empty($dados_sessao['nivel']) ? strtolower(trim($dados_sessao['nivel'])) : 'membro';
}

$user_nivel = $_SESSION['nivel'];
$user_nome = $dados_sessao['nome'];

// Bloqueio de segurança (Permite Admin, Secretaria e Membro conforme solicitado)
if ($user_nivel !== 'admin' && $user_nivel !== 'secretaria' && $user_nivel !== 'membro') {
    header("Location: sistema_dashboard.php");
    exit;
}

// --- FUNÇÕES AUXILIARES DE DATA ---
function dataBR($data) { 
    return ($data && $data != '0000-00-00') ? date('d/m/Y', strtotime($data)) : ''; 
}

function dataSQL($data) { 
    if (strpos($data, '/')) {
        $partes = explode('/', $data);
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}

// --- AÇÃO GET: GERAR ARQUIVO VCF (AGENDA TELEFÔNICA) ---
if (isset($_GET['gerar_vcf'])) {
    $projeto_id = (int)$_GET['gerar_vcf'];
    $stmt_vcf = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
    $stmt_vcf->execute([$projeto_id]);
    $contatos_vcf = $stmt_vcf->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/vcard');
    header('Content-Disposition: attachment; filename="Agenda_JMM_Projeto_'.$projeto_id.'.vcf"');
    
    foreach ($contatos_vcf as $contato) {
        echo "BEGIN:VCARD\n";
        echo "VERSION:3.0\n";
        echo "FN:JMM " . $contato['nome'] . "\n";
        echo "TEL;TYPE=CELL;TYPE=VOICE;TYPE=pref:+55" . $contato['telefone'] . "\n";
        echo "END:VCARD\n";
    }
    exit;
}

// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    // AÇÃO: SALVAR OU ATUALIZAR PROJETO
    if ($acao == 'novo_projeto') {
        $id_projeto = $_POST['id_projeto_edit'] ?? '';
        $nome_p = trim($_POST['nome_p']);
        $data_i = dataSQL($_POST['data_i']);
        $data_f = dataSQL($_POST['data_f']);
        $mensagem = $_POST['mensagem'];

        if (!empty($id_projeto)) {
            // UPDATE
            $sql = "UPDATE projetos SET nome_projeto = ?, data_inicio = ?, data_fim = ?, mensagem = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$nome_p, $data_i, $data_f, $mensagem, $id_projeto]);
            $feedback = "atualizado";
        } else {
            // INSERT
            $sql = "INSERT INTO projetos (nome_projeto, data_inicio, data_fim, mensagem) VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nome_p, $data_i, $data_f, $mensagem]);
            $feedback = "criado";
        }
        header("Location: secretaria.php?tab=projetos&projeto_ok=$feedback");
        exit;
    }

    // AÇÃO: EXCLUIR PROJETO
    if ($acao == 'deletar_projeto') {
        $id_del = (int)$_POST['id_p'];
        $pdo->prepare("DELETE FROM marketing_contatos WHERE projeto_id = ?")->execute([$id_del]);
        $pdo->prepare("DELETE FROM projetos WHERE id = ?")->execute([$id_del]);
        header("Location: secretaria.php?tab=projetos&del_ok=1");
        exit;
    }

    // AÇÃO: IMPORTAR EXCEL (.XLSX)
    if ($acao == 'importar_marketing' && $lib_carregada) {
        $projeto_alvo = $_POST['projeto_id'];
        if (isset($_FILES['arquivo_excel']) && $_FILES['arquivo_excel']['error'] == 0) {
            
            // Tenta carregar a classe SimpleXLSX (Namespace Shuchkin ou Global)
            $classe_xlsx = class_exists('\Shuchkin\SimpleXLSX') ? '\Shuchkin\SimpleXLSX' : 'SimpleXLSX';
            
            if ($xlsx = $classe_xlsx::parse($_FILES['arquivo_excel']['tmp_name'])) {
                
                // Busca duplicados existentes para validar
                $stmt_check = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
                $stmt_check->execute([$projeto_alvo]);
                $existentes = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
                $nomes_no_banco = array_column($existentes, 'nome');
                $tels_no_banco = array_column($existentes, 'telefone');

                $cont_novos = 0;
                $cont_dups = 0;

                foreach ($xlsx->rows() as $indice => $linha) {
                    if ($indice == 0) continue; // Pula cabeçalho
                    
                    $nome_xls = trim($linha[0] ?? '');
                    $tel_xls = preg_replace('/\D/', '', $linha[1] ?? '');

                    if (!empty($nome_xls) && !empty($tel_xls)) {
                        // Validação Rigorosa: Se nome ou telefone já existem, ignora
                        if (in_array($nome_xls, $nomes_no_banco) || in_array($tel_xls, $tels_no_banco)) {
                            $cont_dups++;
                        } else {
                            $sql_ins = "INSERT INTO marketing_contatos (projeto_id, nome, telefone, status) VALUES (?, ?, ?, 'Pendente')";
                            $pdo->prepare($sql_ins)->execute([$projeto_alvo, $nome_xls, $tel_xls]);
                            
                            // Registra nos arrays para não duplicar no próprio arquivo
                            $nomes_no_banco[] = $nome_xls;
                            $tels_no_banco[] = $tel_xls;
                            $cont_novos++;
                        }
                    }
                }
                header("Location: secretaria.php?tab=marketing&n=$cont_novos&d=$cont_dups&f_projeto=$projeto_alvo");
                exit;
            }
        }
    }

    // AÇÃO EM MASSA: MARCAR SELECIONADOS COMO ENVIADO
    if ($acao == 'massa_enviado') {
        $ids_selecionados = $_POST['ids_contatos'] ?? [];
        if (!empty($ids_selecionados)) {
            $interrogacoes = implode(',', array_fill(0, count($ids_selecionados), '?'));
            $sql_massa = "UPDATE marketing_contatos SET status = 'Enviado' WHERE id IN ($interrogacoes)";
            $pdo->prepare($sql_massa)->execute($ids_selecionados);
        }
        header("Location: secretaria.php?tab=marketing&massa_ok=" . count($ids_selecionados) . "&f_projeto=" . $_POST['f_projeto']);
        exit;
    }

    // AÇÃO INDIVIDUAL: MARCAR ENVIADO (CHAMADA AJAX OU CLIQUE)
    if ($acao == 'marcar_enviado') {
        $id_contato = (int)$_POST['contato_id'];
        $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id = ?")->execute([$id_contato]);
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

// --- CONSULTAS PARA EXIBIÇÃO ---
$projetos_todos = $pdo->query("SELECT * FROM projetos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$id_filtro = $_GET['f_projeto'] ?? ($projetos_todos[0]['id'] ?? 0);

$projeto_selecionado = null;
$lista_contatos = [];

if ($id_filtro > 0) {
    foreach ($projetos_todos as $proj) {
        if ($proj['id'] == $id_filtro) $projeto_selecionado = $proj;
    }
    $stmt_lista = $pdo->prepare("SELECT * FROM marketing_contatos WHERE projeto_id = ? ORDER BY status ASC, nome ASC");
    $stmt_lista->execute([$id_filtro]);
    $lista_contatos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secretaria - JMM System</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; padding-bottom: 80px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.75rem; color: #555; background: #fff; border: 1px solid #eee; margin: 2px; }
        .nav-pills .nav-link.active { background-color: #6f42c1 !important; color: #fff !important; }
        
        .btn-whatsapp { 
            background-color: #25d366; 
            color: white; 
            border-radius: 50px; 
            padding: 5px 15px; 
            font-weight: bold; 
            text-decoration: none; 
            font-size: 0.8rem; 
            transition: 0.3s;
        }
        .btn-whatsapp:hover { background-color: #128c7e; color: #fff; transform: scale(1.05); }
        
        .sticky-massa { 
            position: fixed; 
            bottom: 20px; 
            left: 50%; 
            transform: translateX(-50%); 
            width: 90%; 
            max-width: 450px; 
            background: #ffffff; 
            padding: 12px 20px; 
            border-radius: 50px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            z-index: 1000; 
            display: none;
            border: 2px solid #6f42c1;
        }

        .table-custom { font-size: 0.85rem; }
        .table-custom b { color: #333; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-arrow-left fs-4"></i></a>
        <h6 class="m-0 fw-bold text-uppercase" style="letter-spacing: 1px;">Secretaria & Marketing</h6>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
    </div>
</nav>

<div class="container">

    <!-- ABAS PRINCIPAIS -->
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded-pill shadow-sm" id="pills-tab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-projetos" type="button">PROJETOS</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-marketing" type="button">GESTÃO DE LISTAS</button>
        </li>
    </ul>

    <div class="tab-content">
        
        <!-- 1. ABA: GESTÃO DE PROJETOS -->
        <div class="tab-pane fade show active" id="tab-projetos" role="tabpanel">
            <div class="card p-4 border-top border-5 border-primary shadow">
                <h6 class="fw-bold mb-3" id="titulo_formulario">Novo Projeto & Mensagem</h6>
                <form method="POST" id="form_projeto">
                    <input type="hidden" name="form_acao" value="novo_projeto">
                    <input type="hidden" name="id_projeto_edit" id="id_projeto_edit">
                    
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">NOME DO PROJETO</label>
                        <input type="text" name="nome_p" id="input_nome" class="form-control rounded-3" placeholder="Ex: Rifa dos Amigos 2026" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted">DATA INÍCIO</label>
                            <input type="text" name="data_i" id="input_ini" class="form-control" placeholder="00/00/0000" onkeyup="maskData(this)" maxlength="10">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">DATA FIM</label>
                            <input type="text" name="data_f" id="input_fim" class="form-control" placeholder="00/00/0000" onkeyup="maskData(this)" maxlength="10">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">MENSAGEM DE DISPARO (WHATSAPP)</label>
                        <textarea name="mensagem" id="input_msg" class="form-control rounded-3" rows="3" placeholder="Use [NOME] para personalizar."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" id="btn_submit_proj" class="btn btn-primary w-100 fw-bold py-2 shadow">SALVAR PROJETO</button>
                        <button type="button" id="btn_cancelar_proj" class="btn btn-light border d-none" onclick="location.reload()">CANCELAR</button>
                    </div>
                </form>
            </div>

            <!-- GRID DE PROJETOS -->
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0 table-custom">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Nome do Projeto</th>
                            <th>Período</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($projetos_todos as $p): ?>
                        <tr>
                            <td class="ps-3"><b><?=$p['nome_projeto']?></b></td>
                            <td><?=dataBR($p['data_inicio'])?> <i class="bi bi-arrow-right small"></i> <?=dataBR($p['data_fim'])?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <button class="btn btn-link text-primary p-0 me-2" onclick='editarProjeto(<?=json_encode($p)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Deseja excluir este projeto e todos os seus contatos?')">
                                    <input type="hidden" name="form_acao" value="deletar_projeto">
                                    <input type="hidden" name="id_p" value="<?=$p['id']?>">
                                    <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($projetos_todos)): ?><tr><td colspan="3" class="text-center p-4 text-muted">Nenhum projeto cadastrado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. ABA: GESTÃO DE LISTAS (MARKETING) -->
        <div class="tab-pane fade" id="tab-marketing" role="tabpanel">
            
            <!-- IMPORTAÇÃO -->
            <div class="card p-4 border-top border-5 border-success text-center shadow-sm">
                <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-excel"></i> Importar Lista Excel (.xlsx)</h6>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_acao" value="importar_marketing">
                    <select name="projeto_id" class="form-select mb-2 shadow-sm" required>
                        <option value="">Selecione o Projeto Alvo...</option>
                        <?php foreach($projetos_todos as $p): ?>
                            <option value="<?=$p['id']?>" <?=($id_filtro==$p['id']?'selected':'')?>><?=$p['nome_projeto']?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="arquivo_excel" class="form-control mb-3" accept=".xlsx" required>
                    <button type="submit" class="btn btn-success w-100 fw-bold shadow">PROCESSAR IMPORTAÇÃO</button>
                </form>
            </div>

            <!-- FILTRO DE VISUALIZAÇÃO -->
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <form method="GET" class="d-flex align-items-center">
                    <input type="hidden" name="tab" value="marketing">
                    <span class="small fw-bold text-muted me-2">EXIBIR:</span>
                    <select name="f_projeto" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary" onchange="this.form.submit()">
                        <?php foreach($projetos_todos as $p): ?>
                            <option value="<?=$p['id']?>" <?=($id_filtro == $p['id']?'selected':'')?>><?=$p['nome_projeto']?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <?php if($id_filtro): ?>
                <a href="?gerar_vcf=<?=$id_filtro?>" class="btn btn-dark btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-person-rolodex"></i> AGENDA VCF
                </a>
                <?php endif; ?>
            </div>

            <!-- GRID DE CONTATOS -->
            <form method="POST" id="form_massa">
                <input type="hidden" name="form_acao" value="massa_enviado">
                <input type="hidden" name="f_projeto" value="<?=$id_filtro?>">
                
                <div class="card overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-custom">
                            <thead class="table-dark">
                                <tr>
                                    <th class="ps-3"><input type="checkbox" id="selecionar_todos" class="form-check-input"></th>
                                    <th>Nome do Contato</th>
                                    <th>Status</th>
                                    <th class="text-center">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($lista_contatos as $c): 
                                    $msg_zap = str_replace('[NOME]', $c['nome'], ($projeto_selecionado['mensagem'] ?? 'Olá [NOME]!'));
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <?php if($c['status'] == 'Pendente'): ?>
                                            <input type="checkbox" name="ids_contatos[]" value="<?=$c['id']?>" class="form-check-input check-contato">
                                        <?php endif; ?>
                                    </td>
                                    <td><b><?=$c['nome']?></b><br><small class="text-muted"><?=$c['telefone']?></small></td>
                                    <td>
                                        <span id="badge-<?=$c['id']?>" class="badge <?=($c['status']=='Pendente'?'bg-warning text-dark':'bg-success')?>">
                                            <?=$c['status']?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="https://api.whatsapp.com/send?phone=55<?=$c['telefone']?>&text=<?=urlencode($msg_zap)?>" 
                                           target="_blank" onclick="confirmarEnvio(<?=$c['id']?>)" class="btn-whatsapp shadow-sm">
                                            <i class="bi bi-whatsapp"></i> ENVIAR
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($lista_contatos)): ?><tr><td colspan="4" class="text-center p-4 text-muted">Nenhum contato encontrado para este projeto.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- BARRA DE AÇÃO EM MASSA (FLUTUANTE) -->
                <div class="sticky-massa" id="barra_massa">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold"><i class="bi bi-check2-all"></i> <span id="qtd_selecionados">0</span> selecionados</span>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">MARCAR COMO ENVIADO</button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Máscara de Data DD/MM/AAAA
    function maskData(input) {
        let v = input.value.replace(/\D/g,'');
        if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
        if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9);
        input.value = v;
    }

    // Povoar formulário para edição
    function editarProjeto(dados) {
        document.getElementById('id_projeto_edit').value = dados.id;
        document.getElementById('input_nome').value = dados.nome_projeto;
        
        if(dados.data_inicio && dados.data_inicio !== '0000-00-00'){
            let d = dados.data_inicio.split('-');
            document.getElementById('input_ini').value = d[2]+'/'+d[1]+'/'+d[0];
        }
        if(dados.data_fim && dados.data_fim !== '0000-00-00'){
            let d = dados.data_fim.split('-');
            document.getElementById('input_fim').value = d[2]+'/'+d[1]+'/'+d[0];
        }
        
        document.getElementById('input_msg').value = dados.mensagem;
        document.getElementById('titulo_formulario').innerText = "Editar: " + dados.nome_projeto;
        document.getElementById('btn_submit_proj').innerText = "ATUALIZAR PROJETO";
        document.getElementById('btn_submit_proj').classList.replace('btn-primary', 'btn-warning');
        document.getElementById('btn_cancelar_proj').classList.remove('d-none');
        window.scrollTo(0,0);
    }

    // Marcar enviado individualmente via AJAX silencioso
    function confirmarEnvio(id) {
        const formData = new FormData();
        formData.append('form_acao', 'marcar_enviado');
        formData.append('contato_id', id);
        
        fetch('secretaria.php', { method: 'POST', body: formData });
        
        const badge = document.getElementById('badge-' + id);
        if(badge) {
            badge.innerText = 'Enviado';
            badge.className = 'badge bg-success';
        }
    }

    // Lógica de Seleção em Massa
    const checkTodos = document.getElementById('selecionar_todos');
    const checksIndividuais = document.querySelectorAll('.check-contato');
    const barraMassa = document.getElementById('barra_massa');
    const txtQtd = document.getElementById('qtd_selecionados');

    function gerenciarBarraMassa() {
        const selecionados = document.querySelectorAll('.check-contato:checked').length;
        txtQtd.innerText = selecionados;
        barraMassa.style.display = selecionados > 0 ? 'block' : 'none';
    }

    if(checkTodos) {
        checkTodos.addEventListener('change', function() {
            checksIndividuais.forEach(c => c.checked = this.checked);
            gerenciarBarraMassa();
        });
    }

    checksIndividuais.forEach(c => {
        c.addEventListener('change', gerenciarBarraMassa);
    });

    // Pop-ups de Feedback (SweetAlert2)
    document.addEventListener("DOMContentLoaded", function() {
        const params = new URLSearchParams(window.location.search);
        
        if(params.get('projeto_ok')) {
            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Projeto gravado com sucesso.', timer: 2000, showConfirmButton: false });
        }
        if(params.get('del_ok')) {
            Swal.fire({ icon: 'info', title: 'Excluído!', text: 'Projeto e contatos removidos.', timer: 2000, showConfirmButton: false });
        }
        if(params.get('n')) {
            Swal.fire({ 
                icon: params.get('d') > 0 ? 'warning' : 'success', 
                title: 'Importação Finalizada', 
                html: `<b>${params.get('n')}</b> contatos novos.<br><b>${params.get('d')}</b> duplicados ignorados.` 
            });
        }
        if(params.get('massa_ok')) {
            Swal.fire({ icon: 'success', title: 'Atualizado!', text: params.get('massa_ok') + ' contatos marcados como enviado.', timer: 2000, showConfirmButton: false });
        }

        // Persistência de Abas
        const abaAtiva = params.get('tab');
        if(abaAtiva) {
            const btnAba = document.querySelector(`[data-bs-target="#tab-${abaAtiva}"]`);
            if(btnAba) bootstrap.Tab.getOrCreateInstance(btnAba).show();
        }
    });
</script>

</body>
</html>
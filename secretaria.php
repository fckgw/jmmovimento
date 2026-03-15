<?php
/**
 * JMM SYSTEM - SECRETARIA & MARKETING PRO (VERSÃO FIX JS)
 * Correção: Tratamento de quebras de linha no WhatsApp Marketing.
 */
error_reporting(E_ALL); 
ini_set('display_errors', 1);
require_once 'config.php';

// Carrega biblioteca Excel se existir
$lib_carregada = file_exists('SimpleXLSX.php');
if ($lib_carregada) { require_once 'SimpleXLSX.php'; }

// 1. SEGURANÇA E SINCRONIZAÇÃO
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

$stmt_sync = $pdo->prepare("SELECT nivel, nome FROM usuarios WHERE id = ?");
$stmt_sync->execute([$_SESSION['user_id']]);
$dados_user = $stmt_sync->fetch(PDO::FETCH_ASSOC);
if ($dados_user) {
    $_SESSION['nivel'] = !empty($dados_user['nivel']) ? strtolower(trim($dados_user['nivel'])) : 'membro';
}

$user_nivel = $_SESSION['nivel'];
$user_nome = $dados_user['nome'] ?? 'Usuário';

if ($user_nivel !== 'admin' && $user_nivel !== 'secretaria' && $user_nivel !== 'membro') {
    header("Location: sistema_dashboard.php"); exit;
}

// --- FUNÇÕES DE APOIO ---
function dataBR($data) { return ($data && $data != '0000-00-00') ? date('d/m/Y', strtotime($data)) : ''; }
function dataSQL($data) { 
    if (strpos($data, '/')) { $p = explode('/', $data); return $p[2] . '-' . $p[1] . '-' . $p[0]; }
    return $data;
}

// --- AÇÕES GET (VCF) ---
if (isset($_GET['gerar_vcf'])) {
    $id = (int)$_GET['gerar_vcf'];
    $stmt = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
    $stmt->execute([$id]);
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/vcard');
    header('Content-Disposition: attachment; filename="Agenda_JMM.vcf"');
    foreach ($contatos as $c) {
        echo "BEGIN:VCARD\nVERSION:3.0\nFN:JMM " . $c['nome'] . "\nTEL;TYPE=CELL:+55" . $c['telefone'] . "\nEND:VCARD\n";
    }
    exit;
}

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    if ($acao == 'novo_projeto') {
        $id_p = $_POST['id_projeto_edit'] ?? '';
        $nome = trim($_POST['nome_p']);
        $ini = dataSQL($_POST['data_i']);
        $fim = dataSQL($_POST['data_f']);
        $msg = $_POST['mensagem'];
        if (!empty($id_p)) {
            $pdo->prepare("UPDATE projetos SET nome_projeto=?, data_inicio=?, data_fim=?, mensagem=? WHERE id=?")->execute([$nome, $ini, $fim, $msg, $id_p]);
        } else {
            $pdo->prepare("INSERT INTO projetos (nome_projeto, data_inicio, data_fim, mensagem) VALUES (?, ?, ?, ?)")->execute([$nome, $ini, $fim, $msg]);
        }
        header("Location: secretaria.php?tab=projetos&projeto_ok=1"); exit;
    }

    if ($acao == 'deletar_projeto') {
        $pdo->prepare("DELETE FROM projetos WHERE id = ?")->execute([$_POST['id_p']]);
        header("Location: secretaria.php?tab=projetos&del_ok=1"); exit;
    }

    if ($acao == 'importar_marketing' && $lib_carregada) {
        $proj_id = $_POST['projeto_id'];
        $classe_xlsx = class_exists('\Shuchkin\SimpleXLSX') ? '\Shuchkin\SimpleXLSX' : 'SimpleXLSX';
        if ($xlsx = $classe_xlsx::parse($_FILES['arquivo_excel']['tmp_name'])) {
            $stmt_check = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
            $stmt_check->execute([$proj_id]);
            $existentes = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
            $nomes_db = array_column($existentes, 'nome'); $tels_db = array_column($existentes, 'telefone');
            $n=0; $d=0;
            foreach ($xlsx->rows() as $i => $row) {
                if ($i == 0) continue;
                $nome = trim($row[0] ?? ''); $tel = preg_replace('/\D/', '', $row[1] ?? '');
                if (!empty($nome) && !empty($tel)) {
                    if (in_array($nome, $nomes_db) || in_array($tel, $tels_db)) { $d++; }
                    else { $pdo->prepare("INSERT INTO marketing_contatos (projeto_id, nome, telefone) VALUES (?, ?, ?)")->execute([$proj_id, $nome, $tel]); $nomes_db[]=$nome; $tels_db[]=$tel; $n++; }
                }
            }
            header("Location: secretaria.php?tab=marketing&n=$n&d=$d&f_projeto=$proj_id"); exit;
        }
    }

    if ($acao == 'massa_enviado') {
        if (!empty($_POST['ids_contatos'])) {
            $in = implode(',', array_fill(0, count($_POST['ids_contatos']), '?'));
            $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id IN ($in)")->execute($_POST['ids_contatos']);
        }
        header("Location: secretaria.php?tab=marketing&massa_ok=1&f_projeto=".$_POST['f_projeto']); exit;
    }

    if ($acao == 'marcar_enviado') {
        $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id = ?")->execute([$_POST['contato_id']]);
        echo json_encode(['status' => 'ok']); exit;
    }
}

// CONSULTAS
$projetos = $pdo->query("SELECT * FROM projetos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$id_filtro = $_GET['f_projeto'] ?? ($projetos[0]['id'] ?? 0);
$contatos = []; $projeto_ativo = null;
if ($id_filtro > 0) {
    foreach($projetos as $p) if($p['id'] == $id_filtro) $projeto_ativo = $p;
    $stmt = $pdo->prepare("SELECT * FROM marketing_contatos WHERE projeto_id = ? ORDER BY status ASC, nome ASC");
    $stmt->execute([$id_filtro]);
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secretaria - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 80px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.75rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #6f42c1 !important; color: #fff !important; }
        .btn-zap-ind { background: #25d366; color: #fff; border-radius: 50px; padding: 4px 12px; font-size: 0.75rem; font-weight: bold; text-decoration: none; border: none; }
        .btn-mkt-lista { background: #007bff; color: #fff; border-radius: 50px; padding: 4px 12px; font-size: 0.75rem; font-weight: bold; border: none; }
        .sticky-massa { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 450px; background: #fff; padding: 12px; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 1000; display: none; border: 2px solid #6f42c1; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-arrow-left fs-4"></i></a>
        <h6 class="m-0 fw-bold">SECRETARIA & MARKETING</h6>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
    </div>
</nav>

<div class="container">
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded-pill shadow-sm">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-projetos">PROJETOS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-marketing">MARKETING</button></li>
    </ul>

    <div class="tab-content">
        <!-- ABA PROJETOS -->
        <div class="tab-pane fade show active" id="tab-projetos">
            <div class="card p-4 border-top border-5 border-primary shadow">
                <h6 class="fw-bold mb-3" id="titulo_form">Novo Projeto</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_projeto">
                    <input type="hidden" name="id_projeto_edit" id="id_proj">
                    <div class="mb-3"><label class="small fw-bold">NOME</label><input type="text" name="nome_p" id="f_nome" class="form-control" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="small fw-bold">INÍCIO</label><input type="text" name="data_i" id="f_ini" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)"></div>
                        <div class="col-6"><label class="small fw-bold">FIM</label><input type="text" name="data_f" id="f_fim" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)"></div>
                    </div>
                    <div class="mb-3"><label class="small fw-bold">MENSAGEM (Use [NOME])</label><textarea name="mensagem" id="f_msg" class="form-control" rows="3"></textarea></div>
                    <button type="submit" id="btn_sub" class="btn btn-primary w-100 fw-bold">SALVAR PROJETO</button>
                </form>
            </div>
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0" style="font-size:0.85rem">
                    <thead class="table-dark"><tr><th class="ps-3">Projeto</th><th>Período</th><th class="text-end pe-3">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach($projetos as $p): ?>
                        <tr>
                            <td class="ps-3"><b><?=$p['nome_projeto']?></b></td>
                            <td><?=dataBR($p['data_inicio'])?> - <?=dataBR($p['data_fim'])?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-link text-primary p-0 me-2" onclick='editarProj(<?=json_encode($p)?>)'><i class="bi bi-pencil-square"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Excluir?')"><input type="hidden" name="form_acao" value="deletar_projeto"><input type="hidden" name="id_p" value="<?=$p['id']?>"><button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ABA MARKETING -->
        <div class="tab-pane fade" id="tab-marketing">
            <div class="card p-4 border-top border-5 border-success text-center">
                <h6 class="fw-bold">Importar Excel (.xlsx)</h6>
                <form method="POST" enctype="multipart/form-data" class="mt-2">
                    <input type="hidden" name="form_acao" value="importar_marketing">
                    <select name="projeto_id" class="form-select mb-2" required>
                        <option value="">Selecione o Projeto...</option>
                        <?php foreach($projetos as $p): ?><option value="<?=$p['id']?>" <?=($id_filtro==$p['id']?'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?>
                    </select>
                    <input type="file" name="arquivo_excel" class="form-control mb-2" accept=".xlsx" required>
                    <button type="submit" class="btn btn-success w-100 fw-bold">SUBIR LISTA</button>
                </form>
            </div>

            <div class="d-flex justify-content-between mb-3 px-2">
                <form method="GET"><input type="hidden" name="tab" value="marketing">
                    <select name="f_projeto" class="form-select form-select-sm border-0 fw-bold text-primary" onchange="this.form.submit()">
                        <?php foreach($projetos as $p): ?><option value="<?=$p['id']?>" <?=($id_filtro==$p['id']?'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?>
                    </select>
                </form>
                <a href="?gerar_vcf=<?=$id_filtro?>" class="btn btn-dark btn-sm rounded-pill px-3 fw-bold">AGENDA VCF</a>
            </div>

            <form method="POST" id="form_massa">
                <input type="hidden" name="form_acao" value="massa_enviado">
                <input type="hidden" name="f_projeto" value="<?=$id_filtro?>">
                <div class="card overflow-hidden shadow-sm">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.8rem">
                        <thead class="table-dark"><tr><th class="ps-3"><input type="checkbox" id="sel_todos"></th><th>Nome</th><th>Status</th><th class="text-center">Ações</th></tr></thead>
                        <tbody>
                            <?php foreach($contatos as $c): 
                                $msg_raw = ($projeto_ativo['mensagem'] ?? 'Olá [NOME]!');
                                $msg_p = str_replace('[NOME]', $c['nome'], $msg_raw);
                            ?>
                            <tr>
                                <td class="ps-3"><input type="checkbox" name="ids_contatos[]" value="<?=$c['id']?>" class="check-c"></td>
                                <td><b><?=$c['nome']?></b><br><small class="text-muted"><?=$c['telefone']?></small></td>
                                <td><span id="st-<?=$c['id']?>" class="badge <?=($c['status']=='Pendente'?'bg-warning text-dark':'bg-success')?>"><?=$c['status']?></span></td>
                                <td class="text-center text-nowrap">
                                    <!-- INDIVIDUAL -->
                                    <button type="button" onclick="marcar(<?=$c['id']?>); window.open('https://api.whatsapp.com/send?phone=55<?=$c['telefone']?>&text=<?=urlencode($msg_p)?>', '_blank')" class="btn-zap-ind shadow-sm">
                                        <i class="bi bi-person-fill"></i> INDIV.
                                    </button>
                                    <!-- LISTA (Marketing) - USANDO DATA ATTRIBUTE PARA EVITAR ERRO DE JS -->
                                    <button type="button" class="btn-mkt-lista shadow-sm ms-1 btn-assistente" data-msg="<?=htmlspecialchars($msg_p)?>">
                                        <i class="bi bi-megaphone-fill"></i> LISTA
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="sticky-massa shadow" id="barra_massa">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold"><span id="qtd">0</span> selecionados</span>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">SETAR ENVIADO</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function maskData(i) {
        let v = i.value.replace(/\D/g,'');
        if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
        if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9);
        i.value = v;
    }

    function editarProj(p) {
        document.getElementById('id_proj').value = p.id;
        document.getElementById('f_nome').value = p.nome_projeto;
        document.getElementById('f_ini').value = p.data_inicio.split('-').reverse().join('/');
        document.getElementById('f_fim').value = p.data_fim.split('-').reverse().join('/');
        document.getElementById('f_msg').value = p.mensagem;
        document.getElementById('btn_sub').innerText = "ATUALIZAR PROJETO";
        window.scrollTo(0,0);
    }

    function marcar(id) {
        const fd = new FormData(); fd.append('form_acao', 'marcar_enviado'); fd.append('contato_id', id);
        fetch('secretaria.php', { method: 'POST', body: fd });
        const b = document.getElementById('st-'+id); if(b){ b.innerText = 'Enviado'; b.className = 'badge bg-success'; }
    }

    // LISTENER PARA O BOTÃO DE LISTA (MARKETING)
    document.querySelectorAll('.btn-assistente').forEach(btn => {
        btn.addEventListener('click', function() {
            const mensagem = this.getAttribute('data-msg');
            
            // Tenta copiar para a área de transferência
            navigator.clipboard.writeText(mensagem).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Mensagem Copiada!',
                    html: 'A mensagem foi copiada para sua memória.<br><br>1. O WhatsApp vai abrir.<br>2. <b>Pesquise</b> sua Lista de Transmissão.<br>3. <b>Cole (Ctrl+V)</b> e envie.',
                    confirmButtonText: 'ABRIR WHATSAPP',
                    confirmButtonColor: '#25d366'
                }).then((res) => {
                    if(res.isConfirmed) window.open('https://web.whatsapp.com/', '_blank');
                });
            }).catch(err => {
                alert("Erro ao copiar. Tente selecionar o texto manualmente.");
            });
        });
    });

    const checkTodos = document.getElementById('sel_todos');
    const checks = document.querySelectorAll('.check-c');
    const barra = document.getElementById('barra_massa');
    const qtd = document.getElementById('qtd');

    function attBarra() {
        const s = document.querySelectorAll('.check-c:checked').length;
        qtd.innerText = s;
        barra.style.display = s > 0 ? 'block' : 'none';
    }

    if(checkTodos) checkTodos.addEventListener('change', function() { checks.forEach(c => c.checked = this.checked); attBarra(); });
    checks.forEach(c => c.addEventListener('change', attBarra));

    document.addEventListener("DOMContentLoaded", function() {
        const p = new URLSearchParams(window.location.search);
        if(p.get('tab')) {
            const btn = document.querySelector(`[data-bs-target="#tab-${p.get('tab')}"]`);
            if(btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        }
    });
</script>
</body>
</html>
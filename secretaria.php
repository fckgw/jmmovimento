<?php
/**
 * JMM SYSTEM - SECRETARIA & MARKETING (VERSÃO COM VALIDAÇÃO RIGOROSA)
 */
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once 'config.php';

$lib_carregada = file_exists('SimpleXLSX.php');
if ($lib_carregada) { require_once 'SimpleXLSX.php'; }

if (!isset($_SESSION['user_id']) || ($_SESSION['nivel'] !== 'admin' && $_SESSION['nivel'] !== 'secretaria')) {
    header("Location: sistema_dashboard.php"); exit;
}

$user_nome = $_SESSION['user_nome'];

// --- AÇÃO: GERAR VCF (AGENDA) ---
if (isset($_GET['gerar_vcf'])) {
    $proj_id = $_GET['gerar_vcf'];
    $stmt = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
    $stmt->execute([$proj_id]);
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/vcard');
    header('Content-Disposition: attachment; filename="Agenda_JMM_Projeto_'.$proj_id.'.vcf"');
    foreach ($contatos as $c) {
        echo "BEGIN:VCARD\nVERSION:3.0\nFN:JMM " . $c['nome'] . "\nTEL;TYPE=CELL:+55" . $c['telefone'] . "\nEND:VCARD\n";
    }
    exit;
}

// --- PROCESSAMENTO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    if ($acao == 'importar_marketing' && $lib_carregada) {
        $proj_id = $_POST['projeto_id'];
        
        if (isset($_FILES['arquivo_excel']) && $_FILES['arquivo_excel']['error'] == 0) {
            $classe = class_exists('\Shuchkin\SimpleXLSX') ? '\Shuchkin\SimpleXLSX' : (class_exists('SimpleXLSX') ? 'SimpleXLSX' : false);
            
            if ($classe && $xlsx = $classe::parse($_FILES['arquivo_excel']['tmp_name'])) {
                
                // 1. Pega todos os contatos já existentes desse projeto para comparar
                $stmt = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
                $stmt->execute([$proj_id]);
                $db_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Criamos listas simples para busca rápida
                $nomes_no_banco = array_column($db_existentes, 'nome');
                $tels_no_banco  = array_column($db_existentes, 'telefone');

                $novos = 0; 
                $duplicados = 0;

                foreach ($xlsx->rows() as $i => $linha) {
                    if ($i == 0) continue; // Pula cabeçalho
                    
                    $nome = trim($linha[0] ?? '');
                    $tel  = preg_replace('/\D/', '', $linha[1] ?? '');
                    
                    if (!empty($nome) && !empty($tel)) {
                        // 2. VALIDAÇÃO RIGOROSA: Nome existe OU Telefone existe?
                        if (in_array($nome, $nomes_no_banco) || in_array($tel, $tels_no_banco)) {
                            $duplicados++; // Se um dos dois bater, ignora
                        } else {
                            // 3. Se for 100% novo, insere
                            $sql = "INSERT INTO marketing_contatos (projeto_id, nome, telefone, status) VALUES (?, ?, ?, 'Pendente')";
                            $pdo->prepare($sql)->execute([$proj_id, $nome, $tel]);
                            
                            // Adiciona aos arrays temporários para não duplicar dentro do próprio Excel
                            $nomes_no_banco[] = $nome;
                            $tels_no_banco[] = $tel;
                            $novos++;
                        }
                    }
                }
                header("Location: secretaria.php?tab=marketing&n=$novos&d=$duplicados"); exit;
            }
        }
    }

    if ($acao == 'novo_projeto') {
        $pdo->prepare("INSERT INTO projetos (nome_projeto, data_inicio, data_fim) VALUES (?, ?, ?)")->execute([trim($_POST['nome_p']), $_POST['data_i'], $_POST['data_f']]);
        header("Location: secretaria.php?tab=projetos&projeto_ok=1"); exit;
    }

    if ($acao == 'marcar_enviado') {
        $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id = ?")->execute([$_POST['contato_id']]);
        echo json_encode(['status' => 'ok']); exit;
    }
}

// CONSULTAS
$projetos_all = $pdo->query("SELECT * FROM projetos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$id_filtro = $_GET['f_projeto'] ?? ($projetos_all[0]['id'] ?? 0);
$contatos_mkt = [];
if($id_filtro > 0){
    $stmt = $pdo->prepare("SELECT m.*, p.nome_projeto FROM marketing_contatos m JOIN projetos p ON p.id = m.projeto_id WHERE m.projeto_id = ? ORDER BY m.status ASC, m.nome ASC");
    $stmt->execute([$id_filtro]);
    $contatos_mkt = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Marketing - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 70px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.75rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #6f42c1 !important; color: #fff !important; }
        .btn-whatsapp { background-color: #25d366; color: white; border-radius: 50px; padding: 5px 15px; font-weight: bold; text-decoration: none; }
        .btn-vcf { background: #333; color: #fff; border-radius: 10px; font-weight: bold; font-size: 0.8rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container d-flex justify-content-between">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-arrow-left"></i></a>
        <h6 class="m-0 fw-bold">MARKETING RIFA</h6>
        <img src="Img/logo.jpg" height="30" class="rounded-circle">
    </div>
</nav>

<div class="container">

    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded-pill shadow-sm">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-projetos">PROJETOS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-marketing">LISTA DE DISPARO</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- ABA PROJETOS -->
        <div class="tab-pane fade show active" id="tab-projetos">
            <div class="card p-4 border-top border-5 border-primary">
                <h6 class="fw-bold mb-3">Novo Projeto</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_projeto">
                    <input type="text" name="nome_p" class="form-control mb-3" placeholder="Ex: Rifa 2026" required>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><input type="date" name="data_i" class="form-control shadow-sm"></div>
                        <div class="col-6"><input type="date" name="data_f" class="form-control shadow-sm"></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow">CRIAR PROJETO</button>
                </form>
            </div>
            
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Nome</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($projetos_all as $p): ?>
                        <tr><td class="ps-3 fw-bold"><?=$p['nome_projeto']?></td><td><span class="badge bg-success">Ativo</span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ABA MARKETING -->
        <div class="tab-pane fade" id="tab-marketing">
            <div class="card p-4 border-top border-5 border-success">
                <h6 class="fw-bold mb-3 text-center">Importar Contatos Excel</h6>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_acao" value="importar_marketing">
                    <select name="projeto_id" class="form-select mb-3" required>
                        <option value="">Selecione o Projeto...</option>
                        <?php foreach($projetos_all as $p): ?>
                            <option value="<?=$p['id']?>"><?=$p['nome_projeto']?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="arquivo_excel" class="form-control mb-3" accept=".xlsx" required>
                    <button type="submit" class="btn btn-success w-100 fw-bold">SUBIR E VALIDAR</button>
                </form>
            </div>

            <?php if ($id_filtro > 0): ?>
            <div class="card p-3 mb-3 bg-dark text-white border-0 shadow text-center">
                <p class="small mb-2">Para usar a <b>Lista de Transmissão</b> do WhatsApp, você precisa salvar esses nomes na sua agenda:</p>
                <a href="?gerar_vcf=<?=$id_filtro?>" class="btn btn-light btn-sm w-100 fw-bold">
                    <i class="bi bi-download"></i> BAIXAR AGENDA DO PROJETO
                </a>
            </div>
            <?php endif; ?>

            <div class="table-responsive bg-white rounded shadow-sm border mt-3">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.8rem;">
                    <thead class="table-dark">
                        <tr><th class="ps-3">Nome / WhatsApp</th><th>Status</th><th class="text-center">Ação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($contatos_mkt as $c): ?>
                        <tr>
                            <td class="ps-3"><b><?=$c['nome']?></b><br><span class="text-muted small"><?=$c['telefone']?></span></td>
                            <td><span id="st-<?=$c['id']?>" class="badge <?=($c['status']=='Pendente'?'bg-warning text-dark':'bg-success')?>"><?=$c['status']?></span></td>
                            <td class="text-center">
                                <a href="https://api.whatsapp.com/send?phone=55<?=$c['telefone']?>&text=Olá <?=$c['nome']?>!" 
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
    </div>
</div>

<script>
    function marcarEnviado(id) {
        const fd = new FormData();
        fd.append('form_acao', 'marcar_enviado');
        fd.append('contato_id', id);
        fetch('secretaria.php', { method: 'POST', body: fd });
        const badge = document.getElementById('st-'+id);
        badge.innerText = 'Enviado';
        badge.className = 'badge bg-success';
    }

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const n = urlParams.get('n');
        const d = urlParams.get('d');
        
        if(n !== null) {
            Swal.fire({
                icon: d > 0 ? 'warning' : 'success',
                title: 'Processamento Concluído',
                html: `<div class='text-start'>
                        <p class='text-success'><b>${n}</b> contatos novos importados.</p>
                        <p class='text-danger'><b>${d}</b> contatos ignorados (Já existem no projeto).</p>
                       </div>`,
                confirmButtonColor: '#6f42c1'
            });
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
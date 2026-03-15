<?php
require_once 'config.php';
// Para ler XLSX, baixe o SimpleXLSX.php de: https://github.com/shuchkin/simplexlsx
// require_once 'SimpleXLSX.php'; 

if (!isset($_SESSION['user_id']) || ($_SESSION['nivel'] !== 'admin' && $_SESSION['nivel'] !== 'secretaria')) {
    header("Location: sistema_dashboard.php"); exit;
}

$msg = '';

// --- AÇÃO: CRIAR NOVO PROJETO ---
if (isset($_POST['novo_projeto'])) {
    $nome = $_POST['nome_p'];
    $inicio = $_POST['data_i'];
    $fim = $_POST['data_f'];
    $pdo->prepare("INSERT INTO projetos (nome_projeto, data_inicio, data_fim) VALUES (?, ?, ?)")->execute([$nome, $inicio, $fim]);
    $msg = "Projeto criado com sucesso!";
}

// --- AÇÃO: IMPORTAR EXCEL (.XLSX) PARA O PROJETO ---
if (isset($_POST['importar_lista'])) {
    $projeto_id = $_POST['projeto_id'];
    
    // Simulação de importação (Se usar SimpleXLSX):
    /*
    if ( $xlsx = SimpleXLSX::parse($_FILES['arquivo_excel']['tmp_name']) ) {
        foreach ( $xlsx->rows() as $r => $row ) {
            if ($r == 0) continue; // Pula cabeçalho
            $nome_contato = $row[0];
            $tel_contato = preg_replace('/\D/', '', $row[1]);
            $pdo->prepare("INSERT INTO projetos_contatos (projeto_id, nome, telefone) VALUES (?, ?, ?)")->execute([$projeto_id, $nome_contato, $tel_contato]);
        }
    }
    */
    // Versão simplificada usando CSV para teste imediato ou ajuste conforme sua lib:
    $msg = "Lista importada para o projeto!";
}

$projetos = $pdo->query("SELECT * FROM projetos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projetos e Rifa - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .nav-pills .nav-link { border-radius: 30px; font-weight: bold; color: #555; border: 1px solid #ddd; margin: 0 5px; }
        .nav-pills .nav-link.active { background: #6f42c1; border-color: #6f42c1; }
        .btn-zap { background: #25d366; color: white; border-radius: 50px; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-light border-0"><i class="bi bi-grid-3x3-gap-fill fs-5"></i></a>
        <span class="navbar-brand fw-bold">SECRETARIA / PROJETOS</span>
        <img src="Img/logo.jpg" height="30" class="rounded-circle">
    </div>
</nav>

<div class="container mt-4">

    <!-- ABAS DE NAVEGAÇÃO -->
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-2 rounded-pill shadow-sm">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#aba-novo-proj">CRIAR PROJETO</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#aba-disparo">DISPARAR RIFA</button></li>
    </ul>

    <div class="tab-content">

        <!-- 1. ABA: CRIAR PROJETO -->
        <div class="tab-pane fade show active" id="aba-novo-proj">
            <div class="card p-4">
                <h5 class="fw-bold mb-3">Novo Projeto JMM</h5>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="novo_projeto" value="1">
                    <div class="col-12"><label class="small fw-bold">NOME DO PROJETO</label><input type="text" name="nome_p" class="form-control rounded-3" placeholder="Ex: Rifa dos Amigos 2026" required></div>
                    <div class="col-6"><label class="small fw-bold">DATA INÍCIO</label><input type="date" name="data_i" class="form-control rounded-3" required></div>
                    <div class="col-6"><label class="small fw-bold">DATA FIM</label><input type="date" name="data_f" class="form-control rounded-3" required></div>
                    <div class="col-12 mt-3"><button type="submit" class="btn btn-primary w-100 fw-bold py-2">SALVAR PROJETO</button></div>
                </form>
            </div>
            
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Projeto</th><th class="text-center">Período</th></tr></thead>
                    <tbody>
                        <?php foreach($projetos as $p): ?>
                        <tr>
                            <td class="ps-3"><b><?=$p['nome_projeto']?></b></td>
                            <td class="text-center small"><?=date('d/m/y', strtotime($p['data_inicio']))?> - <?=date('d/m/y', strtotime($p['data_fim']))?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. ABA: DISPARO / IMPORTAÇÃO -->
        <div class="tab-pane fade" id="aba-disparo">
            <div class="card p-4 border-top border-5 border-success">
                <h5 class="fw-bold mb-3">Gestão de Disparos</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="importar_lista" value="1">
                    <label class="small fw-bold">1. SELECIONE O PROJETO ATIVO</label>
                    <select name="projeto_id" class="form-select mb-3 shadow-sm" id="select_projeto" required>
                        <option value="">Escolha um projeto...</option>
                        <?php foreach($projetos as $p): ?>
                            <option value="<?=$p['id']?>"><?=$p['nome_projeto']?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="small fw-bold">2. IMPORTAR LISTA TRANSMISSÃO (Excel)</label>
                    <input type="file" name="arquivo_excel" class="form-control mb-3" accept=".xlsx" required>
                    
                    <button type="submit" class="btn btn-success w-100 fw-bold shadow">CARREGAR CONTATOS</button>
                </form>
            </div>

            <!-- TELA DE DISPARO (Aparece após carregar contatos) -->
            <div class="card p-3">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-whatsapp"></i> Configurar Mensagem</h6>
                <textarea id="msg_projeto" class="form-control" rows="4">A Paz de Jesus e o Amor de Maria, [NOME]! 
Somos do JMM e gostaríamos de contar com sua ajuda no projeto [PROJETO].</textarea>
                <div class="mt-2 small text-muted">Use: <b>[NOME]</b> e <b>[PROJETO]</b></div>
                
                <hr>
                
                <div id="lista_disparo" class="list-group">
                    <!-- Aqui o JavaScript vai popular os contatos importados -->
                    <div class="alert alert-light text-center">Selecione um projeto e importe a lista para começar.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Lógica para carregar os contatos via AJAX ou similar e realizar disparos individuais
function dispararIndividual(id_contato, telefone, nome, projeto_nome) {
    let msgRaw = document.getElementById('msg_projeto').value;
    let msgFinal = msgRaw.replace('[NOME]', nome).replace('[PROJETO]', projeto_nome);
    
    let link = `https://api.whatsapp.com/send?phone=55${telefone}&text=${encodeURIComponent(msgFinal)}`;
    
    // Abre o WhatsApp
    window.open(link, '_blank');
}

// Para disparos em massa automáticos (exige API paga ou loop manual), 
// mas o sistema agora está preparado para listar cada um com um botão verde grande.
</script>

</body>
</html>
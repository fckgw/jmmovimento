<?php
/**
 * JMM SYSTEM - MÓDULO DE CHAMADA E PRESENÇA
 */
require_once 'config.php';

// 1. SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_nome = $_SESSION['user_nome'];

// --- 2. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$enc_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id_ativo = $enc_ativo['id'] ?? 0;
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');

// --- 3. TOTAIS PARA OS QUADRINHOS ---
$total_cadastrados = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn() ?: 0;
$total_presentes_hoje = $enc_id_ativo ? $pdo->query("SELECT COUNT(*) FROM presencas WHERE encontro_id = $enc_id_ativo")->fetchColumn() : 0;

// --- 4. LISTA DE NOMES PARA O POP-UP DE PRESENTES ---
$lista_presentes_hoje = [];
if ($enc_id_ativo) {
    $stmt_p = $pdo->prepare("SELECT j.nome FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = ? ORDER BY j.nome ASC");
    $stmt_p->execute([$enc_id_ativo]);
    $lista_presentes_hoje = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
}

// --- 5. ÚLTIMOS 4 ENCONTROS PARA AS COLUNAS DA GRADE ---
$ultimos_enc = $pdo->query("SELECT id, data_encontro FROM encontros ORDER BY data_encontro DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
$ultimos_enc = array_reverse($ultimos_enc);

// --- 6. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_acao'])) {
    $acao = $_POST['form_acao'];

    if ($acao == 'toggle_presenca') {
        $j_id = $_POST['j_id'];
        $e_id = $_POST['e_id'];

        if ($e_id == $enc_id_ativo && $pode_checkin) {
            $check = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
            $check->execute([$j_id, $e_id]);
            $res = $check->fetch();
            
            $confirma_nome = "";
            if ($res) {
                $pdo->prepare("DELETE FROM presencas WHERE id = ?")->execute([$res['id']]);
            } else {
                $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")->execute([$j_id, $e_id]);
                $sj = $pdo->prepare("SELECT nome FROM jovens WHERE id = ?");
                $sj->execute([$j_id]);
                $confirma_nome = $sj->fetchColumn();
            }
            
            $msg_param = ($confirma_nome) ? "?checkok=" . urlencode($confirma_nome) : "";
            header("Location: chamada.php" . $msg_param); exit;
        } else {
            header("Location: chamada.php?erro_check=1"); exit;
        }
    }
}

// --- 7. CONSULTA DE TODOS OS JOVENS PARA A GRADE ---
$todos_jovens = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chamada - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .badge-count { font-size: 1.5rem; font-weight: 800; display: block; }
        .table-chamada { font-size: 0.75rem; }
        .sticky-col { position: sticky; left: 0; background-color: white !important; z-index: 1; border-right: 1px solid #dee2e6; }
        .thead-dark th { position: sticky; top: 0; z-index: 2; }
    </style>
</head>
<body class="pb-5">

<?php include 'navbar.php'; ?>

<div class="container">

    <!-- RESUMO CLICÁVEL -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="card p-2 text-center border-0 shadow-sm border-start border-5 border-primary">
                <small class="fw-bold text-muted small">CADASTRADOS</small>
                <span class="badge-count text-primary"><?=$total_cadastrados?></span>
            </div>
        </div>
        <div class="col-6">
            <div class="card p-2 text-center border-0 shadow-sm border-start border-5 border-success" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalPresentes">
                <small class="fw-bold text-muted small">PRESENTES <i class="bi bi-info-circle"></i></small>
                <span class="badge-count text-success"><?=$total_presentes_hoje?></span>
            </div>
        </div>
    </div>

    <!-- ÁREA DE CHECK-IN -->
    <div class="card p-3 border-0 shadow-sm rounded-4 mb-3">
        <?php if($pode_checkin): ?>
            <div class="text-center mb-3">
                <h6 class="fw-bold text-success text-uppercase">Check-in Aberto: <?=$enc_ativo['tema']?></h6>
                <button class="btn btn-dark w-100 rounded-pill shadow-sm py-2 fw-bold" onclick="abrirQr()">
                    <i class="bi bi-qr-code-scan"></i> EXIBIR QR CODE
                </button>
            </div>
        <?php else: ?>
            <div class="alert alert-danger text-center py-2 small fw-bold mb-0">
                <i class="bi bi-lock-fill"></i> ENTRADA BLOQUEADA (ENCONTRO FECHADO)
            </div>
        <?php endif; ?>
        
        <input type="text" id="filtroC" class="form-control mt-3 shadow-sm" placeholder="Buscar jovem na lista..." onkeyup="filtrarC()">
    </div>

    <!-- GRADE DE PRESENÇA -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive" style="max-height: 500px;">
            <table class="table table-sm table-hover align-middle mb-0 table-chamada" id="tabC">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3 py-3 sticky-col" style="z-index: 3;">JOVEM</th>
                        <?php foreach($ultimos_enc as $u): ?>
                            <th class="text-center"><?=date('d/m', strtotime($u['data_encontro']))?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($todos_jovens as $j): ?>
                    <tr>
                        <td class="ps-3 fw-bold text-uppercase text-truncate sticky-col" style="max-width: 150px;"><?=$j['nome']?></td>
                        <?php foreach($ultimos_enc as $u): 
                            $pres = $pdo->query("SELECT id FROM presencas WHERE jovem_id={$j['id']} AND encontro_id={$u['id']}")->fetch();
                            $coluna_ativa = ($u['id'] == $enc_id_ativo);
                        ?>
                        <td class="text-center">
                            <form method="POST">
                                <input type="hidden" name="form_acao" value="toggle_presenca">
                                <input type="hidden" name="j_id" value="<?=$j['id']?>">
                                <input type="hidden" name="e_id" value="<?=$u['id']?>">
                                <button type="submit" class="btn btn-link p-0 border-0" <?= (!$coluna_ativa || !$pode_checkin) ? 'disabled' : '' ?>>
                                    <i class="bi <?= $pres ? 'bi-check-circle-fill text-success' : 'bi-circle text-light' ?> fs-5"></i>
                                </button>
                            </form>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL QR CODE -->
<div class="modal fade" id="mQr"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg rounded-5"><div id="qrcode" class="d-flex justify-content-center mb-3"></div><h6 class="fw-bold text-muted">ESCANEIE PARA CHECK-IN</h6><button class="btn btn-secondary w-100 rounded-pill mt-3" data-bs-dismiss="modal">FECHAR</button></div></div></div>

<!-- MODAL LISTA DE PRESENTES -->
<div class="modal fade" id="modalPresentes" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header border-0 pb-0"><h6 class="modal-title fw-bold">PRESENTES HOJE (<?=$total_presentes_hoje?>)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php if(count($lista_presentes_hoje) > 0): ?><ul class="list-group list-group-flush"><?php foreach($lista_presentes_hoje as $p): ?><li class="list-group-item small text-uppercase fw-bold border-0 py-1"><i class="bi bi-check-circle-fill text-success me-2"></i> <?= $p['nome'] ?></li><?php endforeach; ?></ul><?php else: ?><div class="text-center py-3 text-muted small">Nenhuma presença registrada ainda.</div><?php endif; ?></div><div class="modal-footer border-0"><button type="button" class="btn btn-light w-100 fw-bold border rounded-pill" data-bs-dismiss="modal">FECHAR</button></div></div></div></div>

<!-- MODAL SUCESSO CHECKIN -->
<div class="modal fade" id="modalSucesso" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-4 border-0 shadow-lg rounded-5"><div class="modal-body"><i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i><h4 class="fw-bold mt-3">Confirmado!</h4><p class="mb-4">Jovem <span id="nomeJovemSucesso" class="text-primary fw-bold text-uppercase"></span>, feito o Check-In com sucesso!</p><button type="button" class="btn btn-dark w-100 rounded-pill fw-bold" data-bs-dismiss="modal">OK, PRÓXIMO</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function filtrarC() {
        let val = document.getElementById("filtroC").value.toUpperCase();
        let trs = document.getElementById("tabC").getElementsByTagName("tr");
        for (let i = 1; i < trs.length; i++) {
            let td = trs[i].getElementsByTagName("td")[0];
            trs[i].style.display = (td && td.innerText.toUpperCase().indexOf(val) > -1) ? "" : "none";
        }
    }

    function abrirQr() {
        const container = document.getElementById("qrcode"); container.innerHTML = '';
        new QRCode(container, { text: "https://jmmovimento.com.br/checkin.php?e=<?=$enc_id_ativo?>", width: 240, height: 240 });
        new bootstrap.Modal(document.getElementById('mQr')).show();
    }

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const checkOk = urlParams.get('checkok');
        if(checkOk) {
            document.getElementById('nomeJovemSucesso').innerText = checkOk;
            new bootstrap.Modal(document.getElementById('modalSucesso')).show();
        }
        if(urlParams.get('erro_check')) {
            alert("Ação bloqueada! O encontro não está ativo ou está encerrado.");
        }
    });
</script>
</body>
</html>
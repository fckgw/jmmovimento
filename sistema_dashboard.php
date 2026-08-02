<?php
/**
 * JMM SYSTEM - DASHBOARD PRINCIPAL (v7.8)
 * FOCO: TODOS OS MÓDULOS + SAC AUSENTES
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$user_nome  = $_SESSION['user_nome'];
$user_nivel = $_SESSION['nivel']; 

// --- 1. BUSCAR ÚLTIMO ACESSO ---
$stmt_user = $pdo->prepare("SELECT ultimo_acesso FROM usuarios WHERE id = ?");
$stmt_user->execute([$user_id]);
$dados_usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);
$ultimo_acesso = ($dados_usuario['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($dados_usuario['ultimo_acesso'])) : 'Primeiro acesso';

// --- 2. ESTATÍSTICAS GERAIS ---
$stats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN sexo = 'Masculino' THEN 1 ELSE 0 END) as masc, SUM(CASE WHEN sexo = 'Feminino' THEN 1 ELSE 0 END) as fem FROM jovens")->fetch(PDO::FETCH_ASSOC);
$total_base = $stats['total'] ?: 0;
$perc_m = ($total_base > 0) ? round(($stats['masc'] / $total_base) * 100, 1) : 0;
$perc_f = ($total_base > 0) ? round(($stats['fem'] / $total_base) * 100, 1) : 0;

// --- 3. ANIVERSARIANTES DA SEMANA (DOMINGO A SÁBADO) ---
$hoje = new DateTime();
$diaDaSemana = $hoje->format('w'); 
$inicioSemana = clone $hoje;
$inicioSemana->modify("-" . $diaDaSemana . " days");
$fimSemana = clone $inicioSemana;
$fimSemana->modify("+6 days");
$m_d_inicio = $inicioSemana->format('m-d');
$m_d_fim = $fimSemana->format('m-d');

$sql_niver_semana = "SELECT nome, telefone, data_nascimento, DATE_FORMAT(data_nascimento, '%d/%m') as dia_mes, (YEAR(CURDATE()) - YEAR(data_nascimento)) as idade_nova FROM jovens WHERE (DATE_FORMAT(data_nascimento, '%m-%d') BETWEEN :ini AND :fim) ORDER BY DATE_FORMAT(data_nascimento, '%m-%d') ASC";
$stmt_niver = $pdo->prepare($sql_niver_semana);
$stmt_niver->execute([':ini' => $m_d_inicio, ':fim' => $m_d_fim]);
$lista_niver_semana = $stmt_niver->fetchAll(PDO::FETCH_ASSOC);

// --- 4. ANIVERSARIANTES DE HOJE ---
$hojeMD = date('m-d');
$niver_dia = $pdo->prepare("SELECT nome, telefone, (YEAR(CURDATE()) - YEAR(data_nascimento)) as idade FROM jovens WHERE DATE_FORMAT(data_nascimento, '%m-%d') = ?");
$niver_dia->execute([$hojeMD]);
$lista_niver_hoje = $niver_dia->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .stat-card { border: none; border-radius: 20px; padding: 25px; color: white; position: relative; overflow: hidden; transition: 0.3s; }
        .stat-card i { position: absolute; right: 15px; bottom: 10px; font-size: 3rem; opacity: 0.2; }
        .niver-card { border: none; border-radius: 20px; background: #fff; box-shadow: 0 8px 20px rgba(0,0,0,0.05); overflow: hidden; }
        .niver-header { background: #ffc107; color: #000; padding: 15px 20px; border-bottom: 2px solid #eab100; }
        .menu-card {
            border: none; border-radius: 25px; transition: 0.3s; background: #fff; 
            text-align: center; padding: 25px 15px; height: 100%; display: flex; 
            flex-direction: column; align-items: center; text-decoration: none; 
            color: inherit; box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); border-bottom: 4px solid #6c757d; }
        .icon-circle { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 12px; }
        .bg-female { background-color: #d63384 !important; }
        .bg-ausentes { background-color: #e67e22 !important; }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- HEADER -->
    <div class="row align-items-center mb-5">
        <div class="col-7 d-flex align-items-center">
            <img src="Img/logo.jpg" width="55" class="rounded-circle shadow border border-2 border-white me-3">
            <div>
                <h4 class="fw-bold mb-0">JMM SYSTEM</h4>
                <small class="text-muted fw-bold text-uppercase">Gestão Administrativa</small>
            </div>
        </div>
        <div class="col-5 text-end">
            <div style="line-height: 1.1;">
                <span class="d-block fw-bold text-dark small text-uppercase"><?= $user_nome ?></span>
                <span class="badge <?= ($user_nivel == 'admin') ? 'bg-danger' : 'bg-primary' ?> mb-1" style="font-size: 9px;"><?= strtoupper($user_nivel) ?></span>
                <small class="d-block text-muted" style="font-size: 10px;">Último acesso: <?= $ultimo_acesso ?></small>
                <a href="logout.php" class="text-danger fw-bold text-decoration-none" style="font-size: 11px;"><i class="bi bi-power"></i> Sair</a>
            </div>
        </div>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-card bg-dark shadow"><small class="text-uppercase fw-bold opacity-75 small">Total Jovens</small><h2 class="fw-bold mb-0"><?=$total_base?></h2><i class="bi bi-people-fill"></i></div></div>
        <div class="col-md-4"><div class="stat-card bg-primary shadow"><small class="text-uppercase fw-bold opacity-75 small">Masculino (<?=$perc_m?>%)</small><h2 class="fw-bold mb-0"><?=$stats['masc'] ?: 0?></h2><i class="bi bi-gender-male"></i></div></div>
        <div class="col-md-4"><div class="stat-card bg-female shadow"><small class="text-uppercase fw-bold opacity-75 small">Feminino (<?=$perc_f?>%)</small><h2 class="fw-bold mb-0"><?=$stats['fem'] ?: 0?></h2><i class="bi bi-gender-female"></i></div></div>
    </div>

    <!-- ANIVERSARIANTES DA SEMANA -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="niver-card shadow-sm">
                <div class="niver-header d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-uppercase"><i class="bi bi-calendar-check-fill me-2"></i> Aniversariantes da Semana</h6>
                    <span class="badge bg-white text-dark fw-bold"><?= $inicioSemana->format('d/m') ?> a <?= $fimSemana->format('d/m') ?></span>
                </div>
                <div class="p-4">
                    <?php if($lista_niver_semana): ?>
                        <div class="row g-3">
                            <?php foreach($lista_niver_semana as $n): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                                        <div><span class="badge bg-warning text-dark me-2"><?= $n['dia_mes'] ?></span><span class="fw-bold text-uppercase small"><?= $n['nome'] ?></span></div>
                                        <a href="https://wa.me/55<?=preg_replace('/\D/','',$n['telefone'])?>" target="_blank" class="btn btn-success btn-sm rounded-pill"><i class="bi bi-whatsapp"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-2 small">Nenhum aniversariante nesta semana.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MÓDULOS (TODOS RESTAURADOS) -->
    <h6 class="fw-bold mb-4 text-secondary text-uppercase small">Módulos de Gestão</h6>
    <div class="row g-3">
        <div class="col-6 col-md-3 col-lg-2"><a href="chamada.php" class="menu-card shadow-sm"><div class="icon-circle bg-success text-white"><i class="bi bi-qr-code-scan"></i></div><h6 class="fw-bold small mb-0">CHAMADA</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="gincana.php?tab=jovens" class="menu-card shadow-sm"><div class="icon-circle bg-info text-white"><i class="bi bi-person-vcard-fill"></i></div><h6 class="fw-bold small mb-0">JOVENS</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="encontros.php" class="menu-card shadow-sm"><div class="icon-circle bg-warning text-white"><i class="bi bi-calendar-check-fill"></i></div><h6 class="fw-bold small mb-0">ENCONTROS</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="ata.php" class="menu-card shadow-sm"><div class="icon-circle bg-dark text-white"><i class="bi bi-file-earmark-pdf-fill"></i></div><h6 class="fw-bold small mb-0">ATA</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="drive.php" class="menu-card shadow-sm"><div class="icon-circle bg-primary text-white"><i class="bi bi-cloud-check-fill"></i></div><h6 class="fw-bold small mb-0">DRIVER</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="sac.php" class="menu-card shadow-sm"><div class="icon-circle bg-female text-white"><i class="bi bi-chat-heart-fill"></i></div><h6 class="fw-bold small mb-0">SAC GERAL</h6></a></div>
        
        <!-- NOVO SAC AUSENTES -->
        <div class="col-6 col-md-3 col-lg-2"><a href="sac_ausentes.php" class="menu-card shadow-sm"><div class="icon-circle bg-ausentes text-white"><i class="bi bi-person-x-fill"></i></div><h6 class="fw-bold small mb-0">SAC AUSENTES</h6></a></div>
        
        <!-- NOVO MÓDULO BI JOVEM -->
        <div class="col-6 col-md-3 col-lg-2">
            <a href="bi_jovem.php" class="menu-card shadow-sm">
                <div class="icon-circle text-white" style="background-color: #0dcaf0;">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <h6 class="fw-bold small mb-0">BI JOVEM</h6>
            </a>
        </div>

        <div class="col-6 col-md-3 col-lg-2"><a href="secretaria.php" class="menu-card shadow-sm"><div class="icon-circle text-white" style="background-color: #fd7e14;"><i class="bi bi-briefcase-fill"></i></div><h6 class="fw-bold small mb-0">SECRETARIA</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="financeiro.php" class="menu-card shadow-sm"><div class="icon-circle text-white" style="background-color: #20c997;"><i class="bi bi-cash-stack"></i></div><h6 class="fw-bold small mb-0">FINANCEIRO</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="fluxo.php" class="menu-card shadow-sm"><div class="icon-circle text-white" style="background-color: #6f42c1;"><i class="bi bi-bar-chart-line-fill"></i></div><h6 class="fw-bold small mb-0">FLUXO</h6></a></div>

        <?php if($user_nivel == 'admin'): ?>
            <div class="col-6 col-md-3 col-lg-2"><a href="usuarios.php" class="menu-card shadow-sm"><div class="icon-circle bg-primary text-white"><i class="bi bi-person-gear"></i></div><h6 class="fw-bold small mb-0">USUÁRIOS</h6></a></div>
            <div class="col-6 col-md-3 col-lg-2"><a href="logs.php" class="menu-card shadow-sm"><div class="icon-circle bg-secondary text-white"><i class="bi bi-journal-text"></i></div><h6 class="fw-bold small mb-0">LOGS</h6></a></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($lista_niver_hoje): ?>
<div class="modal fade" id="modalNiverHoje" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-body text-center p-5"><i class="bi bi-cake2-fill text-danger display-1 mb-4"></i><h3 class="fw-bold">ANIVERSARIANTE(S) DE HOJE!</h3><div class="list-group list-group-flush mb-4"><?php foreach($lista_niver_hoje as $nh): ?><div class="list-group-item bg-light border-0 rounded-3 mb-2"><h5 class="fw-bold text-primary mb-0 text-uppercase"><?= $nh['nome'] ?></h5><small class="fw-bold">Completando <?= $nh['idade'] ?> anos!</small><br><a href="https://wa.me/55<?=preg_replace('/\D/','',$nh['telefone'])?>" target="_blank" class="btn btn-success btn-sm mt-2 rounded-pill px-3"><i class="bi bi-whatsapp"></i> PARABENIZAR</a></div><?php endforeach; ?></div><button type="button" class="btn btn-dark w-100 rounded-pill fw-bold" data-bs-dismiss="modal">FECHAR</button></div></div></div></div>
<script>window.onload = () => { new bootstrap.Modal(document.getElementById('modalNiverHoje')).show(); };</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
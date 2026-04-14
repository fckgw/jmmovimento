<?php
/**
 * JMM SYSTEM - DASHBOARD PRINCIPAL
 */
require_once 'config.php';

// 1. SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$user_nome  = $_SESSION['user_nome'];
$user_nivel = $_SESSION['nivel']; // Valor do banco: 'admin' ou 'membro'

// --- 2. BUSCAR DADOS DO USUÁRIO (PERFIL E ÚLTIMO ACESSO) ---
$stmt_user = $pdo->prepare("SELECT nivel, ultimo_acesso FROM usuarios WHERE id = ?");
$stmt_user->execute([$user_id]);
$dados_usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Formatação da data brasileira
$ultimo_acesso = ($dados_usuario['ultimo_acesso']) 
    ? date('d/m/Y H:i', strtotime($dados_usuario['ultimo_acesso'])) 
    : 'Primeiro acesso';

// --- 3. ESTATÍSTICAS ---
$stats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN sexo = 'Masculino' THEN 1 ELSE 0 END) as masc, SUM(CASE WHEN sexo = 'Feminino' THEN 1 ELSE 0 END) as fem FROM jovens")->fetch(PDO::FETCH_ASSOC);
$total_base = $stats['total'] ?: 0;
$perc_m = ($total_base > 0) ? round(($stats['masc'] / $total_base) * 100, 1) : 0;
$perc_f = ($total_base > 0) ? round(($stats['fem'] / $total_base) * 100, 1) : 0;

$enc_ativo = $pdo->query("SELECT tema, status FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
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
        .stat-card { border: none; border-radius: 20px; padding: 25px; color: white; position: relative; overflow: hidden; }
        .stat-card i { position: absolute; right: 15px; bottom: 10px; font-size: 3rem; opacity: 0.2; }
        .menu-card {
            border: none; border-radius: 25px; transition: 0.3s; background: #fff; 
            text-align: center; padding: 25px 15px; height: 100%; display: flex; 
            flex-direction: column; align-items: center; text-decoration: none; 
            color: inherit; box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); border-bottom: 4px solid #6c757d; }
        .icon-circle { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 12px; }
    </style>
</head>
<body>

<div class="container py-4">
    
    <!-- HEADER DIREITA / ESQUERDA -->
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
                <span class="d-block fw-bold text-dark small"><?= mb_strtoupper($user_nome) ?></span>
                <span class="badge <?= ($user_nivel == 'admin') ? 'bg-danger' : 'bg-primary' ?> mb-1" style="font-size: 10px;"><?= strtoupper($user_nivel) ?></span>
                <small class="d-block text-muted" style="font-size: 10px;">Último acesso: <?= $ultimo_acesso ?></small>
                <a href="logout.php" class="text-danger fw-bold text-decoration-none" style="font-size: 11px;"><i class="bi bi-power"></i> Sair</a>
            </div>
        </div>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="row g-3 mb-5">
        <div class="col-md-4"><div class="stat-card bg-dark shadow"><small class="text-uppercase fw-bold opacity-75 small">Total Jovens</small><h2 class="fw-bold mb-0"><?=$total_base?></h2><i class="bi bi-people-fill"></i></div></div>
        <div class="col-md-4"><div class="stat-card bg-primary shadow"><small class="text-uppercase fw-bold opacity-75 small">Masculino (<?=$perc_m?>%)</small><h2 class="fw-bold mb-0"><?=$stats['masc'] ?: 0?></h2><i class="bi bi-gender-male"></i></div></div>
        <div class="col-md-4"><div class="stat-card bg-danger shadow"><small class="text-uppercase fw-bold opacity-75 small">Feminino (<?=$perc_f?>%)</small><h2 class="fw-bold mb-0"><?=$stats['fem'] ?: 0?></h2><i class="bi bi-gender-female"></i></div></div>
    </div>

    <!-- MÓDULOS -->
    <h6 class="fw-bold mb-4 text-secondary text-uppercase small" style="letter-spacing: 1px;">Módulos de Gestão</h6>
    <div class="row g-3">
        <div class="col-6 col-md-3 col-lg-2"><a href="chamada.php" class="menu-card shadow-sm"><div class="icon-circle bg-success text-white"><i class="bi bi-qr-code-scan"></i></div><h6 class="fw-bold small mb-0">CHAMADA</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="jovens.php" class="menu-card shadow-sm"><div class="icon-circle bg-info text-white"><i class="bi bi-person-vcard-fill"></i></div><h6 class="fw-bold small mb-0">JOVENS</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="encontros.php" class="menu-card shadow-sm"><div class="icon-circle bg-warning text-white"><i class="bi bi-calendar-check-fill"></i></div><h6 class="fw-bold small mb-0">ENCONTROS</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="ata.php" class="menu-card shadow-sm"><div class="icon-circle bg-dark text-white"><i class="bi bi-file-earmark-pdf-fill"></i></div><h6 class="fw-bold small mb-0">ATA</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="drive.php" class="menu-card shadow-sm"><div class="icon-circle bg-primary text-white" style="background-color: #6610f2 !important;"><i class="bi bi-cloud-check-fill"></i></div><h6 class="fw-bold small mb-0">DRIVER</h6></a></div>
        <div class="col-6 col-md-3 col-lg-2"><a href="sac.php" class="menu-card shadow-sm"><div class="icon-circle bg-danger text-white" style="background-color: #d63384 !important;"><i class="bi bi-chat-heart-fill"></i></div><h6 class="fw-bold small mb-0">SAC</h6></a></div>

        <!-- BOTÃO LOGS - VISÍVEL SOMENTE SE FOR ADMIN -->
        <?php if($user_nivel == 'admin'): ?>
        <div class="col-6 col-md-3 col-lg-2">
            <a href="logs.php" class="menu-card shadow-sm">
                <div class="icon-circle bg-secondary text-white"><i class="bi bi-journal-text"></i></div>
                <h6 class="fw-bold small mb-0">LOGS</h6>
            </a>
        </div>
        <?php endif; ?>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
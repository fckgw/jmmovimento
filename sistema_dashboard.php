<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$stats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN sexo = 'Masculino' THEN 1 ELSE 0 END) as masc, SUM(CASE WHEN sexo = 'Feminino' THEN 1 ELSE 0 END) as fem FROM jovens")->fetch(PDO::FETCH_ASSOC);
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
        .stat-card i { position: absolute; right: 15px; bottom: 10px; font-size: 3.5rem; opacity: 0.2; }
        .menu-card {
            border: none; border-radius: 25px; transition: 0.3s;
            background: #fff; text-align: center; padding: 25px 15px;
            height: 100%; display: flex; flex-direction: column; align-items: center;
            text-decoration: none; color: inherit; box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .menu-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-bottom: 5px solid; }
        .c-chamada:hover { border-color: #198754; }
        .c-jovens:hover { border-color: #0dcaf0; }
        .c-enc:hover { border-color: #ffc107; }
        .c-ata:hover { border-color: #212529; }
        .c-driver:hover { border-color: #6610f2; }
        .c-sac:hover { border-color: #d63384; }
        .icon-circle {
            width: 70px; height: 70px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div class="d-flex align-items-center">
            <img src="Img/logo.jpg" width="65" class="rounded-circle shadow border border-2 border-white me-3">
            <div><h4 class="fw-bold mb-0">JMM SYSTEM</h4><small class="text-muted">Gestão Master</small></div>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm px-4 rounded-pill fw-bold">SAIR</a>
    </div>

    <!-- MENU DE MÓDULOS -->
    <div class="row g-3">
        <div class="col-6 col-md-4"><a href="chamada.php" class="menu-card c-chamada"><div class="icon-circle bg-success text-white"><i class="bi bi-qr-code-scan"></i></div><h6 class="fw-bold mb-1">CHAMADA</h6></a></div>
        <div class="col-6 col-md-4"><a href="jovens.php" class="menu-card c-jovens"><div class="icon-circle bg-info text-white"><i class="bi bi-person-vcard-fill"></i></div><h6 class="fw-bold mb-1">JOVENS</h6></a></div>
        <div class="col-6 col-md-4"><a href="encontros.php" class="menu-card c-enc"><div class="icon-circle bg-warning text-white"><i class="bi bi-calendar-check-fill"></i></div><h6 class="fw-bold mb-1">ENCONTROS</h6></a></div>
        <div class="col-6 col-md-4"><a href="ata.php" class="menu-card c-ata"><div class="icon-circle bg-dark text-white"><i class="bi bi-file-earmark-pdf-fill"></i></div><h6 class="fw-bold mb-1">ATA</h6></a></div>
        <div class="col-6 col-md-4"><a href="drive.php" class="menu-card c-driver"><div class="icon-circle bg-primary text-white" style="background-color: #6610f2 !important;"><i class="bi bi-cloud-check-fill"></i></div><h6 class="fw-bold mb-1">DRIVER</h6></a></div>
        
        <!-- NOVO BOTÃO SAC -->
        <div class="col-6 col-md-4"><a href="sac.php" class="menu-card c-sac"><div class="icon-circle bg-danger text-white" style="background-color: #d63384 !important;"><i class="bi bi-chat-heart-fill"></i></div><h6 class="fw-bold mb-1">SAC</h6></a></div>
    </div>
</div>
</body>
</html>
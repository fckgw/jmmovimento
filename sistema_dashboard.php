<?php
require_once 'config.php';

// Proteção: Se não estiver logado, volta para o login
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Formata a data do último acesso (se existir)
$data_acesso = $_SESSION['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($_SESSION['ultimo_acesso'])) : 'Primeiro acesso';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, sans-serif; }
        
        /* Barra Superior */
        .top-bar { 
            background: #fff; 
            padding: 10px 30px; 
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .user-info { text-align: right; }
        .user-name { font-weight: 700; color: #333; margin-bottom: 0; line-height: 1.2; }
        .last-access { font-size: 0.7rem; color: #888; text-transform: uppercase; }

        /* Menu */
        .dashboard-container { min-height: calc(100vh - 70px); display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .btn-box { 
            padding: 30px; border-radius: 25px; background: #fff; text-align: center; text-decoration: none; 
            color: #333; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; display: block; height: 100%; border: none; 
        }
        .btn-box:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); color: #0d6efd; }
        .icon-circle { width: 65px; height: 65px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 15px; }
        .bg-purple { background-color: #6f42c1 !important; }
    </style>
</head>
<body>

<!-- BARRA SUPERIOR -->
<header class="top-bar">
    <div class="d-flex align-items-center">
        <img src="Img/logo.jpg" width="45" class="rounded-circle border me-2">
        <span class="fw-bold text-primary d-none d-md-inline">JMM SYSTEM</span>
    </div>
    
    <div class="user-info">
        <p class="user-name"><i class="bi bi-person-circle"></i> <?= $_SESSION['user_nome'] ?></p>
        <span class="last-access">Último acesso: <?= $data_acesso ?></span>
    </div>
</header>

<div class="container dashboard-container">
    <div class="w-100" style="max-width: 1000px;">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark">SALA DE ESTAR</h2>
            <p class="text-muted">Selecione o módulo que deseja gerenciar hoje</p>
        </div>

        <div class="row g-4 justify-content-center">
            <!-- FINANCEIRO -->
            <div class="col-6 col-md-3">
                <a href="financeiro.php" class="btn-box shadow-sm">
                    <div class="icon-circle bg-success text-white shadow-sm"><i class="bi bi-cash-coin"></i></div>
                    <h6 class="fw-bold m-0 small">FINANCEIRO</h6>
                </a>
            </div>
            
            <!-- GINCANA -->
            <div class="col-6 col-md-3">
                <a href="gincana.php" class="btn-box shadow-sm">
                    <div class="icon-circle bg-primary text-white shadow-sm"><i class="bi bi-trophy"></i></div>
                    <h6 class="fw-bold m-0 small">GINCANA</h6>
                </a>
            </div>

            <!-- JOVENS -->
            <div class="col-6 col-md-3">
                <a href="gincana.php#tab-jovens" class="btn-box shadow-sm">
                    <div class="icon-circle bg-info text-white shadow-sm"><i class="bi bi-people-fill"></i></div>
                    <h6 class="fw-bold m-0 small">JOVENS</h6>
                </a>
            </div>

            <!-- DRIVE -->
            <div class="col-6 col-md-3">
                <a href="drive.php" class="btn-box shadow-sm">
                    <div class="icon-circle bg-warning text-white shadow-sm"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                    <h6 class="fw-bold m-0 small">DRIVE</h6>
                </a>
            </div>


           <!-- NOVO MÓDULO: SECRETARIA -->
            <div class="col-6 col-md-3">
                <a href="secretaria.php" class="btn-box shadow-sm">
                    <div class="icon-circle bg-purple text-white shadow-sm"><i class="bi bi-journal-text"></i></div>
                    <h6 class="fw-bold m-0 small">SECRETARIA</h6>
                </a>
            </div>

            <!-- USUÁRIOS (ADMIN) -->
            <?php if ($_SESSION['nivel'] === 'admin'): ?>
            <div class="col-6 col-md-3">
                <a href="usuarios.php" class="btn-box shadow-sm">
                    <div class="icon-circle bg-purple text-white shadow-sm"><i class="bi bi-person-gear"></i></div>
                    <h6 class="fw-bold m-0 small">USUÁRIOS</h6>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="logs.php" class="btn-box shadow-sm">
                    <div class="icon-circle bg-secondary text-white shadow-sm"><i class="bi bi-list-check"></i></div>
                    <h6 class="fw-bold m-0 small">LOGS</h6>
                </a>
            </div>
            <?php endif; ?>

        </div>

        <div class="text-center mt-5">
            <a href="logout.php" class="btn btn-outline-danger px-4 rounded-pill fw-bold">Sair do Sistema</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
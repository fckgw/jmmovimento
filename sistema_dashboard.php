<?php
/**
 * JMM SYSTEM - DASHBOARD COM ACESSO AMPLIADO
 * Agora Membros também visualizam o módulo Secretaria.
 */
require_once 'config.php';

// 1. SEGURANÇA: Proteção de acesso
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// 2. SINCRONIZAÇÃO MESTRA: Busca o nível real no banco para evitar erro de sessão
$user_id = $_SESSION['user_id'];
try {
    $stmt_sync = $pdo->prepare("SELECT nome, nivel, ultimo_acesso FROM usuarios WHERE id = ?");
    $stmt_sync->execute([$user_id]);
    $dados_usuario = $stmt_sync->fetch(PDO::FETCH_ASSOC);

    if ($dados_usuario) {
        // Se no banco o nível estiver vazio, assume 'membro'
        $nivel_banco = !empty($dados_usuario['nivel']) ? $dados_usuario['nivel'] : 'membro';
        
        // Atualiza as sessões
        $_SESSION['nivel'] = trim(strtolower($nivel_banco));
        $_SESSION['user_nome'] = $dados_usuario['nome'];
        
        $user_nivel = $_SESSION['nivel'];
        $user_nome  = $_SESSION['user_nome'];
        $ultimo_acesso = $dados_usuario['ultimo_acesso'];
    }
} catch (PDOException $e) {
    die("Erro de conexão.");
}

$data_formatada = ($ultimo_acesso) ? date('d/m/Y H:i', strtotime($ultimo_acesso)) : 'Primeiro acesso';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sala de Estar - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .top-bar { 
            background: #fff; padding: 12px 25px; border-bottom: 1px solid #dee2e6;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .badge-nivel { font-size: 0.6rem; padding: 3px 10px; border-radius: 10px; text-transform: uppercase; font-weight: 700; }
        .module-card { 
            background: #fff; border-radius: 25px; padding: 30px 15px; text-align: center; text-decoration: none; 
            color: #333; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; 
            display: flex; flex-direction: column; align-items: center; height: 100%; border: none; 
        }
        .module-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); color: #6f42c1; }
        .icon-box { width: 65px; height: 65px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin-bottom: 15px; color: #fff; }
        .bg-secretaria { background-color: #6f42c1 !important; }
    </style>
</head>
<body>

<header class="top-bar">
    <div class="d-flex align-items-center">
        <img src="Img/logo.jpg" width="40" height="40" class="rounded-circle border me-2">
        <div class="lh-1">
            <span class="fw-bold text-primary d-block" style="font-size: 0.8rem;">JMM SYSTEM</span>
            <span class="badge bg-primary badge-nivel"><?= ($user_nivel ?: 'membro') ?></span>
        </div>
    </div>
    <div class="text-end lh-1">
        <p class="m-0 fw-bold small text-uppercase"><?= $user_nome ?></p>
        <small class="text-muted" style="font-size: 0.7rem;">Acesso: <?= $data_formatada ?></small>
    </div>
</header>

<div class="container py-5">
    <div class="text-center mb-5">
        <h3 class="fw-bold text-dark">SALA DE ESTAR</h3>
        <p class="text-muted small">Módulos liberados para seu acesso</p>
    </div>

    <div class="row g-4 justify-content-center">

        <!-- MÓDULO: SECRETARIA (Liberado para Admin, Secretaria e Membro) -->
        <?php if ($user_nivel == 'admin' || $user_nivel == 'secretaria' || $user_nivel == 'membro'): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="secretaria.php" class="module-card border-bottom border-4 border-primary">
                <div class="icon-box bg-secretaria shadow"><i class="bi bi-journal-check"></i></div>
                <span class="fw-bold small">SECRETARIA</span>
                <small class="text-muted" style="font-size: 10px;">PROJETOS E MARKETING</small>
            </a>
        </div>
        <?php endif; ?>

        <!-- MÓDULOS: FINANCEIRO, GINCANA, JOVENS, DRIVE (Liberado para Admin e Membro) -->
        <?php if ($user_nivel == 'admin' || $user_nivel == 'membro'): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="financeiro.php" class="module-card">
                <div class="icon-box bg-success shadow"><i class="bi bi-cash-coin"></i></div>
                <span class="fw-bold small">FINANCEIRO</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="gincana.php" class="module-card">
                <div class="icon-box bg-primary shadow"><i class="bi bi-trophy"></i></div>
                <span class="fw-bold small">GINCANA</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="gincana.php?tab=jovens" class="module-card">
                <div class="icon-box bg-info shadow text-white"><i class="bi bi-people-fill"></i></div>
                <span class="fw-bold small">JOVENS</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="drive.php" class="module-card">
                <div class="icon-box bg-warning shadow text-dark"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                <span class="fw-bold small">DRIVE</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- MÓDULOS EXCLUSIVOS: ADMIN -->
        <?php if ($user_nivel == 'admin'): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="usuarios.php" class="module-card">
                <div class="icon-box bg-dark shadow"><i class="bi bi-person-gear"></i></div>
                <span class="fw-bold small">USUÁRIOS</span>
            </a>
        </div>
        <?php endif; ?>

    </div>

    <div class="text-center mt-5">
        <a href="logout.php" class="btn btn-outline-danger px-4 rounded-pill fw-bold btn-sm">ENCERRAR SESSÃO</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
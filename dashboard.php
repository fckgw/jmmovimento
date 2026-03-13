<?php
require_once 'config.php';

// Segurança: Protege a Sala de Estar
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de Estar - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; min-height: 100vh; display: flex; align-items: center; }
        .card-choice {
            border: none; border-radius: 25px; transition: 0.3s;
            text-align: center; padding: 40px; text-decoration: none; 
            background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: flex; flex-direction: column; height: 100%;
        }
        .card-choice:hover { transform: translateY(-10px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
        .icon-box { 
            width: 90px; height: 90px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 20px; font-size: 3rem;
        }
        .btn-logout { position: absolute; top: 20px; right: 20px; }
    </style>
</head>
<body>

<a href="logout.php" class="btn btn-outline-danger btn-logout rounded-pill fw-bold">SAIR</a>

<div class="container py-5">
    <div class="text-center mb-5">
        <img src="Img/logo.jpg" width="120" class="rounded-circle shadow mb-3">
        <h1 class="fw-bold text-dark">SALA DE ESTAR</h1>
        <p class="text-muted fs-5">Bem-vindo, <strong><?= $_SESSION['user_nome'] ?></strong>. Escolha o seu destino:</p>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- OPÇÃO 1: IR PARA O SITE -->
        <div class="col-md-5">
            <a href="site/index.php" class="card-choice border-top border-5 border-info">
                <div class="icon-box bg-info text-white shadow"><i class="bi bi-globe"></i></div>
                <h3 class="fw-bold text-dark">MENU DO SITE</h3>
                <p class="text-muted">Visualizar a página pública e configurações institucionais.</p>
                <div class="mt-auto"><span class="btn btn-outline-info rounded-pill px-4">Acessar Site</span></div>
            </a>
        </div>

        <!-- OPÇÃO 2: NOVO SISTEMA (GINCANA/FINANCEIRO) -->
        <div class="col-md-5">
            <a href="gincana.php" class="card-choice border-top border-5 border-primary">
                <div class="icon-box bg-primary text-white shadow"><i class="bi bi-cpu"></i></div>
                <h3 class="fw-bold text-dark">NOVO SISTEMA</h3>
                <p class="text-muted">Cronômetro, Ranking de Gincana, Jovens e Gestão Financeira.</p>
                <div class="mt-auto"><span class="btn btn-primary rounded-pill px-4">Entrar no Sistema</span></div>
            </a>
        </div>
    </div>
    
    <div class="text-center mt-5 text-muted small">
        JMMovimento &copy; 2024 - Todos os direitos reservados.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$erro = ''; $sucesso = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nova = $_POST['nova_senha'];
    if ($nova !== $_POST['confirma_senha']) { $erro = "As senhas não conferem."; }
    else {
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET senha = ?, forcar_reset = 0 WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
        registrarLog($pdo, "Alterou a própria senha", "Troca de Senha");
        $sucesso = "Senha atualizada com sucesso! Redirecionando...";
        echo "<script>setTimeout(() => { window.location='sistema_dashboard.php'; }, 2000);</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-senha { width: 100%; max-width: 400px; padding: 30px; border-radius: 20px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .input-group-text { cursor: pointer; background: #fff; }
    </style>
</head>
<body>
<div class="card-senha text-center">
    <h4 class="fw-bold mb-4">Definir Nova Senha</h4>
    <?php if($erro): ?><div class="alert alert-danger small"><?=$erro?></div><?php endif; ?>
    <?php if($sucesso): ?><div class="alert alert-success small"><?=$sucesso?></div><?php endif; ?>
    <form method="POST">
        <div class="mb-3 text-start">
            <label class="small fw-bold">NOVA SENHA</label>
            <div class="input-group">
                <input type="password" name="nova_senha" id="s1" class="form-control" required>
                <span class="input-group-text" onclick="toggle('s1', 'i1')"><i class="bi bi-eye" id="i1"></i></span>
            </div>
        </div>
        <div class="mb-4 text-start">
            <label class="small fw-bold">CONFIRMAR NOVA SENHA</label>
            <div class="input-group">
                <input type="password" name="confirma_senha" id="s2" class="form-control" required>
                <span class="input-group-text" onclick="toggle('s2', 'i2')"><i class="bi bi-eye" id="i2"></i></span>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-bold">ATUALIZAR SENHA</button>
    </form>
</div>
<script>
    function toggle(id, iconId) {
        const p = document.getElementById(id); const i = document.getElementById(iconId);
        if(p.type === 'password') { p.type = 'text'; i.classList.replace('bi-eye', 'bi-eye-slash'); }
        else { p.type = 'password'; i.classList.replace('bi-eye-slash', 'bi-eye'); }
    }
</script>
</body>
</html>
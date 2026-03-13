<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') { header("Location: login.php"); exit; }

$msg = '';
if (isset($_POST['salvar_quota'])) {
    $nova_quota = (float)$_POST['quota_gb'] * 1073741824; // GB para Bytes
    $pdo->prepare("UPDATE usuarios SET quota_limite = ? WHERE id = ?")->execute([$nova_quota, $_POST['u_id']]);
    registrarLog($pdo, "Alterou espaço do Drive do usuário ID ".$_POST['u_id']." para ".$_POST['quota_gb']."GB", "Configuração");
    $msg = "Espaço atualizado com sucesso!";
}

$usuarios = $pdo->query("SELECT id, nome, email, quota_limite FROM usuarios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Configuração JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">Configurações do Sistema</h3>
        <a href="sistema_dashboard.php" class="btn btn-outline-dark">Voltar</a>
    </div>

    <?php if($msg): ?> <div class="alert alert-success"><?=$msg?></div> <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Gerenciar Espaço do Cloud Drive</div>
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead><tr><th>Membro</th><th>Espaço Atual</th><th>Definir (GB)</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><strong><?=$u['nome']?></strong><br><small class="text-muted"><?=$u['email']?></small></td>
                        <td><?= round($u['quota_limite'] / 1073741824, 2) ?> GB</td>
                        <form method="POST">
                            <input type="hidden" name="u_id" value="<?=$u['id']?>">
                            <td><input type="number" step="0.1" name="quota_gb" class="form-control form-control-sm" style="width:80px;" value="<?= round($u['quota_limite'] / 1073741824, 1) ?>"></td>
                            <td><button type="submit" name="salvar_quota" class="btn btn-sm btn-primary">Atualizar</button></td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
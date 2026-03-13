<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$msg = '';
// DELETAR USUÁRIO
if (isset($_GET['del'])) {
    $id_del = (int)$_GET['del'];
    if ($id_del != $_SESSION['user_id']) { // Não pode se auto-deletar
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_del]);
        $msg = "Usuário removido!";
    } else {
        $msg = "Você não pode excluir seu próprio usuário logado.";
    }
}

// ADICIONAR USUÁRIO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Verifica se email já existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $msg = "Erro: E-mail já cadastrado!";
    } else {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)")->execute([$nome, $email, $hash]);
        $msg = "Novo usuário cadastrado com sucesso!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <a href="dashboard.php" class="btn btn-outline-dark btn-sm mb-3"><i class="bi bi-arrow-left"></i> Voltar</a>
        
        <h2 class="fw-bold mb-4">Configurações & Usuários</h2>

        <?php if($msg): ?>
            <div class="alert alert-info"><?= $msg ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- LISTA DE USUÁRIOS -->
            <div class="col-md-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Usuários do Sistema</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Nome</th>
                                    <th>E-mail</th>
                                    <th class="text-end pe-3">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $users = $pdo->query("SELECT * FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
                                foreach($users as $u): 
                                ?>
                                <tr>
                                    <td class="ps-3"><?= $u['nome'] ?></td>
                                    <td><?= $u['email'] ?></td>
                                    <td class="text-end pe-3">
                                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                                            <a href="?del=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza?')"><i class="bi bi-trash"></i></a>
                                        <?php else: ?>
                                            <span class="badge bg-success">Você</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- NOVO USUÁRIO -->
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-bold">Novo Cadastro</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Nome Completo</label>
                                <input type="text" name="nome" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>E-mail de Acesso</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Senha Inicial</label>
                                <input type="text" name="senha" class="form-control" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Cadastrar Usuário</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
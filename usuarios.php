<?php
/**
 * GESTÃO DE USUÁRIOS JMM - VERSÃO FINAL CORRIGIDA
 */
require_once 'config.php';

// Importação do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// SEGURANÇA: Apenas administradores
if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') {
    header("Location: sistema_dashboard.php");
    exit;
}

$msg = ''; 
$erro = ''; 
$senha_para_whatsapp = ''; 
$nome_para_whatsapp = ''; 
$celular_para_whatsapp = ''; 
$email_para_whatsapp = '';

// --- 1. PROCESSAMENTO DE CADASTRO E EDIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    // AÇÃO: CADASTRAR
    if ($acao == 'cadastrar') {
        $nome    = trim($_POST['nome']);
        $email   = trim($_POST['email']);
        $celular = trim($_POST['celular']);
        $nivel   = trim($_POST['nivel']); // Captura do Select

        $caracteres = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $senha_plana = substr(str_shuffle($caracteres), 0, 8);
        $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

        try {
            // INSERT com a coluna nivel
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, celular, senha, nivel, forcar_reset) VALUES (?, ?, ?, ?, ?, 1)");
            if ($stmt->execute([$nome, $email, $celular, $senha_hash, $nivel])) {
                $msg = "Usuário <b>$nome</b> cadastrado com nível <b>$nivel</b>!";
                
                $senha_para_whatsapp = $senha_plana;
                $nome_para_whatsapp = $nome;
                $celular_para_whatsapp = preg_replace('/\D/', '', $celular);
                $email_para_whatsapp = $email;

                // PHPMailer (Opcional)
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP(); $mail->Host = 'email-ssl.com.br'; $mail->SMTPAuth = true;
                    $mail->Username = 'souzafelipe@bdsoft.com.br'; $mail->Password = 'BDSoft@2020';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail->Port = 465; $mail->CharSet = 'UTF-8';
                    $mail->setFrom('souzafelipe@bdsoft.com.br', 'JMM System');
                    $mail->addAddress($email, $nome); $mail->isHTML(true);
                    $mail->Subject = 'Acesso JMM System';
                    $mail->Body = "Usuário: $email <br> Senha: $senha_plana";
                    $mail->send();
                } catch (Exception $e) { }
            }
        } catch (Exception $e) { $erro = "Erro: E-mail já existe ou falha no banco."; }
    }

    // AÇÃO: EDITAR (CORREÇÃO AQUI)
    if ($acao == 'editar') {
        $id_u    = $_POST['id_usuario'];
        $nome    = trim($_POST['nome']);
        $email   = trim($_POST['email']);
        $celular = trim($_POST['celular']);
        $nivel   = trim($_POST['nivel']); // Pega o novo valor do select

        try {
            // UPDATE RIGOROSO
            $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, email=?, celular=?, nivel=? WHERE id=?");
            if($stmt->execute([$nome, $email, $celular, $nivel, $id_u])) {
                $msg = "Usuário <b>$nome</b> atualizado para <b>$nivel</b>!";
            }
        } catch (Exception $e) { $erro = "Erro ao atualizar: " . $e->getMessage(); }
    }
}

// --- 2. EXCLUSÃO ---
if (isset($_GET['delete'])) {
    $id_del = (int)$_GET['delete'];
    if ($id_del != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_del]);
        header("Location: usuarios.php?msg=Removido"); exit;
    }
}

// --- 3. BUSCA DOS DADOS (IMPORTANTE: Confeir nomes das colunas) ---
$lista = $pdo->query("SELECT id, nome, email, nivel, celular FROM usuarios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .badge { padding: 8px 12px; border-radius: 8px; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="sistema_dashboard.php" class="btn btn-outline-secondary px-3 rounded-pill"><i class="bi bi-arrow-left"></i> Voltar</a>
        <h3 class="fw-bold m-0">Gestão de Usuários</h3>
        <button class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">+ NOVO</button>
    </div>

    <!-- FEEDBACK WHATSAPP -->
    <?php if($senha_para_whatsapp): ?>
        <div class="alert alert-warning text-center shadow-sm">
            <h5 class="fw-bold">ENVIAR ACESSO WHATSAPP</h5>
            <p>Senha: <b><?=$senha_para_whatsapp?></b></p>
            <a href="https://api.whatsapp.com/send?phone=55<?=$celular_para_whatsapp?>&text=A Paz de Jesus! Seus dados: Login: <?=$email_para_whatsapp?> Senha: <?=$senha_para_whatsapp?>" target="_blank" class="btn btn-success btn-sm">DISPARAR WHATSAPP</a>
        </div>
    <?php endif; ?>

    <?php if($msg): ?> <div class="alert alert-success"><?=$msg?></div> <?php endif; ?>
    <?php if($erro): ?> <div class="alert alert-danger"><?=$erro?></div> <?php endif; ?>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-dark">
                    <tr><th>MEMBRO</th><th>LOGIN</th><th>NÍVEL</th><th>AÇÕES</th></tr>
                </thead>
                <tbody>
                    <?php foreach($lista as $u): ?>
                    <tr>
                        <td class="text-start ps-4">
                            <div class="fw-bold"><?=$u['nome']?></div>
                            <small class="text-muted"><?=$u['celular']?></small>
                        </td>
                        <td><small><?=$u['email']?></small></td>
                        <td>
                            <?php 
                                $nivel_atual = trim(strtolower($u['nivel']));
                                $cor = 'bg-secondary';
                                if($nivel_atual == 'admin') $cor = 'bg-primary';
                                if($nivel_atual == 'secretaria') $cor = 'bg-warning text-dark';
                                
                                // Se a coluna nível estiver vazia no banco, ele exibe NÃO DEFINIDO
                                $exibir_nivel = (!empty($u['nivel'])) ? strtoupper($u['nivel']) : '<span class="text-danger">NÃO DEFINIDO</span>';
                            ?>
                            <span class="badge <?=$cor?>"><?=$exibir_nivel?></span>
                        </td>
                        <td class="pe-4">
                            <button class="btn btn-link text-primary p-0 me-2" onclick='abrirEdicao(<?= json_encode($u) ?>)'>
                                <i class="bi bi-pencil-square fs-5"></i>
                            </button>

                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?=$u['id']?>" class="btn btn-link text-danger p-0" onclick="return confirm('Excluir?')"><i class="bi bi-trash fs-5"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CADASTRAR -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0">
            <input type="hidden" name="form_acao" value="cadastrar">
            <div class="modal-header bg-dark text-white"><h5>Novo Usuário</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="small fw-bold">NOME</label><input type="text" name="nome" class="form-control" required></div>
                <div class="mb-2"><label class="small fw-bold">E-MAIL</label><input type="email" name="email" class="form-control" required></div>
                <div class="row g-2">
                    <div class="col-7"><label class="small fw-bold">ZAP</label><input type="text" name="celular" class="form-control" required></div>
                    <div class="col-5">
                        <label class="small fw-bold">NÍVEL</label>
                        <select name="nivel" class="form-select" required>
                            <option value="membro">Membro</option>
                            <option value="secretaria">Secretaria</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">CADASTRAR</button></div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0">
            <input type="hidden" name="form_acao" value="editar">
            <input type="hidden" name="id_usuario" id="edit_id">
            <div class="modal-header bg-primary text-white"><h5>Editar Usuário</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="small fw-bold">NOME</label><input type="text" name="nome" id="edit_nome" class="form-control" required></div>
                <div class="mb-2"><label class="small fw-bold">E-MAIL</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="row g-2">
                    <div class="col-7"><label class="small fw-bold">ZAP</label><input type="text" name="celular" id="edit_celular" class="form-control" required></div>
                    <div class="col-5">
                        <label class="small fw-bold">NÍVEL</label>
                        <select name="nivel" id="edit_nivel" class="form-select" required>
                            <option value="membro">Membro</option>
                            <option value="secretaria">Secretaria</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">ATUALIZAR DADOS</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function abrirEdicao(u) {
        document.getElementById('edit_id').value = u.id;
        document.getElementById('edit_nome').value = u.nome;
        document.getElementById('edit_email').value = u.email;
        document.getElementById('edit_celular').value = u.celular;
        
        // Seta o nível no select. Se estiver nulo, assume membro.
        const nivel = u.nivel ? u.nivel.trim().toLowerCase() : 'membro';
        document.getElementById('edit_nivel').value = nivel;
        
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }
</script>
</body>
</html>
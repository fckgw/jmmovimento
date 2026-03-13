<?php
/**
 * GESTÃO DE USUÁRIOS JMM - VERSÃO RESILIENTE (WHATSAPP + SMTP)
 */
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') {
    header("Location: sistema_dashboard.php");
    exit;
}

$msg = ''; $erro = ''; $senha_para_whatsapp = ''; $nome_para_whatsapp = ''; $celular_para_whatsapp = ''; $email_para_whatsapp = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_cadastrar'])) {
    $nome    = trim($_POST['nome']);
    $email   = trim($_POST['email']);
    $celular = trim($_POST['celular']);
    $nivel   = $_POST['nivel'];

    // Gera senha aleatória
    $caracteres = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $senha_plana = substr(str_shuffle($caracteres), 0, 8);
    $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, celular, senha, nivel, forcar_reset) VALUES (?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([$nome, $email, $celular, $senha_hash, $nivel])) {
            
            $msg = "Usuário <b>$nome</b> cadastrado no banco de dados!";
            
            // Dados para o botão de contingência (WhatsApp)
            $senha_para_whatsapp = $senha_plana;
            $nome_para_whatsapp = $nome;
            $celular_para_whatsapp = preg_replace('/\D/', '', $celular);
            $email_para_whatsapp = $email;

            // --- TENTATIVA DE ENVIO VIA SMTP ---
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'email-ssl.com.br';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'souzafelipe@bdsoft.com.br';
                $mail->Password   = 'BDSoft@2020';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom('souzafelipe@bdsoft.com.br', 'JMM System');
                $mail->addAddress($email, $nome);
                $mail->isHTML(true);
                $mail->Subject = 'A Paz de Jesus! Seus dados de acesso JMM System';
                
                $mail->Body = "<div style='font-family:sans-serif;'><h2>Bem-vindo!</h2><p>Usuário: $email<br>Senha: $senha_plana</p><br>
                                <p>Acesse o sistema em: <a href='https://www.jmmovimento.com.br'>https://www.jmmovimento.com.br</a></p></div>";



                $mail->send();
                $msg .= " Convite enviado com sucesso por e-mail!";

            } catch (Exception $e) {
                // Se cair aqui, é porque a Microsoft bloqueou ou o SMTP falhou
                $msg .= "<br><span class='text-danger fw-bold'><i class='bi bi-exclamation-triangle'></i> O e-mail foi recusado pelo servidor de destino (Microsoft/Outlook).</span>";
            }
        }
    } catch (Exception $e) {
        $erro = "E-mail já cadastrado ou erro no banco.";
    }
}

// LÓGICA DE EXCLUSÃO
if (isset($_GET['delete'])) {
    $id_del = (int)$_GET['delete'];
    if ($id_del != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_del]);
        header("Location: usuarios.php?msg_sucesso=Removido"); exit;
    }
}

$lista = $pdo->query("SELECT id, nome, email, nivel, celular FROM usuarios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7f6; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .contingencia-box { background: #fff3cd; border: 2px dashed #ffc107; border-radius: 15px; padding: 20px; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="sistema_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        <h3 class="fw-bold m-0 text-dark">Gestão de Usuários</h3>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalAdd">+ NOVO</button>
    </div>

    <!-- QUADRO DE CONTINGÊNCIA (SÓ APARECE SE GERAR SENHA) -->
    <?php if($senha_para_whatsapp): ?>
        <div class="contingencia-box text-center shadow-sm">
            <h5 class="fw-bold text-dark"><i class="bi bi-whatsapp text-success"></i> ENVIAR ACESSO AGORA</h5>
            <p class="small text-muted mb-3">Como o e-mail pode falhar por bloqueios da Microsoft, envie os dados manualmente abaixo:</p>
            <div class="bg-white p-2 rounded mb-3 border">
                <span class="small text-muted">SENHA GERADA:</span><br>
                <strong class="fs-3 text-danger font-monospace"><?=$senha_para_whatsapp?></strong>
            </div>
            <?php 
                $txt = "A Paz de Jesus e o Amor de Maria, $nome_para_whatsapp!\n\nSeja bem vindo ao *JMM System*.\n\nSeguem seus dados:\n👤 *Usuário:* $email_para_whatsapp\n🔑 *Senha:* $senha_para_whatsapp\n\n🔗 Acesse em: https://jmmovimento.com.br/login.php\n\n_No primeiro acesso o sistema pedirá para você criar sua senha definitiva._";
            ?>
            <a href="https://api.whatsapp.com/send?phone=55<?=$celular_para_whatsapp?>&text=<?=rawurlencode($txt)?>" target="_blank" class="btn btn-success btn-lg w-100 fw-bold shadow">
                <i class="bi bi-whatsapp"></i> ENVIAR PELO WHATSAPP
            </a>
        </div>
    <?php endif; ?>

    <?php if($msg): ?> <div class="alert alert-info shadow-sm"><?=$msg?></div> <?php endif; ?>
    <?php if($erro): ?> <div class="alert alert-danger shadow-sm"><?=$erro?></div> <?php endif; ?>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-dark">
                    <tr><th>NOME</th><th>LOGIN</th><th>NÍVEL</th><th>AÇÕES</th></tr>
                </thead>
                <tbody>
                    <?php foreach($lista as $u): ?>
                    <tr>
                        <td class="fw-bold"><?=$u['nome']?></td>
                        <td><small><?=$u['email']?></small></td>
                        <td><span class="badge <?=$u['nivel']=='admin'?'bg-primary':'bg-secondary'?>"><?=strtoupper($u['nivel'])?></span></td>
                        <td>
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?=$u['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir?')"><i class="bi bi-trash"></i></a>
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
        <form method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="acao_cadastrar" value="1">
            <div class="modal-header bg-dark text-white"><h5>Cadastrar Membro</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold">NOME COMPLETO</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Felipe Souza">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">E-MAIL (LOGIN)</label>
                    <input type="email" name="email" class="form-control" required placeholder="felipe@outlook.com">
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="small fw-bold">WHATSAPP</label>
                        <input type="text" name="celular" class="form-control" placeholder="(11) 99999-8888" required>
                    </div>
                    <div class="col-5">
                        <label class="small fw-bold">ACESSO</label>
                        <select name="nivel" class="form-select">
                            <option value="membro">Membro</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100 fw-bold">CADASTRAR E GERAR ACESSO</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
/**
 * RECUPERAÇÃO DE SENHA JMM SYSTEM
 */
require_once 'config.php';

// Importa PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$msg = ''; $erro = ''; $senha_gerada = ''; $user_nome = ''; $user_celular = ''; $user_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    // 1. Verifica se o e-mail existe na base
    $stmt = $pdo->prepare("SELECT id, nome, celular FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Gera nova senha aleatória (8 caracteres legíveis)
        $caracteres = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $nova_senha_plana = substr(str_shuffle($caracteres), 0, 8);
        $senha_hash = password_hash($nova_senha_plana, PASSWORD_DEFAULT);

        // 3. Atualiza no banco e força o reset no próximo login
        $upd = $pdo->prepare("UPDATE usuarios SET senha = ?, forcar_reset = 1 WHERE id = ?");
        if ($upd->execute([$senha_hash, $user['id']])) {
            
            $senha_gerada = $nova_senha_plana;
            $user_nome = $user['nome'];
            $user_email = $email;
            $user_celular = preg_replace('/\D/', '', $user['celular']);

            registrarLog($pdo, "Solicitou recuperação de senha para: $email", "Esqueceu Senha");

            // 4. Tenta enviar por e-mail
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
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'A Paz de Jesus! Sua Nova Senha JMM System';
                
                $mail->Body = "
                <div style='font-family: sans-serif; color: #333; padding: 20px; border: 1px solid #eee;'>
                    <h2 style='color: #0d6efd;'>JMM System</h2>
                    <p>A Paz de Jesus e o Amor de Maria, $user_nome!</p>
                    <p>Você solicitou a recuperação de sua senha. Aqui estão seus novos dados de acesso:</p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>
                        <p><strong>Usuário:</strong> $email</p>
                        <p><strong>Nova Senha:</strong> <span style='color: #dc3545; font-weight: bold; font-size: 1.2rem;'>$nova_senha_plana</span></p>
                        <p><strong>Link do Sistema:</strong> <a href='https://www.jmmovimento.com.br'>www.jmmovimento.com.br</a></p>
                    </div>
                    <p><small>* Por segurança, você deverá trocar esta senha ao logar.</small></p>
                </div>";

                $mail->send();
                $msg = "Uma nova senha foi gerada e enviada para o seu e-mail!";
            } catch (Exception $e) {
                $msg = "Senha gerada com sucesso! Porém, o serviço de e-mail falhou. Use as opções abaixo para copiar ou enviar via WhatsApp.";
            }
        }
    } else {
        $erro = "E-mail não encontrado em nossa base de dados.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-recovery { width: 100%; max-width: 450px; padding: 30px; border-radius: 20px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .senha-destaque { background: #fff3cd; border: 2px dashed #ffc107; border-radius: 10px; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 1.8rem; font-weight: bold; color: #856404; }
    </style>
</head>
<body>

<div class="card-recovery text-center">
    <img src="Img/logo.jpg" width="70" class="rounded-circle mb-3">
    <h4 class="fw-bold mb-2">Recuperar Senha</h4>
    <p class="text-muted small mb-4">Informe seu e-mail cadastrado para receber uma nova senha.</p>

    <?php if($erro): ?> <div class="alert alert-danger small py-2"><?=$erro?></div> <?php endif; ?>
    
    <?php if($msg): ?> 
        <div class="alert alert-success small py-2"><?=$msg?></div>
        <?php if($senha_gerada): ?>
            <div class="senha-destaque" id="novaSenhaTxt"><?=$senha_gerada?></div>
            
            <div class="d-grid gap-2">
                <button class="btn btn-outline-dark btn-sm" onclick="copiarSenha()">
                    <i class="bi bi-clipboard"></i> Copiar Senha
                </button>
                
                <?php 
                $txtWpp = "A Paz de Jesus, $user_nome!\nMinha nova senha do *JMM System* é:\n🔑 $senha_gerada\n\n🔗 Acessar: https://www.jmmovimento.com.br";
                ?>
                <a href="https://api.whatsapp.com/send?phone=55<?=$user_celular?>&text=<?=rawurlencode($txtWpp)?>" target="_blank" class="btn btn-success fw-bold">
                    <i class="bi bi-whatsapp"></i> Mandar para meu WhatsApp
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(!$senha_gerada): ?>
    <form method="POST">
        <div class="text-start mb-4">
            <label class="small fw-bold text-muted">E-MAIL CADASTRADO</label>
            <input type="email" name="email" class="form-control" placeholder="exemplo@email.com" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">GERAR NOVA SENHA</button>
    </form>
    <?php endif; ?>

    <div class="mt-4">
        <a href="login.php" class="text-decoration-none small text-primary"><i class="bi bi-arrow-left"></i> Voltar para o Login</a>
    </div>
</div>

<script>
    function copiarSenha() {
        const text = document.getElementById('novaSenhaTxt').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Senha copiada para a área de transferência!');
        });
    }
</script>
</body>
</html>
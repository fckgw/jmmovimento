<?php
/**
 * GESTÃO DE USUÁRIOS JMM - VERSÃO INTEGRADA (ADMIN + SECRETARIA)
 * Inclui: PHPMailer, Contingência WhatsApp e Gestão de Níveis.
 */
require_once 'config.php';

// Importação do PHPMailer para envio de e-mails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// SEGURANÇA: Apenas administradores acessam a gestão de usuários
if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') {
    header("Location: sistema_dashboard.php");
    exit;
}

// Inicialização de variáveis de controle
$msg = ''; 
$erro = ''; 
$senha_para_whatsapp = ''; 
$nome_para_whatsapp = ''; 
$celular_para_whatsapp = ''; 
$email_para_whatsapp = '';

// --- LÓGICA DE CADASTRO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_cadastrar'])) {
    $nome    = trim($_POST['nome']);
    $email   = trim($_POST['email']);
    $celular = trim($_POST['celular']);
    $nivel   = $_POST['nivel']; // Pode ser: admin, membro ou secretaria

    // Gera uma senha aleatória segura (sem caracteres ambíguos)
    $caracteres = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $senha_plana = substr(str_shuffle($caracteres), 0, 8);
    $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

    try {
        // Insere no banco de dados. 'forcar_reset = 1' obriga a troca de senha no 1º login
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, celular, senha, nivel, forcar_reset) VALUES (?, ?, ?, ?, ?, 1)");
        
        if ($stmt->execute([$nome, $email, $celular, $senha_hash, $nivel])) {
            
            $msg = "Usuário <b>$nome</b> cadastrado com sucesso!";
            
            // Prepara dados para o botão de contingência (WhatsApp) caso o e-mail falhe
            $senha_para_whatsapp = $senha_plana;
            $nome_para_whatsapp = $nome;
            $celular_para_whatsapp = preg_replace('/\D/', '', $celular);
            $email_para_whatsapp = $email;

            // --- ENVIO DE E-MAIL VIA SMTP ---
            $mail = new PHPMailer(true);
            try {
                // Configurações do Servidor
                $mail->isSMTP();
                $mail->Host       = 'email-ssl.com.br';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'souzafelipe@bdsoft.com.br';
                $mail->Password   = 'BDSoft@2020';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';

                // Destinatários
                $mail->setFrom('souzafelipe@bdsoft.com.br', 'JMM System');
                $mail->addAddress($email, $nome);

                // Conteúdo do E-mail
                $mail->isHTML(true);
                $mail->Subject = 'A Paz de Jesus! Seus dados de acesso JMM System';
                
                $corpo_email = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #0d6efd;'>Bem-vindo ao JMM System!</h2>
                    <p>Olá <b>$nome</b>, seu acesso ao sistema foi criado.</p>
                    <p><b>Usuário (E-mail):</b> $email<br>
                    <b>Senha Provisória:</b> $senha_plana</p>
                    <hr>
                    <p>Acesse agora para personalizar sua senha:</p>
                    <p><a href='https://jmmovimento.com.br' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ACESSAR SISTEMA</a></p>
                    <br>
                    <small>Se você não solicitou este acesso, por favor desconsidere.</small>
                </div>";

                $mail->Body = $corpo_email;

                $mail->send();
                $msg .= " O convite oficial foi enviado para o e-mail informado.";

            } catch (Exception $e) {
                // Erro silencioso no SMTP (exibido apenas como aviso de falha de entrega)
                $msg .= "<br><span class='text-danger fw-bold'><i class='bi bi-exclamation-triangle'></i> Aviso: O servidor de e-mail (Outlook/Microsoft) pode ter recusado o envio automático. Use o WhatsApp abaixo.</span>";
            }
        }
    } catch (Exception $e) {
        $erro = "Erro: Este e-mail já está em uso ou houve um problema na conexão com o banco de dados.";
    }
}

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['delete'])) {
    $id_del = (int)$_GET['delete'];
    // Impede que o usuário logado exclua a si mesmo
    if ($id_del != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_del]);
        header("Location: usuarios.php?msg_sucesso=Removido"); 
        exit;
    }
}

// Busca todos os usuários cadastrados
$lista = $pdo->query("SELECT id, nome, email, nivel, celular FROM usuarios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .contingencia-box { 
            background: #fff3cd; 
            border: 2px dashed #ffc107; 
            border-radius: 15px; 
            padding: 20px; 
            margin-bottom: 25px; 
            animation: pulse 2s infinite; 
        }
        @keyframes pulse { 
            0% { transform: scale(1); } 
            50% { transform: scale(1.01); } 
            100% { transform: scale(1); } 
        }
        .table thead th { border: none; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; }
        .badge { padding: 8px 12px; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>

<div class="container py-4">
    
    <!-- CABEÇALHO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="sistema_dashboard.php" class="btn btn-outline-secondary px-3 rounded-pill">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <h3 class="fw-bold m-0 text-dark">Gestão de Usuários</h3>
        <button class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bi bi-person-plus-fill"></i> NOVO
        </button>
    </div>

    <!-- QUADRO DE CONTINGÊNCIA (WHATSAPP) -->
    <?php if($senha_para_whatsapp): ?>
        <div class="contingencia-box text-center shadow-sm">
            <h5 class="fw-bold text-dark"><i class="bi bi-whatsapp text-success"></i> ENVIAR ACESSO POR WHATSAPP</h5>
            <p class="small text-muted mb-3">O e-mail pode demorar ou cair no SPAM. Envie os dados agora pelo WhatsApp:</p>
            <div class="bg-white p-3 rounded mb-3 border">
                <span class="small text-muted text-uppercase fw-bold">Senha Temporária Gerada:</span><br>
                <strong class="fs-2 text-danger font-monospace"><?=$senha_para_whatsapp?></strong>
            </div>
            <?php 
                $txt = "A Paz de Jesus e o Amor de Maria, *$nome_para_whatsapp*!\n\nSeja bem vindo ao *JMM System*.\n\nSeguem seus dados de acesso:\n👤 *Usuário:* $email_para_whatsapp\n🔑 *Senha:* $senha_para_whatsapp\n\n🔗 *Acesse em:* https://jmmovimento.com.br/login.php\n\n_Ao entrar, o sistema solicitará que você crie sua senha definitiva._";
            ?>
            <a href="https://api.whatsapp.com/send?phone=55<?=$celular_para_whatsapp?>&text=<?=rawurlencode($txt)?>" target="_blank" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                <i class="bi bi-whatsapp"></i> DISPARAR NO WHATSAPP
            </a>
        </div>
    <?php endif; ?>

    <!-- MENSAGENS DE FEEDBACK -->
    <?php if($msg): ?> <div class="alert alert-success border-0 shadow-sm mb-4"><?=$msg?></div> <?php endif; ?>
    <?php if($erro): ?> <div class="alert alert-danger border-0 shadow-sm mb-4"><?=$erro?></div> <?php endif; ?>

    <!-- TABELA DE USUÁRIOS -->
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4 text-start">Membro / Nome</th>
                        <th>Login (E-mail)</th>
                        <th>Acesso</th>
                        <th class="pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lista as $u): ?>
                    <tr>
                        <td class="text-start ps-4">
                            <div class="fw-bold text-dark"><?=$u['nome']?></div>
                            <small class="text-muted"><?=$u['celular']?></small>
                        </td>
                        <td><span class="text-muted"><?=$u['email']?></span></td>
                        <td>
                            <?php 
                                // Cores dinâmicas por nível
                                $cor_badge = 'bg-secondary';
                                if($u['nivel'] == 'admin') $cor_badge = 'bg-primary';
                                if($u['nivel'] == 'secretaria') $cor_badge = 'bg-warning text-dark';
                            ?>
                            <span class="badge <?=$cor_badge?>"><?=strtoupper($u['nivel'])?></span>
                        </td>
                        <td class="pe-4">
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?=$u['id']?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Deseja realmente excluir este usuário?')">
                                    <i class="bi bi-trash fs-5"></i>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-muted small">VOCÊ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: CADASTRAR NOVO USUÁRIO -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="acao_cadastrar" value="1">
            
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus"></i> Novo Acesso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-1">NOME COMPLETO</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Felipe Souza">
                </div>
                
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-1">E-MAIL (SERÁ O LOGIN)</label>
                    <input type="email" name="email" class="form-control" required placeholder="exemplo@jmmovimento.com.br">
                </div>
                
                <div class="row g-3 mb-2">
                    <div class="col-7">
                        <label class="small fw-bold text-muted mb-1">WHATSAPP</label>
                        <input type="text" name="celular" class="form-control" placeholder="(00) 00000-0000" required>
                    </div>
                    <div class="col-5">
                        <label class="small fw-bold text-muted mb-1">NÍVEL ACESSO</label>
                        <select name="nivel" class="form-select">
                            <option value="membro">Membro</option>
                            <option value="secretaria">Secretaria</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                
                <p class="text-muted mt-3 mb-0" style="font-size: 0.75rem;">
                    * Uma senha aleatória será gerada e enviada automaticamente.
                </p>
            </div>
            
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow">CADASTRAR E GERAR SENHA</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
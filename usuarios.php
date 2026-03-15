<?php
/**
 * GESTÃO DE USUÁRIOS JMM - VERSÃO COMPLETA COM EDIÇÃO E WHATSAPP PERSONALIZADO
 * Módulos: Cadastro, Edição, Exclusão, PHPMailer e WhatsApp com Template Específico.
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

// --- 1. LÓGICA DE CADASTRO / EDIÇÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    // AÇÃO: CADASTRAR NOVO USUÁRIO
    if ($acao == 'cadastrar') {
        $nome    = trim($_POST['nome']);
        $email   = trim($_POST['email']);
        $celular = trim($_POST['celular']);
        $nivel   = $_POST['nivel'];

        // Gera senha aleatória segura
        $caracteres = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $senha_plana = substr(str_shuffle($caracteres), 0, 8);
        $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, celular, senha, nivel, forcar_reset) VALUES (?, ?, ?, ?, ?, 1)");
            if ($stmt->execute([$nome, $email, $celular, $senha_hash, $nivel])) {
                $msg = "Usuário <b>$nome</b> cadastrado com sucesso!";
                
                // --- DADOS PARA O WHATSAPP (TEMPLATE SOLICITADO) ---
                $senha_para_whatsapp = $senha_plana;
                $nome_para_whatsapp = $nome;
                $celular_para_whatsapp = preg_replace('/\D/', '', $celular);
                $email_para_whatsapp = $email;

                // --- TENTATIVA DE ENVIO DE E-MAIL (SMTP) ---
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
                    $mail->Body = "<h2>Bem-vindo!</h2><p>Usuário: $email<br>Senha: $senha_plana</p>";
                    $mail->send();
                    $msg .= " E-mail de boas-vindas enviado!";
                } catch (Exception $e) { 
                    $msg .= " (E-mail não enviado, use o WhatsApp abaixo)";
                }
            }
        } catch (Exception $e) { 
            $erro = "Erro: Este e-mail já está cadastrado no sistema."; 
        }
    }

    // AÇÃO: ATUALIZAR USUÁRIO EXISTENTE
    if ($acao == 'editar') {
        $id_u    = $_POST['id_usuario'];
        $nome    = trim($_POST['nome']);
        $email   = trim($_POST['email']);
        $celular = trim($_POST['celular']);
        $nivel   = $_POST['nivel'];

        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, email=?, celular=?, nivel=? WHERE id=?");
            $stmt->execute([$nome, $email, $celular, $nivel, $id_u]);
            $msg = "Dados de <b>$nome</b> atualizados com sucesso!";
        } catch (Exception $e) { 
            $erro = "Erro ao atualizar: Verifique se o e-mail já pertence a outro usuário."; 
        }
    }
}

// --- 2. LÓGICA DE EXCLUSÃO (GET) ---
if (isset($_GET['delete'])) {
    $id_del = (int)$_GET['delete'];
    if ($id_del != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_del]);
        header("Location: usuarios.php?msg_sucesso=Removido"); 
        exit;
    }
}

// Busca todos os usuários ordenados por nome
$lista = $pdo->query("SELECT id, nome, email, nivel, celular FROM usuarios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .contingencia-box { 
            background: #fff3cd; 
            border: 2px dashed #ffc107; 
            border-radius: 15px; 
            padding: 25px; 
            margin-bottom: 25px; 
            animation: pulse 2s infinite; 
        }
        @keyframes pulse { 
            0% { transform: scale(1); } 
            50% { transform: scale(1.02); } 
            100% { transform: scale(1); } 
        }
        .badge { padding: 8px 12px; border-radius: 8px; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="sistema_dashboard.php" class="btn btn-outline-secondary px-3 rounded-pill">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <h3 class="fw-bold m-0">Gestão de Usuários</h3>
        <button class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bi bi-person-plus-fill"></i> NOVO
        </button>
    </div>

    <!-- QUADRO DE ENVIO WHATSAPP (TEMPLATE SOLICITADO) -->
    <?php if($senha_para_whatsapp): ?>
        <div class="contingencia-box text-center shadow-sm">
            <h5 class="fw-bold text-dark"><i class="bi bi-whatsapp text-success"></i> ENVIAR ACESSO AGORA</h5>
            <p class="small text-muted mb-3">Clique no botão abaixo para enviar os dados formatados para o usuário:</p>
            
            <div class="bg-white p-3 rounded mb-3 border">
                <span class="small text-muted fw-bold">SENHA GERADA:</span><br>
                <strong class="fs-2 text-danger font-monospace"><?=$senha_para_whatsapp?></strong>
            </div>

            <?php 
                // Montagem do Texto conforme solicitado
                $txt = "A Paz de Jesus e o Amor de Maria, *$nome_para_whatsapp*!\n\n";
                $txt .= "Seja bem vindo ao *JMM System*.\n\n";
                $txt .= "Seguem seus dados de acesso:\n";
                $txt .= "👤 *Usuário:* $email_para_whatsapp\n";
                $txt .= "🔑 *Senha:* $senha_para_whatsapp\n\n";
                $txt .= "🔗 *Acesse em:* https://jmmovimento.com.br/login.php\n\n";
                $txt .= "_Ao entrar, o sistema solicitará que você crie sua senha definitiva._";

                $whatsapp_url = "https://api.whatsapp.com/send?phone=55" . $celular_para_whatsapp . "&text=" . rawurlencode($txt);
            ?>

            <a href="<?= $whatsapp_url ?>" target="_blank" class="btn btn-success btn-lg w-100 fw-bold rounded-pill shadow">
                <i class="bi bi-whatsapp"></i> ENVIAR ACESSO PELO WHATSAPP
            </a>
        </div>
    <?php endif; ?>

    <!-- ALERTAS DE FEEDBACK -->
    <?php if($msg): ?> <div class="alert alert-success border-0 shadow-sm mb-4"><?=$msg?></div> <?php endif; ?>
    <?php if($erro): ?> <div class="alert alert-danger border-0 shadow-sm mb-4"><?=$erro?></div> <?php endif; ?>

    <!-- LISTAGEM DE USUÁRIOS -->
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4 text-start">MEMBRO / NOME</th>
                        <th>LOGIN (E-MAIL)</th>
                        <th>NÍVEL</th>
                        <th class="pe-4">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lista as $u): ?>
                    <tr>
                        <td class="text-start ps-4">
                            <div class="fw-bold text-dark"><?=$u['nome']?></div>
                            <small class="text-muted"><?=$u['celular']?></small>
                        </td>
                        <td><span class="text-muted small"><?=$u['email']?></span></td>
                        <td>
                            <?php 
                                $nv = trim(strtolower($u['nivel']));
                                $cor = 'bg-secondary';
                                if($nv == 'admin') $cor = 'bg-primary';
                                if($nv == 'secretaria') $cor = 'bg-warning text-dark';
                                
                                $txt_exibir = (!empty($nv)) ? strtoupper($nv) : '<span class="text-danger small">NÃO DEFINIDO</span>';
                            ?>
                            <span class="badge <?=$cor?>"><?=$txt_exibir?></span>
                        </td>
                        <td class="pe-4 text-nowrap">
                            <!-- BOTÃO EDITAR -->
                            <button class="btn btn-link text-primary p-0 me-3" onclick='abrirEdicao(<?= json_encode($u) ?>)'>
                                <i class="bi bi-pencil-square fs-5"></i>
                            </button>

                            <!-- BOTÃO EXCLUIR -->
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?=$u['id']?>" class="btn btn-link text-danger p-0" onclick="return confirm('Deseja realmente excluir este usuário?')">
                                    <i class="bi bi-trash fs-5"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: CADASTRAR -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="form_acao" value="cadastrar">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold">Novo Usuário</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold mb-1">NOME COMPLETO</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1">E-MAIL (LOGIN)</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="small fw-bold mb-1">WHATSAPP</label>
                        <input type="text" name="celular" class="form-control" required placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-5">
                        <label class="small fw-bold mb-1">NÍVEL</label>
                        <select name="nivel" class="form-select" required>
                            <option value="membro">Membro</option>
                            <option value="secretaria">Secretaria</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow">SALVAR E GERAR ACESSO</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="form_acao" value="editar">
            <input type="hidden" name="id_usuario" id="edit_id">
            <div class="modal-header bg-primary text-white p-4">
                <h5 class="modal-title fw-bold">Editar Dados</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold mb-1">NOME COMPLETO</label>
                    <input type="text" name="nome" id="edit_nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1">E-MAIL (LOGIN)</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="small fw-bold mb-1">WHATSAPP</label>
                        <input type="text" name="celular" id="edit_celular" class="form-control" required>
                    </div>
                    <div class="col-5">
                        <label class="small fw-bold mb-1">NÍVEL</label>
                        <select name="nivel" id="edit_nivel" class="form-select" required>
                            <option value="membro">Membro</option>
                            <option value="secretaria">Secretaria</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow">ATUALIZAR DADOS</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /**
     * Preenche o Modal de Edição com os dados do usuário selecionado
     */
    function abrirEdicao(u) {
        document.getElementById('edit_id').value = u.id;
        document.getElementById('edit_nome').value = u.nome;
        document.getElementById('edit_email').value = u.email;
        document.getElementById('edit_celular').value = u.celular;
        
        // Sincroniza o select com o nível do banco (limpando espaços)
        const nivelLimpo = u.nivel ? u.nivel.trim().toLowerCase() : 'membro';
        document.getElementById('edit_nivel').value = nivelLimpo;
        
        // Abre o modal de edição
        const meuModal = new bootstrap.Modal(document.getElementById('modalEdit'));
        meuModal.show();
    }
</script>

</body>
</html>
<?php
/**
 * JMM SYSTEM - LOGIN ADM
 * Fluxo: Validação -> Registro de Acesso -> Log -> Redirecionamento
 */

require_once 'config.php';

// Se o usuário já estiver logado, pula direto para o dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: sistema_dashboard.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        try {
            // Busca o usuário pelo e-mail
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Verifica se usuário existe e se a senha confere
            if ($user && password_verify($senha, $user['senha'])) {
                
                // 1. REGISTRA O ACESSO NO BANCO DE DADOS
                // Salvamos o acesso anterior na sessão para exibir no dashboard antes de atualizar o banco
                $_SESSION['ultimo_acesso'] = $user['ultimo_acesso'];
                
                $agora = date('Y-m-d H:i:s');
                $upd = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?");
                $upd->execute([$agora, $user['id']]);

                // 2. CONFIGURA AS VARIÁVEIS DE SESSÃO
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['nivel']     = $user['nivel'];

                // 3. GRAVA O LOG DE AUDITORIA
                if (function_exists('registrarLog')) {
                    registrarLog($pdo, "Realizou login com sucesso", "Login");
                }

                // 4. VERIFICA SE PRECISA FORÇAR A TROCA DE SENHA
                if ($user['forcar_reset'] == 1) {
                    header("Location: trocar_senha.php?msg=Primeiro acesso: Altere sua senha.");
                    exit;
                }

                // 5. REDIRECIONA PARA O DASHBOARD DO SISTEMA
                header("Location: sistema_dashboard.php");
                exit;

            } else {
                $erro = "E-mail ou senha incorretos.";
                // Opcional: Log de tentativa falha
                if (function_exists('registrarLog')) {
                    registrarLog($pdo, "Tentativa de login falha para o e-mail: $email", "Login");
                }
            }
        } catch (PDOException $e) {
            $erro = "Erro no servidor. Tente novamente mais tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acesso Restrito - JMM System</title>
    
    <!-- Bootstrap 5 e Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { 
            background-color: #f0f2f5; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            background: #ffffff; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            border: none;
            margin: 15px;
        }
        .logo-login { 
            width: 100px; 
            height: 100px;
            object-fit: cover;
            margin-bottom: 20px; 
            border-radius: 50%; 
            border: 4px solid #f8f9fa;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
        }
        .form-control:focus {
            background-color: #fff;
            box-shadow: 0 0 0 0.25 text-primary;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-left: none;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            color: #6c757d;
        }
        .btn-primary {
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            background-color: #0d6efd;
            border: none;
            transition: 0.3s;
        }
        .btn-primary:hover {
            background-color: #0a58ca;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.3);
        }
        .forgot-link {
            font-size: 0.85rem;
            color: #6c757d;
            text-decoration: none;
            transition: 0.2s;
        }
        .forgot-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card text-center">
        <!-- Logo no caminho especificado -->
        <img src="Img/logo.jpg" alt="Logo JMM" class="logo-login">
        
        <h4 class="fw-bold mb-1 text-dark">JMM System</h4>
        <p class="text-muted small mb-4">Acesso Restrito à Administração</p>

        <?php if($erro): ?>
            <div class="alert alert-danger py-2 small border-0 mb-4 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <!-- Campo E-mail -->
            <div class="text-start mb-3">
                <label class="small fw-bold text-secondary ms-2 mb-1 text-uppercase" style="font-size: 0.65rem;">E-mail de Usuário</label>
                <input type="email" name="email" class="form-control" placeholder="exemplo@bdsoft.com.br" required autofocus>
            </div>

            <!-- Campo Senha com Olhinho -->
            <div class="text-start mb-2">
                <label class="small fw-bold text-secondary ms-2 mb-1 text-uppercase" style="font-size: 0.65rem;">Senha de Acesso</label>
                <div class="input-group">
                    <input type="password" name="senha" id="inputSenha" class="form-control" style="border-right: none;" placeholder="••••••••" required>
                    <span class="input-group-text" onclick="toggleSenha()">
                        <i class="bi bi-eye-fill" id="iconOlho"></i>
                    </span>
                </div>
            </div>

            <!-- Link Esqueceu a Senha -->
            <div class="text-end mb-4">
                <a href="esqueceu_senha.php" class="forgot-link">Esqueceu a senha?</a>
            </div>

            <!-- Botão Entrar -->
            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                ENTRAR NO SISTEMA <i class="bi bi-box-arrow-in-right ms-1"></i>
            </button>
        </form>
        
        <div class="mt-5 pt-2 border-top">
            <a href="site/index.php" class="text-muted small text-decoration-none">
                <i class="bi bi-house-door me-1"></i> Voltar para o Site Público
            </a>
        </div>
    </div>

    <script>
        /**
         * Função para Alternar Visibilidade da Senha (Olhinho)
         */
        function toggleSenha() {
            const input = document.getElementById('inputSenha');
            const icon = document.getElementById('iconOlho');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            }
        }
    </script>

</body>
</html>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JMM - Juventude da Matriz em Movimento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #ffffff; 
            height: 100vh; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-hero {
            text-align: center;
            padding: 20px;
        }
        .logo-main {
            width: 220px;
            height: 220px;
            object-fit: cover;
            border-radius: 50%;
            border: 8px solid #f0f2f5;
            margin-bottom: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: 0.5s;
        }
        .logo-main:hover {
            transform: scale(1.05);
        }
        h1 { font-weight: 800; color: #0d6efd; letter-spacing: -1px; margin-bottom: 5px; }
        .tagline { color: #6c757d; font-size: 1.2rem; font-weight: 500; margin-bottom: 40px; }
        .btn-restrita {
            border-radius: 50px;
            padding: 12px 40px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.2);
        }
    </style>
</head>
<body>

    <div class="container-hero">
        <!-- Logo carregando de jmmovimento.com.br/Img/logo.jpg -->
        <img src="../Img/logo.jpg" alt="Logo JMM" class="logo-main">
        
        <h1>JUVENTUDE DA MATRIZ</h1>
        <p class="tagline">EM MOVIMENTO</p>
        
        <div class="d-grid gap-2 d-sm-block">
            <a href="../login.php" class="btn btn-primary btn-restrita btn-lg">
                <i class="bi bi-shield-lock-fill me-2"></i> ÁREA RESTRITA
            </a>
        </div>
        
        <div class="mt-5">
            <small class="text-muted">&copy; <?= date('Y') ?> JMMovimento - Todos os direitos reservados.</small>
        </div>
    </div>

</body>
</html>
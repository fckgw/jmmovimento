<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JMM - Juventude da Matriz em Movimento</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts: Inter para tecnologia e Poppins para títulos -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-tech: #0d6efd;
            --dark-bg: #0a0a0b;
            --accent-blue: #00d2ff;
            --glass: rgba(255, 255, 255, 0.1);
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #ffffff;
            color: #333;
            overflow-x: hidden;
        }

        h1, h2, h3, .navbar-brand { 
            font-family: 'Poppins', sans-serif; 
            letter-spacing: -1px;
        }

        /* Navbar Glassmorphism */
        .navbar {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: 0.4s;
            padding: 15px 0;
        }

        /* Carrossel Full Screen */
        .carousel-item {
            height: 100vh;
            min-height: 500px;
            background: no-repeat center center scroll;
            background-size: cover;
            position: relative;
        }

        /* Camada escura para o texto brilhar */
        .carousel-item::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0.7) 100%);
        }

        .carousel-caption {
            bottom: 20%;
            z-index: 2;
            text-align: left;
            max-width: 800px;
            left: 10%;
        }

        .carousel-caption h2 {
            font-size: 4.5rem;
            font-weight: 800;
            text-shadow: 0 5px 15px rgba(0,0,0,0.3);
            line-height: 1;
            margin-bottom: 20px;
            background: -webkit-linear-gradient(#fff, #adadad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Botões Modernos */
        .btn-tech {
            padding: 12px 35px;
            border-radius: 12px;
            font-weight: 600;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
        }

        .btn-primary-tech {
            background: linear-gradient(45deg, var(--primary-tech), var(--accent-blue));
            color: white;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4);
        }

        .btn-primary-tech:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.5);
            color: white;
        }

        /* Seção Quem Somos (Visual Tech) */
        .section-tech {
            padding: 100px 0;
        }

        .img-tech-frame {
            position: relative;
            padding: 15px;
        }

        .img-tech-frame::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 70%; height: 70%;
            border-top: 5px solid var(--primary-tech);
            border-left: 5px solid var(--primary-tech);
            z-index: 0;
        }

        .img-tech-frame img {
            position: relative;
            z-index: 1;
            border-radius: 5px;
        }

        /* Galeria em formato Grid Tecnológico */
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            height: 300px;
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.5s;
        }

        .gallery-item:hover img {
            transform: scale(1.1);
        }

        .gallery-overlay {
            position: absolute;
            bottom: 0; left: 0; width: 100%;
            padding: 20px;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            opacity: 0;
            transition: 0.3s;
        }

        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }

        /* Estilo Mobile */
        @media (max-width: 768px) {
            .carousel-caption h2 { font-size: 2.5rem; }
            .carousel-caption { left: 5%; bottom: 10%; text-align: center; }
            .carousel-item { height: 80vh; }
        }

        .footer-tech {
            background: var(--dark-bg);
            color: #888;
            padding: 80px 0 30px;
        }
    </style>
</head>
<body>

    <!-- Menu -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="../Img/logo.jpg" alt="JMM" width="45" class="rounded-circle me-2 shadow-sm">
                <span class="fw-bold text-dark">JMM<span class="text-primary">OVIMENTO</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link mx-2" href="#inicio">Início</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="#quem-somos">Movimento</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="#galeria">Galeria</a></li>
                    <li class="nav-item ms-lg-4">
                        <a href="../login.php" class="btn btn-tech btn-primary-tech">
                            <i class="bi bi-cpu me-2"></i>Área Restrita
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Carrossel Hero Full Screen -->
    <header id="inicio">
        <div id="jmmCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
            <div class="carousel-inner">
                <!-- Slide 1: Use a imagem do encontro -->
                <div class="carousel-item active" style="background-image: url('img/foto2.jpg');">
                    <div class="carousel-caption">
                        <span class="badge bg-primary mb-3">CONEXÃO & FÉ</span>
                        <h2>LIDERANÇA QUE <br>TRANSFORMA</h2>
                        <p class="lead text-white-50">A juventude da Matriz em constante movimento tecnológico e espiritual.</p>
                        <a href="#quem-somos" class="btn btn-tech btn-primary-tech mt-3">Explorar</a>
                    </div>
                </div>
                <!-- Slide 2 -->
                <div class="carousel-item" style="background-image: url('img/foto3.jpg');">
                    <div class="carousel-caption">
                        <span class="badge bg-primary mb-3">IDENTIDADE</span>
                        <h2>SOMOS O <br>MOVIMENTO</h2>
                        <p class="lead text-white-50">Muito além de um grupo, uma pastoral focada no futuro.</p>
                        <a href="../login.php" class="btn btn-tech btn-primary-tech mt-3">Acessar Sistema</a>
                    </div>
                </div>
            </div>
            <!-- Controles -->
            <button class="carousel-control-prev" type="button" data-bs-target="#jmmCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon shadow"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#jmmCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon shadow"></span>
            </button>
        </div>
    </header>

    <!-- Seção Quem Somos (Tecnológica) -->
    <section class="section-tech" id="quem-somos">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="img-tech-frame">
                        <img src="img/foto2.jpg" alt="Equipe" class="img-fluid shadow-lg">
                    </div>
                </div>
                <div class="col-lg-6 ps-lg-5">
                    <h6 class="text-primary fw-bold text-uppercase mb-3">Nossa Pastoral</h6>
                    <h2 class="display-4 fw-bold mb-4">Inovação a serviço do Evangelho</h2>
                    <p class="text-muted mb-4">O <strong>JMMovimento</strong> é a Pastoral de Jovens da Igreja Matriz de São João Batista de Caçapava. Unimos a tradição da nossa paróquia com a linguagem tecnológica da nova geração.</p>
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-rocket-takeoff text-primary h3 me-3"></i>
                                <div><h6 class="mb-0 fw-bold">Propósito</h6><small>Crescimento Real</small></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-shield-check text-primary h3 me-3"></i>
                                <div><h6 class="mb-0 fw-bold">Segurança</h6><small>Ambiente Acolhedor</small></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Galeria Estilo Grid Tech -->
    <section id="galeria" class="py-5" style="background: #f8f9fa;">
        <div class="container py-5">
            <div class="row mb-5">
                <div class="col-md-6">
                    <h2 class="fw-bold">Galeria de Eventos</h2>
                    <p class="text-muted">Registros do nosso 1º Encontro JMM</p>
                </div>
                <div class="col-md-6 text-md-end d-flex align-items-end justify-content-md-end">
                    <p class="text-primary fw-bold">#PastoralEmMovimento</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 col-6">
                    <div class="gallery-item shadow-sm">
                        <img src="img/foto3.jpg" alt="Encontro">
                        <div class="gallery-overlay"><h6>Palestra Central</h6></div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="gallery-item shadow-sm">
                        <img src="img/foto4.jpg" alt="Fé">
                        <div class="gallery-overlay"><h6>Espiritualidade</h6></div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="gallery-item shadow-sm">
                        <img src="img/foto5.jpg" alt="Juventude">
                        <div class="gallery-overlay"><h6>Nossa Comunidade</h6></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Rodapé Dark Tech -->
    <footer class="footer-tech">
        <div class="container text-center">
            <img src="../Img/logo.jpg" alt="Logo" width="60" class="rounded-circle mb-4 grayscale">
            <h3 class="text-white fw-bold mb-4">JMM<span class="text-primary">OVIMENTO</span></h3>
            <div class="d-flex justify-content-center mb-5">
                <a href="https://www.instagram.com/nsdajudacpv/" target="_blank" class="mx-3 text-light h4"><i class="bi bi-instagram"></i></a>
                <a href="#" class="mx-3 text-light h4"><i class="bi bi-youtube"></i></a>
                <a href="#" class="mx-3 text-light h4"><i class="bi bi-whatsapp"></i></a>
            </div>
            <p class="small opacity-50">Igreja Matriz de São João Batista - Caçapava/SP</p>
            <p class="small opacity-50">&copy; <?= date('Y') ?> | Sistema desenvolvido para gestão pastoral.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mudar cor da Navbar ao rolar
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('shadow-lg');
                document.querySelector('.navbar').style.padding = '10px 0';
            } else {
                document.querySelector('.navbar').classList.remove('shadow-lg');
                document.querySelector('.navbar').style.padding = '15px 0';
            }
        });
    </script>
</body>
</html>
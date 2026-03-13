<?php
require_once 'config.php';

// Segurança
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- LÓGICA DE SALVAR ---
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'salvar') {
    $id = $_POST['id'];
    $titulo = $_POST['titulo'];
    $conteudo = $_POST['conteudo']; // Conteúdo HTML do editor
    
    // Gera Slug simples (ex: Quem Somos -> quem-somos)
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $titulo)));

    if ($id) {
        // Atualizar
        $stmt = $pdo->prepare("UPDATE site_paginas SET titulo=?, slug=?, conteudo=? WHERE id=?");
        $stmt->execute([$titulo, $slug, $conteudo, $id]);
        $msg = "Página atualizada com sucesso!";
    } else {
        // Criar Nova
        $stmt = $pdo->prepare("INSERT INTO site_paginas (titulo, slug, conteudo) VALUES (?, ?, ?)");
        $stmt->execute([$titulo, $slug, $conteudo]);
        $msg = "Página criada com sucesso!";
    }
}

// --- LÓGICA DE LISTAGEM OU EDIÇÃO ---
$acao_get = $_GET['acao'] ?? 'listar';
$pagina_edit = null;

if ($acao_get == 'editar' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM site_paginas WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $pagina_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($acao_get == 'excluir' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM site_paginas WHERE id = ?")->execute([$_GET['id']]);
    header("Location: site_gestao.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão do Site - CMS</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Editor de Texto (TinyMCE) -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#editor_conteudo',
        height: 500,
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
      });
    </script>

    <style>
        body { min-height: 100vh; display: flex; overflow-x: hidden; background: #f4f7f6; }
        
        /* Menu Lateral (Sidebar) */
        .sidebar {
            min-width: 260px;
            max-width: 260px;
            background: #212529;
            color: #fff;
            transition: all 0.3s;
            display: flex; flex-direction: column;
        }
        .sidebar-header { padding: 20px; background: #1a1e21; text-align: center; }
        .sidebar ul { list-style: none; padding: 0; margin-top: 20px; }
        .sidebar ul li a {
            padding: 15px 25px;
            display: block;
            color: #adb5bd;
            text-decoration: none;
            border-left: 4px solid transparent;
            font-weight: 500;
        }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            color: #fff;
            background: #343a40;
            border-left-color: #0d6efd;
        }
        
        /* Conteúdo Principal */
        .content { width: 100%; padding: 20px; }
        
        /* Mobile Toggle */
        @media (max-width: 768px) {
            .sidebar { margin-left: -260px; position: fixed; height: 100%; z-index: 999; }
            .sidebar.active { margin-left: 0; }
        }
    </style>
</head>
<body>

    <!-- MENU LATERAL -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5 class="fw-bold m-0">CMS JMM</h5>
            <small class="text-muted">Gestor de Conteúdo</small>
        </div>

        <ul>
            <li>
                <a href="dashboard.php"><i class="bi bi-arrow-left-circle me-2"></i> Voltar ao Painel</a>
            </li>
            <li>
                <a href="site_gestao.php" class="<?= ($acao_get == 'listar') ? 'active' : '' ?>">
                    <i class="bi bi-file-text me-2"></i> Todas as Páginas
                </a>
            </li>
            <li>
                <a href="site_gestao.php?acao=editar" class="<?= ($acao_get == 'editar' && !$pagina_edit) ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle me-2"></i> Nova Página
                </a>
            </li>
            <li>
                <a href="site/index.php" target="_blank" class="text-info">
                    <i class="bi bi-box-arrow-up-right me-2"></i> Ver Site Online
                </a>
            </li>
        </ul>
        
        <div class="mt-auto p-3 text-center">
            <small class="text-muted" style="font-size:10px;">JMMovimento v1.0</small>
        </div>
    </nav>

    <!-- ÁREA DE CONTEÚDO -->
    <div class="content">
        <!-- Botão Mobile -->
        <button class="btn btn-dark d-md-none mb-3" onclick="document.getElementById('sidebar').classList.toggle('active')">
            <i class="bi bi-list"></i> Menu
        </button>

        <?php if($msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $msg ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- VISÃO 1: LISTAGEM -->
        <?php if($acao_get == 'listar'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Gerenciar Páginas</h2>
                <a href="?acao=editar" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Página</a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Título</th>
                                <th>Link (Slug)</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $paginas = $pdo->query("SELECT * FROM site_paginas ORDER BY id ASC")->fetchAll();
                            foreach($paginas as $p): 
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= $p['titulo'] ?></td>
                                <td><code>/<?= $p['slug'] ?></code></td>
                                <td><span class="badge bg-success">Ativo</span></td>
                                <td class="text-end pe-4">
                                    <a href="site/index.php?pag=<?= $p['slug'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Ver"><i class="bi bi-eye"></i></a>
                                    <a href="?acao=editar&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <?php if($p['slug'] != 'home'): // Protege a Home de exclusão ?>
                                        <a href="?acao=excluir&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apagar esta página?')" title="Excluir"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- VISÃO 2: EDITOR (CRIAR/EDITAR) -->
        <?php if($acao_get == 'editar'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?= $pagina_edit ? 'Editar Página' : 'Nova Página' ?></h2>
                <a href="site_gestao.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="id" value="<?= $pagina_edit['id'] ?? '' ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Título da Página</label>
                            <input type="text" name="titulo" class="form-control" value="<?= $pagina_edit['titulo'] ?? '' ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Conteúdo</label>
                            <textarea id="editor_conteudo" name="conteudo"><?= $pagina_edit['conteudo'] ?? '' ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg px-5">Salvar Página</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
<?php
/**
 * JMM SYSTEM - CLOUD DRIVE (BUILD FINAL)
 * Correção: Função gerarTrilha integrada e ícones no breadcrumb.
 */
require_once 'config.php';

// 1. SEGURANÇA: Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_nivel = $_SESSION['nivel'];
$pasta_atual = isset($_GET['pasta']) ? (int)$_GET['pasta'] : null;

// --- FUNÇÕES DE APOIO ---

/**
 * Função para gerar a trilha de navegação (Breadcrumbs)
 */
function gerarTrilha($pdo, $id) {
    $trilha = [];
    while ($id) {
        $st = $pdo->prepare("SELECT id, nome, pai_id FROM pastas WHERE id = ?");
        $st->execute([$id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) break;
        array_unshift($trilha, $p);
        $id = $p['pai_id'];
    }
    return $trilha;
}

/**
 * Função para verificar se uma pasta ou algum de seus pais é pública (Herança de Privacidade)
 */
function verificarSePublica($pdo, $id) {
    if (!$id) return false;
    $st = $pdo->prepare("SELECT publica, pai_id FROM pastas WHERE id = ?");
    $st->execute([$id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) return false;
    if ($p['publica'] == 1) return true;
    return verificarSePublica($pdo, $p['pai_id']);
}

// --- LÓGICA DE NEGÓCIO ---

// 1. Determinar se o ambiente atual é público (herdado ou direto)
$ambiente_publico = verificarSePublica($pdo, $pasta_atual);

// 2. Busca a pasta pai para o botão "Voltar" rápido
$id_pai = null;
if ($pasta_atual) {
    $stPai = $pdo->prepare("SELECT pai_id FROM pastas WHERE id = ?");
    $stPai->execute([$pasta_atual]);
    $id_pai = $stPai->fetchColumn();
}

// 3. BUSCAR PASTAS: Se o ambiente é público ou sou Admin, vejo tudo. Caso contrário, vejo as minhas.
if ($pasta_atual) {
    if ($ambiente_publico || $user_nivel == 'admin') {
        $sqlP = "SELECT p.*, u.nome as dono_nome, u.email as dono_email FROM pastas p 
                 LEFT JOIN usuarios u ON p.usuario_id = u.id 
                 WHERE p.pai_id = $pasta_atual ORDER BY p.nome ASC";
    } else {
        $sqlP = "SELECT p.*, u.nome as dono_nome, u.email as dono_email FROM pastas p 
                 LEFT JOIN usuarios u ON p.usuario_id = u.id 
                 WHERE p.pai_id = $pasta_atual AND p.usuario_id = $user_id ORDER BY p.nome ASC";
    }
} else {
    // Raiz: Minhas pastas + pastas marcadas como públicas
    $sqlP = "SELECT p.*, u.nome as dono_nome, u.email as dono_email FROM pastas p 
             LEFT JOIN usuarios u ON p.usuario_id = u.id 
             WHERE p.pai_id IS NULL AND (p.usuario_id = $user_id OR p.publica = 1 OR '$user_nivel' = 'admin') 
             ORDER BY p.nome ASC";
}
$pastas = $pdo->query($sqlP)->fetchAll(PDO::FETCH_ASSOC);

// 4. BUSCAR ARQUIVOS
if ($pasta_atual) {
    if ($ambiente_publico || $user_nivel == 'admin') {
        $sqlA = "SELECT * FROM arquivos WHERE pasta_id = $pasta_atual ORDER BY id DESC";
    } else {
        $sqlA = "SELECT * FROM arquivos WHERE pasta_id = $pasta_atual AND usuario_id = $user_id ORDER BY id DESC";
    }
} else {
    $sqlA = "SELECT * FROM arquivos WHERE pasta_id IS NULL AND (usuario_id = $user_id OR '$user_nivel' = 'admin') ORDER BY id DESC";
}
$arquivos = $pdo->query($sqlA)->fetchAll(PDO::FETCH_ASSOC);

// 5. LISTA PARA MODAL MOVER
$todas_p_mover = $pdo->query("SELECT id, nome FROM pastas WHERE usuario_id = $user_id OR publica = 1 OR '$user_nivel' = 'admin' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// 6. QUOTA
$qMax = $pdo->query("SELECT quota_limite FROM usuarios WHERE id = $user_id")->fetchColumn() ?: 1073741824;
$qUso = $pdo->query("SELECT SUM(tamanho) FROM arquivos WHERE usuario_id = $user_id")->fetchColumn() ?: 0;
$perc = round(($qUso / $qMax) * 100);
if($perc > 100) $perc = 100;

function formatarBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    return number_format($bytes / 1024, 2) . ' KB';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Drive - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
    <style>
        :root { --sidebar-w: 250px; --primary-blue: #1a73e8; }
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; overflow-x: hidden; transition: 0.3s; }
        
        .sidebar { width: var(--sidebar-w); background: #fff; height: 100vh; position: fixed; border-right: 1px solid #e0e0e0; z-index: 1050; transition: 0.3s; left: 0; }
        .sidebar.collapsed { left: calc(-1 * var(--sidebar-w)); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); z-index: 1040; }
        .sidebar-overlay.active { display: block; }
        
        .main { margin-left: var(--sidebar-w); min-height: 100vh; transition: 0.3s; }
        .main.full { margin-left: 0; }

        .drive-header { background: #fff; padding: 12px 25px; border-bottom: 1px solid #e0e0e0; position: sticky; top: 0; z-index: 990; }
        .breadcrumb { font-size: 0.95rem; margin-bottom: 0; flex-wrap: nowrap; overflow-x: auto; }
        .breadcrumb-item i { margin-right: 5px; color: #ffc107; }
        .breadcrumb-item a { color: #5f6368; text-decoration: none; font-weight: 500; }
        .breadcrumb-item.active { color: var(--primary-blue); font-weight: 600; }

        .item-card { background: #fff; border: 1px solid #dadce0; border-radius: 12px; transition: 0.15s; position: relative; height: 100%; cursor: pointer; }
        .item-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-2px); }
        
        .item-check { position: absolute; top: 10px; left: 10px; z-index: 10; width: 18px; height: 18px; cursor: pointer; display: none; }
        .item-card:hover .item-check, .item-check:checked { display: block; }

        .thumb { height: 105px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 12px 12px 0 0; overflow: hidden; position: relative; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .thumb .fa-play-circle { position: absolute; color: white; font-size: 2.5rem; opacity: 0.8; }
        
        .badge-pub { font-size: 9px; background: #e8f0fe; color: #1967d2; padding: 2px 8px; border-radius: 20px; font-weight: bold; }
        #bulk-bar { display: none; position: fixed; bottom: 25px; left: 50%; transform: translateX(-50%); background: #202124; color: #fff; padding: 10px 25px; border-radius: 50px; z-index: 2000; align-items: center; gap: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.4); }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-w)); }
            .sidebar.active { left: 0; }
            .main { margin-left: 0 !important; }
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar d-flex flex-column shadow-sm" id="side">
    <div class="p-4 text-center position-relative">
        <button class="btn-close d-lg-none position-absolute top-0 end-0 m-3" onclick="toggleSidebar()"></button>
        <img src="Img/logo.jpg" width="75" class="rounded-circle border shadow-sm">
        <h6 class="mt-2 fw-bold text-primary">JMM DRIVE</h6>
    </div>
    
    <div class="p-3">
        <button class="btn btn-primary w-100 rounded-pill mb-4 shadow fw-bold py-2" data-bs-toggle="modal" data-bs-target="#modalUp">
            <i class="fas fa-plus me-2"></i> NOVO UPLOAD
        </button>
        
        <nav class="nav flex-column mb-auto">
            <a class="nav-link text-dark fw-bold mb-1" href="drive.php"><i class="fas fa-hdd me-2 text-primary"></i> Meu Drive</a>
            <a class="nav-link text-dark small" href="#" data-bs-toggle="modal" data-bs-target="#modalPasta"><i class="fas fa-folder-plus me-2 text-warning"></i> Criar Pasta</a>
            <hr>
            <a class="nav-link text-muted small" href="sistema_dashboard.php"><i class="fas fa-arrow-left me-2"></i> Painel JMM</a>
        </nav>
    </div>

    <div class="p-3 mt-auto bg-light border-top text-center">
        <div class="d-flex justify-content-between mb-1 small fw-bold"><span>Espaço</span><span><?=$perc?>%</span></div>
        <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?=$perc?>%"></div></div>
        <small class="text-muted" style="font-size: 9px;"><?=formatarBytes($qUso)?> de <?=formatarBytes($qMax)?></small>
    </div>
</div>

<!-- CONTEÚDO -->
<div class="main" id="main-content">
    <header class="drive-header d-flex align-items-center">
        <button class="btn btn-white border shadow-sm me-3" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        
        <?php if($pasta_atual): ?>
            <a href="drive.php<?= $id_pai ? '?pasta='.$id_pai : '' ?>" class="btn btn-light border-0 me-2"><i class="fas fa-arrow-left"></i></a>
        <?php endif; ?>

        <nav aria-label="breadcrumb" class="overflow-hidden">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="drive.php"><i class="fas fa-home"></i> Drive</a></li>
                <?php 
                $trilha_dados = gerarTrilha($pdo, $pasta_atual);
                foreach($trilha_dados as $t_item): 
                ?>
                    <li class="breadcrumb-item active text-truncate" style="max-width: 150px;">
                        <a href="?pasta=<?=$t_item['id']?>"><i class="fas fa-folder-open"></i> <?=htmlspecialchars($t_item['nome'])?></a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </header>

    <div class="container-fluid px-4 mt-3">
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['sucesso']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($_GET['erro']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="container-fluid p-3 p-md-4">
        <!-- PASTAS -->
        <h6 class="text-muted small fw-bold mb-3 text-uppercase">Diretórios</h6>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-2 g-md-3 mb-5">
            <?php foreach($pastas as $f): 
                $pode_editar = ($f['usuario_id'] == $user_id || $user_nivel == 'admin');
            ?>
            <div class="col">
                <div class="item-card p-2 p-md-3 shadow-sm d-flex flex-column h-100">
                    <div class="d-flex justify-content-between mb-1">
                        <a href="?pasta=<?=$f['id']?>"><i class="fas fa-folder fa-2x text-warning"></i></a>
                        <div class="dropdown">
                            <i class="fas fa-ellipsis-v text-muted p-1" data-bs-toggle="dropdown" style="cursor:pointer"></i>
                            <ul class="dropdown-menu shadow border-0 small">
                                <?php if($pode_editar): ?>
                                    <li><a class="dropdown-item" href="#" onclick="abrirRename(<?=$f['id']?>, '<?=addslashes($f['nome'])?>')">Renomear</a></li>
                                    <li><a class="dropdown-item text-danger" href="drive_acoes.php?del_pasta=<?=$f['id']?>" onclick="return confirm('Deseja excluir a pasta e arquivos?')">Excluir</a></li>
                                <?php else: ?>
                                    <li class="p-2 text-center text-muted small">Dono: <?=$f['dono_nome']?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <a href="?pasta=<?=$f['id']?>" class="text-decoration-none text-dark mt-auto">
                        <span class="small fw-bold text-truncate d-block"><?=htmlspecialchars($f['nome'])?></span>
                        <?php if($f['publica']): ?><span class="badge-pub mt-1">Pública</span><?php endif; ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ARQUIVOS -->
        <h6 class="text-muted small fw-bold mb-3 text-uppercase">Arquivos</h6>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-2 g-md-3">
            <?php foreach($arquivos as $a): 
                $path_url = "uploads/drive/" . $a['nome_sistema'];
                $ext = strtolower(pathinfo($a['nome_original'], PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                $isVid = in_array($ext, ['mp4','webm','mov']);
                $prev = $path_url; $type = "image";
                if($isVid) $type = "video";
                if(in_array($ext, ['pdf','doc','docx','xls','xlsx'])) {
                    $prev = "https://docs.google.com/viewer?url=" . urlencode("https://".$_SERVER['HTTP_HOST']."/".$path_url) . "&embedded=true";
                    $type = "pdf";
                }
            ?>
            <div class="col">
                <div class="item-card overflow-hidden shadow-sm d-flex flex-column h-100">
                    <input type="checkbox" class="item-check form-check-input" value="<?=$a['id']?>" onclick="contarSelecao()">
                    <div class="thumb">
                        <a href="<?=$prev?>" data-fancybox="gallery" data-type="<?=$type?>" data-caption="<?=htmlspecialchars($a['nome_original'])?>">
                            <?php if($isImg): ?><img src="<?=$path_url?>">
                            <?php elseif($isVid): ?><i class="fas fa-play-circle fa-2x text-danger"></i>
                            <?php elseif($ext == 'pdf'): ?><i class="fas fa-file-pdf fa-2x text-danger"></i>
                            <?php elseif(in_array($ext,['doc','docx'])): ?><i class="fas fa-file-word fa-2x text-primary"></i>
                            <?php elseif(in_array($ext,['xls','xlsx'])): ?><i class="fas fa-file-excel fa-2x text-success"></i>
                            <?php else: ?><i class="fas fa-file-alt fa-3x text-secondary"></i><?php endif; ?>
                        </a>
                    </div>
                    <div class="p-2 border-top bg-white mt-auto">
                        <small class="text-truncate fw-bold d-block mb-1" style="font-size:10px"><?=htmlspecialchars($a['nome_original'])?></small>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted" style="font-size: 8px;"><?=date('d/m/y H:i', strtotime($a['data_upload']))?></span>
                            <div class="dropdown">
                                <i class="fas fa-ellipsis-h text-muted" style="cursor:pointer; font-size:10px" data-bs-toggle="dropdown"></i>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 small">
                                    <li><a class="dropdown-item" href="<?=$path_url?>" download>Baixar</a></li>
                                    <li><a class="dropdown-item text-danger" href="drive_acoes.php?del_arq=<?=$a['id']?>" onclick="return confirm('Excluir?')">Excluir</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- BARRA EM MASSA -->
<div id="bulk-bar" class="shadow-lg">
    <span id="txt-selected" class="small fw-bold">0 selecionados</span>
    <button class="btn btn-sm btn-outline-light border-0" onclick="abrirModalMover()"><i class="fas fa-file-export me-1"></i> MOVER</button>
    <button class="btn btn-sm btn-outline-danger border-0" onclick="deletarMassa()"><i class="fas fa-trash me-1"></i> EXCLUIR</button>
    <button class="btn btn-sm btn-light rounded-pill px-3" onclick="location.reload()">Sair</button>
</div>

<!-- MODAL UPLOAD -->
<div class="modal fade" id="modalUp" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 p-4 shadow">
    <div class="modal-header border-0 pb-0"><h5>Subir Arquivos</h5><button type="button" class="btn-close" data-bs-dismiss="modal" id="btnX"></button></div>
    <div class="modal-body p-4 text-center pt-0">
        <input type="file" id="files" class="form-control mb-3" multiple>
        <div id="prog" class="progress mb-3 d-none" style="height:25px"><div id="bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%">0%</div></div>
        <div class="d-flex gap-2">
            <button class="btn btn-light border w-50" data-bs-dismiss="modal" id="btnCancel">CANCELAR</button>
            <button onclick="fazerUpload()" id="btnGo" class="btn btn-primary w-50 fw-bold">ENVIAR</button>
        </div>
    </div>
</div></div></div>

<!-- MODAL PASTA -->
<div class="modal fade" id="modalPasta" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="drive_acoes.php" method="POST" class="modal-content border-0 shadow">
    <div class="modal-header"><h5>Nova Pasta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4">
        <input type="text" name="nome_pasta" class="form-control mb-3" placeholder="Nome da pasta" required>
        <div class="form-check form-switch small"><input class="form-check-input" type="checkbox" name="publica" id="swP"><label class="form-check-label fw-bold" for="swP">Compartilhada</label></div>
        <input type="hidden" name="pai_id" value="<?=$pasta_atual?>"><input type="hidden" name="acao_pasta" value="1">
    </div>
    <div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">CRIAR PASTA</button></div>
</form></div></div>

<!-- MODAL RENOMEAR -->
<div class="modal fade" id="modalRename" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="drive_acoes.php" method="POST" class="modal-content border-0 shadow">
    <div class="modal-header"><h5>Renomear Pasta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4">
        <input type="text" name="novo_nome" id="renameInput" class="form-control mb-3" required>
        <input type="hidden" name="id_pasta" id="renameId">
        <input type="hidden" name="acao_renomear" value="1">
        <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold text-white">SALVAR</button>
    </div>
</form></div></div>

<!-- MODAL MOVER -->
<div class="modal fade" id="modalMover" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
    <div class="modal-header bg-dark text-white"><h5>Mover Itens</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4"><label class="small fw-bold mb-2">Destino:</label>
        <select id="selectDestino" class="form-select mb-4 shadow-sm">
            <option value="null">Raiz (Meu Drive)</option>
            <?php foreach($todas_pastas_mover as $tp): ?><option value="<?=$tp['id']?>">📁 <?=htmlspecialchars($tp['nome'])?></option><?php endforeach; ?>
        </select>
        <button onclick="confirmarMover()" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">CONFIRMAR</button>
    </div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    Fancybox.bind("[data-fancybox]", { infinite: false, dragToClose: true });

    const side = document.getElementById('side');
    const main = document.getElementById('main-content');
    const overlay = document.getElementById('overlay');

    function toggleSidebar() {
        if (window.innerWidth > 992) {
            side.classList.toggle('collapsed');
            main.classList.toggle('full');
            localStorage.setItem('drive_menu', side.classList.contains('collapsed') ? 'closed' : 'open');
        } else {
            side.classList.toggle('active');
            overlay.classList.toggle('active');
        }
    }

    window.onload = function() {
        if (window.innerWidth > 992 && localStorage.getItem('drive_menu') === 'closed') {
            side.classList.add('collapsed');
            main.classList.add('full');
        }
    }

    function contarSelecao() {
        const n = document.querySelectorAll('.item-check:checked').length;
        document.getElementById('txt-selected').innerText = n + ' selecionados';
        document.getElementById('bulk-bar').style.display = n > 0 ? 'flex' : 'none';
    }

    function abrirModalMover() { new bootstrap.Modal(document.getElementById('modalMover')).show(); }

    function confirmarMover() {
        const ids = Array.from(document.querySelectorAll('.item-check:checked')).map(c => c.value);
        const dest = document.getElementById('selectDestino').value;
        const fd = new FormData();
        fd.append('acao_mover', '1');
        fd.append('destino_id', dest);
        ids.forEach(id => fd.append('ids[]', id));
        fetch('drive_acoes.php', { method: 'POST', body: fd }).then(r => r.json()).then(() => location.reload());
    }

    function deletarMassa() {
        const ids = Array.from(document.querySelectorAll('.item-check:checked')).map(c => c.value);
        if(!confirm(`Excluir ${ids.length} itens?`)) return;
        const fd = new FormData();
        fd.append('acao_deletar_massa', '1');
        ids.forEach(id => fd.append('ids[]', id));
        fetch('drive_acoes.php', { method: 'POST', body: fd }).then(r => r.json()).then(() => location.reload());
    }

    function abrirRename(id, nome) {
        document.getElementById('renameId').value = id;
        document.getElementById('renameInput').value = nome;
        new bootstrap.Modal(document.getElementById('modalRename')).show();
    }

    function fazerUpload() {
        const fs = document.getElementById('files').files;
        if(!fs.length) return alert("Selecione arquivos");
        const fd = new FormData();
        for(let i=0; i<fs.length; i++) fd.append('arquivos[]', fs[i]);
        fd.append('pasta_id', '<?=$pasta_atual?>');
        fd.append('acao_upload', '1');
        document.getElementById('prog').classList.remove('d-none');
        document.getElementById('btnGo').disabled = true;
        document.getElementById('btnX').style.display = 'none';
        document.getElementById('btnCancel').style.display = 'none';
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "drive_acoes.php");
        xhr.upload.onprogress = e => {
            if (e.lengthComputable) {
                const p = Math.round((e.loaded / e.total) * 100);
                document.getElementById('bar').style.width = p + '%';
                document.getElementById('bar').innerText = p + '%';
            }
        };
        xhr.onload = () => location.reload();
        xhr.send(fd);
    }
</script>
</body>
</html>
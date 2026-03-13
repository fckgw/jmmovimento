<?php
/**
 * SISTEMA GINCANA JMM - JUVENTUDE DA MATRIZ EM MOVIMENTO
 * Módulo: Sistema de Gincana (Index)
 */

// 1. CARREGA CONFIGURAÇÕES E SEGURANÇA
require_once 'config.php';

// Verifica se está logado. Se não, manda pro Login.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. LÓGICA DE PAGINAÇÃO
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// 3. PROCESSAMENTO DE AÇÕES (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';

    // --- MÓDULO JOVENS ---
    if ($acao == 'novo_jovem') {
        $pdo->prepare("INSERT INTO jovens (nome, telefone, ano_nascimento) VALUES (?, ?, ?)")
            ->execute([$_POST['nome'], $_POST['telefone'], $_POST['ano_nascimento']]);
    }
    if ($acao == 'deletar_jovem') {
        $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]);
    }

    // --- MÓDULO EQUIPES ---
    if ($acao == 'novo_grupo') { 
        $pdo->prepare("INSERT INTO grupos (nome_time) VALUES (?)")->execute([$_POST['nome_time']]); 
    }
    if ($acao == 'add_membro') { 
        $pdo->prepare("INSERT INTO membros (grupo_id, nome) VALUES (?, ?)")->execute([$_POST['id_time'], $_POST['nome_membro']]); 
    }
    if ($acao == 'editar_time') { 
        $pdo->prepare("UPDATE grupos SET nome_time = ? WHERE id = ?")->execute([$_POST['nome_time'], $_POST['id_time']]); 
    }
    if ($acao == 'deletar_time') { 
        $id = $_POST['id_time']; 
        $pdo->prepare("DELETE FROM registros WHERE grupo_id = ?")->execute([$id]); 
        $pdo->prepare("DELETE FROM membros WHERE grupo_id = ?")->execute([$id]); 
        $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$id]); 
    }
    if ($acao == 'deletar_membro') { 
        $pdo->prepare("DELETE FROM membros WHERE id = ?")->execute([$_POST['id_membro']]); 
    }

    // --- MÓDULO GINCANA (TIMER) ---
    if (isset($_POST['bt_acao'])) {
        $id_grupo = $_POST['grupo_id']; 
        $agora = date('Y-m-d H:i:s');
        if ($_POST['bt_acao'] == 'start') {
            $pdo->prepare("INSERT INTO registros (grupo_id, inicio, status) VALUES (?, ?, 'rodando')")->execute([$id_grupo, $agora]);
        }
        if ($_POST['bt_acao'] == 'pause') {
            $pdo->prepare("UPDATE registros SET pausa_inicio = ?, status = 'pausado' WHERE grupo_id = ? AND status = 'rodando'")->execute([$agora, $id_grupo]);
        }
        if ($_POST['bt_acao'] == 'resume') {
            $reg = $pdo->query("SELECT pausa_inicio FROM registros WHERE grupo_id = $id_grupo AND status = 'pausado'")->fetch();
            $segundos = time() - strtotime($reg['pausa_inicio'] ?? 'now');
            $pdo->prepare("UPDATE registros SET total_pausa_segundos = total_pausa_segundos + ?, status = 'rodando', pausa_inicio = NULL WHERE grupo_id = ?")->execute([$segundos, $id_grupo]);
        }
        if ($_POST['bt_acao'] == 'finish') {
            $pdo->prepare("UPDATE registros SET fim = ?, status = 'finalizado' WHERE grupo_id = ? AND status != 'finalizado'")->execute([$agora, $id_grupo]);
        }
    }
    
    // Redireciona para evitar reenvio de formulário
    $pg_back = ($pagina_atual > 1) ? "?p=$pagina_atual" : "";
    header("Location: index.php$pg_back"); exit;
}

// 4. CONSULTAS PARA O FRONT-END
$total_jovens = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn();
$total_paginas = ceil($total_jovens / $itens_por_pagina);
$jovens = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC LIMIT $offset, $itens_por_pagina")->fetchAll(PDO::FETCH_ASSOC);

$todos_grupos = $pdo->query("SELECT * FROM grupos ORDER BY nome_time ASC")->fetchAll(PDO::FETCH_ASSOC);
$ativo = $pdo->query("SELECT r.*, g.nome_time FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status IN ('rodando', 'pausado') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ranking = $pdo->query("SELECT g.id as gid, g.nome_time, r.inicio, r.fim, r.total_pausa_segundos, (TIMESTAMPDIFF(SECOND, r.inicio, r.fim) - r.total_pausa_segundos) as tempo FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status = 'finalizado' ORDER BY tempo ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sistema Gincana - JMM</title>
    
    <!-- Meta Tags para WhatsApp -->
    <meta property="og:title" content="JUVENTUDE DA MATRIZ EM MOVIMENTO">
    <meta property="og:image" content="logo.jpg">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root { --primary-jmm: #0d6efd; --accent-jmm: #dc3545; }
        body { background: #f4f7f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding-bottom: 60px; }
        
        /* Layout */
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; overflow: hidden; }
        .logo-img { max-width: 100px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        
        /* Cronômetro */
        #cronometro { font-size: 4rem; font-weight: 900; color: var(--accent-jmm); font-family: 'Courier New', Courier, monospace; letter-spacing: -2px; line-height: 1; margin: 15px 0; }
        
        /* Navegação */
        .nav-pills { background: white; padding: 5px; border-radius: 50px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .nav-pills .nav-link { border-radius: 20px; font-weight: bold; font-size: 0.75rem; color: #666; transition: 0.3s; }
        .nav-pills .nav-link.active { background-color: var(--primary-jmm) !important; color: white !important; box-shadow: 0 4px 12px rgba(13,110,253,0.3); }
        
        /* Botões */
        .btn-xl { padding: 18px; font-weight: 800; border-radius: 12px; font-size: 1.1rem; }
        .badge-count { font-size: 0.65rem; vertical-align: middle; margin-left: 3px; }
        
        /* Paginação */
        .pagination .page-link { color: var(--primary-jmm); border: none; margin: 0 2px; border-radius: 5px; }
        .pagination .active .page-link { background-color: var(--primary-jmm); }
    </style>
</head>
<body>

<!-- BARRA DE NAVEGAÇÃO COM VOLTAR PARA DASHBOARD -->
<nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <!-- Botão MENU (Voltar para Dashboard) -->
            <a href="dashboard.php" class="btn btn-outline-dark border-0 me-2" title="Voltar ao Menu Principal">
                <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
            </a>
            <span class="navbar-text small text-muted lh-1 d-none d-sm-block">
                Olá, <b><?= htmlspecialchars($_SESSION['user_nome']) ?></b>
            </span>
        </div>
        
        <div>
            <a href="trocar_senha.php" class="btn btn-sm btn-outline-secondary me-1">
                <i class="bi bi-key"></i> <span class="d-none d-md-inline">Senha</span>
            </a>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Sair</span>
            </a>
        </div>
    </div>
</nav>

<!-- HEADER -->
<div class="container text-center py-2">
    <img src="logo.jpg" class="logo-img mb-2">
    <h5 class="fw-bold text-primary m-0" style="letter-spacing: -0.5px;">JUVENTUDE DA MATRIZ</h5>
    <small class="text-muted fw-bold">EM MOVIMENTO</small>
</div>

<div class="container mt-3">
    <!-- MENU DE ABAS -->
    <ul class="nav nav-pills nav-fill mb-4" id="pills-tab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="btn-tab-exe" data-bs-toggle="pill" data-bs-target="#tab-exe" type="button">PROVA</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="btn-tab-jovens" data-bs-toggle="pill" data-bs-target="#tab-jovens" type="button">
                JOVENS <span class="badge rounded-pill bg-light text-dark badge-count"><?= $total_jovens ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="btn-tab-equipes" data-bs-toggle="pill" data-bs-target="#tab-equipes" type="button">EQUIPES</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="btn-tab-rank" data-bs-toggle="pill" data-bs-target="#tab-rank" type="button">RANKING</button>
        </li>
    </ul>

    <div class="tab-content">
        
        <!-- ABA 1: EXECUÇÃO (CRONÔMETRO) -->
        <div class="tab-pane fade show active" id="tab-exe" role="tabpanel">
            <div class="card p-4 text-center border-top border-5 border-primary">
                <?php if ($ativo): ?>
                    <h6 class="text-muted small text-uppercase fw-bold">Equipe em Prova</h6>
                    <h1 class="fw-bold text-primary mb-0"><?= $ativo['nome_time'] ?></h1>
                    <div id="cronometro">00:00:00</div>
                    
                    <form method="POST">
                        <input type="hidden" name="grupo_id" value="<?= $ativo['grupo_id'] ?>">
                        <div class="d-grid gap-3">
                            <?php if ($ativo['status'] == 'rodando'): ?>
                                <button type="submit" name="bt_acao" value="pause" class="btn btn-warning btn-xl shadow">PAUSAR</button>
                            <?php else: ?>
                                <button type="submit" name="bt_acao" value="resume" class="btn btn-success btn-xl shadow">RETOMAR</button>
                            <?php endif; ?>
                            <button type="submit" name="bt_acao" value="finish" class="btn btn-danger btn-xl shadow" onclick="return confirm('Finalizar esta prova agora?')">FINALIZAR</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h5 class="fw-bold mb-4">Iniciar Nova Prova</h5>
                    <form method="POST">
                        <select name="grupo_id" class="form-select form-select-lg mb-4 shadow-sm" style="border-radius: 12px;" required>
                            <option value="">Selecione a Equipe...</option>
                            <?php foreach($todos_grupos as $g): ?>
                                <option value="<?=$g['id']?>"><?=$g['nome_time']?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="bt_acao" value="start" class="btn btn-primary btn-xl w-100 shadow">DAR START</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA 2: JOVENS (CADASTRO E LISTA) -->
        <div class="tab-pane fade" id="tab-jovens" role="tabpanel">
            <div class="card p-3 border-top border-5 border-info">
                <h6 class="fw-bold mb-3"><i class="bi bi-person-plus-fill me-1"></i> Novo Cadastro</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_jovem">
                    <input type="text" name="nome" class="form-control mb-2 shadow-sm" placeholder="Nome Completo" required>
                    <div class="row g-2 mb-3">
                        <div class="col-7"><input type="text" name="telefone" class="form-control shadow-sm" placeholder="WhatsApp"></div>
                        <div class="col-5"><input type="number" name="ano_nascimento" class="form-control shadow-sm" placeholder="Ano Nasc."></div>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold text-white shadow">CADASTRAR JOVEM</button>
                </form>
            </div>

            <!-- DASHBOARD JOVENS -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="card bg-primary text-white text-center p-2 mb-0 shadow-sm">
                        <small style="font-size: 0.65rem; font-weight: bold; opacity: 0.8;">TOTAL INSCRITOS</small>
                        <h3 class="m-0 fw-bold"><?= $total_jovens ?></h3>
                    </div>
                </div>
                <div class="col-6 text-center">
                    <a href="gerar_pdf.php" target="_blank" class="btn btn-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center shadow-sm" style="border-radius: 15px;">
                        <i class="bi bi-file-earmark-pdf-fill fs-4"></i>
                        <small style="font-size: 0.65rem; font-weight: bold;">GERAR PRESENÇA</small>
                    </a>
                </div>
            </div>

            <!-- LISTA DE JOVENS -->
            <div class="table-responsive">
                <table class="table table-hover bg-white rounded shadow-sm">
                    <tbody>
                        <?php foreach($jovens as $j): ?>
                        <tr class="align-middle">
                            <td class="ps-3">
                                <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= $j['nome'] ?></div>
                                <small class="text-muted"><?= $j['telefone'] ?> | <?= $j['ano_nascimento'] ?></small>
                            </td>
                            <td class="text-end pe-3">
                                <form method="POST" onsubmit="return confirm('Deseja excluir este cadastro?')">
                                    <input type="hidden" name="form_acao" value="deletar_jovem">
                                    <input type="hidden" name="id_jovem" value="<?=$j['id']?>">
                                    <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAÇÃO -->
            <?php if ($total_paginas > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link shadow-sm" href="?p=<?= $pagina_atual - 1 ?>">Anterior</a>
                    </li>
                    <?php 
                    $range = 2;
                    for ($i = max(1, $pagina_atual - $range); $i <= min($total_paginas, $pagina_atual + $range); $i++): ?>
                        <li class="page-item <?= ($i == $pagina_atual) ? 'active' : '' ?>">
                            <a class="page-link shadow-sm" href="?p=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link shadow-sm" href="?p=<?= $pagina_atual + 1 ?>">Próxima</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

        <!-- ABA 3: GESTÃO DE EQUIPES -->
        <div class="tab-pane fade" id="tab-equipes" role="tabpanel">
            <div class="card p-3 mb-3 border-top border-5 border-primary">
                <h6 class="fw-bold mb-3">Nova Equipe (Santo)</h6>
                <form method="POST" class="d-flex">
                    <input type="hidden" name="form_acao" value="novo_grupo">
                    <input type="text" name="nome_time" class="form-control me-2 shadow-sm" placeholder="Nome da Equipe" required>
                    <button type="submit" class="btn btn-primary shadow">CRIAR</button>
                </form>
            </div>

            <?php foreach($todos_grupos as $g): ?>
            <div class="card p-3 mb-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <form method="POST" class="d-flex flex-grow-1 me-2">
                        <input type="hidden" name="form_acao" value="editar_time">
                        <input type="hidden" name="id_time" value="<?=$g['id']?>">
                        <input type="text" name="nome_time" value="<?=$g['nome_time']?>" class="form-control form-control-sm fw-bold border-0 bg-transparent text-primary fs-6">
                        <button type="submit" class="btn btn-link btn-sm text-success p-0 ms-1"><i class="bi bi-check-circle-fill fs-5"></i></button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Excluir toda a equipe?')">
                        <input type="hidden" name="form_acao" value="deletar_time">
                        <input type="hidden" name="id_time" value="<?=$g['id']?>">
                        <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button>
                    </form>
                </div>
                
                <div class="ps-3 border-start border-3 border-light">
                    <?php 
                    $ms = $pdo->query("SELECT * FROM membros WHERE grupo_id = ".$g['id'])->fetchAll(PDO::FETCH_ASSOC); 
                    foreach($ms as $m): ?>
                        <div class="badge bg-white text-dark border p-2 me-1 mb-1 shadow-xs" style="font-weight: normal;">
                            <?=$m['nome']?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="form_acao" value="deletar_membro">
                                <input type="hidden" name="id_membro" value="<?=$m['id']?>">
                                <button type="submit" class="btn btn-link text-danger p-0 ms-1" style="line-height:1;"><i class="bi bi-x-circle-fill"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    
                    <form method="POST" class="d-flex mt-2">
                        <input type="hidden" name="form_acao" value="add_membro">
                        <input type="hidden" name="id_time" value="<?=$g['id']?>">
                        <input type="text" name="nome_membro" class="form-control form-control-sm shadow-sm" placeholder="Add Integrante..." required>
                        <button type="submit" class="btn btn-link btn-sm p-0 ms-2 text-primary"><i class="bi bi-plus-circle-fill fs-4"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ABA 4: RANKING -->
        <div class="tab-pane fade" id="tab-rank" role="tabpanel">
            <h6 class="text-center text-muted fw-bold mb-3 small">CLASSIFICAÇÃO POR TEMPO</h6>
            <?php foreach($ranking as $idx => $r): 
                $membros_list = $pdo->query("SELECT nome FROM membros WHERE grupo_id = ".$r['gid'])->fetchAll(PDO::FETCH_COLUMN);
                $tempo_fmt = gmdate("H:i:s", $r['tempo']);
                $msg_wpp = "🏆 *RANKING GINCANA JMM*\n🚩 *Equipe:* {$r['nome_time']}\n⏱️ *Tempo:* {$tempo_fmt}\n👥 *Integrantes:* ".implode(", ", $membros_list);
            ?>
                <div class="card p-3 mb-2 shadow-sm border-start border-5 border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success rounded-circle me-2" style="width:30px; height:30px; display:flex; align-items:center; justify-content:center;"><?=($idx+1)?>º</span>
                            <span class="fw-bold fs-5 text-uppercase"><?= $r['nome_time'] ?></span>
                        </div>
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($msg_wpp) ?>" target="_blank" class="btn btn-success rounded-circle shadow">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                    </div>
                    <div class="display-6 fw-bold text-danger my-1"><?= $tempo_fmt ?></div>
                    <small class="text-muted d-block small"><b>Time:</b> <?= implode(", ", $membros_list) ?></small>
                </div>
            <?php endforeach; ?>
        </div>

    </div> <!-- tab-content -->
</div> <!-- container -->

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. PERSISTÊNCIA DE ABAS (LocalStorage)
    // Mantém a aba aberta mesmo após recarregar a página
    document.addEventListener("DOMContentLoaded", function() {
        const activeTabId = localStorage.getItem('activeTabJMM');
        if (activeTabId) {
            const tabBtn = document.getElementById(activeTabId);
            if (tabBtn) {
                const tabTrigger = new bootstrap.Tab(tabBtn);
                tabTrigger.show();
            }
        }
        const tabBtns = document.querySelectorAll('button[data-bs-toggle="pill"]');
        tabBtns.forEach(btn => {
            btn.addEventListener('shown.bs.tab', function (e) {
                localStorage.setItem('activeTabJMM', e.target.id);
            });
        });
    });

    // 2. CRONÔMETRO RESILIENTE (Servidor como base)
    // Sincroniza o contador com o tempo do PHP para ser preciso
    <?php if ($ativo): ?>
        const startTime = new Date("<?= $ativo['inicio'] ?>").getTime();
        const totalPausaMs = <?= (int)$ativo['total_pausa_segundos'] ?> * 1000;
        const status = "<?= $ativo['status'] ?>";
        const pausaInicio = "<?= $ativo['pausa_inicio'] ?>";
        
        function atualizarRelogio() {
            let diff;
            if(status === 'rodando') {
                const agora = new Date().getTime();
                diff = (agora - startTime) - totalPausaMs;
            } else {
                // Se pausado, calcula até o momento em que a pausa começou
                diff = (new Date(pausaInicio).getTime() - startTime) - totalPausaMs;
            }
            if(diff < 0) diff = 0;
            
            const h = Math.floor(diff / 3600000).toString().padStart(2, '0');
            const m = Math.floor((diff % 3600000) / 60000).toString().padStart(2, '0');
            const s = Math.floor((diff % 60000) / 1000).toString().padStart(2, '0');
            document.getElementById('cronometro').innerText = `${h}:${m}:${s}`;
        }
        if(status === 'rodando') setInterval(atualizarRelogio, 1000);
        atualizarRelogio();
    <?php endif; ?>
</script>

</body>
</html>
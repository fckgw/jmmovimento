<?php
/**
 * SISTEMA GINCANA JMM - VERSÃO ULTRA AGILIZADA
 * Módulos: Chamada com Popup de Cadastro e Filtro de Membros
 */

require_once 'config.php';

// 1. SEGURANÇA: Verifica se está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. CONFIGURAÇÕES DE PAGINAÇÃO E FILTRO (ABA JOVENS)
$itens_por_pág = 10;
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $itens_por_pág;

// Lógica de Filtro na Aba Jovens
$filtro_jovem = isset($_GET['f_jovem']) ? trim($_GET['f_jovem']) : '';
$params_filtro = [];
$where_filtro = "WHERE 1=1";

if ($filtro_jovem) {
    $where_filtro .= " AND (nome LIKE ? OR telefone LIKE ?)";
    $params_filtro[] = "%$filtro_jovem%";
    $params_filtro[] = "%$filtro_jovem%";
}

// 3. PROCESSAMENTO DE AÇÕES (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';
    $aba_retorno = $_POST['aba_destino'] ?? 'chamada';

    // Ação: Novo Jovem (Cadastro Manual na aba Jovens)
    if ($acao == 'novo_jovem') {
        $nome = trim($_POST['nome']);
        $tel = trim($_POST['telefone']);
        $ano = (int)$_POST['ano_nascimento'];
        $data_nasc = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;

        $stmt = $pdo->prepare("INSERT INTO jovens (nome, telefone, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $tel, $ano, $data_nasc]);
        registrarLog($pdo, "Cadastrou jovem: $nome", "Jovens");
    }

    // Ação: Deletar Jovem
    if ($acao == 'deletar_jovem') {
        $id_j = (int)$_POST['id_jovem'];
        $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$id_j]);
        registrarLog($pdo, "Excluiu jovem ID: $id_j", "Jovens");
    }

    // --- MÓDULO ENCONTROS ---
    if ($acao == 'novo_encontro') {
        $stmt = $pdo->prepare("INSERT INTO encontros (data_encontro, local_encontro, tema, status) VALUES (?, ?, ?, 'aberto')");
        $stmt->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e']]);
        registrarLog($pdo, "Criou encontro: " . $_POST['tema_e'], "Chamada");
    }

    if ($acao == 'excluir_encontro') {
        $id_e = (int)$_POST['e_id'];
        $pdo->prepare("DELETE FROM presencas WHERE encontro_id = ?")->execute([$id_e]);
        $pdo->prepare("DELETE FROM encontros WHERE id = ?")->execute([$id_e]);
    }

    if ($acao == 'remover_presenca') {
        $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ? AND encontro_id = ?")->execute([$_POST['j_id'], $_POST['e_id']]);
    }

    // --- MÓDULO EQUIPES ---
    if ($acao == 'novo_grupo') { 
        $pdo->prepare("INSERT INTO grupos (nome_time) VALUES (?)")->execute([$_POST['nome_time']]); 
    }
    if ($acao == 'editar_time') { 
        $pdo->prepare("UPDATE grupos SET nome_time = ? WHERE id = ?")->execute([$_POST['nome_time'], $_POST['id_time']]); 
    }
    if ($acao == 'deletar_time') { 
        $id_t = (int)$_POST['id_time'];
        $pdo->prepare("DELETE FROM registros WHERE grupo_id = ?")->execute([$id_t]); 
        $pdo->prepare("DELETE FROM membros WHERE grupo_id = ?")->execute([$id_t]); 
        $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$id_t]); 
    }
    if ($acao == 'add_membro') { 
        $pdo->prepare("INSERT INTO membros (grupo_id, nome) VALUES (?, ?)")->execute([$_POST['id_time'], $_POST['nome_membro']]); 
    }
    if ($acao == 'deletar_membro') { 
        $pdo->prepare("DELETE FROM membros WHERE id = ?")->execute([$_POST['id_membro']]); 
    }

    // --- MÓDULO GINCANA ---
    if (isset($_POST['bt_acao'])) {
        $id_g = $_POST['grupo_id']; $agora = date('Y-m-d H:i:s');
        if ($_POST['bt_acao'] == 'start') $pdo->prepare("INSERT INTO registros (grupo_id, inicio, status) VALUES (?, ?, 'rodando')")->execute([$id_g, $agora]);
        elseif ($_POST['bt_acao'] == 'pause') $pdo->prepare("UPDATE registros SET pausa_inicio = ?, status = 'pausado' WHERE grupo_id = ? AND status = 'rodando'")->execute([$agora, $id_g]);
        elseif ($_POST['bt_acao'] == 'resume') {
            $reg = $pdo->query("SELECT pausa_inicio FROM registros WHERE grupo_id = $id_g AND status = 'pausado'")->fetch();
            $segundos = time() - strtotime($reg['pausa_inicio']);
            $pdo->prepare("UPDATE registros SET total_pausa_segundos = total_pausa_segundos + ?, status = 'rodando', pausa_inicio = NULL WHERE grupo_id = ?")->execute([$segundos, $id_g]);
        }
        elseif ($_POST['bt_acao'] == 'finish') $pdo->prepare("UPDATE registros SET fim = ?, status = 'finalizado' WHERE grupo_id = ? AND status != 'finalizado'")->execute([$agora, $id_g]);
    }
    
    $url_final = "gincana.php?tab=$aba_retorno" . ($pagina_atual > 1 ? "&p=$pagina_atual" : "") . ($filtro_jovem ? "&f_jovem=".urlencode($filtro_jovem) : "");
    header("Location: $url_final"); exit;
}

// 4. CONSULTAS
// Contagem total para paginação (considerando filtro)
$sql_total = "SELECT COUNT(*) FROM jovens $where_filtro";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params_filtro);
$total_registros_filtro = $stmt_total->fetchColumn();
$total_paginas = ceil($total_registros_filtro / $itens_por_pág);

// Busca jovens para o GRID
$sql_grid = "SELECT * FROM jovens $where_filtro ORDER BY nome ASC LIMIT $offset, $itens_por_pág";
$stmt_grid = $pdo->prepare($sql_grid);
$stmt_grid->execute($params_filtro);
$jovens_grid = $stmt_grid->fetchAll(PDO::FETCH_ASSOC);

$encontros = $pdo->query("SELECT * FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);
$enc_ativo_id = $_GET['encontro_id'] ?? ($encontros[0]['id'] ?? null);

$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nome_time ASC")->fetchAll(PDO::FETCH_ASSOC);
$ativo = $pdo->query("SELECT r.*, g.nome_time FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status IN ('rodando', 'pausado') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ranking = $pdo->query("SELECT g.id as gid, g.nome_time, r.inicio, r.fim, r.total_pausa_segundos, (TIMESTAMPDIFF(SECOND, r.inicio, r.fim) - r.total_pausa_segundos) as tempo FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status = 'finalizado' ORDER BY tempo ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gincana JMM - Gestão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <style>
        body { background: #f4f7f6; padding-bottom: 80px; font-family: sans-serif; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.75rem; color: #555; }
        .nav-pills .nav-link.active { background-color: #0d6efd !important; box-shadow: 0 4px 12px rgba(13,110,253,0.3); }
        #cronometro { font-size: 3.5rem; font-weight: 900; color: #dc3545; font-family: monospace; }
        .small-label { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-grid-3x3-gap-fill"></i></a>
        <span class="fw-bold text-primary">JMM SYSTEM</span>
        <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalGerirEncontros">ENCONTROS</button>
    </div>
</nav>

<div class="container">
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded-pill shadow-sm" id="pills-tab" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="tab-chamada-btn" data-bs-toggle="pill" data-bs-target="#tab-chamada" type="button">CHAMADA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-jovens-btn" data-bs-toggle="pill" data-bs-target="#tab-jovens" type="button">JOVENS</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-exe-btn" data-bs-toggle="pill" data-bs-target="#tab-exe" type="button">PROVA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-rank-btn" data-bs-toggle="pill" data-bs-target="#tab-rank" type="button">RANKING</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- ABA 1: CHAMADA (FOCO EM AGILIDADE) -->
        <div class="tab-pane fade show active" id="tab-chamada" role="tabpanel">
            <div class="card p-3 border-top border-5 border-success">
                <h6 class="fw-bold mb-2">Encontro Selecionado</h6>
                <div class="d-flex gap-2 mb-3">
                    <select class="form-select shadow-sm" id="selectEncontro" onchange="window.location.href='gincana.php?tab=chamada&encontro_id='+this.value">
                        <?php foreach($encontros as $e): ?>
                            <option value="<?=$e['id']?>" <?=$e['id']==$enc_ativo_id?'selected':''?>><?=date('d/m', strtotime($e['data_encontro']))?> - <?=$e['tema']?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-dark shadow-sm" onclick="abrirQr()"><i class="bi bi-qr-code"></i></button>
                </div>
                
                <label class="small-label mb-1">Busca Rápida de Jovem</label>
                <input type="text" id="inputBuscaManual" class="form-control border-primary" placeholder="Nome ou Celular..." onkeyup="buscarChamada(this.value)">
                <div id="resultadoChamada" class="list-group mt-2 shadow-sm"></div>
            </div>

            <div class="table-responsive bg-white rounded shadow-sm border mt-3">
                <table class="table table-sm table-hover mb-0 text-center" style="font-size: 0.7rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3 text-start">Membro</th>
                            <?php 
                            $col_encs = array_slice($encontros, 0, 4); 
                            foreach(array_reverse($col_encs) as $u) echo "<th>".date('d/m', strtotime($u['data_encontro']))."</th>";
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $res_chamada = $pdo->query("SELECT id, nome FROM jovens ORDER BY nome ASC LIMIT 60")->fetchAll();
                        foreach($res_chamada as $lj): ?>
                        <tr>
                            <td class="ps-3 text-start fw-bold text-truncate" style="max-width:140px"><?=$lj['nome']?></td>
                            <?php foreach(array_reverse($col_encs) as $u): 
                                $st = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
                                $st->execute([$lj['id'], $u['id']]);
                                $pres = $st->fetch();
                            ?>
                                <td>
                                    <?php if($pres): ?>
                                        <form method="POST" onsubmit="return confirm('Remover presença?')">
                                            <input type="hidden" name="form_acao" value="remover_presenca"><input type="hidden" name="aba_destino" value="chamada">
                                            <input type="hidden" name="j_id" value="<?=$lj['id']?>"><input type="hidden" name="e_id" value="<?=$u['id']?>">
                                            <button type="submit" class="btn btn-link p-0 text-success"><i class="bi bi-check-circle-fill"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <i class="bi bi-dash text-light"></i>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ABA 2: JOVENS (COM FILTRO E PAGINAÇÃO) -->
        <div class="tab-pane fade" id="tab-jovens" role="tabpanel">
            <div class="card p-3 border-top border-5 border-info">
                <h6 class="fw-bold mb-3">Filtrar Base de Dados</h6>
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="jovens">
                    <input type="text" name="f_jovem" class="form-control" placeholder="Buscar por Nome ou Celular..." value="<?= htmlspecialchars($filtro_jovem) ?>">
                    <button type="submit" class="btn btn-info text-white fw-bold">BUSCAR</button>
                    <?php if($filtro_jovem): ?>
                        <a href="gincana.php?tab=jovens" class="btn btn-light border"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover bg-white rounded shadow-sm border">
                    <thead class="table-light"><tr><th class="ps-3">Jovem</th><th class="text-end pe-3">Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($jovens_grid as $j): ?>
                        <tr class="align-middle">
                            <td class="ps-3">
                                <div class="fw-bold" style="font-size: 0.85rem;"><?=$j['nome']?></div>
                                <small class="text-muted" style="font-size: 0.7rem;">
                                    <i class="bi bi-whatsapp"></i> <?=$j['telefone']?> | 
                                    <?= ($j['data_nascimento'] ? date('d/m/y', strtotime($j['data_nascimento'])) : $j['ano_nascimento']) ?>
                                </small>
                            </td>
                            <td class="text-end pe-3">
                                <form method="POST" onsubmit="return confirm('Excluir?')">
                                    <input type="hidden" name="form_acao" value="deletar_jovem"><input type="hidden" name="id_jovem" value="<?=$j['id']?>">
                                    <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$jovens_grid): ?> <tr><td colspan="2" class="text-center py-4 text-muted small">Nenhum jovem encontrado.</td></tr> <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($total_paginas > 1): ?>
            <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
                <?php for($i=1; $i<=$total_paginas; $i++): ?>
                    <li class="page-item <?=($pagina_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&tab=jovens&f_jovem=<?=urlencode($filtro_jovem)?>"><?=$i?></a></li>
                <?php endfor; ?>
            </ul></nav>
            <?php endif; ?>
        </div>

        <!-- ABA 3: PROVA (CRONÔMETRO) -->
        <div class="tab-pane fade" id="tab-exe" role="tabpanel">
            <div class="card p-4 text-center border-top border-5 border-primary">
                <?php if ($ativo): ?>
                    <h1 class="fw-bold text-primary mb-1 text-uppercase"><?= $ativo['nome_time'] ?></h1>
                    <div id="cronometro">00:00:00</div>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="grupo_id" value="<?= $ativo['grupo_id'] ?>"><input type="hidden" name="aba_destino" value="exe">
                        <div class="d-grid gap-3">
                            <button type="submit" name="bt_acao" value="<?=($ativo['status']=='rodando'?'pause':'resume')?>" class="btn <?=($ativo['status']=='rodando'?'btn-warning':'btn-success')?> btn-xl shadow"><?=($ativo['status']=='rodando'?'PAUSAR PROVA':'RETOMAR PROVA')?></button>
                            <button type="submit" name="bt_acao" value="finish" class="btn btn-danger btn-xl shadow" onclick="return confirm('Finalizar?')">FINALIZAR AGORA</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h5 class="fw-bold mb-3 text-uppercase">Gincana em Andamento</h5>
                    <form method="POST">
                        <input type="hidden" name="aba_destino" value="exe">
                        <select name="grupo_id" class="form-select form-select-lg mb-3 shadow-sm" required>
                            <option value="">Selecione o Time...</option>
                            <?php foreach($grupos as $g): ?><option value="<?=$g['id']?>"><?=$g['nome_time']?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" name="bt_acao" value="start" class="btn btn-primary btn-xl w-100 shadow">START</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA 4: RANKING -->
        <div class="tab-pane fade" id="tab-rank" role="tabpanel">
            <h6 class="fw-bold text-center mb-3">RANKING OFICIAL</h6>
            <?php foreach($ranking as $i => $r): ?>
                <div class="card p-3 mb-2 border-start border-5 border-success d-flex flex-row justify-content-between align-items-center shadow-sm">
                    <div><span class="badge bg-success rounded-pill me-1"><?=($i+1)?>º</span> <span class="fw-bold text-uppercase small"><?= $r['nome_time'] ?></span></div>
                    <div class="h4 m-0 fw-bold text-danger"><?= gmdate("H:i:s", $r['tempo']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL: GESTÃO DE ENCONTROS -->
<div class="modal fade" id="modalGerirEncontros" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0 shadow">
    <div class="modal-header bg-dark text-white"><h5>Eventos / Encontros</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4">
        <form method="POST" class="bg-light p-3 rounded mb-4 border">
            <input type="hidden" name="form_acao" value="novo_encontro"><input type="hidden" name="aba_destino" value="chamada">
            <h6 class="fw-bold mb-3 small">CADASTRAR NOVO ENCONTRO</h6>
            <div class="row g-2">
                <div class="col-md-3"><label class="small-label">Data</label><input type="date" name="data_e" class="form-control" required value="<?=date('Y-m-d')?>"></div>
                <div class="col-md-4"><label class="small-label">Local</label><input type="text" name="local_e" class="form-control" placeholder="Ex: Matriz" required></div>
                <div class="col-md-5"><label class="small-label">Tema</label><input type="text" name="tema_e" class="form-control" placeholder="Tema do Encontro" required></div>
                <div class="col-12 mt-3"><button type="submit" class="btn btn-success w-100 fw-bold">SALVAR ENCONTRO</button></div>
            </div>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle small">
            <thead><tr><th>Data</th><th>Tema</th><th>Ação</th></tr></thead>
            <tbody>
                <?php foreach($encontros as $e): ?>
                <tr>
                    <td><?=date('d/m/y', strtotime($e['data_encontro']))?></td><td><?=$e['tema']?></td>
                    <td><form method="POST" onsubmit="return confirm('Excluir?')"><input type="hidden" name="form_acao" value="excluir_encontro"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div></div></div>

<!-- MODAL: CADASTRO RÁPIDO (POPUP NA CHAMADA) -->
<div class="modal fade" id="modalCadRapido" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCadRapido" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill"></i> Cadastro Rápido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-2">
                    <label class="small-label">NOME E SOBRENOME</label>
                    <input type="text" id="cad_nome" name="nome" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="small-label">WHATSAPP (CELULAR)</label>
                    <input type="text" id="cad_tel" name="tel" class="form-control" placeholder="(00) 00000-0000" required>
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="small-label">DATA NASCIMENTO</label>
                        <input type="date" id="cad_data" name="data_nasc" class="form-control" onchange="document.getElementById('cad_ano').value = this.value.split('-')[0]">
                    </div>
                    <div class="col-5">
                        <label class="small-label">ANO (AUTO)</label>
                        <input type="number" id="cad_ano" name="ano" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" onclick="enviarCadastroRapido()" class="btn btn-info text-white rounded-pill px-4 fw-bold">SALVAR E MARCAR PRESENÇA</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: QR CODE -->
<div class="modal fade" id="modalQr" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg"><h5 class="fw-bold mb-3 text-primary">QR CODE CHAMADA</h5><div id="qrcode" class="d-flex justify-content-center mb-3 p-3 bg-white border rounded shadow-sm"></div><p class="small text-muted">Check-in Facial dos Jovens</p><button class="btn btn-secondary w-100 rounded-pill fw-bold" data-bs-dismiss="modal">FECHAR</button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalCadRapido = new bootstrap.Modal(document.getElementById('modalCadRapido'));

    // Persistência de Abas
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if(tab) {
            const btn = document.getElementById('tab-' + tab + '-btn');
            if(btn) new bootstrap.Tab(btn).show();
        } else {
            const activeId = localStorage.getItem('activeTabGincana');
            if(activeId) {
                const btn = document.getElementById(activeId);
                if(btn) new bootstrap.Tab(btn).show();
            }
        }
        document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(btn => {
            btn.addEventListener('shown.bs.tab', e => localStorage.setItem('activeTabGincana', e.target.id));
        });
    });

    // Chamada Manual e Popup de Cadastro
    function buscarChamada(q) {
        const res = document.getElementById('resultadoChamada');
        if(q.length < 2) { res.innerHTML = ''; return; }
        fetch('acoes_presenca.php?buscar=' + q)
        .then(r => r.json()).then(dados => {
            let html = '';
            if(dados.length === 0) {
                html = `<button type="button" onclick="abrirPopupCadastro('${q}')" class="list-group-item list-group-item-action list-group-item-warning fw-bold small"><i class="bi bi-plus-circle-fill me-1"></i> "${q}" não encontrado. Clique para Cadastrar Agora!</button>`;
            } else {
                dados.forEach(d => {
                    html += `<button type="button" onclick="salvarManual(${d.id})" class="list-group-item list-group-item-action small fw-bold py-2">${d.nome} <small class="text-muted">(${d.telefone})</small></button>`;
                });
            }
            res.innerHTML = html;
        });
    }

    function abrirPopupCadastro(nome) {
        document.getElementById('cad_nome').value = nome;
        modalCadRapido.show();
    }

    function enviarCadastroRapido() {
        const fd = new FormData();
        fd.append('nome', document.getElementById('cad_nome').value);
        fd.append('tel', document.getElementById('cad_tel').value);
        fd.append('data_nasc', document.getElementById('cad_data').value);
        fd.append('ano', document.getElementById('cad_ano').value);

        fetch('acoes_presenca.php?cadastrar_rapido_completo=1', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if(res.id) salvarManual(res.id);
        });
    }

    function salvarManual(jId) {
        const eId = document.getElementById('selectEncontro').value;
        const fd = new FormData(); fd.append('jovem_id', jId); fd.append('encontro_id', eId);
        fetch('acoes_presenca.php?salvar_manual=1', { method: 'POST', body: fd }).then(() => location.reload());
    }

    function abrirQr() {
        const eId = document.getElementById('selectEncontro').value;
        const container = document.getElementById("qrcode");
        container.innerHTML = '';
        new QRCode(container, { text: "https://jmmovimento.com.br/checkin.php?e=" + eId, width: 240, height: 240 });
        new bootstrap.Modal(document.getElementById('modalQr')).show();
    }

    // Cronômetro
    <?php if ($ativo): ?>
        const startTime = new Date("<?= $ativo['inicio'] ?>").getTime();
        const totalPausaMs = <?= (int)$ativo['total_pausa_segundos'] ?> * 1000;
        const status = "<?= $ativo['status'] ?>";
        const pausaInicio = "<?= $ativo['pausa_inicio'] ?>";
        function relogio() {
            let diff = (status === 'rodando') ? (new Date().getTime() - startTime) - totalPausaMs : (new Date(pausaInicio).getTime() - startTime) - totalPausaMs;
            if(diff < 0) diff = 0;
            const h = Math.floor(diff / 3600000).toString().padStart(2, '0');
            const m = Math.floor((diff % 3600000) / 60000).toString().padStart(2, '0');
            const s = Math.floor((diff % 60000) / 1000).toString().padStart(2, '0');
            document.getElementById('cronometro').innerText = `${h}:${m}:${s}`;
        }
        if(status === 'rodando') setInterval(relogio, 1000);
        relogio();
    <?php endif; ?>
</script>
</body>
</html>
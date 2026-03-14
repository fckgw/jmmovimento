<?php
/**
 * JMM SYSTEM - GESTÃO INTEGRADA MASTER FINAL (VERSÃO CORRIGIDA)
 */
require_once 'config.php';

// 1. SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_nivel = $_SESSION['nivel'];

// --- 2. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$enc_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id_ativo = $enc_ativo['id'] ?? 0;

// --- 3. TOTAIS PARA OS BADGES ---
$total_cadastrados = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn() ?: 0;
$total_presentes_hoje = 0;
if ($enc_id_ativo) {
    $total_presentes_hoje = $pdo->query("SELECT COUNT(*) FROM presencas WHERE encontro_id = $enc_id_ativo")->fetchColumn() ?: 0;
}

// --- 4. LÓGICA DE PAGINAÇÃO E FILTRO (ABA JOVENS) ---
$itens_por_pag = 10;
$p_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($p_atual < 1) $p_atual = 1;
$offset = ($p_atual - 1) * $itens_por_pag;

$f_j = isset($_GET['f_jovem']) ? trim($_GET['f_jovem']) : '';
$where_j = "WHERE 1=1";
$params_j = [];
if ($f_j) { 
    $where_j .= " AND (nome LIKE ? OR telefone LIKE ? OR data_nascimento LIKE ?)"; 
    $params_j[] = "%$f_j%"; $params_j[] = "%$f_j%"; $params_j[] = "%$f_j%";
}

// Cálculo correto do total de páginas para evitar o Warning
$total_registros_filtrados = $pdo->prepare("SELECT COUNT(*) FROM jovens $where_j");
$total_registros_filtrados->execute($params_j);
$total_paginas = ceil($total_registros_filtrados->fetchColumn() / $itens_por_pag);

// --- 5. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';
    $aba_retorno = $_POST['aba_destino'] ?? 'chamada';

    // Gestão de Encontros
    if ($acao == 'novo_encontro') {
        $pdo->prepare("INSERT INTO encontros (data_encontro, local_encontro, tema, ativo) VALUES (?, ?, ?, 0)")->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e']]);
    }
    if ($acao == 'ativar_encontro') {
        $pdo->exec("UPDATE encontros SET ativo = 0");
        $pdo->prepare("UPDATE encontros SET ativo = 1 WHERE id = ?")->execute([$_POST['e_id']]);
    }
    if ($acao == 'salvar_ata') {
        $pdo->prepare("UPDATE encontros SET ata = ? WHERE id = ?")->execute([$_POST['texto_ata'], $enc_id_ativo]);
    }
    if ($acao == 'checkin_massa') {
        if (!empty($_POST['jovens_ids']) && $enc_id_ativo) {
            foreach ($_POST['jovens_ids'] as $jid) {
                $pdo->prepare("INSERT IGNORE INTO presencas (jovem_id, encontro_id, metodo) VALUES (?, ?, 'manual')")->execute([$jid, $enc_id_ativo]);
            }
        }
    }
    if ($acao == 'remover_presenca') {
        $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ? AND encontro_id = ?")->execute([$_POST['j_id'], $_POST['e_id']]);
    }

    // Gestão de Jovens
    if ($acao == 'novo_jovem') {
        $id_j = $_POST['id_jovem_edit'] ?? '';
        if (!empty($id_j)) {
            $pdo->prepare("UPDATE jovens SET nome=?, telefone=?, ano_nascimento=?, data_nascimento=? WHERE id=?")
                ->execute([trim($_POST['nome']), trim($_POST['telefone']), (int)$_POST['ano_nascimento'], $_POST['data_nascimento'] ?: null, $id_j]);
        } else {
            $pdo->prepare("INSERT INTO jovens (nome, telefone, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?)")
                ->execute([trim($_POST['nome']), trim($_POST['telefone']), (int)$_POST['ano_nascimento'], $_POST['data_nascimento'] ?: null]);
        }
    }
    if ($acao == 'deletar_jovem') { $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]); }

    // Equipes e Gincana
    if ($acao == 'novo_grupo') { $pdo->prepare("INSERT INTO grupos (nome_time) VALUES (?)")->execute([$_POST['nome_time']]); }
    if ($acao == 'editar_equipe_gincana') { $pdo->prepare("UPDATE grupos SET nome_time = ? WHERE id = ?")->execute([$_POST['nome_time'], $_POST['id_time']]); }
    if ($acao == 'deletar_equipe_gincana') { $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$_POST['id_time']]); }

    // Cabo de Guerra
    if ($acao == 'novo_time_cg') { $pdo->prepare("INSERT INTO cg_times (nome_santo, capitao_id, pontos) VALUES (?, ?, 0)")->execute([$_POST['nome_santo'], $_POST['capitao_id'] ?: null]); }
    if ($acao == 'editar_time_cg') { $pdo->prepare("UPDATE cg_times SET nome_santo = ?, capitao_id = ? WHERE id = ?")->execute([$_POST['nome_santo'], $_POST['capitao_id'], $_POST['time_id']]); }
    if ($acao == 'excluir_time_cg') { $pdo->prepare("DELETE FROM cg_times WHERE id = ?")->execute([$_POST['time_id']]); }
    if ($acao == 'vitoria_cg') {
        $pdo->prepare("UPDATE cg_disputas SET vencedor_id = ?, tempo_segundos = ?, status = 'concluido' WHERE id = ?")->execute([$_POST['vencedor_id'], (int)$_POST['tempo_cg'], $_POST['disputa_id']]);
        $pdo->prepare("UPDATE cg_times SET pontos = pontos + 3 WHERE id = ?")->execute([$_POST['vencedor_id']]);
    }
    if ($acao == 'gerar_batalhas') {
        $pdo->exec("DELETE FROM cg_disputas WHERE status = 'pendente'");
        $times = $pdo->query("SELECT id FROM cg_times")->fetchAll(PDO::FETCH_COLUMN);
        shuffle($times);
        for ($i=0; $i < count($times); $i+=2) { if(isset($times[$i+1])) $pdo->prepare("INSERT INTO cg_disputas (time_a_id, time_b_id) VALUES (?, ?)")->execute([$times[$i], $times[$i+1]]); }
    }

    if (isset($_POST['bt_acao'])) {
        $id_g = $_POST['grupo_id']; $agora = date('Y-m-d H:i:s');
        if ($_POST['bt_acao'] == 'start') $pdo->prepare("INSERT INTO registros (grupo_id, inicio, status) VALUES (?, ?, 'rodando')")->execute([$id_g, $agora]);
        elseif ($_POST['bt_acao'] == 'finish') $pdo->prepare("UPDATE registros SET fim = ?, status = 'finalizado' WHERE grupo_id = ? AND status != 'finalizado'")->execute([$agora, $id_g]);
    }

    header("Location: gincana.php?tab=$aba_retorno&p=$p_atual"); exit;
}

// --- 6. CONSULTAS DE INTERFACE ---
$encontros_all = $pdo->query("SELECT * FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);

// Jovens presentes no encontro ativo (Mapa de Chamada)
$jovens_presentes = $pdo->query("SELECT j.* FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = '$enc_id_ativo' ORDER BY j.nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$jovens_ausentes = $pdo->query("SELECT id, nome FROM jovens WHERE id NOT IN (SELECT jovem_id FROM presencas WHERE encontro_id = '$enc_id_ativo') ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$media_idade = $pdo->query("SELECT ROUND(AVG(YEAR(CURDATE()) - YEAR(data_nascimento))) FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = '$enc_id_ativo' AND j.data_nascimento IS NOT NULL")->fetchColumn() ?: 0;

// Lista Geral (GRID)
$stmt_grid = $pdo->prepare("SELECT * FROM jovens $where_j ORDER BY nome ASC LIMIT $offset, $itens_por_pag");
$stmt_grid->execute($params_j);
$jovens_grid = $stmt_grid->fetchAll(PDO::FETCH_ASSOC);

$aniv_hoje = $pdo->query("SELECT nome FROM jovens WHERE DAY(data_nascimento) = DAY(NOW()) AND MONTH(data_nascimento) = MONTH(NOW())")->fetchAll(PDO::FETCH_ASSOC);
$cg_times = $pdo->query("SELECT t.*, j.nome as capitao_nome FROM cg_times t LEFT JOIN jovens j ON t.capitao_id = j.id ORDER BY t.pontos DESC, t.id ASC")->fetchAll(PDO::FETCH_ASSOC);
$disputas_cg = $pdo->query("SELECT d.*, ta.nome_santo as nome_a, tb.nome_santo as nome_b FROM cg_disputas d JOIN cg_times ta ON d.time_a_id = ta.id JOIN cg_times tb ON d.time_b_id = tb.id WHERE d.status = 'pendente'")->fetchAll(PDO::FETCH_ASSOC);
$equipes_gincana = $pdo->query("SELECT * FROM grupos ORDER BY nome_time ASC")->fetchAll(PDO::FETCH_ASSOC);
$ativo_gincana = $pdo->query("SELECT r.*, g.nome_time FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status IN ('rodando', 'pausado') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ranking_gincana = $pdo->query("SELECT g.nome_time, (TIMESTAMPDIFF(SECOND, r.inicio, r.fim) - r.total_pausa_segundos) as tempo FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status = 'finalizado' ORDER BY tempo ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>JMM Master</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 70px; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.65rem; color: #555; background: #fff; margin: 2px; }
        .nav-pills .nav-link.active { background-color: #0d6efd !important; color: #fff !important; }
        #cronometro, .timer-display { font-size: 3rem; font-weight: 900; color: #dc3545; font-family: monospace; }
        .badge-count { font-size: 0.6rem; vertical-align: middle; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-grid-3x3-gap-fill fs-5"></i></a>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
        <button class="btn btn-primary btn-sm rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalGerirEncontros">ENCONTROS</button>
    </div>
</nav>

<div class="container">
    
    <!-- MENU DE ABAS -->
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded shadow-sm" id="pills-tab" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="tab-chamada-btn" data-bs-toggle="pill" data-bs-target="#tab-chamada" type="button">CHAMADA <span class="badge bg-success badge-count" id="badge-presenca"><?=$total_presentes_hoje?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="tab-cg-btn" data-bs-toggle="pill" data-bs-target="#tab-cg" type="button">CABO</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-exe-btn" data-bs-toggle="pill" data-bs-target="#tab-exe" type="button">PROVA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-jovens-btn" data-bs-toggle="pill" data-bs-target="#tab-jovens" type="button">JOVENS <span class="badge bg-secondary badge-count"><?=$total_cadastrados?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="tab-ata-btn" data-bs-toggle="pill" data-bs-target="#tab-ata" type="button">ATA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-rank-btn" data-bs-toggle="pill" data-bs-target="#tab-rank" type="button">RANK</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- 1. CHAMADA -->
        <div class="tab-pane fade show active" id="tab-chamada" role="tabpanel">
            <div class="card p-3 border-top border-5 border-success text-center">
                <h6 class="fw-bold mb-1 text-success text-uppercase">Check-in: <?=$enc_ativo['tema'] ?? '---'?></h6>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-success fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalMassa"><i class="bi bi-people-fill"></i> CHAMADA EM MASSA</button>
                    <button class="btn btn-dark fw-bold rounded-pill" onclick="abrirQr()"><i class="bi bi-qr-code-scan"></i> QR CODE</button>
                </div>
                <hr>
                <input type="text" class="form-control" placeholder="Busca Manual (Nome, Tel ou Data)..." onkeyup="buscarChamada(this.value)">
                <div id="resultadoChamada" class="list-group mt-2"></div>
            </div>
            
            <div class="table-responsive bg-white rounded shadow-sm border mt-3">
                <table class="table table-sm text-center mb-0" style="font-size:0.7rem;">
                    <thead class="table-dark"><tr><th class="text-start ps-3">Jovem</th><?php $cols = array_slice($encontros_all, 0, 4); foreach(array_reverse($cols) as $u) echo "<th>".date('d/m', strtotime($u['data_encontro']))."</th>"; ?></tr></thead>
                    <tbody><?php foreach($jovens_presentes as $lj): ?><tr><td class="text-start ps-3 fw-bold"><?=$lj['nome']?></td><?php foreach(array_reverse($cols) as $u): $pres = $pdo->query("SELECT id FROM presencas WHERE jovem_id={$lj['id']} AND encontro_id={$u['id']}")->fetch(); ?><td><?php if($pres): ?><form method="POST"><input type="hidden" name="form_acao" value="remover_presenca"><input type="hidden" name="aba_destino" value="chamada"><input type="hidden" name="j_id" value="<?=$lj['id']?>"><input type="hidden" name="e_id" value="<?=$u['id']?>"><button type="submit" class="btn btn-link p-0 text-success"><i class="bi bi-check-circle-fill"></i></button></form><?php else: ?><i class="bi bi-dash text-light"></i><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>

        <!-- 2. CABO DE GUERRA -->
        <div class="tab-pane fade" id="tab-cg" role="tabpanel">
            <div class="card p-3 border-top border-5 border-warning mb-3">
                <form method="POST" class="row g-2 mb-3">
                    <input type="hidden" name="form_acao" value="novo_time_cg"><input type="hidden" name="aba_destino" value="cg">
                    <div class="col-6"><input type="text" name="nome_santo" class="form-control" placeholder="Time Santo" required></div>
                    <div class="col-6"><select name="capitao_id" class="form-select"><option value="">Capitão...</option><?php foreach($jovens_presentes as $jph): ?><option value="<?=$jph['id']?>"><?=$jph['nome']?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold mt-2">ADICIONAR TIME</button>
                </form>
                <form method="POST"><input type="hidden" name="form_acao" value="gerar_batalhas"><input type="hidden" name="aba_destino" value="cg"><button type="submit" class="btn btn-dark w-100 fw-bold">SORTEAR BATALHAS</button></form>
            </div>
            <?php foreach($disputas_cg as $dp): ?>
                <div class="card p-3 text-center shadow-sm border-start border-5 border-primary">
                    <div class="d-flex justify-content-around align-items-center mb-1"><div class="fw-bold"><?=$dp['nome_a']?></div><div class="small">VS</div><div class="fw-bold"><?=$dp['nome_b']?></div></div>
                    <div id="timer-cg-<?=$dp['id']?>" class="h3 fw-bold text-danger my-1">00:00:00</div>
                    <div class="d-flex gap-2"><button class="btn btn-success btn-sm w-100" onclick="startCgTimer(<?=$dp['id']?>)">START</button><button class="btn btn-dark btn-sm w-100" onclick="vencerCg(<?=$dp['id']?>, <?=$dp['time_a_id']?>, <?=$dp['time_b_id']?>, '<?=$dp['nome_a']?>', '<?=$dp['nome_b']?>')">VENCEDOR</button></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 3. PROVA (GINCANA) -->
        <div class="tab-pane fade" id="tab-exe" role="tabpanel">
            <div class="card p-4 text-center border-top border-5 border-primary shadow-sm">
                <?php if ($ativo_gincana): ?>
                    <h1 class="fw-bold text-primary mb-2 text-uppercase"><?= $ativo_gincana['nome_time'] ?></h1><div id="cronometro">00:00:00</div>
                    <form method="POST" class="mt-3"><input type="hidden" name="grupo_id" value="<?= $ativo_gincana['grupo_id'] ?>"><input type="hidden" name="aba_destino" value="exe"><button type="submit" name="bt_acao" value="finish" class="btn btn-danger btn-xl w-100 shadow">FINALIZAR PROVA</button></form>
                <?php else: ?>
                    <form method="POST"><input type="hidden" name="aba_destino" value="exe"><select name="grupo_id" class="form-select form-select-lg mb-3 shadow-sm" required><option value="">Escolha Equipe...</option><?php foreach($equipes_gincana as $eg): ?><option value="<?=$eg['id']?>"><?=$eg['nome_time']?></option><?php endforeach; ?></select><button type="submit" name="bt_acao" value="start" class="btn btn-primary btn-xl w-100 shadow">START</button></form>
                <?php endif; ?>
            </div>
            <div class="mt-3"><?php foreach($equipes_gincana as $eg): ?><div class="card p-2 mb-2 shadow-sm d-flex flex-row justify-content-between align-items-center"><form method="POST" class="d-flex flex-grow-1"><input type="hidden" name="form_acao" value="editar_equipe_gincana"><input type="hidden" name="id_time" value="<?=$eg['id']?>"><input type="hidden" name="aba_destino" value="exe"><input type="text" name="nome_time" value="<?=$eg['nome_time']?>" class="form-control form-control-sm border-0 bg-transparent fw-bold text-primary"><button type="submit" class="btn btn-link text-success p-0 ms-1"><i class="bi bi-check-circle"></i></button></form><form method="POST"><input type="hidden" name="form_acao" value="deletar_equipe_gincana"><input type="hidden" name="id_time" value="<?=$eg['id']?>"><input type="hidden" name="aba_destino" value="exe"><button type="submit" class="btn btn-link text-danger p-0 ms-2" onclick="return confirm('Apagar?')"><i class="bi bi-trash"></i></button></form></div><?php endforeach; ?></div>
        </div>

        <!-- 4. JOVENS (GRID COMPLETO) -->
        <div class="tab-pane fade" id="tab-jovens" role="tabpanel">
            <div class="card p-3 border-top border-5 border-info shadow-sm">
                <h6 class="fw-bold mb-3" id="titulo_form_jovem">Cadastro de Jovem</h6>
                <form method="POST" id="form_jovem_base">
                    <input type="hidden" name="form_acao" value="novo_jovem"><input type="hidden" name="aba_destino" value="jovens">
                    <input type="hidden" name="id_jovem_edit" id="id_jovem_edit">
                    <input type="text" name="nome" id="edit_nome" class="form-control mb-2" placeholder="Nome Completo" required>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><input type="date" name="data_nascimento" id="edit_data" class="form-control" onchange="document.getElementById('ano_s').value = this.value.split('-')[0]"></div>
                        <div class="col-5"><input type="number" name="ano_nascimento" id="ano_s" class="form-control" placeholder="Ano" required></div>
                    </div>
                    <input type="text" name="telefone" id="edit_tel" class="form-control mb-3" placeholder="WhatsApp">
                    <div class="d-flex gap-2">
                        <button type="submit" id="btn_save_jovem" class="btn btn-info w-100 fw-bold text-white shadow">SALVAR CADASTRO</button>
                        <button type="button" id="btn_cancel_edit" class="btn btn-light border d-none" onclick="cancelarEdicaoJovem()">CANCELAR</button>
                    </div>
                </form>
            </div>
            <form method="GET" class="d-flex gap-2 mb-3"><input type="hidden" name="tab" value="jovens"><input type="text" name="f_jovem" class="form-control form-control-sm" placeholder="Buscar..." value="<?=htmlspecialchars($f_j)?>"><button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-search"></i></button></form>
            <div class="table-responsive"><table class="table table-sm table-hover bg-white rounded border"><tbody><?php foreach($jovens_grid as $j): ?>
                <tr class="align-middle">
                    <td class="ps-3"><div class="fw-bold small"><?=$j['nome']?></div><small class="text-muted small"><?=$j['telefone']?> | <?=($j['data_nascimento']?date('d/m/y',strtotime($j['data_nascimento'])):$j['ano_nascimento'])?></small></td>
                    <td class="text-end pe-3">
                        <button class="btn btn-link text-success p-0 me-2" onclick="checkinRapido(<?=$j['id']?>, this)"><i class="bi bi-person-check-fill fs-5"></i></button>
                        <button class="btn btn-link text-primary p-0 me-2" onclick="povoarEdicaoJovem(<?=htmlspecialchars(json_encode($j))?>)"><i class="bi bi-pencil-square fs-5"></i></button>
                        <form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="deletar_jovem"><input type="hidden" name="id_jovem" value="<?=$j['id']?>"><button type="submit" class="btn btn-link text-danger p-0" onclick="return confirm('Excluir?')"><i class="bi bi-trash fs-5"></i></button></form>
                    </td>
                </tr><?php endforeach; ?></tbody></table></div>
            <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?=($p_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&tab=jovens&f_jovem=<?=urlencode($f_j)?>"><?=$i?></a></li><?php endfor; ?></ul></nav>
        </div>

        <!-- 5. ATA -->
        <div class="tab-pane fade" id="tab-ata" role="tabpanel">
            <div class="card p-3 border-top border-5 border-dark shadow-sm">
                <div class="row text-center mb-3"><div class="col-6 border-end"><h6>Presentes</h6><h3 class="fw-bold text-primary"><?=$total_presentes_hoje?></h3></div><div class="col-6"><h6>Média Idade</h6><h3 class="fw-bold text-success"><?=$media_idade?> anos</h3></div></div>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="salvar_ata"><input type="hidden" name="aba_destino" value="ata">
                    <textarea name="texto_ata" class="form-control mb-3" rows="8" placeholder="Relato do encontro..."><?= $enc_ativo['ata'] ?? '' ?></textarea>
                    <button type="submit" class="btn btn-dark w-100 fw-bold mb-2">SALVAR ATA</button>
                    <a href="gerar_encontro_pdf.php" target="_blank" class="btn btn-outline-danger w-100 fw-bold">PDF DO ENCONTRO</a>
                </form>
            </div>
        </div>

        <!-- 6. RANKING -->
        <div class="tab-pane fade" id="tab-rank" role="tabpanel">
            <h6 class="fw-bold text-center mb-3 small-label">RANKING GERAL</h6>
            <?php foreach($ranking_gincana as $i => $r): ?>
                <div class="card p-3 mb-2 border-start border-5 border-success d-flex flex-row justify-content-between align-items-center shadow-sm">
                    <div><span class="badge bg-success rounded-pill me-1"><?=($i+1)?>º</span> <span class="fw-bold text-uppercase small"><?= $r['nome_time'] ?></span></div>
                    <div class="h4 m-0 fw-bold text-danger"><?= gmdate("H:i:s", $r['tempo']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAIS -->
<div class="modal fade" id="modalGerirEncontros" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5>Eventos</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4">
    <form method="POST" class="bg-light p-3 rounded mb-3 border shadow-sm"><input type="hidden" name="form_acao" value="novo_encontro">
        <div class="row g-2">
            <div class="col-md-3"><label class="small fw-bold">DATA</label><input type="date" name="data_e" class="form-control" required value="<?=date('Y-m-d')?>"></div>
            <div class="col-md-4"><label class="small fw-bold">LOCAL</label><input type="text" name="local_e" class="form-control" required></div>
            <div class="col-md-5"><label class="small fw-bold">TEMA</label><input type="text" name="tema_e" class="form-control" required></div>
            <button type="submit" class="btn btn-success w-100 mt-2 fw-bold shadow-sm">CADASTRAR</button>
        </div>
    </form>
    <table class="table table-sm small"><tbody><?php foreach($encontros_all as $e): ?><tr><td><?=date('d/m/y', strtotime($e['data_encontro']))?></td><td><?=$e['tema']?> <?=($e['ativo']?'<span class="badge bg-success">ATIVO</span>':'')?></td><td class="text-end"><div class="btn-group"><form method="POST"><input type="hidden" name="form_acao" value="ativar_encontro"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-sm btn-outline-success border-0"><i class="bi bi-lightning"></i></button></form><form method="POST" onsubmit="return confirm('Apagar?')"><input type="hidden" name="form_acao" value="excluir_encontro"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button></form></div></td></tr><?php endforeach; ?></tbody></table>
</div></div></div></div>

<div class="modal fade" id="modalMassa" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content border-0 shadow-lg"><input type="hidden" name="form_acao" value="checkin_massa"><input type="hidden" name="aba_destino" value="chamada">
    <div class="modal-header bg-success text-white"><h5>Chamada em Massa</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4 text-center"><button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="toggleMassa()">SELECIONAR TODOS</button><div class="scroll-massa text-start" style="max-height:250px; overflow-y:auto"><?php foreach($jovens_ausentes as $ja): ?><div class="form-check border-bottom py-1"><input class="form-check-input check-m" type="checkbox" name="jovens_ids[]" value="<?=$ja['id']?>" id="m_<?=$ja['id']?>"><label class="form-check-label small" for="m_<?=$ja['id']?>"><?=$ja['nome']?></label></div><?php endforeach; ?></div></div>
    <div class="modal-footer"><button type="submit" class="btn btn-success w-100 fw-bold">GRAVAR PRESENÇAS</button></div>
</form></div></div>

<div class="modal fade" id="modalQr" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4"><div id="qrcode" class="d-flex justify-content-center mb-3"></div><button class="btn btn-secondary w-100 rounded-pill" data-bs-dismiss="modal">Fechar</button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- FUNÇÕES GLOBAIS ---
    function checkinRapido(jId, btn) {
        const eId = <?=$enc_id_ativo?>; if(!eId) return alert('Ative um encontro primeiro!');
        const fd = new FormData(); fd.append('jovem_id', jId); fd.append('encontro_id', eId);
        fetch('acoes_presenca.php?salvar_manual=1', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => { if(data.status === 'ok') location.reload(); });
    }

    function povoarEdicaoJovem(j) {
        document.getElementById('id_jovem_edit').value = j.id;
        document.getElementById('edit_nome').value = j.nome;
        document.getElementById('edit_data').value = j.data_nascimento;
        document.getElementById('ano_s').value = j.ano_nascimento;
        document.getElementById('edit_tel').value = j.telefone;
        document.getElementById('titulo_form_jovem').innerText = "Editar: " + j.nome;
        document.getElementById('btn_save_jovem').innerText = "ATUALIZAR DADOS";
        document.getElementById('btn_save_jovem').classList.replace('btn-info', 'btn-warning');
        document.getElementById('btn_cancel_edit').classList.remove('d-none');
        window.scrollTo(0, 0);
    }
    function cancelarEdicaoJovem() { location.reload(); }

    function abrirQr() {
        const eId = <?=$enc_id_ativo?>; if(!eId) return alert('Ative um encontro!');
        const container = document.getElementById("qrcode"); container.innerHTML = '';
        new QRCode(container, { text: "https://jmmovimento.com.br/checkin.php?e=" + eId, width: 240, height: 240 });
        new bootstrap.Modal(document.getElementById('modalQr')).show();
    }
    function buscarChamada(q) {
        const res = document.getElementById('resultadoChamada'); if(q.length < 2) { res.innerHTML = ''; return; }
        fetch('acoes_presenca.php?buscar=' + q).then(r => r.json()).then(dados => {
            let html = ''; dados.forEach(d => { html += `<button type="button" onclick="checkinRapido(${d.id})" class="list-group-item small fw-bold py-2 text-uppercase">${d.nome}</button>`; });
            res.innerHTML = html;
        });
    }
    function toggleMassa() { document.querySelectorAll('.check-m').forEach(c => c.checked = !c.checked); }

    let cgTimers = {}, cgInts = {};
    function startCgTimer(id) { if(cgInts[id]) return; if(!cgTimers[id]) cgTimers[id] = 0; cgInts[id] = setInterval(() => { cgTimers[id]++; document.getElementById('timer-cg-'+id).innerText = new Date(cgTimers[id]*1000).toISOString().substr(11,8); }, 1000); }
    function vencerCg(dId, idA, idB, nA, nB) {
        const t = cgTimers[dId] || 0;
        const vId = confirm("Venceu: " + nA + "?") ? idA : idB;
        const fd = new FormData(); fd.append('form_acao', 'vitoria_cg'); fd.append('aba_destino', 'cg'); fd.append('vencedor_id', vId); fd.append('disputa_id', dId); fd.append('tempo_cg', t);
        fetch('gincana.php', { method: 'POST', body: fd }).then(() => location.reload());
    }

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if(tab) { const btn = document.getElementById('tab-' + tab + '-btn'); if(btn) new bootstrap.Tab(btn).show(); }
        else { const activeId = localStorage.getItem('activeTabGincana'); if(activeId) { const btn = document.getElementById(activeId); if(btn) new bootstrap.Tab(btn).show(); } }
        document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(btn => { btn.addEventListener('shown.bs.tab', e => localStorage.setItem('activeTabGincana', e.target.id)); });

        <?php if ($ativo_gincana): ?>
            const startT = new Date("<?= $ativo_gincana['inicio'] ?>").getTime();
            const totalP = <?= (int)$ativo_gincana['total_pausa_segundos'] ?> * 1000;
            setInterval(() => {
                let diff = (new Date().getTime() - startT) - totalP;
                if(diff < 0) diff = 0;
                document.getElementById('cronometro').innerText = new Date(diff).toISOString().substr(11, 8);
            }, 1000);
        <?php endif; ?>
    });
</script>
</body>
</html>
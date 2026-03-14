<?php
/**
 * JMM SYSTEM - GESTÃO INTEGRADA MASTER FINAL
 * Módulos: Chamada, Encontros (com Edição), Prova, Jovens (Edit/Checkin), Ata e Ranking.
 */
require_once 'config.php';

// 1. SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_nome = $_SESSION['user_nome'];
$user_nivel = $_SESSION['nivel'];

// --- 2. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$enc_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id_ativo = $enc_ativo['id'] ?? 0;
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');

// --- 3. TOTAIS PARA OS BADGES ---
$total_cadastrados = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn() ?: 0;
$total_presentes_hoje = $enc_id_ativo ? $pdo->query("SELECT COUNT(*) FROM presencas WHERE encontro_id = $enc_id_ativo")->fetchColumn() : 0;

// --- 4. LÓGICA DE PAGINAÇÃO E FILTRO (ABA JOVENS) ---
$itens_por_pag = 10;
$p_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($p_atual < 1) $p_atual = 1;
$offset = ($p_atual - 1) * $itens_por_pag;

$f_j = isset($_GET['f_jovem']) ? trim($_GET['f_jovem']) : '';
$where_j = "WHERE 1=1";
$params_j = [];
if ($f_j) { 
    $where_j .= " AND (nome LIKE ? OR telefone LIKE ?)"; 
    $params_j[] = "%$f_j%"; $params_j[] = "%$f_j%";
}
$total_paginas = ceil($pdo->query("SELECT COUNT(*) FROM jovens $where_j")->fetchColumn() / $itens_por_pag);

// --- 5. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';
    $aba_retorno = $_POST['aba_destino'] ?? 'chamada';

    // Gestão de Encontros (Insert / Update)
    if ($acao == 'novo_encontro') {
        $id_e = $_POST['id_encontro_edit'] ?? '';
        $data = $_POST['data_e'];
        $local = trim($_POST['local_e']);
        $tema = trim($_POST['tema_e']);

        if (!empty($id_e)) {
            // UPDATE
            $pdo->prepare("UPDATE encontros SET data_encontro=?, local_encontro=?, tema=? WHERE id=?")
                ->execute([$data, $local, $tema, $id_e]);
            registrarLog($pdo, "Editou encontro: $tema", "Encontros");
        } else {
            // INSERT
            $pdo->prepare("INSERT INTO encontros (data_encontro, local_encontro, tema, status, ativo) VALUES (?, ?, ?, 'aberto', 0)")
                ->execute([$data, $local, $tema]);
            registrarLog($pdo, "Criou encontro: $tema", "Encontros");
        }
    }

    if ($acao == 'ativar_encontro') {
        $pdo->exec("UPDATE encontros SET ativo = 0");
        $pdo->prepare("UPDATE encontros SET ativo = 1 WHERE id = ?")->execute([$_POST['e_id']]);
    }

    if ($acao == 'status_encontro') {
        $pdo->prepare("UPDATE encontros SET status = ? WHERE id = ?")->execute([$_POST['novo_status'], $_POST['e_id']]);
    }

    if ($acao == 'excluir_encontro') {
        $pdo->prepare("DELETE FROM presencas WHERE encontro_id = ?")->execute([$_POST['e_id']]);
        $pdo->prepare("DELETE FROM encontros WHERE id = ?")->execute([$_POST['e_id']]);
    }

    // Salvar Ata
    if ($acao == 'salvar_ata') {
        $pdo->prepare("UPDATE encontros SET ata = ? WHERE id = ?")->execute([$_POST['texto_ata'], $enc_id_ativo]);
    }

    // Gestão de Jovens (Insert / Update)
    if ($acao == 'novo_jovem') {
        $id_j = $_POST['id_jovem_edit'] ?? '';
        $data_br = $_POST['data_nascimento'];
        $data_sql = null;
        if(!empty($data_br)){
            $pt = explode('/', $data_br);
            if(count($pt) == 3) $data_sql = $pt[2].'-'.$pt[1].'-'.$pt[0];
        }

        if (!empty($id_j)) {
            $pdo->prepare("UPDATE jovens SET nome=?, telefone=?, ano_nascimento=?, data_nascimento=? WHERE id=?")
                ->execute([trim($_POST['nome']), trim($_POST['telefone']), (int)$_POST['ano_nascimento'], $data_sql, $id_j]);
        } else {
            $pdo->prepare("INSERT INTO jovens (nome, telefone, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?)")
                ->execute([trim($_POST['nome']), trim($_POST['telefone']), (int)$_POST['ano_nascimento'], $data_sql]);
        }
    }

    if ($acao == 'deletar_jovem') { $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]); }
    if ($acao == 'remover_presenca') { $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ? AND encontro_id = ?")->execute([$_POST['j_id'], $_POST['e_id']]); }

    // Prova Principal
    if ($acao == 'novo_grupo') { $pdo->prepare("INSERT INTO grupos (nome_time) VALUES (?)")->execute([$_POST['nome_time']]); }
    if ($acao == 'editar_equipe_gincana') { $pdo->prepare("UPDATE grupos SET nome_time = ? WHERE id = ?")->execute([$_POST['nome_time'], $_POST['id_time']]); }
    if ($acao == 'deletar_equipe_gincana') { $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$_POST['id_time']]); }

    if (isset($_POST['bt_acao'])) {
        $id_g = $_POST['grupo_id']; $agora = date('Y-m-d H:i:s');
        if ($_POST['bt_acao'] == 'start') $pdo->prepare("INSERT INTO registros (grupo_id, inicio, status) VALUES (?, ?, 'rodando')")->execute([$id_g, $agora]);
        elseif ($_POST['bt_acao'] == 'finish') $pdo->prepare("UPDATE registros SET fim = ?, status = 'finalizado' WHERE grupo_id = ? AND status != 'finalizado'")->execute([$agora, $id_g]);
    }

    header("Location: gincana.php?tab=$aba_retorno&p=$p_atual"); exit;
}

// --- 6. CONSULTAS DE EXIBIÇÃO ---
$encontros_all = $pdo->query("SELECT * FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);

$media_idade = 0;
if ($enc_id_ativo) {
    $res_idade = $pdo->query("SELECT FLOOR(AVG(TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()))) as media FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = '$enc_id_ativo' AND j.data_nascimento IS NOT NULL")->fetch();
    $media_idade = $res_idade['media'] ?? 0;
}

$jovens_grid = $pdo->query("SELECT * FROM jovens $where_j ORDER BY nome ASC LIMIT $offset, $itens_por_pag")->fetchAll(PDO::FETCH_ASSOC);
$jovens_presentes_hoje = $pdo->query("SELECT j.* FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = '$enc_id_ativo' ORDER BY j.nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipes_gincana = $pdo->query("SELECT * FROM grupos ORDER BY nome_time ASC")->fetchAll(PDO::FETCH_ASSOC);
$ativo_gincana = $pdo->query("SELECT r.*, g.nome_time FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status IN ('rodando', 'pausado') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ranking_gincana = $pdo->query("SELECT g.nome_time, (TIMESTAMPDIFF(SECOND, r.inicio, r.fim) - r.total_pausa_segundos) as tempo FROM registros r JOIN grupos g ON g.id = r.grupo_id WHERE r.status = 'finalizado' ORDER BY tempo ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>JMM System - Master</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 70px; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.65rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #0d6efd !important; color: #fff !important; border-color: #0d6efd; }
        #cronometro { font-size: 3rem; font-weight: 900; color: #dc3545; font-family: monospace; }
        .ck-editor__editable { min-height: 250px; border-radius: 0 0 15px 15px !important; }
        .small-label { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-grid-3x3-gap-fill fs-5"></i></a>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border shadow-sm">
        <div class="text-end lh-1">
            <small class="fw-bold text-muted" style="font-size: 10px;"><?= mb_strtoupper($user_nome) ?></small>
        </div>
    </div>
</nav>

<div class="container">
    
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded shadow-sm" id="pills-tab" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="tab-chamada-btn" data-bs-toggle="pill" data-bs-target="#tab-chamada" type="button">CHAMADA <span class="badge bg-success small"><?=$total_presentes_hoje?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="tab-enc-btn" data-bs-toggle="pill" data-bs-target="#tab-enc" type="button">ENCONTROS</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-exe-btn" data-bs-toggle="pill" data-bs-target="#tab-exe" type="button">PROVA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-jovens-btn" data-bs-toggle="pill" data-bs-target="#tab-jovens" type="button">JOVENS <span class="badge bg-secondary small"><?=$total_cadastrados?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="tab-ata-btn" data-bs-toggle="pill" data-bs-target="#tab-ata" type="button">ATA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-rank-btn" data-bs-toggle="pill" data-bs-target="#tab-rank" type="button">RANK</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- 1. CHAMADA -->
        <div class="tab-pane fade show active" id="tab-chamada" role="tabpanel">
            <div class="card p-3 border-top border-5 border-success text-center shadow-sm">
                <?php if($pode_checkin): ?>
                    <h6 class="fw-bold text-success text-uppercase">Check-in: <?=$enc_ativo['tema']?></h6>
                    <button class="btn btn-dark btn-lg w-100 rounded-pill shadow mt-3" onclick="abrirQr()"><i class="bi bi-qr-code-scan"></i> QR CODE</button>
                    <hr>
                    <input type="text" class="form-control" placeholder="Busca Manual..." onkeyup="buscarChamada(this.value)">
                    <div id="resultadoChamada" class="list-group mt-2"></div>
                <?php else: ?>
                    <div class="alert alert-danger py-3 mb-0 fw-bold"><i class="bi bi-lock-fill me-2"></i> ENTRADA BLOQUEADA</div>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive bg-white rounded shadow-sm border mt-3">
                <table class="table table-sm text-center mb-0" style="font-size:0.7rem;">
                    <thead class="table-dark"><tr><th class="text-start ps-3">Jovem</th><?php $cols = array_slice($encontros_all, 0, 4); foreach(array_reverse($cols) as $u) echo "<th>".date('d/m', strtotime($u['data_encontro']))."</th>"; ?></tr></thead>
                    <tbody><?php foreach($jovens_presentes_hoje as $lj): ?><tr><td class="text-start ps-3 fw-bold text-truncate" style="max-width: 140px;"><?=$lj['nome']?></td><?php foreach(array_reverse($cols) as $u): $pres = $pdo->query("SELECT id FROM presencas WHERE jovem_id={$lj['id']} AND encontro_id={$u['id']}")->fetch(); ?><td><?php if($pres): ?><form method="POST"><input type="hidden" name="form_acao" value="remover_presenca"><input type="hidden" name="aba_destino" value="chamada"><input type="hidden" name="j_id" value="<?=$lj['id']?>"><input type="hidden" name="e_id" value="<?=$u['id']?>"><button type="submit" class="btn btn-link p-0 text-success"><i class="bi bi-check-circle-fill"></i></button></form><?php else: ?><i class="bi bi-dash text-light"></i><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>

        <!-- 2. GESTÃO DE ENCONTROS (ATUALIZADA) -->
        <div class="tab-pane fade" id="tab-enc" role="tabpanel">
            <div class="card p-3 border-top border-5 border-primary shadow-sm">
                <h6 class="fw-bold mb-3" id="titulo_form_encontro">Novo Evento</h6>
                <form method="POST" id="form_encontro_base">
                    <input type="hidden" name="form_acao" value="novo_encontro"><input type="hidden" name="aba_destino" value="enc">
                    <input type="hidden" name="id_encontro_edit" id="id_encontro_edit">
                    <div class="row g-2 mb-2">
                        <div class="col-md-3"><label class="small-label">Data</label><input type="date" name="data_e" id="enc_data" class="form-control" required></div>
                        <div class="col-md-4"><label class="small-label">Local / Paróquia</label><input type="text" name="local_e" id="enc_local" class="form-control" placeholder="Ex: Capela São José" required></div>
                        <div class="col-md-5"><label class="small-label">Tema do Encontro</label><input type="text" name="tema_e" id="enc_tema" class="form-control" placeholder="Ex: Amor de Maria" required></div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" id="btn_save_encontro" class="btn btn-primary w-100 fw-bold">SALVAR ENCONTRO</button>
                        <button type="button" id="btn_cancel_enc" class="btn btn-light border d-none" onclick="location.reload()">CANCELAR</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table table-sm align-middle mb-0" style="font-size: 0.8rem;">
                    <thead class="table-light"><tr><th class="ps-3">Data / Tema / Local</th><th class="text-center">Status</th><th class="text-end pe-3">Ações</th></tr></thead>
                    <tbody>
                        <?php foreach($encontros_all as $e): ?>
                        <tr>
                            <td class="ps-3">
                                <b><?=date('d/m/y', strtotime($e['data_encontro']))?></b> - <?=$e['tema']?><br>
                                <small class="text-muted"><i class="bi bi-geo-alt"></i> <?=$e['local_encontro']?></small>
                            </td>
                            <td class="text-center"><?= ($e['ativo'] ? '<span class="badge bg-success">ATIVO</span>' : '') ?> <?= ($e['status'] == 'finalizado' ? '<span class="badge bg-danger">ENCERRADO</span>' : '') ?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <button class="btn btn-link text-primary p-0 me-2" onclick="povoarEdicaoEncontro(<?=htmlspecialchars(json_encode($e))?>)"><i class="bi bi-pencil-square fs-5"></i></button>
                                <?php if(!$e['ativo']): ?><form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="ativar_encontro"><input type="hidden" name="aba_destino" value="enc"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-link text-success p-0 me-2"><i class="bi bi-lightning-fill fs-5"></i></button></form><?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Excluir encontro?')"><input type="hidden" name="form_acao" value="excluir_encontro"><input type="hidden" name="aba_destino" value="enc"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. PROVA -->
        <div class="tab-pane fade" id="tab-exe" role="tabpanel">
            <div class="card p-4 text-center border-top border-5 border-primary shadow-sm">
                <?php if ($ativo_gincana): ?>
                    <h1 class="fw-bold text-primary mb-2 text-uppercase"><?= $ativo_gincana['nome_time'] ?></h1><div id="cronometro">00:00:00</div>
                    <form method="POST" class="mt-3"><input type="hidden" name="grupo_id" value="<?= $ativo_gincana['grupo_id'] ?>"><input type="hidden" name="aba_destino" value="exe"><button type="submit" name="bt_acao" value="finish" class="btn btn-danger py-3 w-100 shadow fw-bold">FINALIZAR PROVA</button></form>
                <?php else: ?>
                    <form method="POST"><input type="hidden" name="aba_destino" value="exe"><select name="grupo_id" class="form-select form-select-lg mb-3 shadow-sm" required><option value="">Escolha Equipe...</option><?php foreach($equipes_gincana as $eg): ?><option value="<?=$eg['id']?>"><?=$eg['nome_time']?></option><?php endforeach; ?></select><button type="submit" name="bt_acao" value="start" class="btn btn-primary btn-xl w-100 shadow">START GINCANA</button></form>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. JOVENS -->
        <div class="tab-pane fade" id="tab-jovens" role="tabpanel">
            <div class="card p-3 border-top border-5 border-info shadow-sm">
                <h6 class="fw-bold mb-3" id="titulo_form_jovem">Cadastro de Jovem</h6>
                <form method="POST" id="form_jovem_base">
                    <input type="hidden" name="form_acao" value="novo_jovem"><input type="hidden" name="aba_destino" value="jovens"><input type="hidden" name="id_jovem_edit" id="id_jovem_edit">
                    <input type="text" name="nome" id="edit_nome" class="form-control mb-2 shadow-sm" placeholder="Nome e Sobrenome" required>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><input type="text" name="data_nascimento" id="edit_data" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)" maxlength="10"></div>
                        <div class="col-5"><input type="number" name="ano_nascimento" id="ano_s" class="form-control" placeholder="Ano" required></div>
                    </div>
                    <input type="text" name="telefone" id="edit_tel" class="form-control mb-3 shadow-sm" placeholder="WhatsApp">
                    <div class="d-flex gap-2"><button type="submit" id="btn_save_jovem" class="btn btn-info w-100 fw-bold text-white shadow">SALVAR</button><button type="button" id="btn_cancel_edit" class="btn btn-light border d-none" onclick="location.reload()">CANCELAR</button></div>
                </form>
            </div>
            <form method="GET" class="d-flex gap-2 mb-3"><input type="hidden" name="tab" value="jovens"><input type="text" name="f_jovem" class="form-control form-control-sm" placeholder="Buscar..." value="<?=htmlspecialchars($f_j)?>"><button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-search"></i></button></form>
            <div class="table-responsive"><table class="table table-sm table-hover bg-white rounded border"><tbody><?php foreach($jovens_grid as $j): ?>
                <tr class="align-middle"><td class="ps-3"><div class="fw-bold small"><?=$j['nome']?></div><small class="text-muted small"><?=$j['telefone']?> | <?=($j['data_nascimento']?date('d/m/Y',strtotime($j['data_nascimento'])):$j['ano_nascimento'])?></small></td><td class="text-end pe-3 text-nowrap">
                    <?php if($pode_checkin): ?><button class="btn btn-link text-success p-0 me-2" onclick="checkinRapido(<?=$j['id']?>)"><i class="bi bi-person-check-fill fs-5"></i></button><?php endif; ?>
                    <button class="btn btn-link text-primary p-0 me-2" onclick="povoarEdicao(<?=htmlspecialchars(json_encode($j))?>)"><i class="bi bi-pencil-square fs-5"></i></button>
                    <form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="deletar_jovem"><input type="hidden" name="id_jovem" value="<?=$j['id']?>"><button type="submit" class="btn btn-link text-danger p-0" onclick="return confirm('Excluir?')"><i class="bi bi-trash fs-5"></i></button></form>
                </td></tr><?php endforeach; ?></tbody></table></div>
            <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?=($p_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&tab=jovens&f_jovem=<?=urlencode($f_j)?>"><?=$i?></a></li><?php endfor; ?></ul></nav>
        </div>

        <!-- 5. ATA -->
        <div class="tab-pane fade" id="tab-ata" role="tabpanel">
            <div class="card p-3 border-top border-5 border-dark shadow-sm text-center">
                <div class="row mb-3"><div class="col-6 border-end"><h6>Presentes</h6><h3 class="fw-bold text-primary"><?=$total_presentes_hoje?></h3></div><div class="col-6"><h6>Média Idade</h6><h3 class="fw-bold text-success"><?=$media_idade?> anos</h3></div></div>
                <form method="POST"><input type="hidden" name="form_acao" value="salvar_ata"><input type="hidden" name="aba_destino" value="ata"><textarea name="texto_ata" id="texto_ata"><?= $enc_ativo['ata'] ?? '' ?></textarea><button type="submit" class="btn btn-dark w-100 fw-bold my-3 py-2 shadow">SALVAR ATA</button><a href="gerar_encontro_pdf.php" target="_blank" class="btn btn-outline-danger w-100 fw-bold">EXPORTAR PDF</a></form>
            </div>
        </div>

        <!-- 6. RANKING -->
        <div class="tab-pane fade" id="tab-rank" role="tabpanel">
            <h6 class="fw-bold text-center mb-3">CLASSIFICAÇÃO GERAL</h6>
            <?php foreach($ranking_gincana as $i => $r): ?>
                <div class="card p-3 mb-2 border-start border-5 border-success d-flex flex-row justify-content-between align-items-center shadow-sm">
                    <div><span class="badge bg-success rounded-pill me-1"><?=($i+1)?>º</span> <span class="fw-bold text-uppercase small"><?= $r['nome_time'] ?></span></div>
                    <div class="h4 m-0 fw-bold text-danger"><?= gmdate("H:i:s", $r['tempo']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQr" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0"><div id="qrcode" class="d-flex justify-content-center mb-3"></div><button class="btn btn-secondary w-100 rounded-pill" data-bs-dismiss="modal">Fechar</button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    ClassicEditor.create(document.querySelector('#texto_ata')).catch(e => console.log(e));

    function maskData(i) {
        let v = i.value.replace(/\D/g,'');
        if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
        if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9);
        i.value = v;
        if(v.length == 10) document.getElementById('ano_s').value = v.split('/')[2];
    }

    function povoarEdicaoEncontro(e) {
        document.getElementById('id_encontro_edit').value = e.id;
        document.getElementById('enc_data').value = e.data_encontro;
        document.getElementById('enc_local').value = e.local_encontro;
        document.getElementById('enc_tema').value = e.tema;
        document.getElementById('titulo_form_encontro').innerText = "Editar Encontro: " + e.tema;
        document.getElementById('btn_save_encontro').innerText = "ATUALIZAR";
        document.getElementById('btn_save_encontro').classList.replace('btn-primary', 'btn-warning');
        document.getElementById('btn_cancel_enc').classList.remove('d-none');
        window.scrollTo(0,0);
    }

    function checkinRapido(jId) {
        const fd = new FormData(); fd.append('jovem_id', jId); fd.append('encontro_id', <?=$enc_id_ativo?>);
        fetch('acoes_presenca.php?salvar_manual=1', { method: 'POST', body: fd }).then(r => r.json()).then(data => { if(data.status === 'ok') location.reload(); });
    }

    function povoarEdicao(j) {
        document.getElementById('id_jovem_edit').value = j.id; document.getElementById('edit_nome').value = j.nome;
        if(j.data_nascimento) { let d = j.data_nascimento.split('-'); document.getElementById('edit_data').value = d[2]+'/'+d[1]+'/'+d[0]; }
        document.getElementById('ano_s').value = j.ano_nascimento; document.getElementById('edit_tel').value = j.telefone;
        document.getElementById('titulo_form_jovem').innerText = "Editar: " + j.nome;
        document.getElementById('btn_save_jovem').innerText = "ATUALIZAR";
        document.getElementById('btn_cancel_edit').classList.remove('d-none'); window.scrollTo(0,0);
    }

    function abrirQr() {
        const container = document.getElementById("qrcode"); container.innerHTML = '';
        new QRCode(container, { text: "https://jmmovimento.com.br/checkin.php?e=<?=$enc_id_ativo?>", width: 240, height: 240 });
        new bootstrap.Modal(document.getElementById('modalQr')).show();
    }

    function buscarChamada(q) {
        const res = document.getElementById('resultadoChamada'); if(q.length < 2) { res.innerHTML = ''; return; }
        fetch('acoes_presenca.php?buscar=' + q).then(r => r.json()).then(dados => {
            let h = ''; dados.forEach(d => { h += `<button type="button" onclick="checkinRapido(${d.id})" class="list-group-item small fw-bold text-uppercase">${d.nome}</button>`; });
            res.innerHTML = h;
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if(tab) { const btn = document.getElementById('tab-' + tab + '-btn'); if(btn) new bootstrap.Tab(btn).show(); }
        else { const activeId = localStorage.getItem('activeTabGincana'); if(activeId) { const btn = document.getElementById(activeId); if(btn) new bootstrap.Tab(btn).show(); } }
        document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(btn => { btn.addEventListener('shown.bs.tab', e => localStorage.setItem('activeTabGincana', e.target.id)); });
    });
</script>
</body>
</html>
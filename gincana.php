<?php
/**
 * JMM SYSTEM - MASTER v7.3
 * FOCO: RESTAURAÇÃO TOTAL (BOTÃO EXCLUIR + CADASTRO + FILTROS) + PDF PERSONALIZADO
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_nome = $_SESSION['user_nome'];

// --- FUNÇÃO AUXILIAR IDADE ---
function calcularIdade($data_nasc, $ano_nasc) {
    if ($data_nasc && $data_nasc != '0000-00-00') {
        $nascimento = new DateTime($data_nasc);
        $hoje = new DateTime();
        return $hoje->diff($nascimento)->y;
    }
    return $ano_nasc ? (date('Y') - $ano_nasc) : '??';
}

// --- 1. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$query_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
$enc_ativo = $query_ativo->fetch(PDO::FETCH_ASSOC);

$enc_id_ativo = $enc_ativo['id'] ?? 0;
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');
$nome_enc_atual = $enc_ativo['tema'] ?? 'Nenhum encontro ativo';
$data_enc_atual = $enc_ativo['data_encontro'] ?? date('Y-m-d');

// --- 2. LÓGICA DE ANIVERSARIANTES (BASE DO ÚLTIMO ENCONTRO PASSADO) ---
$st_ant = $pdo->prepare("SELECT data_encontro FROM encontros WHERE data_encontro < ? ORDER BY data_encontro DESC LIMIT 1");
$st_ant->execute([$data_enc_atual]);
$enc_anterior = $st_ant->fetch();

// Inicia no dia seguinte ao encontro anterior
$data_inicio_periodo = $enc_anterior ? date('Y-m-d', strtotime($enc_anterior['data_encontro'] . ' +1 day')) : date('Y-m-d', strtotime($data_enc_atual . ' -6 days'));

$sql_niver = "SELECT *, DATE_FORMAT(data_nascimento, '%d/%m/%Y') as data_completa,
              DATE_FORMAT(data_nascimento, '%m-%d') as mes_dia
              FROM jovens 
              WHERE DATE_FORMAT(data_nascimento, '%m-%d') 
              BETWEEN DATE_FORMAT(?, '%m-%d') AND DATE_FORMAT(?, '%m-%d')
              ORDER BY mes_dia ASC, nome ASC";
$st_niver = $pdo->prepare($sql_niver);
$st_niver->execute([$data_inicio_periodo, $data_enc_atual]);
$aniversariantes_periodo = $st_niver->fetchAll(PDO::FETCH_ASSOC);
$total_niver_periodo = count($aniversariantes_periodo);

// Variáveis para o PDF
$periodo_pdf_nome = date('d-m', strtotime($data_inicio_periodo)) . "_a_" . date('d-m', strtotime($data_enc_atual));
$periodo_texto_pdf = date('d/m/Y', strtotime($data_inicio_periodo)) . " até " . date('d/m/Y', strtotime($data_enc_atual));

// --- 3. TOTAIS E ESTATÍSTICAS ---
$total_cad = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn() ?: 0;
$total_pres = 0;
$lista_p = [];
$stats_presenca_hoje = ['masc' => 0, 'fem' => 0];

if ($enc_id_ativo) {
    $st_lp = $pdo->prepare("SELECT j.nome, j.sexo, j.data_nascimento, j.ano_nascimento, j.telefone FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = ? ORDER BY j.nome ASC");
    $st_lp->execute([$enc_id_ativo]);
    $lista_p = $st_lp->fetchAll(PDO::FETCH_ASSOC);
    $total_pres = count($lista_p);
    foreach ($lista_p as $p_item) {
        if ($p_item['sexo'] == 'Masculino') $stats_presenca_hoje['masc']++;
        if ($p_item['sexo'] == 'Feminino') $stats_presenca_hoje['fem']++;
    }
}

// --- 4. FILTROS E PAGINAÇÃO ---
$itens_p = 15;
$p_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$off = ($p_atual - 1) * $itens_p;
$f_j = isset($_GET['f_jovem']) ? trim($_GET['f_jovem']) : '';
$f_tipo = isset($_GET['f_tipo']) ? $_GET['f_tipo'] : 'todos';

$where = "WHERE 1=1";
$params = [];
if ($f_j) { 
    if ($f_tipo == 'nome') { $where .= " AND nome LIKE ?"; $params[] = "%$f_j%"; }
    elseif ($f_tipo == 'telefone') { $f_j_limpo = preg_replace('/\D/', '', $f_j); $where .= " AND telefone LIKE ?"; $params[] = "%$f_j_limpo%"; }
    elseif ($f_tipo == 'data') {
        $partes = explode('/', $f_j);
        if (count($partes) >= 2) { $where .= " AND DATE_FORMAT(data_nascimento, '%d/%m') = ?"; $params[] = str_pad($partes[0], 2, "0", STR_PAD_LEFT)."/".str_pad($partes[1], 2, "0", STR_PAD_LEFT); }
    } else {
        $f_j_limpo = preg_replace('/\D/', '', $f_j);
        $where .= " AND (nome LIKE ? OR telefone LIKE ? OR DATE_FORMAT(data_nascimento, '%d/%m') LIKE ?)";
        $params[] = "%$f_j%"; $params[] = "%$f_j_limpo%"; $params[] = "%$f_j%";
    }
}
$st_ct = $pdo->prepare("SELECT COUNT(*) FROM jovens $where");
$st_ct->execute($params);
$total_encontrados = $st_ct->fetchColumn();
$total_paginas = ceil($total_encontrados / $itens_p);
$registro_nao_localizado = ($f_j != '' && $total_encontrados == 0);

// --- 5. PROCESSAMENTO DE AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_acao'])) {
    $acao = $_POST['form_acao'];
    if ($acao == 'toggle_presenca') {
        $st = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
        $st->execute([$_POST['j_id'], $_POST['e_id']]);
        if ($st->fetch()) { $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ? AND encontro_id = ?")->execute([$_POST['j_id'], $_POST['e_id']]); }
        else { $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")->execute([$_POST['j_id'], $_POST['e_id']]); }
        exit;
    }
    if ($acao == 'deletar_jovem') {
        $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ?")->execute([$_POST['id_jovem']]);
        $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]);
        header("Location: gincana.php?tab=jovens&delok=1"); exit;
    }
    if ($acao == 'novo_jovem') {
        $id_j = $_POST['id_jovem_edit'] ?? null;
        $fone = preg_replace('/\D/', '', $_POST['telefone']);
        $data_n = !empty($_POST['data_nascimento']) ? implode('-', array_reverse(explode('/', $_POST['data_nascimento']))) : null;
        $val = [trim($_POST['nome']), $fone, $_POST['sexo'], (int)$_POST['ano_nascimento'], $data_n, str_replace('@','',$_POST['instagram'] ?? ''), $_POST['irmaos'] ?? 'Não'];
        if ($id_j) { $val[] = $id_j; $pdo->prepare("UPDATE jovens SET nome=?, telefone=?, sexo=?, ano_nascimento=?, data_nascimento=?, instagram=?, irmaos=? WHERE id=?")->execute($val); }
        else { $pdo->prepare("INSERT INTO jovens (nome, telefone, sexo, ano_nascimento, data_nascimento, instagram, irmaos) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute($val); }
        header("Location: gincana.php?tab=jovens&saveok=" . urlencode($_POST['nome'])); exit;
    }
}

// Consultas Grid
$jovens_chamada = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$query_u = $pdo->query("SELECT id, data_encontro FROM encontros ORDER BY data_encontro DESC LIMIT 4");
$ultimos_enc = array_reverse($query_u->fetchAll(PDO::FETCH_ASSOC));
$st_ex = $pdo->prepare("SELECT j.*, (SELECT id FROM presencas WHERE jovem_id = j.id AND encontro_id = ?) as presenca_hoje FROM jovens j $where ORDER BY j.nome ASC LIMIT $off, $itens_p");
$st_ex->execute(array_merge([$enc_id_ativo], $params));
$jovens_exibicao = $st_ex->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>JMM Master - Jovens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 70px; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.65rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #0d6efd !important; color: #fff !important; }
        .label-small { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; }
        .bg-niver-card { background: #fff3cd; border-left: 5px solid #ffc107 !important; cursor: pointer; }
        .filter-section { background: #fff; border-radius: 15px; padding: 15px; border-left: 5px solid #212529; }
        .niver-hoje { border: 2px solid #ffc107 !important; background: #fffdf5; position: relative; }
        .badge-hoje { position: absolute; top: -10px; right: 10px; background: #ffc107; color: #000; font-size: 10px; padding: 3px 8px; border-radius: 10px; font-weight: 800; }
        .btn-checkin-lista { font-size: 1.6rem; border: none; background: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-grid-3x3-gap-fill fs-5"></i></a>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
        <small class="fw-bold text-muted"><?= mb_strtoupper($usuario_nome) ?></small>
    </div>
</nav>

<div class="container">
    <!-- QUADROS TOPO -->
    <div class="row g-2 mb-3 text-center">
        <div class="col-4"><div class="card p-2 border-start border-5 border-primary"><small class="label-small">Cadastrados</small><h4 class="fw-bold mb-0 text-primary"><?=$total_cad?></h4></div></div>
        <div class="col-4" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modalPresentes"><div class="card p-2 border-start border-5 border-success"><small class="label-small">Presentes Hoje</small><h4 class="fw-bold mb-0 text-success"><?=$total_pres?></h4></div></div>
        <div class="col-4" data-bs-toggle="modal" data-bs-target="#modalNiverEncontro"><div class="card p-2 bg-niver-card"><small class="label-small text-warning">Aniversariantes Semana Anterior</small><h4 class="fw-bold mb-0 text-dark"><?=$total_niver_periodo?></h4></div></div>
    </div>
    
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded shadow-sm" id="pills-tab">
        <li class="nav-item"><button class="nav-link active" id="tab-chamada-btn" data-bs-toggle="pill" data-bs-target="#tab-chamada">CHAMADA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-jovens-btn" data-bs-toggle="pill" data-bs-target="#tab-jovens">JOVENS</button></li>
    </ul>

    <div class="tab-content">
        <!-- ABA CHAMADA -->
        <div class="tab-pane fade show active" id="tab-chamada">
            <div class="card p-3 border-top border-5 border-success">
                <input type="text" id="filtroC" class="form-control mb-3" placeholder="Busca rápida..." onkeyup="filtrarC()">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle" id="tabC" style="font-size: 0.75rem;">
                        <thead class="table-dark">
                            <tr><th>Jovem</th><?php foreach($ultimos_enc as $u): ?><th class="text-center"><?=date('d/m', strtotime($u['data_encontro']))?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach($jovens_chamada as $j): $idade_j = calcularIdade($j['data_nascimento'], $j['ano_nascimento']); ?>
                            <tr data-search="<?=mb_strtolower($j['nome'])?>">
                                <td class="fw-bold text-uppercase"><?=$j['nome']?></td>
                                <?php foreach($ultimos_enc as $u): 
                                    $st = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id=? AND encontro_id=?"); $st->execute([$j['id'], $u['id']]);
                                    $has = $st->fetch(); $ativo = ($u['id'] == $enc_id_ativo);
                                ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link p-0" <?= $ativo ? "onclick=\"handleCheckin({$j['id']}, {$u['id']}, '{$j['nome']}', '{$idade_j}')\"" : "" ?>>
                                        <i id="icon-<?=$j['id']?>" class="bi <?= $has ? 'bi-check-circle-fill text-success' : 'bi-circle text-light' ?> fs-5"></i>
                                    </button>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ABA JOVENS -->
        <div class="tab-pane fade" id="tab-jovens">
            <!-- FILTRO -->
            <div class="filter-section mb-3 shadow-sm">
                <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-funnel-fill me-1"></i> FILTRAR JOVENS</h6>
                <form method="GET">
                    <input type="hidden" name="tab" value="jovens">
                    <div class="row g-2">
                        <div class="col-5"><select name="f_tipo" id="f_tipo" class="form-select shadow-sm" onchange="ajustarBusca()"><option value="todos" <?=$f_tipo=='todos'?'selected':''?>>Todos</option><option value="nome" <?=$f_tipo=='nome'?'selected':''?>>Nome</option><option value="telefone" <?=$f_tipo=='telefone'?'selected':''?>>Telefone</option><option value="data" <?=$f_tipo=='data'?'selected':''?>>Nasc (DD/MM)</option></select></div>
                        <div class="col-5"><input type="text" name="f_jovem" id="f_jovem" class="form-control shadow-sm" value="<?=htmlspecialchars($f_j)?>" onkeyup="aplicarMascaraBusca(this)"></div>
                        <div class="col-2"><button type="submit" class="btn btn-dark w-100 shadow-sm"><i class="bi bi-search"></i></button></div>
                    </div>
                </form>
            </div>

            <!-- FORMULÁRIO -->
            <div class="card p-3 border-top border-5 border-info shadow-sm mb-4">
                <h6 class="fw-bold mb-3" id="t_j">Cadastro / Edição</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_jovem"><input type="hidden" name="id_jovem_edit" id="id_j_e">
                    <div class="row g-2">
                        <div class="col-8"><label class="label-small">Nome Completo</label><input type="text" name="nome" id="j_n" class="form-control text-uppercase shadow-sm" required></div>
                        <div class="col-4"><label class="label-small">Irmãos?</label><select name="irmaos" id="j_ir" class="form-select shadow-sm"><option value="Não">Não</option><option value="Sim">Sim</option></select></div>
                        <div class="col-7 mt-2"><label class="label-small">Instagram</label><input type="text" name="instagram" id="j_i" class="form-control shadow-sm" placeholder="@"></div>
                        <div class="col-5 mt-2"><label class="label-small">WhatsApp</label><input type="text" name="telefone" id="j_t" class="form-control shadow-sm" onkeyup="maskFone(this)" placeholder="(99) 99999-9999"></div>
                        <div class="col-4 mt-2"><label class="label-small">Sexo</label><select name="sexo" id="j_s" class="form-select shadow-sm" required><option value="">Selecione...</option><option value="Masculino">Masculino</option><option value="Feminino">Feminino</option></select></div>
                        <div class="col-5 mt-2"><label class="label-small">Nasc (DD/MM/AAAA)</label><input type="text" name="data_nascimento" id="j_d" class="form-control shadow-sm" onkeyup="maskData(this)" maxlength="10"></div>
                        <div class="col-3 mt-2"><label class="label-small">Ano</label><input type="number" name="ano_nascimento" id="j_a" class="form-control shadow-sm"></div>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold text-white mt-3 shadow">SALVAR REGISTRO</button>
                    <button type="button" id="btn_canc" class="btn btn-light border w-100 mt-2 d-none" onclick="location.reload()">CANCELAR</button>
                </form>
            </div>

            <!-- GRID JOVENS CADASTRADOS -->
            <h6 class="fw-bold mb-2 ps-1"><i class="bi bi-people-fill text-primary"></i> Jovens Cadastrados</h6>
            <div class="table-responsive">
                <table class="table table-sm bg-white border align-middle shadow-sm">
                    <tbody>
                        <?php foreach($jovens_exibicao as $jv): $id_list = calcularIdade($jv['data_nascimento'], $jv['ano_nascimento']); ?>
                        <tr>
                            <td class="ps-3 py-2">
                                <div class="fw-bold text-uppercase small"><?=$jv['nome']?></div>
                                <small class="text-muted" style="font-size: 0.68rem;">
                                    <?=$jv['telefone']?> / <?=($jv['data_nascimento'] ? date('d/m/Y', strtotime($jv['data_nascimento'])) : 'N/D')?> / <?=$jv['sexo']?> / <?=$id_list?> ANOS
                                </small>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php if($pode_checkin): ?>
                                    <button type="button" class="btn-checkin-lista" onclick="handleCheckin(<?=$jv['id']?>, <?=$enc_id_ativo?>, '<?=$jv['nome']?>', '<?=$id_list?>')">
                                        <i id="icon-grid-<?=$jv['id']?>" class="bi <?= $jv['presenca_hoje'] ? 'bi-person-check-fill text-success' : 'bi-person-check text-muted' ?>"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-link text-primary p-0 mx-2" onclick='povJ(<?=json_encode($jv)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                <button class="btn btn-link text-danger p-0" onclick="solicitarExclusao(<?=$jv['id']?>, '<?=$jv['nome']?>')"><i class="bi bi-trash fs-5"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav><ul class="pagination pagination-sm justify-content-center"><?php for($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?=($p_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&tab=jovens&f_jovem=<?=urlencode($f_j)?>&f_tipo=<?=$f_tipo?>"><?=$i?></a></li><?php endfor; ?></ul></nav>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO EXCLUSÃO -->
<div class="modal fade" id="modalConfirmDel" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-4 border-0 shadow-lg rounded-4">
    <i class="bi bi-exclamation-triangle text-danger display-1 mb-2"></i>
    <h4 class="fw-bold">Tem certeza?</h4>
    <p class="text-muted">Deseja excluir permanentemente o jovem:<br><b id="nomeDel" class="text-dark"></b>?</p>
    <form method="POST">
        <input type="hidden" name="form_acao" value="deletar_jovem"><input type="hidden" name="id_jovem" id="idDel">
        <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-light border w-100 rounded-pill fw-bold" data-bs-dismiss="modal">CANCELAR</button>
            <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold shadow">SIM, EXCLUIR</button>
        </div>
    </form>
</div></div></div>

<!-- MODAL ANIVERSARIANTES -->
<div class="modal fade" id="modalNiverEncontro" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg rounded-4">
    <div class="modal-header border-0 bg-warning text-dark"><h6 class="modal-title fw-bold"><i class="bi bi-cake2-fill"></i> ANIVERSARIANTES DO ENCONTRO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3"><small class="text-muted fw-bold">Período: <?=$periodo_texto_pdf?></small><button onclick="exportarNiversPDF()" class="btn btn-danger btn-sm rounded-pill fw-bold">PDF</button></div>
        <?php foreach($aniversariantes_periodo as $ap): 
            $is_hoje = (date('m-d', strtotime($ap['data_nascimento'])) == date('m-d', strtotime($data_enc_atual)));
            $id_n = (date('Y') - ($ap['ano_nascimento'] ?: date('Y', strtotime($ap['data_nascimento']))));
        ?>
            <div class="card p-3 mb-2 border-0 shadow-sm <?= $is_hoje ? 'niver-hoje' : 'bg-light' ?>">
                <?php if($is_hoje): ?><span class="badge-hoje">ANIVERSÁRIO NO DIA DO ENCONTRO!</span><?php endif; ?>
                <div class="fw-bold text-uppercase small"><?=$ap['nome']?></div>
                <small class="text-muted">Nasc: <?=$ap['data_completa']?> | Contato: <?=$ap['telefone']?></small>
                <div class="text-danger fw-bold small mt-1">NOVA IDADE: <?=$id_n?> ANOS</div>
            </div>
        <?php endforeach; ?>
    </div>
</div></div></div>

<!-- MODAL PRESENTES -->
<div class="modal fade" id="modalPresentes" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg rounded-4">
    <div class="modal-header border-0 bg-success text-white"><h6 class="modal-title fw-bold">PRESENTES HOJE (<?=$total_pres?>)</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-uppercase">
        <div class="row g-2 mb-3 text-center"><div class="col-6"><div class="badge bg-primary w-100 p-2 shadow-sm">MASC: <?=$stats_presenca_hoje['masc']?></div></div><div class="col-6"><div class="badge bg-danger w-100 p-2 shadow-sm">FEM: <?=$stats_presenca_hoje['fem']?></div></div></div>
        <?php foreach($lista_p as $lp): $id_p = calcularIdade($lp['data_nascimento'], $lp['ano_nascimento']); $cor = ($lp['sexo'] == 'Masculino') ? 'text-primary' : 'text-danger'; ?>
            <div class="d-flex justify-content-between border-bottom py-2"><span class="fw-bold small"><?=$lp['nome']?></span><small class="text-muted"><i class="bi bi-person-fill <?=$cor?>"></i> <b><?=$id_p?> ANOS</b></small></div>
        <?php endforeach; ?>
    </div>
</div></div></div>

<!-- POP-UPS -->
<div class="modal fade" id="modalS" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg rounded-4"><i class="bi bi-check-circle-fill text-success fs-1"></i><h4 class="fw-bold mt-2">Check-IN Realizado!</h4><hr><p class="mb-0">Jovem: <br><b id="nomeS" class="text-primary text-uppercase"></b> - <b id="idadeS" class="text-dark"></b> anos</p></div></div></div>
<div class="modal fade" id="modalNotFound" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg rounded-4"><i class="bi bi-search text-warning display-1 mb-2"></i><h4 class="fw-bold">Nenhum registro!</h4><p class="text-muted">Busca: <b class="text-dark">"<?=htmlspecialchars($f_j)?>"</b> não localizado.</p><button class="btn btn-dark w-100 rounded-pill fw-bold" data-bs-dismiss="modal">VOLTAR</button></div></div></div>
<div class="modal fade" id="modalDelOk" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg rounded-4"><i class="bi bi-trash-fill text-danger fs-1"></i><h4 class="fw-bold mt-2">Excluído!</h4><p class="text-muted mb-0">Registro removido.</p><button class="btn btn-dark w-100 rounded-pill mt-3 shadow" data-bs-dismiss="modal">OK</button></div></div></div>
<div class="modal fade" id="modalSave" tabindex="-1"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg rounded-4"><i class="bi bi-cloud-check-fill text-info fs-1"></i><h4 class="fw-bold mt-2">Cadastro Salvo!</h4><p id="nomeSave" class="fw-bold text-dark text-uppercase"></p><button class="btn btn-dark w-100 rounded-pill shadow" data-bs-dismiss="modal">OK</button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function maskFone(i) { let v = i.value.replace(/\D/g,''); v = v.replace(/^(\d{2})(\d)/g,"($1) $2"); v = v.replace(/(\d)(\d{4})$/,"$1-$2"); i.value = v; }
    function maskData(i) { let v = i.value.replace(/\D/g,''); if(v.length>2) v=v.substring(0,2)+'/'+v.substring(2); if(v.length>5) v=v.substring(0,5)+'/'+v.substring(5,9); i.value=v; if(v.length==10 && document.getElementById('j_a')) document.getElementById('j_a').value=v.split('/')[2]; }
    function ajustarBusca() { document.getElementById('f_jovem').value = ''; const t = document.getElementById('f_tipo').value; document.getElementById('f_jovem').placeholder = (t==='data'?'Ex: 25/04':'Busca...'); }
    function aplicarMascaraBusca(i) { const t = document.getElementById('f_tipo').value; if(t==='telefone') maskFone(i); if(t==='data'){ let v=i.value.replace(/\D/g,''); if(v.length>2) v=v.substring(0,2)+'/'+v.substring(2,4); i.value=v; } }
    function filtrarC() { let b = document.getElementById("filtroC").value.toLowerCase(); let l = document.getElementById("tabC").getElementsByTagName("tbody")[0].getElementsByTagName("tr"); for(let i=0; i<l.length; i++){ let d = l[i].getAttribute("data-search") || ""; l[i].style.display = (d.includes(b)) ? "" : "none"; } }

    function handleCheckin(jId, eId, nome, idade) {
        const fd = new FormData(); fd.append('form_acao', 'toggle_presenca'); fd.append('j_id', jId); fd.append('e_id', eId);
        fetch('gincana.php', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(() => {
            document.getElementById('nomeS').innerText = nome; document.getElementById('idadeS').innerText = idade;
            new bootstrap.Modal(document.getElementById('modalS')).show();
            setTimeout(() => { location.reload(); }, 1600);
        });
    }

    function povJ(j) { 
        document.getElementById('id_j_e').value = j.id; document.getElementById('j_n').value = j.nome; 
        document.getElementById('j_ir').value = j.irmaos || 'Não'; document.getElementById('j_t').value = j.telefone; 
        maskFone(document.getElementById('j_t')); document.getElementById('j_s').value = j.sexo || ''; 
        document.getElementById('j_a').value = j.ano_nascimento; if(j.data_nascimento && j.data_nascimento != '0000-00-00'){ let d = j.data_nascimento.split('-'); document.getElementById('j_d').value = d[2]+'/'+d[1]+'/'+d[0]; }
        document.getElementById('btn_canc').classList.remove('d-none'); document.getElementById('t_j').innerText = "Editar: " + j.nome;
        new bootstrap.Tab(document.getElementById('tab-jovens-btn')).show(); window.scrollTo(0,0);
    }

    function solicitarExclusao(id, nome) { document.getElementById('idDel').value = id; document.getElementById('nomeDel').innerText = nome; new bootstrap.Modal(document.getElementById('modalConfirmDel')).show(); }

    function exportarNiversPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const encHoje = "<?=date('m-d', strtotime($data_enc_atual))?>";
        doc.setFontSize(18); doc.setTextColor(200, 0, 0); doc.text("ANIVERSARIANTES DA SEMANA - JMM", 14, 15);
        doc.setFontSize(10); doc.setTextColor(50);
        doc.text("Tema: <?=$nome_enc_atual?>", 14, 22); doc.text("Período: <?=$periodo_texto_pdf?>", 14, 28);
        const rows = [];
        <?php foreach($aniversariantes_periodo as $ap): $id_n = (date('Y') - ($ap['ano_nascimento'] ?: date('Y', strtotime($ap['data_nascimento'])))); ?>
            rows.push({ nome: "<?=$ap['nome']?>", nasc: "<?=$ap['data_completa']?>", tel: "<?=$ap['telefone']?>", idade: "<?=$id_n?> ANOS", isHoje: ("<?=$ap['mes_dia']?>" === encHoje) });
        <?php endforeach; ?>
        const tableBody = rows.map(r => [ r.isHoje ? r.nome + "\nANIVERSÁRIO NO DIA DO ENCONTRO!" : r.nome, r.nasc, r.tel, r.idade ]);
        doc.autoTable({ head: [['NOME', 'NASCIMENTO', 'TELEFONE', 'IDADE NOVA']], body: tableBody, startY: 35, headStyles: { fillColor: [255, 193, 7], textColor: [0,0,0] }, didParseCell: function(data) { if (data.section === 'body' && rows[data.row.index].isHoje) { data.cell.styles.fillColor = [255, 250, 200]; data.cell.styles.fontStyle = 'bold'; } } });
        doc.save(`Aniversariantes_Semana-<?=$periodo_pdf_nome?>-JMM.pdf`);
    }

    document.addEventListener("DOMContentLoaded", function() {
        const p = new URLSearchParams(window.location.search);
        if(p.get('tab')) { const b = document.getElementById('tab-' + p.get('tab') + '-btn'); if(b) new bootstrap.Tab(b).show(); }
        if(p.get('saveok')) { document.getElementById('nomeSave').innerText = p.get('saveok'); new bootstrap.Modal(document.getElementById('modalSave')).show(); }
        if(p.get('delok')) { new bootstrap.Modal(document.getElementById('modalDelOk')).show(); }
        <?php if($registro_nao_localizado): ?> new bootstrap.Modal(document.getElementById('modalNotFound')).show(); <?php endif; ?>
    });
</script>
</body>
</html>
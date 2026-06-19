<?php
/**
 * JMM SYSTEM - MASTER v5.5
 * FOCO: MÁSCARAS DINÂMICAS NA BUSCA E PADRONIZAÇÃO DE DADOS
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_nome = $_SESSION['user_nome'];

// --- 1. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$query_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
$enc_ativo = $query_ativo->fetch(PDO::FETCH_ASSOC);

$enc_id_ativo = $enc_ativo['id'] ?? 0;
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');
$nome_enc_atual = $enc_ativo['tema'] ?? 'Nenhum encontro ativo';

// --- 2. TOTAIS E ESTATÍSTICAS ---
$total_cad = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn() ?: 0;
$total_pres = 0;
if ($enc_id_ativo) {
    $st_p = $pdo->prepare("SELECT COUNT(*) FROM presencas WHERE encontro_id = ?");
    $st_p->execute([$enc_id_ativo]);
    $total_pres = $st_p->fetchColumn();
}

// --- 3. LISTA DETALHADA DE PRESENTES (MODAL) ---
$lista_p = [];
$stats_presenca = ['masc' => 0, 'fem' => 0];
if ($enc_id_ativo) {
    $st_lp = $pdo->prepare("SELECT j.nome, j.sexo, j.data_nascimento, j.ano_nascimento 
                            FROM jovens j 
                            JOIN presencas p ON j.id = p.jovem_id 
                            WHERE p.encontro_id = ? 
                            ORDER BY j.nome ASC");
    $st_lp->execute([$enc_id_ativo]);
    $lista_p = $st_lp->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lista_p as $p_item) {
        if ($p_item['sexo'] == 'Masculino') $stats_presenca['masc']++;
        if ($p_item['sexo'] == 'Feminino') $stats_presenca['fem']++;
    }
}

// --- 4. GRID DE CHAMADA ---
$query_u = $pdo->query("SELECT id, data_encontro FROM encontros ORDER BY data_encontro DESC LIMIT 4");
$ultimos_enc = array_reverse($query_u->fetchAll(PDO::FETCH_ASSOC));

// --- 5. PAGINAÇÃO E FILTROS AVANÇADOS (PHP) ---
$itens_p = 15;
$p_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$off = ($p_atual - 1) * $itens_p;

$f_j = isset($_GET['f_jovem']) ? trim($_GET['f_jovem']) : '';
$f_tipo = isset($_GET['f_tipo']) ? $_GET['f_tipo'] : 'todos';

$where = "WHERE 1=1";
$params = [];

if ($f_j) { 
    if ($f_tipo == 'nome') {
        $where .= " AND nome LIKE ?";
        $params[] = "%$f_j%";
    } elseif ($f_tipo == 'telefone') {
        $f_j_limpo = preg_replace('/\D/', '', $f_j);
        $where .= " AND telefone LIKE ?";
        $params[] = "%$f_j_limpo%";
    } elseif ($f_tipo == 'data') {
        $data_busca = (strpos($f_j, '/') !== false) ? implode('-', array_reverse(explode('/', $f_j))) : $f_j;
        $where .= " AND data_nascimento = ?";
        $params[] = $data_busca;
    } else {
        $f_j_limpo = preg_replace('/\D/', '', $f_j);
        $where .= " AND (nome LIKE ? OR telefone LIKE ? OR instagram LIKE ? OR data_nascimento LIKE ?)";
        $params[] = "%$f_j%"; $params[] = "%$f_j_limpo%"; $params[] = "%$f_j%"; $params[] = "%$f_j%";
    }
}

$st_ct = $pdo->prepare("SELECT COUNT(*) FROM jovens $where");
$st_ct->execute($params);
$total_paginas = ceil($st_ct->fetchColumn() / $itens_p);

// --- 6. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_acao'])) {
    $acao = $_POST['form_acao'];
    $aba = $_POST['aba_destino'] ?? 'chamada';

    if ($acao == 'toggle_presenca' && $enc_id_ativo) {
        $j_id = $_POST['j_id']; $e_id = $_POST['e_id'];
        if ($e_id == $enc_id_ativo && $pode_checkin) {
            $st_ch = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
            $st_ch->execute([$j_id, $e_id]);
            $res_ch = $st_ch->fetch();
            if ($res_ch) {
                $pdo->prepare("DELETE FROM presencas WHERE id = ?")->execute([$res_ch['id']]);
                $confirm_nome = "";
            } else {
                $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")->execute([$j_id, $e_id]);
                $st_n = $pdo->prepare("SELECT nome FROM jovens WHERE id = ?"); $st_n->execute([$j_id]);
                $confirm_nome = $st_n->fetchColumn();
            }
            $url_ok = ($confirm_nome) ? "&checkok=" . urlencode($confirm_nome) : "";
            header("Location: gincana.php?tab=$aba&p=$p_atual&f_jovem=$f_j&f_tipo=$f_tipo" . $url_ok); exit;
        }
    }

    if ($acao == 'novo_jovem') {
        $id_j = $_POST['id_jovem_edit'] ?? null;
        $nome = trim($_POST['nome']);
        $fone_limpo = preg_replace('/\D/', '', $_POST['telefone']);
        $irmaos = $_POST['irmaos'] ?? 'Não';
        $sexo = $_POST['sexo'];
        $ano = (int)$_POST['ano_nascimento'];
        $insta = str_replace('@', '', trim($_POST['instagram'] ?? ''));
        $data_n = !empty($_POST['data_nascimento']) ? implode('-', array_reverse(explode('/', $_POST['data_nascimento']))) : null;

        if ($irmaos == 'Não' && !empty($fone_limpo)) {
            $sql_v = "SELECT nome FROM jovens WHERE telefone = ? AND id != ?";
            $st_v = $pdo->prepare($sql_v); $st_v->execute([$fone_limpo, (int)$id_j]);
            if ($st_v->fetch()) { header("Location: gincana.php?tab=jovens&erro_telefone=" . urlencode("Telefone já existe!")); exit; }
        }

        if ($id_j) {
            $pdo->prepare("UPDATE jovens SET nome=?, telefone=?, sexo=?, ano_nascimento=?, data_nascimento=?, instagram=?, irmaos=? WHERE id=?")->execute([$nome, $fone_limpo, $sexo, $ano, $data_n, $insta, $irmaos, $id_j]);
        } else {
            $pdo->prepare("INSERT INTO jovens (nome, telefone, sexo, ano_nascimento, data_nascimento, instagram, irmaos) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$nome, $fone_limpo, $sexo, $ano, $data_n, $insta, $irmaos]);
        }
    }

    // Outras ações de Encontro e Ata omitidas por brevidade mas presentes na lógica...
    if ($acao == 'ativar_encontro') { $pdo->exec("UPDATE encontros SET ativo = 0"); $pdo->prepare("UPDATE encontros SET ativo = 1 WHERE id = ?")->execute([$_POST['e_id']]); }
    if ($acao == 'status_encontro') { $pdo->prepare("UPDATE encontros SET status = ? WHERE id = ?")->execute([$_POST['novo_status'], $_POST['e_id']]); }
    if ($acao == 'novo_encontro') {
        if (!empty($_POST['id_encontro_edit'])) { $pdo->prepare("UPDATE encontros SET data_encontro=?, local_encontro=?, tema=? WHERE id=?")->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e'], $_POST['id_encontro_edit']]); }
        else { $pdo->prepare("INSERT INTO encontros (data_encontro, local_encontro, tema, status, ativo) VALUES (?, ?, ?, 'aberto', 0)")->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e']]); }
    }
    if ($acao == 'salvar_ata') { $pdo->prepare("UPDATE encontros SET ata = ? WHERE id = ?")->execute([$_POST['texto_ata'], $enc_id_ativo]); }
    if ($acao == 'deletar_jovem') { $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ?")->execute([$_POST['id_jovem']]); $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]); }

    header("Location: gincana.php?tab=$aba&p=$p_atual&f_jovem=$f_j&f_tipo=$f_tipo"); exit;
}

// --- 7. CONSULTAS GRID ---
$jovens_chamada = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$encontros_grid = $pdo->query("SELECT * FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);
$st_grid = $pdo->prepare("SELECT j.*, (SELECT id FROM presencas WHERE jovem_id = j.id AND encontro_id = ?) as presenca_hoje FROM jovens j $where ORDER BY j.nome ASC LIMIT $off, $itens_p");
$st_grid->execute(array_merge([$enc_id_ativo], $params));
$jovens_exibicao = $st_grid->fetchAll(PDO::FETCH_ASSOC);
$niver = $pdo->query("SELECT *, DATE_FORMAT(data_nascimento, '%d/%m') as dia_mes FROM jovens WHERE DATE_FORMAT(data_nascimento, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d') ORDER BY dia_mes ASC")->fetchAll(PDO::FETCH_ASSOC);

function calcularIdade($data_nasc, $ano_nasc) {
    if ($data_nasc && $data_nasc != '0000-00-00') {
        $nascimento = new DateTime($data_nasc);
        $hoje = new DateTime();
        return $hoje->diff($nascimento)->y;
    }
    return $ano_nasc ? (date('Y') - $ano_nasc) : '??';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>JMM Master v5.5</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 70px; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.65rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #0d6efd !important; color: #fff !important; }
        .label-small { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; }
        .btn-checkin-lista { font-size: 1.6rem; border: none; background: none; }
        .modal-detalhe-presenca { font-size: 0.85rem; border-bottom: 1px solid #eee; padding: 8px 0; }
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

    <div class="row g-2 mb-3">
        <div class="col-6"><div class="card p-2 text-center border-start border-5 border-primary"><small class="label-small">Cadastrados</small><h4 class="fw-bold mb-0 text-primary"><?=$total_cad?></h4></div></div>
        <div class="col-6" data-bs-toggle="modal" data-bs-target="#modalP"><div class="card p-2 text-center border-start border-5 border-success" style="cursor:pointer;"><small class="label-small">Presentes Hoje</small><h4 class="fw-bold mb-0 text-success"><?=$total_pres?></h4></div></div>
    </div>
    
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded shadow-sm" id="pills-tab">
        <li class="nav-item"><button class="nav-link active" id="tab-chamada-btn" data-bs-toggle="pill" data-bs-target="#tab-chamada">CHAMADA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-enc-btn" data-bs-toggle="pill" data-bs-target="#tab-enc">ENCONTROS</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-jovens-btn" data-bs-toggle="pill" data-bs-target="#tab-jovens">JOVENS</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-ata-btn" data-bs-toggle="pill" data-bs-target="#tab-ata">ATA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-niver-btn" data-bs-toggle="pill" data-bs-target="#tab-niver" style="background:#fff3cd">NIVER 🎂</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- ABA: CHAMADA -->
        <div class="tab-pane fade show active" id="tab-chamada">
            <div class="card p-3 border-top border-5 border-success">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0 text-uppercase"><?= $pode_checkin ? 'Check-in: '.$nome_enc_atual : 'Check-in Bloqueado' ?></h6>
                    <?php if($pode_checkin): ?><button class="btn btn-dark btn-sm rounded-pill" onclick="abrirQr()"><i class="bi bi-qr-code-scan"></i> QR</button><?php endif; ?>
                </div>
                <input type="text" id="filtroC" class="form-control mb-3 shadow-sm" placeholder="Busca rápida por nome..." onkeyup="filtrarC()">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-hover align-middle" id="tabC" style="font-size: 0.75rem;">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th class="ps-2">Jovem</th>
                                <?php foreach($ultimos_enc as $u): ?>
                                    <th class="text-center"><?=date('d/m', strtotime($u['data_encontro']))?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jovens_chamada as $j): ?>
                            <tr data-search="<?=mb_strtolower($j['nome'].' '.$j['telefone'].' '.$j['instagram'])?>">
                                <td class="ps-2 py-2 text-uppercase fw-bold"><?=$j['nome']?></td>
                                <?php foreach($ultimos_enc as $u): 
                                    $st_pr = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id=? AND encontro_id=?"); $st_pr->execute([$j['id'], $u['id']]);
                                ?>
                                <td class="text-center">
                                    <form method="POST">
                                        <input type="hidden" name="form_acao" value="toggle_presenca"><input type="hidden" name="aba_destino" value="chamada">
                                        <input type="hidden" name="j_id" value="<?=$j['id']?>"><input type="hidden" name="e_id" value="<?=$u['id']?>">
                                        <button type="submit" class="btn btn-link p-0 border-0" <?= ($u['id'] != $enc_id_ativo || !$pode_checkin) ? 'disabled' : '' ?>>
                                            <i class="bi <?= $st_pr->fetch() ? 'bi-check-circle-fill text-success' : 'bi-circle text-light' ?> fs-5"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ABA: ENCONTROS -->
        <div class="tab-pane fade" id="tab-enc">
            <div class="card p-3 border-top border-5 border-primary">
                <h6 class="fw-bold mb-3" id="t_enc">Novo Encontro</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_encontro"><input type="hidden" name="id_encontro_edit" id="id_e_e">
                    <div class="row g-2">
                        <div class="col-4"><label class="label-small">Data</label><input type="date" name="data_e" id="e_d" class="form-control" required></div>
                        <div class="col-8"><label class="label-small">Tema</label><input type="text" name="tema_e" id="e_t" class="form-control" required></div>
                        <div class="col-12 mt-2"><label class="label-small">Local</label><input type="text" name="local_e" id="e_l" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold mt-3 shadow">SALVAR</button>
                </form>
            </div>
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table table-sm align-middle" style="font-size:0.8rem;">
                    <tbody>
                        <?php foreach($encontros_grid as $eg): ?>
                        <tr>
                            <td class="ps-3 py-2"><b><?=date('d/m/y', strtotime($eg['data_encontro']))?></b> - <?=$eg['tema']?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-link text-primary p-0 me-2" onclick='povE(<?=json_encode($eg)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                <form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="ativar_encontro"><input type="hidden" name="e_id" value="<?=$eg['id']?>"><button type="submit" class="btn btn-link <?=($eg['ativo']?'text-success':'text-muted')?> p-0 me-2"><i class="bi bi-lightning-fill fs-5"></i></button></form>
                                <form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="status_encontro"><input type="hidden" name="e_id" value="<?=$eg['id']?>"><input type="hidden" name="novo_status" value="<?=($eg['status']=='aberto'?'finalizado':'aberto')?>"><button type="submit" class="btn btn-link <?=($eg['status']=='aberto'?'text-danger':'text-success')?> p-0"><i class="bi <?=($eg['status']=='aberto'?'bi-lock-fill':'bi-unlock-fill')?> fs-5"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ABA: JOVENS -->
        <div class="tab-pane fade" id="tab-jovens">
            <!-- CADASTRO -->
            <div class="card p-3 border-top border-5 border-info shadow-sm">
                <h6 class="fw-bold mb-3" id="t_j">Cadastro Jovem</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_jovem"><input type="hidden" name="id_jovem_edit" id="id_j_e">
                    <div class="row g-2">
                        <div class="col-8"><label class="label-small">Nome Completo</label><input type="text" name="nome" id="j_n" class="form-control text-uppercase" required></div>
                        <div class="col-4"><label class="label-small">Irmãos?</label><select name="irmaos" id="j_ir" class="form-select fw-bold shadow-sm"><option value="Não">Não</option><option value="Sim">Sim</option></select></div>
                        <div class="col-7 mt-2"><label class="label-small">Instagram</label><input type="text" name="instagram" id="j_i" class="form-control"></div>
                        <div class="col-5 mt-2"><label class="label-small">WhatsApp</label><input type="text" name="telefone" id="j_t" class="form-control" onkeyup="maskFone(this)" placeholder="(99) 99999-9999"></div>
                        <div class="col-4 mt-2"><label class="label-small">Sexo</label><select name="sexo" id="j_s" class="form-select" required><option value="Masculino">Masculino</option><option value="Feminino">Feminino</option></select></div>
                        <div class="col-5 mt-2"><label class="label-small">Nasc</label><input type="text" name="data_nascimento" id="j_d" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)" maxlength="10"></div>
                        <div class="col-3 mt-2"><label class="label-small">Ano</label><input type="number" name="ano_nascimento" id="j_a" class="form-control"></div>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold text-white mt-3 shadow">SALVAR CADASTRO</button>
                    <button type="button" id="btn_canc" class="btn btn-light w-100 mt-2 border d-none" onclick="location.reload()">CANCELAR EDIÇÃO</button>
                </form>
            </div>

            <!-- BUSCA AVANÇADA COM MÁSCARA DINÂMICA -->
            <div class="card p-3 shadow-sm border-0">
                <form method="GET" class="row g-2">
                    <input type="hidden" name="tab" value="jovens">
                    <div class="col-5">
                        <select name="f_tipo" id="f_tipo" class="form-select shadow-sm" style="font-size:0.8rem;" onchange="ajustarBusca()">
                            <option value="todos" <?=$f_tipo=='todos'?'selected':''?>>Todos</option>
                            <option value="nome" <?=$f_tipo=='nome'?'selected':''?>>Por Nome</option>
                            <option value="telefone" <?=$f_tipo=='telefone'?'selected':''?>>Por Tel</option>
                            <option value="data" <?=$f_tipo=='data'?'selected':''?>>Por Data</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <input type="text" name="f_jovem" id="f_jovem" class="form-control shadow-sm" placeholder="Buscar..." value="<?=htmlspecialchars($f_j)?>" style="font-size:0.8rem;" onkeyup="aplicarMascaraBusca(this)">
                    </div>
                    <div class="col-2"><button type="submit" class="btn btn-dark w-100 shadow-sm"><i class="bi bi-search"></i></button></div>
                </form>
            </div>

            <!-- LISTAGEM -->
            <div class="table-responsive">
                <table class="table table-sm bg-white border align-middle shadow-sm" style="font-size: 0.8rem;">
                    <tbody>
                        <?php foreach($jovens_exibicao as $jv): ?>
                        <tr>
                            <td class="ps-3 py-2 text-uppercase fw-bold"><?=$jv['nome']?> <?= (($jv['irmaos'] ?? 'Não') == 'Sim' ? '👥' : '') ?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php if($pode_checkin): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="form_acao" value="toggle_presenca"><input type="hidden" name="aba_destino" value="jovens">
                                        <input type="hidden" name="j_id" value="<?=$jv['id']?>"><input type="hidden" name="e_id" value="<?=$enc_id_ativo?>">
                                        <button type="submit" class="btn-checkin-lista"><i class="bi <?= $jv['presenca_hoje'] ? 'bi-person-check-fill text-success' : 'bi-person-check text-muted' ?>"></i></button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn btn-link text-primary p-0 mx-2" onclick='povJ(<?=json_encode($jv)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Excluir?')"><input type="hidden" name="form_acao" value="deletar_jovem"><input type="hidden" name="id_jovem" value="<?=$jv['id']?>"><button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav><ul class="pagination pagination-sm justify-content-center"><?php for($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?=($p_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&tab=jovens&f_jovem=<?=urlencode($f_j)?>&f_tipo=<?=$f_tipo?>"><?=$i?></a></li><?php endfor; ?></ul></nav>
        </div>

        <!-- ABA: ATA -->
        <div class="tab-pane fade" id="tab-ata">
            <div class="card p-3 border-top border-5 border-dark">
                <form method="POST">
                    <input type="hidden" name="form_acao" value="salvar_ata">
                    <textarea name="texto_ata" id="texto_ata"><?= $enc_ativo['ata'] ?? '' ?></textarea>
                    <button type="submit" class="btn btn-dark w-100 mt-3 fw-bold">SALVAR ATA</button>
                </form>
            </div>
        </div>

        <!-- ABA: NIVER -->
        <div class="tab-pane fade" id="tab-niver">
            <div class="card p-3 border-top border-5 border-warning">
                <?php foreach($niver as $ni): ?>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="fw-bold small text-uppercase"><?=$ni['nome']?> (<?=$ni['dia_mes']?>)</span>
                        <a href="https://wa.me/55<?=preg_replace('/\D/','',$ni['telefone'])?>" target="_blank" class="btn btn-success btn-sm rounded-pill"><i class="bi bi-whatsapp"></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PRESENTES -->
<div class="modal fade" id="modalP"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
    <div class="modal-header border-0 bg-light">
        <h6 class="modal-title fw-bold">PRESENTES HOJE (<?=$total_pres?>)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-6"><div class="badge bg-primary w-100 p-2">MASC: <?=$stats_presenca['masc']?></div></div>
            <div class="col-6"><div class="badge bg-danger w-100 p-2">FEM: <?=$stats_presenca['fem']?></div></div>
        </div>
        <?php foreach($lista_p as $lp): 
            $idade = calcularIdade($lp['data_nascimento'], $lp['ano_nascimento']);
            $cor = ($lp['sexo'] == 'Masculino') ? 'text-primary' : 'text-danger';
        ?>
            <div class="modal-detalhe-presenca d-flex justify-content-between">
                <span class="fw-bold text-uppercase"><?=$lp['nome']?></span>
                <small class="text-muted"><i class="bi bi-person-fill <?=$cor?>"></i> <?=$lp['sexo'][0]?> | <b><?=$idade?> anos</b></small>
            </div>
        <?php endforeach; ?>
    </div>
</div></div></div>

<div class="modal fade" id="mQr"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4"><div id="qr" class="d-flex justify-content-center mb-3"></div><button class="btn btn-secondary w-100 rounded-pill" data-bs-dismiss="modal">Fechar</button></div></div></div>
<div class="modal fade" id="modalS"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4 border-0 shadow-lg"><i class="bi bi-check-circle-fill text-success fs-1"></i><h4 class="fw-bold mt-2">Check-IN Confirmado!</h4><p id="nomeS" class="fw-bold text-primary text-uppercase"></p><button class="btn btn-dark w-100 rounded-pill" data-bs-dismiss="modal">OK</button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if(document.querySelector('#texto_ata')) ClassicEditor.create(document.querySelector('#texto_ata'));

    // MÁSCARAS
    function maskFone(i) { let v = i.value.replace(/\D/g,''); v = v.replace(/^(\d{2})(\d)/g,"($1) $2"); v = v.replace(/(\d)(\d{4})$/,"$1-$2"); i.value = v; }
    function maskData(i) { let v = i.value.replace(/\D/g,''); if(v.length>2) v=v.substring(0,2)+'/'+v.substring(2); if(v.length>5) v=v.substring(0,5)+'/'+v.substring(5,9); i.value=v; if(v.length==10) document.getElementById('j_a').value=v.split('/')[2]; }

    // MÁSCARA DINÂMICA NA BUSCA
    function ajustarBusca() {
        const input = document.getElementById('f_jovem');
        input.value = ''; // Limpa ao trocar tipo
    }

    function aplicarMascaraBusca(input) {
        const tipo = document.getElementById('f_tipo').value;
        if (tipo === 'telefone') maskFone(input);
        if (tipo === 'data') maskData(input);
    }

    function filtrarC() { let b=document.getElementById("filtroC").value.toLowerCase(); let l=document.getElementById("tabC").getElementsByTagName("tbody")[0].getElementsByTagName("tr"); for(let i=0;i<l.length;i++){ let d=l[i].getAttribute("data-search")||""; l[i].style.display=(d.includes(b))?"":"none"; } }
    function povE(e) { document.getElementById('id_e_e').value=e.id; document.getElementById('e_d').value=e.data_encontro; document.getElementById('e_t').value=e.tema; document.getElementById('e_l').value=e.local_encontro; document.getElementById('t_enc').innerText="Editar Encontro"; window.scrollTo(0,0); }
    
    function povJ(j) { 
        document.getElementById('id_j_e').value = j.id; 
        document.getElementById('j_n').value = j.nome; 
        document.getElementById('j_ir').value = j.irmaos || 'Não'; 
        document.getElementById('j_t').value = j.telefone; 
        maskFone(document.getElementById('j_t')); 
        document.getElementById('j_i').value = j.instagram || ''; 
        document.getElementById('j_s').value = j.sexo || ''; 
        document.getElementById('j_a').value = j.ano_nascimento; 
        if(j.data_nascimento && j.data_nascimento!='0000-00-00'){ 
            let d = j.data_nascimento.split('-'); 
            document.getElementById('j_d').value = d[2]+'/'+d[1]+'/'+d[0]; 
        } else { document.getElementById('j_d').value = ''; }
        document.getElementById('btn_canc').classList.remove('d-none'); 
        document.getElementById('t_j').innerText="Editar: " + j.nome; 
        new bootstrap.Tab(document.getElementById('tab-jovens-btn')).show(); 
        window.scrollTo(0,0); 
    }
    
    function abrirQr() { document.getElementById("qr").innerHTML=''; new QRCode(document.getElementById("qr"), { text: "https://jmmovimento.com.br/checkin.php?e=<?=$enc_id_ativo?>", width: 200, height: 200 }); new bootstrap.Modal(document.getElementById('mQr')).show(); }
    document.addEventListener("DOMContentLoaded", function() {
        const p=new URLSearchParams(window.location.search);
        if(p.get('tab')) { const b=document.getElementById('tab-' + p.get('tab') + '-btn'); if(b) new bootstrap.Tab(b).show(); }
        if(p.get('checkok')) { document.getElementById('nomeS').innerText=p.get('checkok'); new bootstrap.Modal(document.getElementById('modalS')).show(); }
    });
</script>
</body>
</html>
<?php
/**
 * JMM SYSTEM - GESTÃO INTEGRADA MASTER FINAL
 */
require_once 'config.php';

// 1. SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_nome = $_SESSION['user_nome'];

// --- 2. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$enc_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id_ativo = $enc_ativo['id'] ?? 0;
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');

// --- 3. TOTAIS PARA OS QUADRINHOS TOPO ---
$total_cadastrados = $pdo->query("SELECT COUNT(*) FROM jovens")->fetchColumn() ?: 0;
$total_presentes_hoje = $enc_id_ativo ? $pdo->query("SELECT COUNT(*) FROM presencas WHERE encontro_id = $enc_id_ativo")->fetchColumn() : 0;

// --- 4. ESTATÍSTICAS DE GÊNERO (NOVO) ---
$count_m = $pdo->query("SELECT COUNT(*) FROM jovens WHERE sexo = 'Masculino'")->fetchColumn() ?: 0;
$count_f = $pdo->query("SELECT COUNT(*) FROM jovens WHERE sexo = 'Feminino'")->fetchColumn() ?: 0;
$total_genero = $count_m + $count_f;
$perc_m = ($total_genero > 0) ? round(($count_m / $total_genero) * 100, 1) : 0;
$perc_f = ($total_genero > 0) ? round(($count_f / $total_genero) * 100, 1) : 0;

// --- 5. LISTA DE NOMES PARA O POP-UP DE PRESENTES ---
$lista_presentes_hoje = [];
if ($enc_id_ativo) {
    $stmt_p = $pdo->prepare("SELECT j.nome FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = ? ORDER BY j.nome ASC");
    $stmt_p->execute([$enc_id_ativo]);
    $lista_presentes_hoje = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
}

// --- 6. ÚLTIMOS 4 ENCONTROS PARA A GRADE ---
$ultimos_enc = $pdo->query("SELECT id, data_encontro FROM encontros ORDER BY data_encontro DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
$ultimos_enc = array_reverse($ultimos_enc);

// --- 7. PAGINAÇÃO E FILTRO (ABA JOVENS) ---
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
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM jovens $where_j");
$stmt_count->execute($params_j);
$total_paginas = ceil($stmt_count->fetchColumn() / $itens_por_pag);

// --- 8. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';
    $aba_retorno = $_POST['aba_destino'] ?? 'chamada';

    if ($acao == 'toggle_presenca') {
        $j_id = $_POST['j_id'];
        $e_id = $_POST['e_id'];
        if ($e_id == $enc_id_ativo && $pode_checkin) {
            $check = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
            $check->execute([$j_id, $e_id]);
            $res = $check->fetch();
            $confirma_nome = "";
            if ($res) {
                $pdo->prepare("DELETE FROM presencas WHERE id = ?")->execute([$res['id']]);
            } else {
                $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")->execute([$j_id, $e_id]);
                $sj = $pdo->prepare("SELECT nome FROM jovens WHERE id = ?"); $sj->execute([$j_id]);
                $confirma_nome = $sj->fetchColumn();
            }
            $msg_param = ($confirma_nome) ? "&checkok=" . urlencode($confirma_nome) : "";
            header("Location: gincana.php?tab=$aba_retorno&p=$p_atual&f_jovem=$f_j" . $msg_param); exit;
        } else {
            header("Location: gincana.php?tab=$aba_retorno&p=$p_atual&f_jovem=$f_j&erro_check=1"); exit;
        }
    }

    if ($acao == 'deletar_jovem') {
        $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ?")->execute([$_POST['id_jovem']]);
        $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]);
    }

    if ($acao == 'novo_encontro') {
        if (!empty($_POST['id_encontro_edit'])) {
            $pdo->prepare("UPDATE encontros SET data_encontro=?, local_encontro=?, tema=? WHERE id=?")->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e'], $_POST['id_encontro_edit']]);
        } else {
            $pdo->prepare("INSERT INTO encontros (data_encontro, local_encontro, tema, status, ativo) VALUES (?, ?, ?, 'aberto', 0)")->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e']]);
        }
    }
    
    if ($acao == 'ativar_encontro') {
        $pdo->exec("UPDATE encontros SET ativo = 0");
        $pdo->prepare("UPDATE encontros SET ativo = 1 WHERE id = ?")->execute([$_POST['e_id']]);
    }

    if ($acao == 'status_encontro') {
        $pdo->prepare("UPDATE encontros SET status = ? WHERE id = ?")->execute([$_POST['novo_status'], $_POST['e_id']]);
    }

    if ($acao == 'novo_jovem') {
        $data_sql = null;
        if(!empty($_POST['data_nascimento'])){
            $pt = explode('/', $_POST['data_nascimento']);
            if(count($pt) == 3) $data_sql = $pt[2].'-'.$pt[1].'-'.$pt[0];
        }
        $params = [trim($_POST['nome']), trim($_POST['telefone']), $_POST['sexo'], (int)$_POST['ano_nascimento'], $data_sql];
        if (!empty($_POST['id_jovem_edit'])) {
            $params[] = $_POST['id_jovem_edit'];
            $pdo->prepare("UPDATE jovens SET nome=?, telefone=?, sexo=?, ano_nascimento=?, data_nascimento=? WHERE id=?")->execute($params);
        } else {
            $pdo->prepare("INSERT INTO jovens (nome, telefone, sexo, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?, ?)")->execute($params);
        }
    }

    if ($acao == 'salvar_ata') {
        $pdo->prepare("UPDATE encontros SET ata = ? WHERE id = ?")->execute([$_POST['texto_ata'], $enc_id_ativo]);
    }

    header("Location: gincana.php?tab=$aba_retorno&p=$p_atual&f_jovem=$f_j"); exit;
}

// --- 9. CONSULTAS FINAIS ---
$todos_jovens = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$encontros_all = $pdo->query("SELECT * FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);
$sql_jovens_check = "SELECT j.*, (SELECT id FROM presencas WHERE jovem_id = j.id AND encontro_id = ?) as presenca_hoje FROM jovens j $where_j ORDER BY j.nome ASC LIMIT $offset, $itens_por_pag";
$stmt_j = $pdo->prepare($sql_jovens_check); $stmt_j->execute(array_merge([$enc_id_ativo], $params_j));
$jovens_list = $stmt_j->fetchAll(PDO::FETCH_ASSOC);

$inicio_semana = date('Y-m-d', strtotime('monday this week')); $fim_semana = date('Y-m-d', strtotime('sunday this week'));
$aniversariantes = $pdo->query("SELECT *, (YEAR(CURDATE()) - YEAR(data_nascimento)) as idade_nova, DATE_FORMAT(data_nascimento, '%d/%m') as dia_mes FROM jovens WHERE DATE_FORMAT(data_nascimento, '%m-%d') BETWEEN DATE_FORMAT('$inicio_semana', '%m-%d') AND DATE_FORMAT('$fim_semana', '%m-%d') ORDER BY dia_mes ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        .nav-pills .nav-link.active { background-color: #0d6efd !important; color: #fff !important; }
        .badge-count { font-size: 1.5rem; font-weight: 800; display: block; }
        .small-label { font-size: 0.65rem; font-weight: 800; color: #888; text-transform: uppercase; }
        .stat-sexo { font-size: 0.75rem; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-grid-3x3-gap-fill fs-5"></i></a>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
        <small class="fw-bold text-muted"><?= mb_strtoupper($user_nome) ?></small>
    </div>
</nav>

<div class="container">

    <div class="row g-2 mb-3">
        <div class="col-6"><div class="card p-2 text-center border-start border-5 border-primary"><small class="fw-bold text-muted small">CADASTRADOS</small><span class="badge-count text-primary"><?=$total_cadastrados?></span></div></div>
        <div class="col-6"><div class="card p-2 text-center border-start border-5 border-success" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalPresentes"><small class="fw-bold text-muted small">PRESENTES <i class="bi bi-info-circle"></i></small><span class="badge-count text-success"><?=$total_presentes_hoje?></span></div></div>
    </div>
    
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded shadow-sm" id="pills-tab">
        <li class="nav-item"><button class="nav-link active" id="tab-chamada-btn" data-bs-toggle="pill" data-bs-target="#tab-chamada">CHAMADA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-enc-btn" data-bs-toggle="pill" data-bs-target="#tab-enc">ENCONTROS</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-jovens-btn" data-bs-toggle="pill" data-bs-target="#tab-jovens">JOVENS</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-ata-btn" data-bs-toggle="pill" data-bs-target="#tab-ata">ATA</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-niver-btn" data-bs-toggle="pill" data-bs-target="#tab-niver" style="background:#fff3cd">NIVER 🎂</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- 1. CHAMADA -->
        <div class="tab-pane fade show active" id="tab-chamada">
            <div class="card p-3 border-top border-5 border-success">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0 text-uppercase"><?= $pode_checkin ? 'Check-in: '.$enc_ativo['tema'] : 'Check-in Bloqueado' ?></h6>
                    <?php if($pode_checkin): ?><button class="btn btn-dark btn-sm rounded-pill" onclick="abrirQr()"><i class="bi bi-qr-code-scan"></i> QR</button><?php endif; ?>
                </div>
                <input type="text" id="filtroC" class="form-control form-control-sm mb-3" placeholder="Filtrar por nome..." onkeyup="filtrarC()">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-sm table-hover align-middle" id="tabC" style="font-size: 0.75rem;">
                        <thead class="table-dark sticky-top">
                            <tr><th class="ps-2">Jovem</th><?php foreach($ultimos_enc as $u): ?><th class="text-center"><?=date('d/m', strtotime($u['data_encontro']))?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach($todos_jovens as $j): ?>
                            <tr>
                                <td class="ps-2 fw-bold text-uppercase text-truncate" style="max-width: 140px;"><?=$j['nome']?></td>
                                <?php foreach($ultimos_enc as $u): 
                                    $pres = $pdo->query("SELECT id FROM presencas WHERE jovem_id={$j['id']} AND encontro_id={$u['id']}")->fetch();
                                    $coluna_ativa = ($u['id'] == $enc_id_ativo);
                                ?>
                                <td class="text-center">
                                    <form method="POST">
                                        <input type="hidden" name="form_acao" value="toggle_presenca"><input type="hidden" name="aba_destino" value="chamada">
                                        <input type="hidden" name="j_id" value="<?=$j['id']?>"><input type="hidden" name="e_id" value="<?=$u['id']?>">
                                        <button type="submit" class="btn btn-link p-0 border-0" <?= (!$coluna_ativa || !$pode_checkin) ? 'disabled' : '' ?>>
                                            <i class="bi <?= $pres ? 'bi-check-circle-fill text-success' : 'bi-circle text-light' ?> fs-5"></i>
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

        <!-- 2. ENCONTROS -->
        <div class="tab-pane fade" id="tab-enc">
            <div class="card p-3 border-top border-5 border-primary">
                <h6 class="fw-bold mb-3" id="t_enc">Novo Evento</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_encontro"><input type="hidden" name="aba_destino" value="enc"><input type="hidden" name="id_encontro_edit" id="id_e_e">
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="small-label">Data</label><input type="date" name="data_e" id="e_d" class="form-control" required></div>
                        <div class="col-8"><label class="small-label">Tema</label><input type="text" name="tema_e" id="e_t" class="form-control" required></div>
                        <div class="col-12"><label class="small-label">Local</label><input type="text" name="local_e" id="e_l" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow">SALVAR</button>
                </form>
            </div>
            <div class="table-responsive bg-white rounded">
                <table class="table table-sm align-middle mb-0" style="font-size: 0.8rem;">
                    <tbody>
                        <?php foreach($encontros_all as $e): ?>
                        <tr>
                            <td class="ps-3 py-2"><b><?=date('d/m/Y', strtotime($e['data_encontro']))?></b> - <?=$e['tema']?></td>
                            <td class="text-center"><?=($e['ativo']?'<span class="badge bg-success">ATIVO</span>':'')?> <?=($e['status']=='finalizado'?'<span class="badge bg-danger">BLOQUEADO</span>':'')?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-link text-primary p-0 me-2" onclick='povE(<?=json_encode($e)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                <?php if(!$e['ativo']): ?><form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="ativar_encontro"><input type="hidden" name="aba_destino" value="enc"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-link text-warning p-0 me-2"><i class="bi bi-lightning-fill fs-5"></i></button></form><?php endif; ?>
                                <form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="status_encontro"><input type="hidden" name="aba_destino" value="enc"><input type="hidden" name="e_id" value="<?=$e['id']?>"><input type="hidden" name="novo_status" value="<?=($e['status']=='aberto'?'finalizado':'aberto')?>"><button type="submit" class="btn btn-link <?=($e['status']=='aberto'?'text-danger':'text-success')?> p-0"><i class="bi <?=($e['status']=='aberto'?'bi-lock-fill':'bi-unlock-fill')?> fs-5"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. JOVENS -->
        <div class="tab-pane fade" id="tab-jovens">
            <!-- Estatísticas de Gênero -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="alert alert-primary p-2 mb-0 text-center stat-sexo">
                        <i class="bi bi-gender-male"></i> MASCULINO: <?=$count_m?> (<?=$perc_m?>%)
                    </div>
                </div>
                <div class="col-6">
                    <div class="alert alert-danger p-2 mb-0 text-center stat-sexo" style="background-color: #f8d7da; color: #842029;">
                        <i class="bi bi-gender-female"></i> FEMININO: <?=$count_f?> (<?=$perc_f?>%)
                    </div>
                </div>
            </div>

            <div class="card p-3 border-top border-5 border-info">
                <h6 class="fw-bold mb-3" id="t_j">Cadastro Jovem</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_jovem"><input type="hidden" name="aba_destino" value="jovens"><input type="hidden" name="id_jovem_edit" id="id_j_e">
                    <input type="text" name="nome" id="j_n" class="form-control mb-2 shadow-sm text-uppercase" placeholder="Nome Completo" required>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small-label">Sexo</label>
                            <select name="sexo" id="j_s" class="form-select shadow-sm" required>
                                <option value="">Selecione...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Feminino">Feminino</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small-label">Ano Nasc.</label>
                            <input type="number" name="ano_nascimento" id="j_a" class="form-control shadow-sm" placeholder="Ex: 2010">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><label class="small-label">Data Nascimento</label><input type="text" name="data_nascimento" id="j_d" class="form-control shadow-sm" placeholder="DD/MM/AAAA" onkeyup="maskData(this)" maxlength="10"></div>
                        <div class="col-5"><label class="small-label">WhatsApp</label><input type="text" name="telefone" id="j_t" class="form-control shadow-sm" placeholder="DDD+Nº"></div>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold text-white shadow mt-2">SALVAR JOVEM</button>
                </form>
            </div>
            <form method="GET" class="d-flex gap-2 mb-2"><input type="hidden" name="tab" value="jovens"><input type="text" name="f_jovem" class="form-control form-control-sm" placeholder="Buscar..." value="<?=htmlspecialchars($f_j)?>"><button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-search"></i></button></form>
            <div class="table-responsive"><table class="table table-sm bg-white border align-middle"><tbody><?php foreach($jovens_list as $j): ?>
                <tr>
                    <td class="ps-3 py-2"><div class="fw-bold small text-uppercase"><?=$j['nome']?></div><small class="text-muted small"><?=$j['sexo']?> | <?=($j['data_nascimento']?date('d/m/Y',strtotime($j['data_nascimento'])):$j['ano_nascimento'])?></small></td>
                    <td class="text-end pe-3 text-nowrap">
                        <?php if($pode_checkin): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="form_acao" value="toggle_presenca"><input type="hidden" name="aba_destino" value="jovens">
                            <input type="hidden" name="j_id" value="<?=$j['id']?>"><input type="hidden" name="e_id" value="<?=$enc_id_ativo?>">
                            <button type="submit" class="btn btn-link p-0 me-2"><i class="bi <?= $j['presenca_hoje'] ? 'bi-person-check-fill text-success' : 'bi-person-check text-muted' ?> fs-4"></i></button>
                        </form>
                        <?php endif; ?>
                        <button class="btn btn-link text-primary p-0 me-2" onclick='povJ(<?=json_encode($j)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Deseja excluir permanentemente?')">
                            <input type="hidden" name="form_acao" value="deletar_jovem"><input type="hidden" name="aba_destino" value="jovens">
                            <input type="hidden" name="id_jovem" value="<?=$j['id']?>">
                            <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash fs-5"></i></button>
                        </form>
                    </td>
                </tr><?php endforeach; ?></tbody></table></div>
            <nav><ul class="pagination pagination-sm justify-content-center"><?php for($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?=($p_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&tab=jovens&f_jovem=<?=urlencode($f_j)?>"><?=$i?></a></li><?php endfor; ?></ul></nav>
        </div>

        <!-- 4. ATA -->
        <div class="tab-pane fade" id="tab-ata">
            <div class="card p-3 border-top border-5 border-dark">
                <h6 class="fw-bold text-center mb-3 text-uppercase">Ata do Encontro</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="salvar_ata"><input type="hidden" name="aba_destino" value="ata">
                    <textarea name="texto_ata" id="texto_ata"><?= $enc_ativo['ata'] ?? '' ?></textarea>
                    <button type="submit" class="btn btn-dark w-100 fw-bold mt-3 shadow">SALVAR ATA</button>
                </form>
            </div>
        </div>

        <!-- 5. NIVER -->
        <div class="tab-pane fade" id="tab-niver">
            <div class="card p-3 border-top border-5 border-warning shadow-sm">
                <h6 class="fw-bold text-center mb-3 text-uppercase">Aniversariantes da Semana</h6>
                <?php foreach($aniversariantes as $nv): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div><span class="fw-bold small text-uppercase"><?=$nv['nome']?></span><br><small class="text-muted small"><?=$nv['dia_mes']?> - <?=$nv['idade_nova']?> anos</small></div>
                    <a href="https://wa.me/55<?=preg_replace('/\D/','',$nv['telefone'])?>" target="_blank" class="btn btn-success btn-sm rounded-pill"><i class="bi bi-whatsapp"></i> Parabéns</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<div class="modal fade" id="mQr"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4"><div id="qrcode" class="d-flex justify-content-center mb-3"></div><button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div></div></div>

<div class="modal fade" id="modalPresentes" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header border-0 pb-0"><h6 class="modal-title fw-bold">PRESENTES (<?=$total_presentes_hoje?>)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php if(count($lista_presentes_hoje) > 0): ?><ul class="list-group list-group-flush"><?php foreach($lista_presentes_hoje as $p): ?><li class="list-group-item small text-uppercase fw-bold border-0 py-1"><i class="bi bi-check-circle-fill text-success me-2"></i> <?= $p['nome'] ?></li><?php endforeach; ?></ul><?php else: ?><div class="text-center py-3 text-muted small">Nenhuma presença registrada ainda.</div><?php endif; ?></div><div class="modal-footer border-0"><button type="button" class="btn btn-light w-100 fw-bold border" data-bs-dismiss="modal">FECHAR</button></div></div></div></div>

<div class="modal fade" id="modalSucesso" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-4 border-0 shadow-lg"><div class="modal-body"><i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i><h4 class="fw-bold mt-3">Confirmado!</h4><p class="mb-4">Jovem <span id="nomeJovemSucesso" class="text-primary fw-bold"></span>, feito o Check-In com sucesso!</p><button type="button" class="btn btn-dark w-100 rounded-pill" data-bs-dismiss="modal">OK</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if(document.querySelector('#texto_ata')) ClassicEditor.create(document.querySelector('#texto_ata'));

    function maskData(i) {
        let v = i.value.replace(/\D/g,'');
        if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
        if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9);
        i.value = v;
        if(v.length == 10) document.getElementById('j_a').value = v.split('/')[2];
    }

    function filtrarC() {
        let val = document.getElementById("filtroC").value.toUpperCase();
        let trs = document.getElementById("tabC").getElementsByTagName("tr");
        for (let i = 1; i < trs.length; i++) {
            let td = trs[i].getElementsByTagName("td")[0];
            trs[i].style.display = (td && td.innerText.toUpperCase().indexOf(val) > -1) ? "" : "none";
        }
    }

    function povE(e) {
        document.getElementById('id_e_e').value = e.id;
        document.getElementById('e_d').value = e.data_encontro;
        document.getElementById('e_l').value = e.local_encontro;
        document.getElementById('e_t').value = e.tema;
        document.getElementById('t_enc').innerText = "Editar Encontro";
        window.scrollTo(0,0);
    }

    function povJ(j) {
        document.getElementById('id_j_e').value = j.id;
        document.getElementById('j_n').value = j.nome;
        document.getElementById('j_s').value = j.sexo;
        if(j.data_nascimento) { let d = j.data_nascimento.split('-'); document.getElementById('j_d').value = d[2]+'/'+d[1]+'/'+d[0]; }
        document.getElementById('j_a').value = j.ano_nascimento;
        document.getElementById('j_t').value = j.telefone;
        document.getElementById('t_j').innerText = "Editar Jovem: " + j.nome;
        window.scrollTo(0,0);
    }

    function abrirQr() {
        const container = document.getElementById("qrcode"); container.innerHTML = '';
        new QRCode(container, { text: "https://jmmovimento.com.br/checkin.php?e=<?=$enc_id_ativo?>", width: 240, height: 240 });
        new bootstrap.Modal(document.getElementById('mQr')).show();
    }

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if(tab) { const btn = document.getElementById('tab-' + tab + '-btn'); if(btn) new bootstrap.Tab(btn).show(); }

        const checkOk = urlParams.get('checkok');
        if(checkOk) {
            document.getElementById('nomeJovemSucesso').innerText = checkOk;
            var mS = new bootstrap.Modal(document.getElementById('modalSucesso')); mS.show();
        }

        if(urlParams.get('erro_check')) { alert("Ação bloqueada! O encontro não está ativo ou o status está como encerrado."); }
    });
</script>
</body>
</html>
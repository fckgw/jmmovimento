<?php
/**
 * JMM SYSTEM - GESTÃO DE JOVENS + OFFLINE + CHECK-IN RÁPIDO
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- CONFIGURAÇÃO DO ENCONTRO ATIVO ---
$enc_ativo = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id_ativo = $enc_ativo['id'] ?? 0;
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');

// --- ESTATÍSTICAS DE GÊNERO ---
$stats_gen = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN sexo = 'Masculino' THEN 1 ELSE 0 END) as masc,
    SUM(CASE WHEN sexo = 'Feminino' THEN 1 ELSE 0 END) as fem
    FROM jovens")->fetch();

$total_geral = $stats_gen['total'] ?: 0;
$perc_m = ($total_geral > 0) ? round(($stats_gen['masc'] / $total_geral) * 100, 1) : 0;
$perc_f = ($total_geral > 0) ? round(($stats_gen['fem'] / $total_geral) * 100, 1) : 0;

// --- PAGINAÇÃO E FILTRO ---
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

// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_acao'])) {
    $acao = $_POST['form_acao'];

    // Salvar Jovem
    if ($acao == 'novo_jovem') {
        $data_nasc = !empty($_POST['data_nascimento']) ? implode('-', array_reverse(explode('/', $_POST['data_nascimento']))) : null;
        if (!empty($_POST['id_jovem_edit'])) {
            $pdo->prepare("UPDATE jovens SET nome=?, telefone=?, sexo=?, ano_nascimento=?, data_nascimento=? WHERE id=?")
                ->execute([trim($_POST['nome']), trim($_POST['telefone']), $_POST['sexo'], $_POST['ano_nascimento'], $data_nasc, $_POST['id_jovem_edit']]);
        } else {
            $pdo->prepare("INSERT INTO jovens (nome, telefone, sexo, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?, ?)")
                ->execute([trim($_POST['nome']), trim($_POST['telefone']), $_POST['sexo'], $_POST['ano_nascimento'], $data_nasc]);
        }
    }

    // Toggle Check-in Direto
    if ($acao == 'toggle_presenca') {
        $j_id = $_POST['j_id'];
        $e_id = $_POST['e_id'];
        if ($pode_checkin) {
            $check = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
            $check->execute([$j_id, $e_id]);
            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ? AND encontro_id = ?")->execute([$j_id, $e_id]);
            } else {
                $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")->execute([$j_id, $e_id]);
                $sj = $pdo->prepare("SELECT nome FROM jovens WHERE id = ?"); $sj->execute([$j_id]);
                $confirm_nome = $sj->fetchColumn();
                header("Location: jovens.php?p=$p_atual&f_jovem=$f_j&checkok=".urlencode($confirm_nome)); exit;
            }
        }
    }

    // Deletar
    if ($acao == 'deletar_jovem') {
        $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ?")->execute([$_POST['id_jovem']]);
        $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$_POST['id_jovem']]);
    }

    header("Location: jovens.php?p=$p_atual&f_jovem=$f_j");
    exit;
}

// --- CONSULTA DA LISTA ---
$sql_list = "SELECT j.*, (SELECT id FROM presencas WHERE jovem_id = j.id AND encontro_id = ?) as presenca_hoje 
             FROM jovens j $where_j ORDER BY j.nome ASC LIMIT $offset, $itens_por_pag";
$stmt_l = $pdo->prepare($sql_list);
$stmt_l->execute(array_merge([$enc_id_ativo], $params_j));
$jovens = $stmt_l->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="manifest.json">
    <title>Gestão de Jovens - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .stat-box { font-size: 0.8rem; font-weight: bold; border-radius: 10px; border: none; }
        .btn-checkin-lista { font-size: 1.5rem; border: none; background: none; transition: 0.2s; }
        .btn-checkin-lista:active { transform: scale(1.2); }
        .offline-indicator { display: none; position: fixed; top: 0; width: 100%; z-index: 10000; text-align: center; background: #ffc107; font-size: 0.7rem; font-weight: bold; }
    </style>
</head>
<body class="pb-5">

<div id="offline-msg" class="offline-indicator py-1">VOCÊ ESTÁ OFFLINE - DADOS SERÃO SALVOS LOCALMENTE</div>

<?php include 'navbar.php'; ?>

<div class="container">
    
    <!-- ESTATÍSTICAS -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="card stat-box p-2 text-center bg-primary text-white shadow-sm">
                MASC: <?=$stats_gen['masc']?> (<?=$perc_m?>%)
            </div>
        </div>
        <div class="col-6">
            <div class="card stat-box p-2 text-center bg-danger text-white shadow-sm">
                FEM: <?=$stats_gen['fem']?> (<?=$perc_f?>%)
            </div>
        </div>
    </div>

    <!-- FORMULÁRIO -->
    <div class="card p-3 border-0 shadow-sm rounded-4 mb-3">
        <h6 class="fw-bold mb-3" id="t_form">Cadastrar Jovem</h6>
        <form method="POST" id="main-form">
            <input type="hidden" name="form_acao" value="novo_jovem">
            <input type="hidden" name="id_jovem_edit" id="id_j_e">
            <input type="text" name="nome" id="j_n" class="form-control mb-2 text-uppercase" placeholder="Nome Completo" required>
            <div class="row g-2 mb-2">
                <div class="col-6"><select name="sexo" id="j_s" class="form-select" required><option value="">Sexo...</option><option value="Masculino">Masculino</option><option value="Feminino">Feminino</option></select></div>
                <div class="col-6"><input type="number" name="ano_nascimento" id="j_a" class="form-control" placeholder="Ano Nasc."></div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-7"><input type="text" name="data_nascimento" id="j_d" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)" maxlength="10"></div>
                <div class="col-5"><input type="text" name="telefone" id="j_t" class="form-control" placeholder="WhatsApp"></div>
            </div>
            <button type="submit" class="btn btn-dark w-100 fw-bold shadow-sm">SALVAR JOVEM</button>
            <button type="button" id="btn-cancel" class="btn btn-light w-100 mt-2 d-none" onclick="location.reload()">CANCELAR EDIÇÃO</button>
        </form>
    </div>

    <!-- FILTRO -->
    <form method="GET" class="d-flex gap-2 mb-3">
        <input type="text" name="f_jovem" class="form-control shadow-sm" placeholder="Buscar por nome..." value="<?=$f_j?>">
        <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i></button>
    </form>

    <!-- LISTA COM CHECK-IN -->
    <div class="table-responsive">
        <table class="table table-sm bg-white border align-middle shadow-sm rounded-3">
            <tbody>
                <?php foreach($jovens as $j): ?>
                <tr>
                    <td class="ps-3 py-2">
                        <div class="fw-bold small text-uppercase"><?=$j['nome']?></div>
                        <small class="text-muted" style="font-size: 0.7rem;"><?=$j['sexo']?> | <?=($j['data_nascimento']?date('d/m/Y',strtotime($j['data_nascimento'])):$j['ano_nascimento'])?></small>
                    </td>
                    <td class="text-end pe-3 text-nowrap">
                        <!-- BOTÃO CHECK-IN DIRETO -->
                        <?php if($pode_checkin): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="form_acao" value="toggle_presenca">
                            <input type="hidden" name="j_id" value="<?=$j['id']?>">
                            <input type="hidden" name="e_id" value="<?=$enc_id_ativo?>">
                            <button type="submit" class="btn-checkin-lista">
                                <i class="bi <?= $j['presenca_hoje'] ? 'bi-person-check-fill text-success' : 'bi-person-check text-muted' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- BOTÕES DE GESTÃO -->
                        <button class="btn btn-link text-primary p-0 mx-2" onclick='povJ(<?=json_encode($j)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Excluir permanentemente?')">
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
    <nav><ul class="pagination pagination-sm justify-content-center">
        <?php for($i=1; $i<=$total_paginas; $i++): ?>
            <li class="page-item <?=($p_atual==$i)?'active':''?>"><a class="page-link" href="?p=<?=$i?>&f_jovem=<?=urlencode($f_j)?>"><?=$i?></a></li>
        <?php endfor; ?>
    </ul></nav>

</div>

<!-- MODAL SUCESSO -->
<div class="modal fade" id="modalSucesso" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-4 border-0 shadow-lg"><div class="modal-body"><i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i><h5 class="fw-bold mt-3">Confirmado!</h5><p id="n_j_s" class="text-uppercase fw-bold text-primary"></p><button type="button" class="btn btn-dark w-100 rounded-pill" data-bs-dismiss="modal">OK</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Registro do Service Worker para Offline
    if ('serviceWorker' in navigator) { navigator.serviceWorker.register('sw.js'); }

    function maskData(i) {
        let v = i.value.replace(/\D/g,'');
        if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
        if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9);
        i.value = v;
        if(v.length == 10) document.getElementById('j_a').value = v.split('/')[2];
    }

    function povJ(j) {
        document.getElementById('id_j_e').value = j.id;
        document.getElementById('j_n').value = j.nome;
        document.getElementById('j_s').value = j.sexo;
        document.getElementById('j_a').value = j.ano_nascimento;
        document.getElementById('j_t').value = j.telefone;
        if(j.data_nascimento) { let d = j.data_nascimento.split('-'); document.getElementById('j_d').value = d[2]+'/'+d[1]+'/'+d[0]; }
        document.getElementById('t_form').innerText = "Editar Jovem";
        document.getElementById('btn-cancel').classList.remove('d-none');
        window.scrollTo(0,0);
    }

    // Monitor de Conexão
    window.addEventListener('offline', () => document.getElementById('offline-msg').style.display = 'block');
    window.addEventListener('online', () => document.getElementById('offline-msg').style.display = 'none');

    // Pop-up Sucesso
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const checkOk = urlParams.get('checkok');
        if(checkOk) {
            document.getElementById('n_j_s').innerText = checkOk;
            new bootstrap.Modal(document.getElementById('modalSucesso')).show();
        }
    });
</script>
</body>
</html>
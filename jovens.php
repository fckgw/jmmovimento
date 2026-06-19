<?php
/**
 * JMM SYSTEM - GESTÃO DE JOVENS + OFFLINE + CHECK-IN RÁPIDO
 * Versão: 2.0 (Garantia de Encontro Ativo e Check-in Automático)
 */
require_once 'config.php';

// Proteção de Sessão
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 1. CONFIGURAÇÃO DO ENCONTRO ATIVO ---
// Buscamos o encontro marcado como 'ativo'. 
// Ordenamos por ID DESC para garantir que, se houver dois ativos por erro, pegue o mais recente.
$query_encontro = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
$enc_ativo = $query_encontro->fetch(PDO::FETCH_ASSOC);

$enc_id_ativo = $enc_ativo['id'] ?? null;
$nome_encontro_atual = $enc_ativo['nome'] ?? 'Nenhum encontro ativo';
// O check-in só é habilitado se o encontro existir E o status for 'aberto'
$pode_checkin = ($enc_ativo && $enc_ativo['status'] == 'aberto');

// --- 2. ESTATÍSTICAS DE GÊNERO ---
$stats_gen = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN sexo = 'Masculino' THEN 1 ELSE 0 END) as masc,
    SUM(CASE WHEN sexo = 'Feminino' THEN 1 ELSE 0 END) as fem
    FROM jovens")->fetch(PDO::FETCH_ASSOC);

$total_geral = $stats_gen['total'] ?: 0;
$perc_m = ($total_geral > 0) ? round(($stats_gen['masc'] / $total_geral) * 100, 1) : 0;
$perc_f = ($total_geral > 0) ? round(($stats_gen['fem'] / $total_geral) * 100, 1) : 0;

// --- 3. PAGINAÇÃO E FILTROS ---
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$filtro_nome = isset($_GET['f_jovem']) ? trim($_GET['f_jovem']) : '';
$where_query = "WHERE 1=1";
$parametros_busca = [];

if ($filtro_nome) { 
    $where_query .= " AND (nome LIKE ? OR telefone LIKE ?)"; 
    $parametros_busca[] = "%$filtro_nome%"; 
    $parametros_busca[] = "%$filtro_nome%";
}

// Contagem para paginação
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM jovens $where_query");
$stmt_count->execute($parametros_busca);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $itens_por_pagina);

// --- 4. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_acao'])) {
    $acao = $_POST['form_acao'];

    // Ação: Salvar ou Editar Jovem
    if ($acao == 'novo_jovem') {
        $nome = trim($_POST['nome']);
        $telefone = trim($_POST['telefone']);
        $sexo = $_POST['sexo'];
        $ano_nasc = $_POST['ano_nascimento'];
        $data_nasc = !empty($_POST['data_nascimento']) ? implode('-', array_reverse(explode('/', $_POST['data_nascimento']))) : null;
        $id_edit = $_POST['id_jovem_edit'];

        if (!empty($id_edit)) {
            $sql = "UPDATE jovens SET nome=?, telefone=?, sexo=?, ano_nascimento=?, data_nascimento=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nome, $telefone, $sexo, $ano_nasc, $data_nasc, $id_edit]);
        } else {
            $sql = "INSERT INTO jovens (nome, telefone, sexo, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nome, $telefone, $sexo, $ano_nasc, $data_nasc]);
        }
    }

    // Ação: Toggle Presença (Check-in Direto)
    if ($acao == 'toggle_presenca' && $pode_checkin) {
        $jovem_id = $_POST['j_id'];
        $encontro_id = $enc_id_ativo;

        // Verifica se já existe a presença
        $check_presenca = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
        $check_presenca->execute([$jovem_id, $encontro_id]);
        
        if ($check_presenca->fetch()) {
            // Se já existe, remove (desmarcar)
            $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ? AND encontro_id = ?")->execute([$jovem_id, $encontro_id]);
        } else {
            // Se não existe, insere (marcar check-in)
            $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")->execute([$jovem_id, $encontro_id]);
            
            // Busca o nome para o modal de sucesso
            $stmt_nome = $pdo->prepare("SELECT nome FROM jovens WHERE id = ?");
            $stmt_nome->execute([$jovem_id]);
            $nome_confirmado = $stmt_nome->fetchColumn();
            
            header("Location: jovens.php?p=$pagina_atual&f_jovem=$filtro_nome&checkok=" . urlencode($nome_confirmado));
            exit;
        }
    }

    // Ação: Deletar Jovem
    if ($acao == 'deletar_jovem') {
        $id_del = $_POST['id_jovem'];
        $pdo->prepare("DELETE FROM presencas WHERE jovem_id = ?")->execute([$id_del]);
        $pdo->prepare("DELETE FROM jovens WHERE id = ?")->execute([$id_del]);
    }

    header("Location: jovens.php?p=$pagina_atual&f_jovem=$filtro_nome");
    exit;
}

// --- 5. CONSULTA DA LISTA DE JOVENS ---
// Seleciona os jovens e verifica se cada um tem presença no encontro ativo selecionado acima
$sql_lista = "SELECT j.*, 
             (SELECT id FROM presencas WHERE jovem_id = j.id AND encontro_id = ?) as presenca_hoje 
             FROM jovens j $where_query ORDER BY j.nome ASC LIMIT $offset, $itens_por_pagina";

$stmt_lista = $pdo->prepare($sql_lista);
$stmt_lista->execute(array_merge([$enc_id_ativo], $parametros_busca));
$jovens = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
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
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .stat-box { font-size: 0.85rem; font-weight: bold; border-radius: 12px; border: none; }
        .btn-checkin-lista { font-size: 1.6rem; border: none; background: none; transition: transform 0.2s; padding: 0; line-height: 1; }
        .btn-checkin-lista:active { transform: scale(1.3); }
        .offline-indicator { display: none; position: fixed; top: 0; width: 100%; z-index: 10000; text-align: center; background: #ffc107; font-size: 0.75rem; font-weight: bold; padding: 5px 0; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table-custom { background: white; border-radius: 15px; overflow: hidden; }
    </style>
</head>
<body class="pb-5">

<div id="offline-msg" class="offline-indicator">VOCÊ ESTÁ SEM INTERNET - DADOS SENDO MANIPULADOS LOCALMENTE</div>

<?php include 'navbar.php'; ?>

<div class="container mt-3">
    
    <!-- STATUS DO ENCONTRO ATIVO -->
    <div class="alert <?= $pode_checkin ? 'alert-success' : 'alert-warning' ?> card-custom py-2 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <small class="d-block text-uppercase fw-bold" style="font-size: 0.65rem;">Encontro Selecionado:</small>
            <span class="fw-bold"><?= $nome_encontro_atual ?></span>
        </div>
        <div class="text-end">
            <span class="badge <?= $pode_checkin ? 'bg-success' : 'bg-secondary' ?> text-uppercase">
                <?= $pode_checkin ? 'Check-in Aberto' : 'Check-in Fechado' ?>
            </span>
        </div>
    </div>

    <!-- ESTATÍSTICAS RÁPIDAS -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="card stat-box p-3 text-center bg-primary text-white shadow-sm">
                MASCULINO: <?=$stats_gen['masc']?> <br> <small><?=$perc_m?>%</small>
            </div>
        </div>
        <div class="col-6">
            <div class="card stat-box p-3 text-center bg-danger text-white shadow-sm">
                FEMININO: <?=$stats_gen['fem']?> <br> <small><?=$perc_f?>%</small>
            </div>
        </div>
    </div>

    <!-- FORMULÁRIO DE CADASTRO E EDIÇÃO -->
    <div class="card p-3 card-custom mb-3">
        <h6 class="fw-bold mb-3" id="titulo_formulario">Cadastrar Novo Jovem</h6>
        <form method="POST" id="main-form">
            <input type="hidden" name="form_acao" value="novo_jovem">
            <input type="hidden" name="id_jovem_edit" id="id_jovem_edit">
            
            <input type="text" name="nome" id="campo_nome" class="form-control mb-2 text-uppercase" placeholder="Nome Completo" required>
            
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <select name="sexo" id="campo_sexo" class="form-select" required>
                        <option value="">Gênero...</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Feminino">Feminino</option>
                    </select>
                </div>
                <div class="col-6">
                    <input type="number" name="ano_nascimento" id="campo_ano" class="form-control" placeholder="Ano Nasc.">
                </div>
            </div>
            
            <div class="row g-2 mb-2">
                <div class="col-7">
                    <input type="text" name="data_nascimento" id="campo_data" class="form-control" placeholder="Data DD/MM/AAAA" onkeyup="mascaraData(this)" maxlength="10">
                </div>
                <div class="col-5">
                    <input type="text" name="telefone" id="campo_telefone" class="form-control" placeholder="WhatsApp">
                </div>
            </div>
            
            <button type="submit" class="btn btn-dark w-100 fw-bold shadow-sm py-2">SALVAR INFORMAÇÕES</button>
            <button type="button" id="btn-cancelar" class="btn btn-light w-100 mt-2 d-none" onclick="location.reload()">CANCELAR EDIÇÃO</button>
        </form>
    </div>

    <!-- BARRA DE BUSCA -->
    <form method="GET" class="d-flex gap-2 mb-3">
        <input type="text" name="f_jovem" class="form-control shadow-sm border-0" placeholder="Buscar jovem por nome..." value="<?=$filtro_nome?>">
        <button type="submit" class="btn btn-dark shadow-sm"><i class="bi bi-search"></i></button>
    </form>

    <!-- TABELA DE JOVENS COM CHECK-IN -->
    <div class="table-responsive">
        <table class="table table-sm align-middle table-custom shadow-sm">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 py-2" style="font-size: 0.75rem;">JOVEM</th>
                    <th class="text-end pe-3" style="font-size: 0.75rem;">AÇÕES / CHECK-IN</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($jovens)): ?>
                    <tr><td colspan="2" class="text-center py-4 text-muted">Nenhum registro encontrado.</td></tr>
                <?php endif; ?>

                <?php foreach($jovens as $j): ?>
                <tr>
                    <td class="ps-3 py-2">
                        <div class="fw-bold text-uppercase" style="font-size: 0.85rem;"><?=$j['nome']?></div>
                        <small class="text-muted" style="font-size: 0.7rem;">
                            <?=$j['sexo']?> | <?= ($j['data_nascimento'] ? date('d/m/Y', strtotime($j['data_nascimento'])) : ($j['ano_nascimento'] ?: '---')) ?>
                        </small>
                    </td>
                    <td class="text-end pe-3 text-nowrap">
                        <!-- LÓGICA DO BOTÃO DE CHECK-IN -->
                        <?php if($pode_checkin): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="form_acao" value="toggle_presenca">
                            <input type="hidden" name="j_id" value="<?=$j['id']?>">
                            <button type="submit" class="btn-checkin-lista me-2">
                                <i class="bi <?= $j['presenca_hoje'] ? 'bi-person-check-fill text-success' : 'bi-person-check text-muted' ?>"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <!-- Ícone apenas visual se o check-in estiver fechado -->
                            <?php if($j['presenca_hoje']): ?>
                                <i class="bi bi-person-check-fill text-success fs-4 me-2 opacity-50"></i>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- EDIÇÃO E EXCLUSÃO -->
                        <button class="btn btn-link text-primary p-0 mx-2" onclick='preencherEdicao(<?=json_encode($j)?>)'>
                            <i class="bi bi-pencil-square fs-5"></i>
                        </button>
                        
                        <form method="POST" class="d-inline" onsubmit="return confirm('Deseja excluir este registro permanentemente?')">
                            <input type="hidden" name="form_acao" value="deletar_jovem">
                            <input type="hidden" name="id_jovem" value="<?=$j['id']?>">
                            <button type="submit" class="btn btn-link text-danger p-0">
                                <i class="bi bi-trash fs-5"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINAÇÃO -->
    <?php if($total_paginas > 1): ?>
    <nav>
        <ul class="pagination pagination-sm justify-content-center mt-3">
            <?php for($i=1; $i<=$total_paginas; $i++): ?>
                <li class="page-item <?=($pagina_atual == $i) ? 'active' : ''?>">
                    <a class="page-link" href="?p=<?=$i?>&f_jovem=<?=urlencode($filtro_nome)?>"><?=$i?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<!-- MODAL DE SUCESSO NO CHECK-IN -->
<div class="modal fade" id="modalSucesso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4 border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h5 class="fw-bold mt-3">Presença Confirmada!</h5>
                <p id="nome_jovem_sucesso" class="text-uppercase fw-bold text-primary"></p>
                <button type="button" class="btn btn-dark w-100 rounded-pill py-2 mt-2" data-bs-dismiss="modal">CONCLUÍDO</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Registro de Service Worker para PWA/Offline
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(err => console.log("Erro SW:", err));
    }

    // Máscara Simples para Data
    function mascaraData(input) {
        let valor = input.value.replace(/\D/g,'');
        if(valor.length > 2) valor = valor.substring(0,2) + '/' + valor.substring(2);
        if(valor.length > 5) valor = valor.substring(0,5) + '/' + valor.substring(5,9);
        input.value = valor;
        
        // Se preencher a data completa, tenta sugerir o ano no campo ao lado
        if(valor.length === 10) {
            document.getElementById('campo_ano').value = valor.split('/')[2];
        }
    }

    // Função para preencher o formulário de edição
    function preencherEdicao(jovem) {
        document.getElementById('id_jovem_edit').value = jovem.id;
        document.getElementById('campo_nome').value = jovem.nome;
        document.getElementById('campo_sexo').value = jovem.sexo;
        document.getElementById('campo_ano').value = jovem.ano_nascimento;
        document.getElementById('campo_telefone').value = jovem.telefone;
        
        if(jovem.data_nascimento) {
            let partes = jovem.data_nascimento.split('-');
            document.getElementById('campo_data').value = partes[2] + '/' + partes[1] + '/' + partes[0];
        } else {
            document.getElementById('campo_data').value = '';
        }
        
        document.getElementById('titulo_formulario').innerText = "Editar Cadastro do Jovem";
        document.getElementById('btn-cancelar').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Monitoramento de conexão
    window.addEventListener('offline', () => {
        document.getElementById('offline-msg').style.display = 'block';
    });
    window.addEventListener('online', () => {
        document.getElementById('offline-msg').style.display = 'none';
    });

    // Exibir Modal de Sucesso após Check-in
    document.addEventListener("DOMContentLoaded", function() {
        const parametros = new URLSearchParams(window.location.search);
        const checkOk = parametros.get('checkok');
        if(checkOk) {
            document.getElementById('nome_jovem_sucesso').innerText = checkOk;
            const meuModal = new bootstrap.Modal(document.getElementById('modalSucesso'));
            meuModal.show();
            
            // Limpa a URL após exibir o modal para não repetir ao atualizar
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]checkok=[^&]+/, ""));
        }
    });
</script>
</body>
</html>
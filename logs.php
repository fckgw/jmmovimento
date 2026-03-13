<?php
/**
 * AUDITORIA E LOGS JMM - COMPLETA
 */
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') {
    header("Location: dashboard.php"); exit;
}

// --- LÓGICA DE LIMPEZA ---
if (isset($_POST['limpar_logs'])) {
    $pdo->exec("DELETE FROM logs");
    registrarLog($pdo, "Realizou limpeza total da base de logs", "Logs");
    header("Location: logs.php?msg=Base de logs limpa com sucesso"); exit;
}

// --- CONFIGURAÇÃO DE PAGINAÇÃO ---
$itens_por_pág = 15;
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $itens_por_pág;

// --- FILTROS ---
$where = "WHERE 1=1";
$params = [];

if (!empty($_GET['f_user'])) {
    $where .= " AND usuario_nome LIKE ?";
    $params[] = "%".$_GET['f_user']."%";
}
if (!empty($_GET['f_tela'])) {
    $where .= " AND tela = ?";
    $params[] = $_GET['f_tela'];
}
if (!empty($_GET['f_inicio']) && !empty($_GET['f_fim'])) {
    $where .= " AND DATE(data_hora) BETWEEN ? AND ?";
    $params[] = $_GET['f_inicio'];
    $params[] = $_GET['f_fim'];
}

// Total de registros filtrados
$sql_count = "SELECT COUNT(*) FROM logs $where";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $itens_por_pág);

// Consulta final com limite
$sql_logs = "SELECT * FROM logs $where ORDER BY data_hora DESC LIMIT $offset, $itens_por_pág";
$stmt_logs = $pdo->prepare($sql_logs);
$stmt_logs->execute($params);
$lista_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

// Lista de telas únicas para o filtro
$telas = $pdo->query("SELECT DISTINCT tela FROM logs ORDER BY tela ASC")->fetchAll(PDO::FETCH_COLUMN);

// Prepara URL para PDF mantendo filtros
$query_pdf = $_SERVER['QUERY_STRING'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Auditoria - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: sans-serif; }
        .card { border-radius: 15px; border: none; }
        .small-log { font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="container-fluid py-4" style="max-width: 1200px;">
    <!-- CABEÇALHO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="sistema_dashboard.php" class="btn btn-outline-dark btn-sm mb-1"><i class="bi bi-arrow-left"></i> Voltar</a>
            <h2 class="fw-bold m-0 text-dark">Logs do Sistema</h2>
        </div>
        <div class="d-flex gap-2">
            <a href="gerar_logs_pdf.php?<?=$query_pdf?>" target="_blank" class="btn btn-danger fw-bold"><i class="bi bi-file-pdf"></i> PDF</a>
            <form method="POST" onsubmit="return confirm('ATENÇÃO: Deseja apagar TODO o histórico de logs? Esta ação não pode ser desfeita.')">
                <button type="submit" name="limpar_logs" class="btn btn-outline-danger fw-bold"><i class="bi bi-trash"></i> LIMPAR TUDO</button>
            </form>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card p-3 mb-4 shadow-sm">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted">USUÁRIO</label>
                <input type="text" name="f_user" class="form-control" placeholder="Nome..." value="<?= $_GET['f_user'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">TELA</label>
                <select name="f_tela" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach($telas as $t): ?>
                        <option value="<?=$t?>" <?=($_GET['f_tela']??'')==$t?'selected':''?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">INÍCIO</label>
                <input type="date" name="f_inicio" class="form-control" value="<?= $_GET['f_inicio'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted">FIM</label>
                <input type="date" name="f_fim" class="form-control" value="<?= $_GET['f_fim'] ?? '' ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100 fw-bold">FILTRAR</button>
                <a href="logs.php" class="btn btn-light border"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>

    <!-- GRID -->
    <div class="card shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small-log">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">DATA / HORA</th>
                        <th>USUÁRIO</th>
                        <th>AÇÃO REALIZADA</th>
                        <th class="text-center">TELA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lista_logs)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">Nenhum registro encontrado para os filtros aplicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach($lista_logs as $log): ?>
                    <tr>
                        <td class="ps-3 fw-bold"><?= date('d/m/Y H:i:s', strtotime($log['data_hora'])) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= $log['usuario_nome'] ?></span></td>
                        <td><?= $log['acao'] ?></td>
                        <td class="text-center"><span class="badge bg-info-subtle text-info-emphasis border border-info px-2"><?= $log['tela'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINAÇÃO -->
    <?php if($total_paginas > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?p=<?= $pagina - 1 ?>&<?= $query_pdf ?>">Anterior</a>
            </li>
            <?php for($i=1; $i<=$total_paginas; $i++): ?>
                <li class="page-item <?= ($pagina == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?p=<?= $i ?>&<?= $query_pdf ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="?p=<?= $pagina + 1 ?>&<?= $query_pdf ?>">Próxima</a>
            </li>
        </ul>
    </nav>
    <p class="text-center text-muted small">Total de registros: <?= $total_registros ?></p>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
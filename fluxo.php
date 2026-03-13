<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// --- FILTROS ---
$tipo = $_GET['f_tipo'] ?? '';
$inicio = $_GET['f_inicio'] ?? date('Y-m-01');
$fim = $_GET['f_fim'] ?? date('Y-m-t');

$params = [$inicio, $fim];
$where = "WHERE DATE(f.vencimento) BETWEEN ? AND ?";

if($tipo) { $where .= " AND f.tipo = ?"; $params[] = $tipo; }

$sql = "SELECT f.*, c.nome as nome_conta FROM financeiro f 
        LEFT JOIN contas_bancarias c ON f.conta_id = c.id 
        $where ORDER BY f.vencimento DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$saldos = $pdo->query("SELECT * FROM contas_bancarias")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Caixa - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style> body { background: #f4f7f6; } .card { border-radius: 15px; border: none; } </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="sistema_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        <h4 class="fw-bold m-0 text-primary">EXTRATO DE FLUXO</h4>
        <button class="btn btn-danger btn-sm" onclick="window.print()"><i class="bi bi-file-pdf"></i> Imprimir</button>
    </div>

    <!-- CARDS DE SALDO -->
    <div class="row mb-4">
        <?php foreach($saldos as $s): ?>
        <div class="col-md-4 mb-2">
            <div class="card shadow-sm p-3 border-start border-4 border-primary">
                <small class="text-muted fw-bold text-uppercase"><?=$s['nome']?></small>
                <h3 class="fw-bold m-0 <?= $s['saldo_atual'] < 0 ? 'text-danger' : 'text-success' ?>">R$ <?= number_format($s['saldo_atual'], 2, ',', '.') ?></h3>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FILTROS -->
    <div class="card p-3 mb-4 shadow-sm">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="date" name="f_inicio" class="form-control" value="<?=$inicio?>"></div>
            <div class="col-md-3"><input type="date" name="f_fim" class="form-control" value="<?=$fim?>"></div>
            <div class="col-md-3">
                <select name="f_tipo" class="form-select">
                    <option value="">Todos os Lançamentos</option>
                    <option value="pagar" <?=$tipo=='pagar'?'selected':''?>>Contas a Pagar (Saídas)</option>
                    <option value="receber" <?=$tipo=='receber'?'selected':''?>>Contas a Receber (Entradas)</option>
                </select>
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-dark w-100">FILTRAR</button></div>
        </form>
    </div>

    <!-- GRID -->
    <div class="card shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr><th>Data</th><th>Estabelecimento / Conta</th><th class="text-center">Valor</th><th class="text-center">Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach($lancamentos as $l): ?>
                    <tr>
                        <td class="small"><?=date('d/m/Y H:i', strtotime($l['vencimento']))?></td>
                        <td>
                            <div class="fw-bold"><?=$l['estabelecimento']?></div>
                            <small class="text-muted">Origem: <?=$l['nome_conta']?></small>
                        </td>
                        <td class="text-center fw-bold <?=$l['tipo']=='pagar'?'text-danger':'text-success'?>">
                            <?=$l['tipo']=='pagar'?'-':'+'?> R$ <?=number_format($l['valor'], 2, ',', '.')?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?=$l['status']=='pago'?'bg-success':'bg-warning'?>"><?=strtoupper($l['status'])?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
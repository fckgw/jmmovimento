<?php
/**
 * JMM SYSTEM - MÓDULO DE ATA PROFISSIONAL (CORES CORRIGIDAS)
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 1. BUSCAR ENCONTRO ATIVO
$enc = $pdo->query("SELECT *, DATE_FORMAT(data_encontro, '%d/%m/%Y') as data_br FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id = $enc['id'] ?? 0;

if (!$enc) {
    die("<div class='container mt-5 alert alert-warning text-center'>Nenhum encontro ativo. Ative um encontro no menu <a href='encontros.php'>ENCONTROS</a> primeiro.</div>");
}

// 2. ESTATÍSTICAS DOS PRESENTES
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN j.sexo = 'Masculino' THEN 1 ELSE 0 END) as masc,
        SUM(CASE WHEN j.sexo = 'Feminino' THEN 1 ELSE 0 END) as fem
    FROM presencas p
    JOIN jovens j ON p.jovem_id = j.id
    WHERE p.encontro_id = ?
");
$stmt_stats->execute([$enc_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_p = $stats['total'] ?: 0;
$perc_m = ($total_p > 0) ? round(($stats['masc'] / $total_p) * 100, 1) : 0;
$perc_f = ($total_p > 0) ? round(($stats['fem'] / $total_p) * 100, 1) : 0;

// 3. LISTA DE PRESENTES (NOMES + IDADES)
$stmt_jovens = $pdo->prepare("
    SELECT j.nome, j.sexo, 
    FLOOR(TIMESTAMPDIFF(YEAR, j.data_nascimento, CURDATE())) as idade
    FROM presencas p
    JOIN jovens j ON p.jovem_id = j.id
    WHERE p.encontro_id = ?
    ORDER BY j.nome ASC
");
$stmt_jovens->execute([$enc_id]);
$lista_presentes = $stmt_jovens->fetchAll(PDO::FETCH_ASSOC);

// 4. SALVAR ATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['texto_ata'])) {
    $pdo->prepare("UPDATE encontros SET ata = ? WHERE id = ?")->execute([$_POST['texto_ata'], $enc_id]);
    header("Location: ata.php?sucesso=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ata - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .ck-editor__editable { min-height: 400px; border-radius: 0 0 15px 15px !important; }
        
        /* Ajuste de Visibilidade dos Cards */
        .stat-card-ata { border: none; border-radius: 15px; padding: 20px; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-val { font-size: 2.2rem; font-weight: 900; line-height: 1; margin-top: 5px; color: #ffffff !important; }
        .stat-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.85) !important; }
        
        /* Cores específicas para contraste total */
        .bg-total { background: linear-gradient(45deg, #2c3e50, #000000); } /* Darker mas com texto branco puro */
        .bg-masc { background: linear-gradient(45deg, #0d6efd, #004085); }
        .bg-fem { background: linear-gradient(45deg, #d63384, #85144b); }
    </style>
</head>
<body class="pb-5">

<?php include 'navbar.php'; ?>

<div class="container">
    
    <!-- INFO DO ENCONTRO -->
    <div class="card p-4 shadow-sm mb-4 border-0 rounded-4" style="border-left: 8px solid #000 !important;">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h6 class="text-muted fw-bold text-uppercase mb-1">Redigindo Ata de Reunião</h6>
                <h3 class="fw-bold mb-1"><?= $enc['tema'] ?></h3>
                <p class="mb-0 text-muted">
                    <i class="bi bi-geo-alt-fill text-danger"></i> <strong>LOCAL:</strong> <?= $enc['local_encontro'] ?> <br>
                    <i class="bi bi-calendar-check-fill text-primary"></i> <strong>DATA:</strong> <?= $enc['data_br'] ?>
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <a href="gerar_ata_pdf.php" target="_blank" class="btn btn-danger fw-bold rounded-pill px-4 shadow">
                    <i class="bi bi-file-earmark-pdf"></i> GERAR PDF DA ATA
                </a>
            </div>
        </div>
    </div>

    <!-- PAINEL DE ESTATÍSTICAS CORRIGIDO (AUTO CONTRASTE) -->
    <div class="row g-3 mb-4">
        <!-- TOTAL -->
        <div class="col-md-4">
            <div class="stat-card-ata bg-total text-center">
                <div class="stat-label">Total de Presentes</div>
                <div class="stat-val"><?= $total_p ?></div>
            </div>
        </div>
        <!-- MASCULINO -->
        <div class="col-md-4">
            <div class="stat-card-ata bg-masc text-center">
                <div class="stat-label">Masculino (<?= $perc_m ?>%)</div>
                <div class="stat-val"><?= $stats['masc'] ?: 0 ?></div>
            </div>
        </div>
        <!-- FEMININO -->
        <div class="col-md-4">
            <div class="stat-card-ata bg-fem text-center">
                <div class="stat-label">Feminino (<?= $perc_f ?>%)</div>
                <div class="stat-val"><?= $stats['fem'] ?: 0 ?></div>
            </div>
        </div>
    </div>

    <!-- EDITOR CKEDITOR -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0 text-uppercase"><i class="bi bi-pencil-square me-2"></i>Conteúdo da Ata</h6>
        </div>
        <form method="POST">
            <textarea name="texto_ata" id="editor"><?= $enc['ata'] ?></textarea>
            <div class="p-3 bg-light text-end">
                <button type="submit" class="btn btn-success fw-bold px-5 rounded-pill shadow">
                    <i class="bi bi-save2 me-2"></i>SALVAR ATA AGORA
                </button>
            </div>
        </form>
    </div>

    <!-- NOMINATA DE PRESENTES -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-dark text-white py-3">
            <h6 class="fw-bold mb-0 text-uppercase"><i class="bi bi-people me-2"></i>Nominata de Presença</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Nome Completo</th>
                        <th class="text-center">Sexo</th>
                        <th class="text-center">Idade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($total_p > 0): ?>
                        <?php foreach($lista_presentes as $lp): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-uppercase small"><?= $lp['nome'] ?></td>
                            <td class="text-center small"><?= $lp['sexo'] ?></td>
                            <td class="text-center small"><?= $lp['idade'] ?: '--' ?> anos</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">Nenhum check-in registrado para este encontro.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    ClassicEditor.create(document.querySelector('#editor')).catch(e => console.error(e));
</script>
</body>
</html>
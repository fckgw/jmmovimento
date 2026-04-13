<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { exit; }

// Buscar Encontro Ativo com Data Formatada
$enc = $pdo->query("SELECT *, DATE_FORMAT(data_encontro, '%d/%m/%Y') as data_br FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$enc_id = $enc['id'];

// Estatísticas dos Presentes
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN j.sexo = 'Masculino' THEN 1 ELSE 0 END) as masc,
    SUM(CASE WHEN j.sexo = 'Feminino' THEN 1 ELSE 0 END) as fem
    FROM presencas p JOIN jovens j ON p.jovem_id = j.id 
    WHERE p.encontro_id = $enc_id")->fetch();

// Lista de Jovens para a Nominata
$stmt_j = $pdo->prepare("
    SELECT j.nome, FLOOR(TIMESTAMPDIFF(YEAR, j.data_nascimento, CURDATE())) as idade
    FROM presencas p JOIN jovens j ON p.jovem_id = j.id
    WHERE p.encontro_id = ? ORDER BY j.nome ASC
");
$stmt_j->execute([$enc_id]);
$jovens = $stmt_j->fetchAll(PDO::FETCH_ASSOC);

$total = $stats['total'];
$p_m = ($total > 0) ? round(($stats['masc']/$total)*100,1) : 0;
$p_f = ($total > 0) ? round(($stats['fem']/$total)*100,1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Impressão de Ata - JMM</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; font-size: 13px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { width: 75px; border-radius: 50%; margin-bottom: 5px; }
        .main-title { font-size: 20px; font-weight: bold; text-transform: uppercase; margin: 0; }
        .info-box { background: #f2f2f2; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ccc; }
        .stats-table { width: 100%; text-align: center; margin-bottom: 25px; border-collapse: collapse; }
        .stats-table td { border: 1px solid #ddd; padding: 10px; background: #fff; }
        .content-area { padding: 10px; min-height: 350px; border: 1px solid #eee; margin-bottom: 30px; }
        .youth-list { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .youth-list th { background: #eee; border: 1px solid #ddd; padding: 6px; text-align: left; }
        .youth-list td { border: 1px solid #eee; padding: 5px; text-transform: uppercase; font-size: 11px; }
        .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
        .no-print { text-align: center; margin-bottom: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding: 12px 30px; background: #28a745; color: white; border: none; border-radius: 50px; cursor: pointer; font-weight: bold; font-size: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">🖨️ CLIQUE PARA IMPRIMIR OU SALVAR EM PDF</button>
</div>

<div class="header">
    <img src="Img/logo.jpg" class="logo"><br>
    <h1 class="main-title">Ata Oficial de Encontro - Movimento JMM</h1>
</div>

<div class="info-box">
    <strong>ENCONTRO:</strong> <?= $enc['tema'] ?><br>
    <strong>LOCAL:</strong> <?= $enc['local_encontro'] ?><br>
    <strong>DATA DO EVENTO:</strong> <?= $enc['data_br'] ?><br>
    <strong>EMISSÃO DO RELATÓRIO:</strong> <?= date('d/m/Y H:i:s') ?>
</div>

<table class="stats-table">
    <tr>
        <td><strong>TOTAL PRESENTES</strong><br><?= $total ?> jovens</td>
        <td><strong>MASCULINO</strong><br><?= $stats['masc'] ?: 0 ?> (<?= $p_m ?>%)</td>
        <td><strong>FEMININO</strong><br><?= $stats['fem'] ?: 0 ?> (<?= $p_f ?>%)</td>
    </tr>
</table>

<div class="content-area">
    <h3 style="margin-top: 0; border-bottom: 1px solid #333;">RELATO DA REUNIÃO</h3>
    <?= $enc['ata'] ?: '<p style="color:red italic">Nenhuma descrição foi registrada na ata para este encontro.</p>' ?>
</div>

<div>
    <h3 style="border-bottom: 1px solid #333;">NOMINATA DE PRESENTES</h3>
    <table class="youth-list">
        <thead>
            <tr>
                <th width="80%">NOME COMPLETO DO JOVEM</th>
                <th style="text-align: center;">IDADE</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($jovens as $j): ?>
            <tr>
                <td><?= $j['nome'] ?></td>
                <td style="text-align: center;"><?= $j['idade'] ?> anos</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="footer">
    Documento oficial gerado pelo JMM System em <?= date('d/m/Y') ?> às <?= date('H:i') ?><br>
    https://jmmovimento.com.br
</div>

</body>
</html>
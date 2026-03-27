<?php
/**
 * JMM SYSTEM - GERADOR DE RELATÓRIO CONSOLIDADO
 */
require_once 'config.php';

if (!isset($_GET['p_id'])) { die("Projeto não informado."); }

$p_id = (int)$_GET['p_id'];

// Dados do Projeto
$stmt = $pdo->prepare("SELECT * FROM projetos WHERE id = ?");
$stmt->execute([$p_id]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);

// Estatísticas Marketing
$mkt_total = $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = $p_id")->fetchColumn();
$mkt_sent  = $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = $p_id AND status = 'Enviado'")->fetchColumn();

// Lista de Doações
$stmt_f = $pdo->prepare("SELECT * FROM rifas_pagamentos WHERE projeto_id = ? ORDER BY data_pagamento ASC");
$stmt_f->execute([$p_id]);
$fieis = $stmt_f->fetchAll(PDO::FETCH_ASSOC);

$total_geral = 0;
foreach($fieis as $f) $total_geral += $f['valor'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório - <?=$projeto['nome_projeto']?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; font-size: 12px; margin: 30px; }
        .header { text-align: center; border-bottom: 2px solid #6f42c1; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #6f42c1; text-transform: uppercase; font-size: 20px; }
        .header p { margin: 5px 0; font-weight: bold; }
        
        .section-title { background: #f4f4f4; padding: 8px; font-weight: bold; border-left: 5px solid #6f42c1; margin: 20px 0 10px; text-transform: uppercase; }
        
        .stats-grid { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .stats-card { border: 1px solid #ddd; padding: 10px; width: 30%; text-align: center; border-radius: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #6f42c1; color: #fff; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        
        .footer { margin-top: 30px; text-align: right; font-size: 14px; }
        .total-box { background: #6f42c1; color: white; padding: 15px; display: inline-block; border-radius: 5px; }

        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h1>JMM SYSTEM - RELATÓRIO DE SECRETARIA</h1>
        <p>PROJETO: <?=$projeto['nome_projeto']?></p>
        <span>Período: <?=date('d/m/Y', strtotime($projeto['data_inicio']))?> a <?=date('d/m/Y', strtotime($projeto['data_fim']))?></span>
    </div>

    <div class="section-title">Balanço de Marketing (WhatsApp)</div>
    <table style="width: 50%;">
        <tr><td>Total de Contatos na Lista:</td><td><b><?=$mkt_total?></b></td></tr>
        <tr><td>Mensagens Disparadas:</td><td style="color: green;"><b><?=$mkt_sent?></b></td></tr>
        <tr><td>Pendentes:</td><td style="color: orange;"><b><?=$mkt_total - $mkt_sent?></b></td></tr>
    </table>

    <div class="section-title">Lista de Doações / Rifas Confirmadas</div>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Nº Rifa</th>
                <th>Nome do Fiel</th>
                <th>Cidade/UF</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($fieis as $f): ?>
            <tr>
                <td><?=date('d/m/Y', strtotime($f['data_pagamento']))?></td>
                <td><b><?=$f['numero_rifa']?></b></td>
                <td><?=$f['nome_fiel']?></td>
                <td><?=$f['cidade']?>/<?=$f['estado']?></td>
                <td>R$ <?=number_format($f['valor'], 2, ',', '.')?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="total-box">
            TOTAL ARRECADADO NESTE PROJETO:<br>
            <strong style="font-size: 20px;">R$ <?=number_format($total_geral, 2, ',', '.')?></strong>
        </div>
    </div>

    <div class="no-print" style="margin-top: 50px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Clique aqui para Salvar como PDF / Imprimir</button>
    </div>

</body>
</html>
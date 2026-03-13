<?php
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_SESSION['user_id'])) exit;

function txt($t) { return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8'); }

$enc = $pdo->query("SELECT * FROM encontros WHERE ativo = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$enc) die("Nenhum encontro ativo.");

$jovens = $pdo->query("SELECT j.nome, j.telefone FROM jovens j JOIN presencas p ON j.id = p.jovem_id WHERE p.encontro_id = {$enc['id']} ORDER BY j.nome ASC")->fetchAll();
$total = count($jovens);

$pdf = new FPDF();
$pdf->AddPage();
if(file_exists('Img/logo.jpg')) $pdf->Image('Img/logo.jpg', 10, 8, 20);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, txt('RELATÓRIO DE ENCONTRO - JMM'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, txt('Tema: ' . $enc['tema']), 0, 1, 'C');
$pdf->Cell(0, 7, txt('Data: ' . date('d/m/Y', strtotime($enc['data_encontro'])) . ' | Local: ' . $enc['local_encontro']), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, txt('1. ATA / OBSERVAÇÕES'), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 7, txt($enc['ata'] ?: 'Nenhuma ata registrada para este encontro.'), 1, 'L');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, txt('2. LISTA DE PRESENÇA (Total: ' . $total . ')'), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
foreach($jovens as $idx => $j) {
    $pdf->Cell(10, 7, ($idx+1), 1, 0, 'C');
    $pdf->Cell(130, 7, txt($j['nome']), 1, 0, 'L');
    $pdf->Cell(50, 7, $j['telefone'], 1, 1, 'C');
}

ob_end_clean();
$pdf->Output('I', 'Relatorio_ATA_Encontro_JMM.pdf');
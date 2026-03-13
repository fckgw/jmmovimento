<?php
require_once 'config.php';
require_once 'fpdf.php';

if (!isset($_SESSION['user_id'])) exit;

function txt($t) { return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8'); }

$ranking = $pdo->query("
    SELECT t.*, j.nome as capitao, 
    (SELECT SUM(tempo_segundos) FROM cg_disputas WHERE vencedor_id = t.id) as tempo_total 
    FROM cg_times t LEFT JOIN jovens j ON t.capitao_id = j.id 
    ORDER BY t.pontos DESC, tempo_total ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pdf = new FPDF();
$pdf->AddPage();
if(file_exists('Img/logo.jpg')) $pdf->Image('Img/logo.jpg', 10, 8, 20);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, txt('RANKING CABO DE GUERRA - JMM'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 10, txt('Gerado em: ' . date('d/m/Y H:i')), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFillColor(243, 156, 18);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(15, 8, 'POS', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'TIME (SANTO)', 1, 0, 'L', true);
$pdf->Cell(50, 8, txt('CAPITÃO'), 1, 0, 'L', true);
$pdf->Cell(25, 8, 'TEMPO', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'PTS', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);
foreach($ranking as $idx => $r) {
    $pdf->Cell(15, 8, ($idx+1) . 'o', 1, 0, 'C');
    $pdf->Cell(80, 8, txt($r['nome_santo']), 1);
    $pdf->Cell(50, 8, txt($r['capitao'] ?? '---'), 1);
    $pdf->Cell(25, 8, gmdate("i:s", $r['tempo_total']), 1, 0, 'C');
    $pdf->Cell(20, 8, $r['pontos'], 1, 1, 'C');
}

ob_end_clean();
$pdf->Output('I', 'Ranking_Cabo_Guerra.pdf');
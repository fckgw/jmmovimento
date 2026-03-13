<?php
require_once 'config.php';

if (!file_exists('fpdf.php')) {
    die("Erro: Biblioteca FPDF ausente.");
}
require_once 'fpdf.php';

// Filtro de encontro caso venha via GET
$enc_id = $_GET['encontro_id'] ?? null;
$jovens = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    function Header() {
        if(file_exists('Img/logo.jpg')) $this->Image('Img/logo.jpg', 10, 8, 22);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(13, 110, 253); 
        $this->Cell(0, 10, utf8_decode('JUVENTUDE DA MATRIZ EM MOVIMENTO'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, utf8_decode('LISTA DE PRESENÇA E CHAMADA'), 0, 1, 'C');
        $this->Ln(10);
        $this->SetFillColor(13, 110, 253);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(110, 8, 'NOME COMPLETO', 1, 0, 'L', true);
        $this->Cell(50, 8, 'TELEFONE', 1, 0, 'C', true);
        $this->Cell(30, 8, 'ANO NASC.', 1, 1, 'C', true);
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

foreach ($jovens as $j) {
    $pdf->Cell(110, 8, utf8_decode($j['nome']), 1);
    $pdf->Cell(50, 8, $j['telefone'], 1, 0, 'C');
    $pdf->Cell(30, 8, $j['ano_nascimento'], 1, 1, 'C');
}

if (ob_get_contents()) ob_end_clean();
$pdf->Output('I', 'Relatorio_JMM_Final_Lista_Presenca_JMM.pdf');
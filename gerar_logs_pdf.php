<?php
/**
 * JMM SYSTEM - GERADOR DE PDF DE LOGS (COMPATÍVEL COM PHP 8.3+)
 */
require_once 'config.php';

// Verifica se a biblioteca existe
if (!file_exists('fpdf.php')) {
    die("Erro: A biblioteca fpdf.php nao foi encontrada na raiz.");
}
require_once 'fpdf.php';

// Segurança: Somente Admin
if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') {
    exit("Acesso negado");
}

// Limpa qualquer saída anterior para evitar erro de "Some data has already been output"
if (ob_get_length()) ob_end_clean();
ob_start();

// --- LÓGICA DE FILTROS ---
$where = "WHERE 1=1";
$params = [];
if (!empty($_GET['f_user'])) { $where .= " AND usuario_nome LIKE ?"; $params[] = "%".$_GET['f_user']."%"; }
if (!empty($_GET['f_tela'])) { $where .= " AND tela = ?"; $params[] = $_GET['f_tela']; }
if (!empty($_GET['f_inicio']) && !empty($_GET['f_fim'])) { $where .= " AND DATE(data_hora) BETWEEN ? AND ?"; $params[] = $_GET['f_inicio']; $params[] = $_GET['f_fim']; }

$sql = "SELECT * FROM logs $where ORDER BY data_hora DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função auxiliar para converter texto para o formato do FPDF sem usar utf8_decode (obsoleto no PHP 8.2+)
function txt($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class PDF extends FPDF {
    function Header() {
        if(file_exists('Img/logo.jpg')) $this->Image('Img/logo.jpg', 10, 8, 20);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, txt('RELATÓRIO DE AUDITORIA - JMM SYSTEM'), 0, 1, 'C');
        $this->Ln(10);
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(35, 7, 'DATA/HORA', 1, 0, 'C', true);
        $this->Cell(45, 7, 'USUARIO', 1, 0, 'L', true);
        $this->Cell(80, 7, 'ACAO', 1, 0, 'L', true);
        $this->Cell(30, 7, 'TELA', 1, 1, 'C', true);
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

foreach ($logs as $l) {
    $pdf->Cell(35, 6, date('d/m/Y H:i:s', strtotime($l['data_hora'])), 1);
    $pdf->Cell(45, 6, txt($l['usuario_nome']), 1);
    $pdf->Cell(80, 6, txt($l['acao']), 1);
    $pdf->Cell(30, 6, txt($l['tela']), 1, 1, 'C');
}

// Garante que nenhum erro/aviso PHP saia no arquivo final
ob_end_clean();
$pdf->Output('I', 'Logs_Auditoria_JMM.pdf');
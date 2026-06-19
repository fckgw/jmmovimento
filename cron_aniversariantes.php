<?php
/**
 * JMM SYSTEM - AUTOMAÇÃO DE ANIVERSARIANTES v3.1
 * Envio de e-mails com Telefone e lista de destinatários atualizada.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'config.php'; 
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function enviarEmail($assunto, $corpo) {
    $mail = new PHPMailer(true);
    try {
        // Configurações do Servidor
        $mail->isSMTP();
        $mail->Host       = 'email-ssl.com.br';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'souzafelipe@bdsoft.com.br';
        $mail->Password   = 'BDSoft@2020';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // --- DESTINATÁRIOS CONFIGURADOS ---
        $mail->setFrom('souzafelipe@bdsoft.com.br', 'JMM System - Notificações');
        $mail->addAddress('souzafelipe@bdsoft.com.br', 'Falecom JMM');
        
        // Cópias (CC)
        $mail->addCC('dfnoleto@gmail.com');
        $mail->addCC('souza.ffr@gmail.com'); 
        $mail->addCC('adrianoxxavier@gmail.com'); 

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

$hoje = date('Y-m-d');
$hojeMD = date('m-d');
$diaSemana = date('w'); // 1 = Segunda

// --- 1. RELATÓRIO SEMANAL (SEGUNDA-FEIRA) ---
$sqlConfig = $pdo->query("SELECT valor FROM sistema_config WHERE chave = 'ultimo_envio_semanal'")->fetchColumn();
$segundaFeiraAtual = date('Y-m-d', strtotime('monday this week'));

if ($diaSemana == 1 && $sqlConfig != $segundaFeiraAtual) {
    $inicioSemana = date('m-d');
    $fimSemana = date('m-d', strtotime('+6 days'));

    $sqlSemana = "SELECT nome, telefone, DATE_FORMAT(data_nascimento, '%d/%m') as niver,
                  FLOOR(TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE())) as idade
                  FROM jovens 
                  WHERE DATE_FORMAT(data_nascimento, '%m-%d') BETWEEN ? AND ?
                  ORDER BY DAY(data_nascimento) ASC";
    
    $stmt = $pdo->prepare($sqlSemana);
    $stmt->execute([$inicioSemana, $fimSemana]);
    $aniversariantesSemana = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($aniversariantesSemana) {
        $html = "<div style='font-family: Arial; color: #333;'>";
        $html .= "<h2 style='color: #0d6efd;'>🎂 Planejamento Semanal de Aniversariantes JMM</h2>";
        $html .= "<p>Estes são os jovens que fazem aniversário nesta semana:</p>";
        $html .= "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        $html .= "<tr style='background: #f8f9fa;'><th>JOVEM</th><th>DATA</th><th>IDADE</th><th>TELEFONE</th></tr>";
        foreach ($aniversariantesSemana as $j) {
            $html .= "<tr>
                        <td><b>" . strtoupper($j['nome']) . "</b></td>
                        <td>{$j['niver']}</td>
                        <td>{$j['idade']} anos</td>
                        <td>{$j['telefone']}</td>
                      </tr>";
        }
        $html .= "</table><br><p>Prepare as felicitações!</p></div>";
        
        if (enviarEmail("JMM - Aniversariantes da Semana", $html)) {
            $pdo->prepare("UPDATE sistema_config SET valor = ? WHERE chave = 'ultimo_envio_semanal'")->execute([$segundaFeiraAtual]);
        }
    }
}

// --- 2. NOTIFICAÇÃO DIÁRIA ---
$sqlHoje = "SELECT nome, telefone, DATE_FORMAT(data_nascimento, '%d/%m/%Y') as data_completa,
            FLOOR(TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE())) as idade_hoje
            FROM jovens WHERE DATE_FORMAT(data_nascimento, '%m-%d') = ?";

$stmtH = $pdo->prepare($sqlHoje);
$stmtH->execute([$hojeMD]);
$aniversariantesHoje = $stmtH->fetchAll(PDO::FETCH_ASSOC);

if ($aniversariantesHoje) {
    foreach ($aniversariantesHoje as $hj) {
        $corpo = "
        <div style='font-family: Arial; border: 2px solid #198754; padding: 25px; border-radius: 15px; max-width: 500px;'>
            <h2 style='color: #198754; margin-top: 0;'>🎉 Aniversariante de Hoje!</h2>
            <p>Um membro do JMM está em festa hoje!</p>
            <hr style='border: 0; border-top: 1px solid #eee;'>
            <p><strong>NOME:</strong> <span style='text-transform: uppercase;'>{$hj['nome']}</span></p>
            <p><strong>IDADE:</strong> <span style='font-size: 1.2em; font-weight: bold; color: #0d6efd;'>{$hj['idade_hoje']} anos</span></p>
            <p><strong>DATA:</strong> {$hj['data_completa']}</p>
            <p><strong>TELEFONE:</strong> {$hj['telefone']}</p>
            <hr style='border: 0; border-top: 1px solid #eee;'>
            <div style='text-align: center; margin-top: 20px;'>
                <a href='https://wa.me/55".preg_replace('/\D/','',$hj['telefone'])."' 
                   style='background: #25d366; color: white; padding: 12px 25px; text-decoration: none; border-radius: 30px; font-weight: bold;'>
                   ENVIAR PARABÉNS (WHATSAPP)
                </a>
            </div>
        </div>";
        
        enviarEmail("🎂 HOJE: Aniversário de " . $hj['nome'], $corpo);
    }
}

// Retorno para o disparo manual da Dashboard
if (isset($_GET['manual'])) {
    header("Location: sistema_dashboard.php?cron_ok=1");
    exit;
}

echo "Varredura de aniversariantes concluída em " . date('d/m/Y H:i:s');
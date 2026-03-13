<?php
// Script para baixar PHPMailer direto no servidor
$folders = ['PHPMailer', 'PHPMailer/src'];
foreach($folders as $f) { if(!is_dir($f)) mkdir($f); }

$base = "https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/";
$files = ['PHPMailer.php', 'SMTP.php', 'Exception.php'];

echo "Iniciando download do PHPMailer...<br>";
foreach($files as $file) {
    $content = file_get_contents($base . $file);
    file_put_contents("PHPMailer/src/" . $file, $content);
    echo "Baixado: $file<br>";
}
echo "<b>Pronto! PHPMailer instalado com sucesso.</b>";
?>
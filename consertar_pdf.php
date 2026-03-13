<?php
// Script para baixar a biblioteca FPDF caso ela tenha sumido
$url = "http://www.fpdf.org/en/dl.php?v=186&f=zip";
$zipFile = "fpdf.zip";

echo "Iniciando reparo do PDF...<br>";
file_put_contents($zipFile, fopen($url, 'r'));

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    // Extrai o arquivo fpdf.php para a raiz
    $zip->extractTo('./');
    $zip->close();
    echo "<b>Sucesso! O arquivo fpdf.php foi restaurado na raiz.</b><br>";
    unlink($zipFile);
} else {
    echo "Erro ao extrair. Verifique se a pasta tem permissao de escrita.";
}
?>
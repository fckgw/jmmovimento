<?php
// Script para baixar o FPDF direto no servidor da Locaweb
$url = "http://www.fpdf.org/en/dl.php?v=186&f=zip";
$zipFile = "fpdf.zip";

echo "Baixando FPDF... ";
file_put_contents($zipFile, fopen($url, 'r'));

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    // Extrai apenas o arquivo principal fpdf.php
    $zip->extractTo('./');
    $zip->close();
    echo "Sucesso! O arquivo fpdf.php foi instalado na pasta.<br>";
    unlink($zipFile); // Deleta o zip
} else {
    echo "Erro ao extrair o ZIP. Verifique as permissões da pasta.";
}
?>
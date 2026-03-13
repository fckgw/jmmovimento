<?php
require_once 'config.php';

// 1. Limpa os arquivos físicos
$files = glob(__DIR__ . '/uploads/drive/*'); 
foreach($files as $file){
    if(is_file($file)) unlink($file); 
}

// 2. Limpa o Banco de Dados
$pdo->exec("DELETE FROM arquivos");
$pdo->exec("DELETE FROM pastas");
$pdo->exec("ALTER TABLE arquivos AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE pastas AUTO_INCREMENT = 1");

echo "<h1>Drive Limpo com Sucesso!</h1>";
echo "<p>Arquivos e registros foram apagados. Comece os testes agora.</p>";
echo "<a href='drive.php'>Ir para o Drive</a>";
?>
<?php
require_once 'config.php';

try {
    // Adiciona as colunas ignorando erros caso elas já existam
    $pdo->exec("ALTER TABLE financeiro ADD COLUMN estabelecimento VARCHAR(255) AFTER tipo");
    $pdo->exec("ALTER TABLE financeiro ADD COLUMN forma_pagamento VARCHAR(100) AFTER valor");
    $pdo->exec("ALTER TABLE financeiro ADD COLUMN itens_json LONGTEXT AFTER comprovante_url");
    
    echo "<h1>Sucesso!</h1><p>As colunas foram adicionadas. O erro 1054 deve sumir agora.</p>";
    echo "<a href='financeiro.php'>Voltar para o Financeiro</a>";
} catch (Exception $e) {
    echo "<h1>Aviso</h1><p>Erro ou Colunas já existentes: " . $e->getMessage() . "</p>";
}
?>
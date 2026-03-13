<?php
require 'config.php';

try {
    // Adiciona colunas novas na tabela existente
    $sql = "ALTER TABLE financeiro 
            ADD COLUMN estabelecimento VARCHAR(255) NULL AFTER id,
            ADD COLUMN forma_pagamento VARCHAR(50) NULL AFTER valor,
            ADD COLUMN itens_json LONGTEXT NULL AFTER comprovante_url";
    
    $pdo->exec($sql);
    echo "Tabela atualizada com sucesso! (Colunas Estabelecimento, Pagamento e Itens criadas)";

} catch (PDOException $e) {
    // Se der erro, provavelmente as colunas já existem
    echo "Aviso (pode ignorar se já rodou): " . $e->getMessage();
}
?>
<?php
require 'config.php';

try {
    // 1. Tabela Financeira
    $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('pagar', 'receber') NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        vencimento DATE NOT NULL,
        status ENUM('pendente', 'pago') DEFAULT 'pendente',
        comprovante_url VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "Tabela 'financeiro' criada com sucesso!<br>";
    echo "Agora você pode apagar este arquivo.";

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
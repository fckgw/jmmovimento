<?php
require_once 'config.php';

try {
    echo "<h2>Atualizando Tabelas para Cabo de Guerra...</h2>";

    // Cria/Atualiza cg_times
    $pdo->exec("CREATE TABLE IF NOT EXISTS cg_times (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_santo VARCHAR(100) NOT NULL
    )");
    
    // Adiciona colunas se não existirem (usando try/catch para ignorar se já existirem)
    try { $pdo->exec("ALTER TABLE cg_times ADD COLUMN capitao_id INT NULL"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE cg_times ADD COLUMN pontos INT DEFAULT 0"); } catch(Exception $e){}

    // Cria cg_disputas
    $pdo->exec("CREATE TABLE IF NOT EXISTS cg_disputas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        time_a_id INT,
        time_b_id INT,
        vencedor_id INT,
        tempo_segundos INT DEFAULT 0,
        fase ENUM('quartas', 'semi', 'final') DEFAULT 'quartas',
        status ENUM('pendente', 'concluido') DEFAULT 'pendente'
    )");

    echo "<b>Sucesso! O banco de dados foi sincronizado com o Cabo de Guerra.</b><br>";
    echo "<a href='gincana.php'>Voltar para o Sistema</a>";

} catch (PDOException $e) {
    die("Erro ao atualizar: " . $e->getMessage());
}
?>
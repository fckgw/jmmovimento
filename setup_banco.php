<?php
require_once 'config.php';

try {
    echo "<h2>Iniciando Configuração do Banco JMMovimento...</h2>";

    // 1. Tabela de Usuários
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        senha VARCHAR(255)
    )");
    echo "- Tabela 'usuarios' OK<br>";

    // 2. Tabela de Jovens
    $pdo->exec("CREATE TABLE IF NOT EXISTS jovens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100),
        telefone VARCHAR(20),
        ano_nascimento INT,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabela 'jovens' OK<br>";

    // 3. Tabela de Grupos/Equipes
    $pdo->exec("CREATE TABLE IF NOT EXISTS grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_time VARCHAR(100)
    )");
    echo "- Tabela 'grupos' OK<br>";

    // 4. Tabela de Membros das Equipes
    $pdo->exec("CREATE TABLE IF NOT EXISTS membros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo_id INT,
        nome VARCHAR(100)
    )");
    echo "- Tabela 'membros' OK<br>";

    // 5. Tabela de Registros da Gincana (Cronômetro)
    $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo_id INT,
        inicio DATETIME,
        fim DATETIME,
        pausa_inicio DATETIME,
        total_pausa_segundos INT DEFAULT 0,
        status ENUM('pendente', 'rodando', 'pausado', 'finalizado') DEFAULT 'pendente'
    )");
    echo "- Tabela 'registros' OK<br>";

    // 6. Tabela Financeira
    $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('pagar', 'receber'),
        estabelecimento VARCHAR(255),
        descricao TEXT,
        valor DECIMAL(10,2),
        forma_pagamento VARCHAR(100),
        vencimento DATETIME,
        status VARCHAR(50),
        comprovante_url VARCHAR(255),
        itens_json LONGTEXT
    )");
    echo "- Tabela 'financeiro' OK<br>";

    // 7. CRIAR SEU USUÁRIO DE ACESSO
    $email = 'souzafelipe@bdsoft.com.br';
    $senha = password_hash('Fckgw!151289', PASSWORD_DEFAULT);
    
    $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check->execute([$email]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
        $ins->execute(['Felipe Souza', $email, $senha]);
        echo "<b>- Usuário ADMIN criado com sucesso!</b><br>";
    } else {
        echo "- Usuário ADMIN já existia.<br>";
    }


    // Adicione isso dentro do try do seu setup.php atual:

$pdo->exec("CREATE TABLE IF NOT EXISTS encontros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_encontro DATE NOT NULL,
    local_encontro VARCHAR(255) NOT NULL,
    status ENUM('aberto', 'finalizado') DEFAULT 'aberto',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS presencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jovem_id INT NOT NULL,
    encontro_id INT NOT NULL,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    foto_path VARCHAR(255),
    metodo ENUM('qrcode', 'manual') DEFAULT 'qrcode'
)");

// Inserção de segurança para não dar erro de tabela vazia
$pdo->exec("INSERT IGNORE INTO encontros (data_encontro, local_encontro, status) VALUES 
('2026-02-28', 'Local Anterior', 'finalizado'),
('2026-03-14', 'Nossa Senhora Aparecida', 'aberto')");

echo "- Tabelas de Presença e Encontros criadas com sucesso!<br>";

    echo "<br>--- TUDO PRONTO! ---<br>";
    echo "<a href='login.php'>Clique aqui para ir ao Login</a>";

} catch (PDOException $e) {
    die("<br>ERRO AO CONFIGURAR: " . $e->getMessage());
}
?>
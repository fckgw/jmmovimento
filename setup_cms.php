<?php
// setup_cms.php
require 'config.php';

try {
    // Tabela de Páginas do Site
    $sql = "CREATE TABLE IF NOT EXISTS site_paginas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL, -- Ex: 'quem-somos'
        conteudo LONGTEXT,
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Cria a página inicial padrão se estiver vazia
    $check = $pdo->query("SELECT count(*) FROM site_paginas")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("INSERT INTO site_paginas (titulo, slug, conteudo) VALUES 
        ('Página Inicial', 'home', '<h1>Bem-vindo ao JMM</h1><p>Este é o conteúdo inicial do site.</p>'),
        ('Quem Somos', 'quem-somos', '<h1>Nossa História</h1><p>Conteúdo sobre o grupo...</p>')");
        echo "Tabelas e páginas padrão criadas!";
    } else {
        echo "Tabelas já existem.";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
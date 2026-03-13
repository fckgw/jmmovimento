<?php
require_once 'config.php';

// 1. Criar tabela de usuários se não existir
$sql = "CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    senha VARCHAR(255)
)";
$pdo->exec($sql);

// 2. Inserir seu usuário com a senha criptografada
$email = 'souzafelipe@bdsoft.com.br';
$senha_plana = 'Fckgw!151289';
$senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);

if (!$stmt->fetch()) {
    $ins = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
    $ins->execute(['Felipe Souza', $email, $senha_hash]);
    echo "Usuário criado com sucesso!";
} else {
    // Atualiza a senha caso o usuário já exista
    $upd = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
    $upd->execute([$senha_hash, $email]);
    echo "Senha do usuário atualizada!";
}
?>
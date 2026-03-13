<?php
// ARQUIVO: config.php

// 1. Forçar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Iniciar Sessão e Fuso Horário
session_start();
date_default_timezone_set('America/Sao_Paulo');

// 3. Definição do Banco de Dados
$server_host = $_SERVER['HTTP_HOST'];


function registrarLog($pdo, $acao, $tela) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_nome = $_SESSION['user_nome'] ?? 'Sistema/Visitante';
    $stmt = $pdo->prepare("INSERT INTO logs (usuario_id, usuario_nome, acao, tela) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $user_nome, $acao, $tela]);
}


// Verifica se é Produção ou Homologação
if (strpos($server_host, 'jmmovimento.com.br') !== false) {
    // PRODUÇÃO
    $host = 'jmmovimento.mysql.dbaas.com.br';
    $db_name = 'jmmovimento';
    $user = 'jmmovimento';
    $pass = 'BDSoft@1020';
} else {
    // HOMOLOGAÇÃO / TESTE (BDSOFT)
    $host = 'jovensmatriz.mysql.dbaas.com.br';
    $db_name = 'jovensmatriz';
    $user = 'jovensmatriz';
    $pass = 'BDSoft@1020';
}

// 4. Tentativa de Conexão
try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Configura timezone do MySQL
    $pdo->exec("SET time_zone = '-03:00'");

} catch (PDOException $e) {
    // Se der erro de conexão, mostra na tela
    die("<div style='color:red; background:white; padding:20px; border:2px solid red;'>
            <h2>ERRO CRÍTICO DE BANCO DE DADOS</h2>
            <p><b>Mensagem:</b> " . $e->getMessage() . "</p>
            <p><b>Host tentado:</b> $host</p>
            <hr>
            <small>Dica: Se você está no bdsoft.com.br e o banco é locaweb, você precisa liberar o IP do bdsoft no painel da locaweb.</small>
         </div>");
}
?>
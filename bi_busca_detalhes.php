<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { exit(json_encode([])); }

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
if ($mes < 1 || $mes > 12) { exit(json_encode([])); }

$sql = "SELECT nome, data_nascimento, telefone,
        DATE_FORMAT(data_nascimento, '%d/%m/%Y') as data_nasc_formatada,
        (YEAR(CURDATE()) - YEAR(data_nascimento)) as idade,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '-', ''), ' ', ''), '(', ''), ')', ''), '.', '') as tel_limpo
        FROM jovens 
        WHERE MONTH(data_nascimento) = :mes 
        HAVING LENGTH(tel_limpo) >= 11
        ORDER BY DAY(data_nascimento) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':mes' => $mes]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
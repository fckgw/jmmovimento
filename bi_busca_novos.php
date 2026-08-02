<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !isset($_GET['data'])) { exit(json_encode([])); }

$data_enc = $_GET['data'];

// Busca jovens cujo MIN(id) de presença pertence a este encontro/data
$sql = "SELECT j.nome, j.telefone, j.sexo, 
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(j.telefone, '-', ''), ' ', ''), '(', ''), ')', ''), '.', '') as tel_limpo
        FROM jovens j
        INNER JOIN presencas p ON j.id = p.jovem_id
        INNER JOIN encontros e ON p.encontro_id = e.id
        WHERE e.data_encontro = :data
        AND p.id IN (SELECT MIN(id) FROM presencas GROUP BY jovem_id)
        ORDER BY j.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':data' => $data_enc]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
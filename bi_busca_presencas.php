<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !isset($_GET['jovem_id'])) { exit(json_encode([])); }

$jovem_id = (int)$_GET['jovem_id'];

$sql = "SELECT e.data_encontro, e.tema, e.local_encontro, p.metodo,
        DATE_FORMAT(e.data_encontro, '%d/%m/%Y') as data_formatada
        FROM presencas p
        INNER JOIN encontros e ON p.encontro_id = e.id
        WHERE p.jovem_id = :jovem_id
        ORDER BY e.data_encontro DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':jovem_id' => $jovem_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
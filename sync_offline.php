<?php
require_once 'config.php';
header('Content-Type: application/json');

$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) { exit; }

$sucesso = 0;
foreach ($dados as $item) {
    // Evita duplicidade: verifica se o jovem já tem presença no encontro
    $st = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
    $st->execute([$item['j_id'], $item['e_id']]);
    
    if (!$st->fetch()) {
        $ins = $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)");
        if ($ins->execute([$item['j_id'], $item['e_id']])) {
            $sucesso++;
        }
    }
}
echo json_encode(['status' => 'ok', 'sincronizados' => $sucesso]);
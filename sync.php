<?php
require_once 'config.php';
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) exit;

foreach ($data as $item) {
    if ($item['tipo'] == 'novo_jovem') {
        $pdo->prepare("INSERT INTO jovens (nome, telefone, sexo, ano_nascimento) VALUES (?, ?, ?, ?)")
            ->execute([$item['nome'], $item['telefone'], $item['sexo'], $item['ano']]);
    } 
    if ($item['tipo'] == 'checkin') {
        // Evita duplicidade se sincronizar duas vezes
        $check = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
        $check->execute([$item['j_id'], $item['e_id']]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id) VALUES (?, ?)")
                ->execute([$item['j_id'], $item['e_id']]);
        }
    }
}
echo json_encode(['status' => 'sincronizado']);
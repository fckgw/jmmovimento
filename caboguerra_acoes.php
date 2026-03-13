<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) exit;

// --- AÇÃO: GERAR TIMES AUTOMATICAMENTE ---
if (isset($_POST['gerar_times'])) {
    $num_times = (int)$_POST['qtd_times'];
    
    // Limpa dados anteriores
    $pdo->exec("DELETE FROM cg_membros");
    $pdo->exec("DELETE FROM cg_times");
    $pdo->exec("DELETE FROM cg_disputas");

    // Lista de Santos para nomes
    $santos = ['São Bento', 'Santa Teresinha', 'São Jorge', 'N. Sra. Aparecida', 'Santo Antônio', 'São Francisco', 'Santa Rita', 'São Judas Tadeu'];
    shuffle($santos);

    // Busca todos os jovens cadastrados
    $jovens = $pdo->query("SELECT id FROM jovens")->fetchAll(PDO::FETCH_COLUMN);
    shuffle($jovens);

    // Cria os times
    for ($i = 0; $i < $num_times; $i++) {
        $nome = $santos[$i] ?? "Time " . ($i + 1);
        $stmt = $pdo->prepare("INSERT INTO cg_times (nome_santo) VALUES (?)");
        $stmt->execute([$nome]);
        $times_ids[] = $pdo->lastInsertId();
    }

    // Distribui jovens nos times
    $time_index = 0;
    foreach ($jovens as $j_id) {
        $stmt = $pdo->prepare("INSERT INTO cg_membros (time_id, jovem_id) VALUES (?, ?)");
        $stmt->execute([$times_ids[$time_index], $j_id]);
        $time_index = ($time_index + 1) % $num_times;
    }

    registrarLog($pdo, "Gerou $num_times times automaticamente para Cabo de Guerra", "Cabo de Guerra");
    header("Location: gincana.php?tab=cg");
    exit;
}

// --- AÇÃO: REGISTRAR VENCEDOR ---
if (isset($_POST['registrar_vitoria'])) {
    $disputa_id = $_POST['disputa_id'];
    $vencedor_id = $_POST['vencedor_id'];
    $tempo = $_POST['tempo_final'];

    $stmt = $pdo->prepare("UPDATE cg_disputas SET vencedor_id = ?, tempo_segundos = ?, status = 'concluido' WHERE id = ?");
    $stmt->execute([$vencedor_id, $tempo, $disputa_id]);

    // Soma 3 pontos para o time
    $pdo->prepare("UPDATE cg_times SET pontos = pontos + 3 WHERE id = ?")->execute([$vencedor_id]);

    registrarLog($pdo, "Registrou vitória na disputa ID $disputa_id", "Cabo de Guerra");
    header("Location: gincana.php?tab=cg");
    exit;
}
<?php
/**
 * JMM SYSTEM - PROCESSAMENTO DE PRESENÇAS E CADASTRO RÁPIDO
 * Este arquivo atende às requisições AJAX do Admin (Chamada) e do Jovem (Check-in)
 */

require_once 'config.php';

// Define que o retorno será sempre JSON
header('Content-Type: application/json');

// 1. AÇÃO: BUSCAR JOVEM (Para Check-in Manual ou Identificação no QR Code)
if (isset($_GET['buscar'])) {
    $termo = trim($_GET['buscar']);

    if (strlen($termo) < 2) {
        echo json_encode([]);
        exit;
    }

    try {
        $busca = "%" . $termo . "%";
        // Busca por Nome ou por Telefone
        $stmt = $pdo->prepare("SELECT id, nome, telefone FROM jovens WHERE nome LIKE ? OR telefone LIKE ? ORDER BY nome ASC LIMIT 10");
        $stmt->execute([$busca, $busca]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($resultados);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. AÇÃO: CADASTRO RÁPIDO COMPLETO (Via Popup/Modal na Chamada)
if (isset($_GET['cadastrar_rapido_completo'])) {
    $nome = trim($_POST['nome'] ?? '');
    $tel  = trim($_POST['tel'] ?? '');
    $data_nasc = !empty($_POST['data_nasc']) ? $_POST['data_nasc'] : null;
    $ano = !empty($_POST['ano']) ? (int)$_POST['ano'] : null;

    if (empty($nome)) {
        echo json_encode(['status' => 'error', 'message' => 'O nome é obrigatório.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO jovens (nome, telefone, ano_nascimento, data_nascimento) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $tel, $ano, $data_nasc]);
        
        $novo_id = $pdo->lastInsertId();

        // Registra no Log de Auditoria
        if (function_exists('registrarLog')) {
            registrarLog($pdo, "Cadastrou jovem via Popup de Chamada: $nome", "Chamada");
        }

        echo json_encode(['status' => 'ok', 'id' => $novo_id]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar: ' . $e->getMessage()]);
    }
    exit;
}

// 3. AÇÃO: SALVAR PRESENÇA MANUAL (Admin clicando no nome encontrado)
if (isset($_GET['salvar_manual'])) {
    $jovem_id    = $_POST['jovem_id'] ?? null;
    $encontro_id = $_POST['encontro_id'] ?? null;

    if (!$jovem_id || !$encontro_id) {
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos para presença manual.']);
        exit;
    }

    try {
        // Verifica se já não foi marcado para evitar duplicidade
        $check = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
        $check->execute([$jovem_id, $encontro_id]);

        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id, metodo) VALUES (?, ?, 'manual')");
            $stmt->execute([$jovem_id, $encontro_id]);
            
            if (function_exists('registrarLog')) {
                registrarLog($pdo, "Marcou presença manual Jovem ID: $jovem_id", "Chamada");
            }
        }
        echo json_encode(['status' => 'ok']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 4. AÇÃO: SALVAR PRESENÇA VIA QR CODE (Com Selfie do Jovem)
if (isset($_GET['salvar'])) {
    $j_id = $_POST['jovem_id'] ?? null;
    $e_id = $_POST['encontro_id'] ?? null;
    $foto_base64 = $_POST['foto'] ?? null;

    if (!$j_id || !$e_id || !$foto_base64) {
        echo json_encode(['status' => 'error', 'message' => 'Dados da selfie incompletos.']);
        exit;
    }

    try {
        // Processar e salvar a imagem física
        $pasta_destino = __DIR__ . "/uploads/presencas/";
        if (!is_dir($pasta_destino)) {
            mkdir($pasta_destino, 0755, true);
        }

        // Remove o cabeçalho do base64 (data:image/jpeg;base64,)
        $img_limpa = str_replace('data:image/jpeg;base64,', '', $foto_base64);
        $img_limpa = str_replace(' ', '+', $img_limpa);
        $dados_da_imagem = base64_decode($img_limpa);

        // Gera nome único para a foto
        $nome_arquivo = "selfie_" . $j_id . "_" . $e_id . "_" . time() . ".jpg";
        $caminho_completo = $pasta_destino . $nome_arquivo;

        if (file_put_contents($caminho_completo, $dados_da_imagem)) {
            // Salva o registro no banco de dados
            $stmt = $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id, foto_path, metodo) VALUES (?, ?, ?, 'qrcode')");
            $stmt->execute([$j_id, $e_id, $nome_arquivo]);

            echo json_encode(['status' => 'ok', 'message' => 'Presença confirmada com foto!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao gravar a foto no servidor.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Resposta padrão caso nenhum parâmetro seja atendido
echo json_encode(['status' => 'error', 'message' => 'Ação não permitida.']);
<?php
/**
 * JMM SYSTEM - PROCESSAMENTO DE PRESENÇAS, BUSCA INTELIGENTE E CADASTRO RÁPIDO
 * Este arquivo atende às requisições AJAX do Admin (Chamada) e do Jovem (Check-in)
 */

require_once 'config.php';

// Define que o retorno será sempre JSON para comunicação com o JavaScript/AJAX
header('Content-Type: application/json');

// 1. AÇÃO: BUSCAR JOVEM (Para Check-in Manual ou Identificação no QR Code)
// Agora permite buscar por Nome, Celular ou Data de Nascimento (AAAA-MM-DD)
if (isset($_GET['buscar'])) {
    $termo = trim($_GET['buscar']);

    // Exige ao menos 2 caracteres para iniciar a busca e evitar sobrecarga
    if (strlen($termo) < 2) {
        echo json_encode([]);
        exit;
    }

    try {
        $busca = "%" . $termo . "%";
        
        // SQL Otimizado: Busca em três colunas simultaneamente
        $sql = "SELECT id, nome, telefone, data_nascimento, ano_nascimento 
                FROM jovens 
                WHERE nome LIKE ? 
                OR telefone LIKE ? 
                OR data_nascimento LIKE ? 
                ORDER BY nome ASC 
                LIMIT 15";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$busca, $busca, $busca]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retorna a lista de jovens encontrados
        echo json_encode($resultados);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro na busca: ' . $e->getMessage()]);
    }
    exit;
}

// 2. AÇÃO: CADASTRO RÁPIDO COMPLETO (Via Popup/Modal na aba de Chamada)
// Garante que a Data de Nascimento e o Telefone sejam salvos corretamente
if (isset($_GET['cadastrar_rapido_completo'])) {
    $nome       = trim($_POST['nome'] ?? '');
    $tel        = trim($_POST['tel'] ?? '');
    $data_nasc  = !empty($_POST['data_nasc']) ? $_POST['data_nasc'] : null;
    $ano        = !empty($_POST['ano']) ? (int)$_POST['ano'] : null;

    if (empty($nome)) {
        echo json_encode(['status' => 'error', 'message' => 'O nome completo é obrigatório para o cadastro.']);
        exit;
    }

    try {
        // Insere o jovem na base principal de dados
        $sql = "INSERT INTO jovens (nome, telefone, ano_nascimento, data_nascimento, data_cadastro) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $tel, $ano, $data_nasc]);
        
        $novo_id = $pdo->lastInsertId();

        // Registra a ação no LOG de Auditoria (Função presente no config.php)
        if (function_exists('registrarLog')) {
            registrarLog($pdo, "Cadastrou jovem via Popup (Chamada): $nome", "Chamada");
        }

        // Retorna o ID gerado para que o sistema possa marcar a presença dele logo em seguida
        echo json_encode(['status' => 'ok', 'id' => $novo_id]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao realizar cadastro rápido: ' . $e->getMessage()]);
    }
    exit;
}

// 3. AÇÃO: SALVAR PRESENÇA MANUAL (Admin clicando no nome ou via Check-in em Massa)
if (isset($_GET['salvar_manual'])) {
    // Segurança básica: Verifica se existe sessão de usuário logado
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Por favor, faça login.']);
        exit;
    }

    $jovem_id    = $_POST['jovem_id'] ?? null;
    $encontro_id = $_POST['encontro_id'] ?? null;

    if (!$jovem_id || !$encontro_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID do jovem ou do encontro não informado.']);
        exit;
    }

    try {
        // Verifica se o jovem já tem presença marcada para este encontro (evita duplicidade no banco)
        $check = $pdo->prepare("SELECT id FROM presencas WHERE jovem_id = ? AND encontro_id = ?");
        $check->execute([$jovem_id, $encontro_id]);

        if (!$check->fetch()) {
            // Caso não esteja presente, insere o registro
            $stmt = $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id, metodo, data_hora) VALUES (?, ?, 'manual', NOW())");
            $stmt->execute([$jovem_id, $encontro_id]);
            
            // Grava Log se necessário (Opcional para não poluir logs de check-in em massa)
            // registrarLog($pdo, "Presença manual: Jovem ID $jovem_id", "Chamada");
            
            echo json_encode(['status' => 'ok', 'message' => 'Presença confirmada.']);
        } else {
            echo json_encode(['status' => 'ok', 'message' => 'O jovem já estava presente neste encontro.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar presença: ' . $e->getMessage()]);
    }
    exit;
}

// 4. AÇÃO: SALVAR PRESENÇA VIA QR CODE (Com Selfie enviada pelo Jovem)
if (isset($_GET['salvar'])) {
    $j_id = $_POST['jovem_id'] ?? null;
    $e_id = $_POST['encontro_id'] ?? null;
    $foto_base64 = $_POST['foto'] ?? null;

    if (!$j_id || !$e_id || !$foto_base64) {
        echo json_encode(['status' => 'error', 'message' => 'Dados de identificação ou imagem ausentes.']);
        exit;
    }

    try {
        // 4.1. Processamento da Imagem (Decodifica a string Base64 da câmera)
        $pasta_destino = __DIR__ . "/uploads/presencas/";
        
        // Cria a pasta se não existir e garante permissões
        if (!is_dir($pasta_destino)) {
            mkdir($pasta_destino, 0755, true);
        }

        // Remove o prefixo do Base64 enviado pelo navegador (data:image/jpeg;base64,)
        $img_data = str_replace('data:image/jpeg;base64,', '', $foto_base64);
        $img_data = str_replace(' ', '+', $img_data);
        $dados_binarios = base64_decode($img_data);

        // Gera um nome de arquivo único para a selfie
        $nome_arquivo = "selfie_" . $j_id . "_" . $e_id . "_" . time() . ".jpg";
        $caminho_final = $pasta_destino . $nome_arquivo;

        // Tenta salvar o arquivo físico no servidor
        if (file_put_contents($caminho_final, $dados_binarios)) {
            
            // 4.2. Grava o registro no banco de dados vinculando ao caminho da foto
            $stmt = $pdo->prepare("INSERT INTO presencas (jovem_id, encontro_id, foto_path, metodo, data_hora) VALUES (?, ?, ?, 'qrcode', NOW())");
            $stmt->execute([$j_id, $e_id, $nome_arquivo]);

            echo json_encode(['status' => 'ok', 'message' => 'Check-in realizado com sucesso!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro técnico: Não foi possível salvar a imagem no servidor.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Falha geral: ' . $e->getMessage()]);
    }
    exit;
}

// Resposta final caso o arquivo seja acessado sem os parâmetros esperados
echo json_encode(['status' => 'error', 'message' => 'Nenhuma solicitação válida recebida.']);
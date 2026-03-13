<?php
/**
 * JMM SYSTEM - PROCESSAMENTO DRIVE (RESOLUÇÃO DE TELA BRANCA)
 */
ob_start(); // Inicia o buffer para evitar erro de redirecionamento
require_once 'config.php';

// 1. SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado. Sessão expirada.");
}

$user_id = $_SESSION['user_id'];
$user_nivel = $_SESSION['nivel'];
$diretorio_raiz = __DIR__ . "/uploads/drive/";

// --- AÇÃO: DELETAR ARQUIVO ÚNICO ---
if (isset($_GET['del_arq'])) {
    $id_a = (int)$_GET['del_arq'];

    // Busca dados do arquivo e id da pasta para saber para onde voltar
    $stmt = $pdo->prepare("SELECT nome_sistema, nome_original, usuario_id, pasta_id FROM arquivos WHERE id = ?");
    $stmt->execute([$id_a]);
    $arq = $stmt->fetch();

    if ($arq) {
        // Verifica permissão (Dono ou Admin)
        if ($arq['usuario_id'] == $user_id || $user_nivel == 'admin') {
            $caminho_arquivo = $diretorio_raiz . $arq['nome_sistema'];
            
            // Tenta apagar o arquivo físico
            if (file_exists($caminho_arquivo)) {
                @unlink($caminho_arquivo);
            }

            // Remove do banco
            $pdo->prepare("DELETE FROM arquivos WHERE id = ?")->execute([$id_a]);
            registrarLog($pdo, "Excluiu arquivo: " . $arq['nome_original'], "Drive");

            // Define para onde voltar (mantém na mesma pasta)
            $url_retorno = "drive.php" . ($arq['pasta_id'] ? "?pasta=" . $arq['pasta_id'] : "");
            header("Location: " . $url_retorno . "&sucesso=Arquivo removido com sucesso");
            exit;
        } else {
            header("Location: drive.php?erro=Você não tem permissão para excluir este arquivo");
            exit;
        }
    }
    header("Location: drive.php?erro=Arquivo não encontrado");
    exit;
}

// --- AÇÃO: DELETAR PASTA ---
if (isset($_GET['del_pasta'])) {
    $id_p = (int)$_GET['del_pasta'];

    // Busca a pasta
    $stmt = $pdo->prepare("SELECT nome, usuario_id, pai_id FROM pastas WHERE id = ?");
    $stmt->execute([$id_p]);
    $pasta = $stmt->fetch();

    if ($pasta) {
        // Somente Dono ou Admin deleta a PASTA
        if ($pasta['usuario_id'] == $user_id || $user_nivel == 'admin') {
            
            // 1. Busca todos os arquivos de dentro dessa pasta no banco
            $stmt_arq = $pdo->prepare("SELECT nome_sistema FROM arquivos WHERE pasta_id = ?");
            $stmt_arq->execute([$id_p]);
            $arquivos_internos = $stmt_arq->fetchAll();

            // 2. Apaga arquivos físicos
            foreach ($arquivos_internos as $ai) {
                $f_path = $diretorio_raiz . $ai['nome_sistema'];
                if (file_exists($f_path)) { @unlink($f_path); }
            }

            // 3. Deleta registros (Cascade Manual)
            $pdo->prepare("DELETE FROM arquivos WHERE pasta_id = ?")->execute([$id_p]);
            $pdo->prepare("DELETE FROM pastas WHERE id = ?")->execute([$id_p]);

            registrarLog($pdo, "Excluiu pasta completa: " . $pasta['nome'], "Drive");

            $url_back = "drive.php" . ($pasta['pai_id'] ? "?pasta=" . $pasta['pai_id'] : "");
            header("Location: " . $url_back . "&sucesso=Pasta removida");
            exit;
        } else {
            header("Location: drive.php?erro=Apenas o dono pode excluir a pasta");
            exit;
        }
    }
    header("Location: drive.php?erro=Pasta não encontrada");
    exit;
}

// --- AÇÃO: MOVER ARQUIVOS (AJAX) ---
if (isset($_POST['acao_mover'])) {
    $destino_id = ($_POST['destino_id'] === 'null' || empty($_POST['destino_id'])) ? null : (int)$_POST['destino_id'];
    $ids = $_POST['ids'] ?? [];

    if (!empty($ids)) {
        foreach ($ids as $id_arq) {
            $pdo->prepare("UPDATE arquivos SET pasta_id = ? WHERE id = ? AND (usuario_id = ? OR '$user_nivel' = 'admin')")
                ->execute([$destino_id, (int)$id_arq, $user_id]);
        }
        registrarLog($pdo, "Moveu " . count($ids) . " arquivos", "Drive");
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nenhum item selecionado']);
    }
    exit;
}

// --- AÇÃO: DELETAR EM MASSA (AJAX) ---
if (isset($_POST['acao_deletar_massa'])) {
    $ids = $_POST['ids'] ?? [];
    $sucessos = 0;
    $falhas = 0;

    foreach ($ids as $id_arq) {
        $stmt = $pdo->prepare("SELECT a.id, a.nome_sistema, a.usuario_id, a.pasta_id, p.publica FROM arquivos a LEFT JOIN pastas p ON a.pasta_id = p.id WHERE a.id = ?");
        $stmt->execute([(int)$id_arq]);
        $arq = $stmt->fetch();

        if ($arq) {
            $pode = ($arq['usuario_id'] == $user_id || $user_nivel == 'admin' || ($arq['pasta_id'] && $arq['publica'] == 1));
            if ($pode) {
                @unlink($diretorio_raiz . $arq['nome_sistema']);
                $pdo->prepare("DELETE FROM arquivos WHERE id = ?")->execute([$arq['id']]);
                $sucessos++;
            } else {
                $falhas++;
            }
        }
    }
    echo json_encode(['status' => 'ok', 'sucessos' => $sucessos, 'falhas' => $falhas]);
    exit;
}

// --- AÇÃO: RENOMEAR ---
if (isset($_POST['acao_renomear'])) {
    $id_pasta = (int)$_POST['id_pasta'];
    $pdo->prepare("UPDATE pastas SET nome = ? WHERE id = ? AND (usuario_id = ? OR '$user_nivel' = 'admin')")
        ->execute([$_POST['novo_nome'], $id_pasta, $user_id]);
    header("Location: drive.php?pasta=$id_pasta&sucesso=Nome alterado");
    exit;
}

// --- AÇÃO: CRIAR PASTA ---
if (isset($_POST['acao_pasta'])) {
    $nome = trim($_POST['nome_pasta']);
    $pai_id = (!empty($_POST['pai_id']) && $_POST['pai_id'] !== 'null') ? (int)$_POST['pai_id'] : null;
    $publica = isset($_POST['publica']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO pastas (nome, pai_id, usuario_id, publica) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nome, $pai_id, $user_id, $publica]);
    header("Location: drive.php?pasta=" . ($pai_id ?? "") . "&sucesso=Pasta criada");
    exit;
}

// --- AÇÃO: UPLOAD AJAX ---
if (isset($_POST['acao_upload'])) {
    $pasta_id = (!empty($_POST['pasta_id']) && $_POST['pasta_id'] !== 'null') ? (int)$_POST['pasta_id'] : null;
    
    // Verifica se a pasta atual é pública para permitir upload de outros membros
    $pode_subir = true;
    if($pasta_id) {
        $st = $pdo->prepare("SELECT usuario_id, publica FROM pastas WHERE id = ?");
        $st->execute([$pasta_id]);
        $f = $st->fetch();
        if($f['usuario_id'] != $user_id && $f['publica'] == 0 && $user_nivel != 'admin') {
            $pode_subir = false;
        }
    }

    if(!$pode_subir) {
        echo json_encode(['status' => 'error', 'message' => 'Você não tem permissão de upload nesta pasta privada']);
        exit;
    }

    foreach ($_FILES['arquivos']['tmp_name'] as $k => $tmp_name) {
        if ($_FILES['arquivos']['error'][$k] === UPLOAD_ERR_OK) {
            $nome_original = $_FILES['arquivos']['name'][$k];
            $ext = pathinfo($nome_original, PATHINFO_EXTENSION);
            $nome_sistema = uniqid() . "_drive." . $ext;
            
            if (move_uploaded_file($tmp_name, $diretorio_raiz . $nome_sistema)) {
                $stmt = $pdo->prepare("INSERT INTO arquivos (nome_original, nome_sistema, tamanho, tipo_mime, pasta_id, usuario_id, data_upload) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$nome_original, $nome_sistema, $_FILES['arquivos']['size'][$k], $_FILES['arquivos']['type'][$k], $pasta_id, $user_id]);
            }
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}
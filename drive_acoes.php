<?php
/**
 * JMM SYSTEM - PROCESSAMENTO DRIVE (VERSÃO ESTÁVEL)
 */
ob_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { exit; }
$user_id = $_SESSION['user_id'];
$user_nivel = $_SESSION['nivel'];
$diretorio_raiz = __DIR__ . "/uploads/drive/";

// --- AÇÃO: DELETAR PASTA ---
if (isset($_GET['del_pasta'])) {
    $id_p = (int)$_GET['del_pasta'];
    $stmt = $pdo->prepare("SELECT nome, usuario_id FROM pastas WHERE id = ?");
    $stmt->execute([$id_p]);
    $pasta = $stmt->fetch();

    if ($pasta && ($pasta['usuario_id'] == $user_id || $user_nivel == 'admin')) {
        $stmt_arq = $pdo->prepare("SELECT nome_sistema FROM arquivos WHERE pasta_id = ?");
        $stmt_arq->execute([$id_p]);
        while($arq = $stmt_arq->fetch()) { @unlink($diretorio_raiz . $arq['nome_sistema']); }
        $pdo->prepare("DELETE FROM arquivos WHERE pasta_id = ?")->execute([$id_p]);
        $pdo->prepare("DELETE FROM pastas WHERE id = ?")->execute([$id_p]);
        registrarLog($pdo, "Excluiu pasta: " . $pasta['nome'], "Drive");
        header("Location: drive.php?sucesso=Pasta removida");
        exit;
    }
    header("Location: drive.php?erro=Acesso negado");
    exit;
}

// --- AÇÃO: DELETAR ARQUIVO ÚNICO ---
if (isset($_GET['del_arq'])) {
    $id_a = (int)$_GET['del_arq'];
    $stmt = $pdo->prepare("SELECT a.nome_sistema, a.usuario_id, p.publica FROM arquivos a LEFT JOIN pastas p ON a.pasta_id = p.id WHERE a.id = ?");
    $stmt->execute([$id_a]);
    $arq = $stmt->fetch();

    if ($arq && ($arq['usuario_id'] == $user_id || $user_nivel == 'admin' || ($arq['publica'] == 1))) {
        @unlink($diretorio_raiz . $arq['nome_sistema']);
        $pdo->prepare("DELETE FROM arquivos WHERE id = ?")->execute([$id_a]);
        header("Location: " . $_SERVER['HTTP_REFERER'] . "&sucesso=Arquivo removido");
        exit;
    }
    header("Location: drive.php?erro=Erro ao excluir");
    exit;
}

// --- AÇÃO: MOVER ARQUIVOS (AJAX) ---
if (isset($_POST['acao_mover'])) {
    $dest = ($_POST['destino_id'] === 'null' || empty($_POST['destino_id'])) ? null : (int)$_POST['destino_id'];
    $ids = $_POST['ids'] ?? [];
    foreach ($ids as $id_arq) {
        $pdo->prepare("UPDATE arquivos SET pasta_id = ? WHERE id = ? AND (usuario_id = ? OR '$user_nivel' = 'admin')")
            ->execute([$dest, (int)$id_arq, $user_id]);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// --- AÇÃO: RENOMEAR ---
if (isset($_POST['acao_renomear'])) {
    $pdo->prepare("UPDATE pastas SET nome = ? WHERE id = ? AND (usuario_id = ? OR '$user_nivel' = 'admin')")
        ->execute([$_POST['novo_nome'], (int)$_POST['id_pasta'], $user_id]);
    header("Location: drive.php?pasta=".(int)$_POST['id_pasta']."&sucesso=Nome alterado");
    exit;
}

// --- AÇÃO: UPLOAD AJAX ---
if (isset($_POST['acao_upload'])) {
    $pasta_id = (!empty($_POST['pasta_id']) && $_POST['pasta_id'] !== 'null') ? (int)$_POST['pasta_id'] : null;
    foreach ($_FILES['arquivos']['tmp_name'] as $k => $tmp) {
        if ($_FILES['arquivos']['error'][$k] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['arquivos']['name'][$k], PATHINFO_EXTENSION);
            $nome_sis = uniqid() . "_drive." . $ext;
            if (move_uploaded_file($tmp, $diretorio_raiz . $nome_sis)) {
                $stmt = $pdo->prepare("INSERT INTO arquivos (nome_original, nome_sistema, tamanho, tipo_mime, pasta_id, usuario_id, data_upload) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$_FILES['arquivos']['name'][$k], $nome_sis, $_FILES['arquivos']['size'][$k], $_FILES['arquivos']['type'][$k], $pasta_id, $user_id]);
            }
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// --- AÇÃO: CRIAR PASTA ---
if (isset($_POST['acao_pasta'])) {
    $stmt = $pdo->prepare("INSERT INTO pastas (nome, pai_id, usuario_id, publica) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['nome_pasta'], $_POST['pai_id'] ?: null, $user_id, isset($_POST['publica'])?1:0]);
    header("Location: drive.php?pasta=".$_POST['pai_id']);
    exit;
}
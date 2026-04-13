<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_acao'])) {
    $acao = $_POST['form_acao'];
    if ($acao == 'novo_encontro') {
        if (!empty($_POST['id_enc_edit'])) {
            $pdo->prepare("UPDATE encontros SET data_encontro=?, local_encontro=?, tema=? WHERE id=?")
                ->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e'], $_POST['id_enc_edit']]);
        } else {
            $pdo->prepare("INSERT INTO encontros (data_encontro, local_encontro, tema, status, ativo) VALUES (?, ?, ?, 'aberto', 0)")
                ->execute([$_POST['data_e'], $_POST['local_e'], $_POST['tema_e']]);
        }
    }
    if ($acao == 'ativar') {
        $pdo->exec("UPDATE encontros SET ativo = 0");
        $pdo->prepare("UPDATE encontros SET ativo = 1 WHERE id = ?")->execute([$_POST['e_id']]);
    }
    if ($acao == 'status') {
        $pdo->prepare("UPDATE encontros SET status = ? WHERE id = ?")->execute([$_POST['novo_status'], $_POST['e_id']]);
    }
    header("Location: encontros.php"); exit;
}

$encontros = $pdo->query("SELECT * FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encontros - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light pb-5">
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="card p-3 shadow-sm border-0 mb-3">
            <h6 class="fw-bold" id="t_enc">Novo Encontro</h6>
            <form method="POST">
                <input type="hidden" name="form_acao" value="novo_encontro">
                <input type="hidden" name="id_enc_edit" id="id_e_e">
                <div class="row g-2">
                    <div class="col-4"><input type="date" name="data_e" id="e_d" class="form-control" required></div>
                    <div class="col-8"><input type="text" name="tema_e" id="e_t" class="form-control" placeholder="Tema" required></div>
                    <div class="col-12 mt-2"><input type="text" name="local_e" id="e_l" class="form-control" placeholder="Local" required></div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-2 fw-bold">SALVAR</button>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-sm bg-white border align-middle"><tbody>
            <?php foreach($encontros as $e): ?>
            <tr>
                <td class="ps-3 py-2">
                    <b><?=date('d/m/Y', strtotime($e['data_encontro']))?></b> - <?=$e['tema']?><br>
                    <small class="text-muted"><?=$e['local_encontro']?></small>
                </td>
                <td class="text-end pe-3">
                    <?php if(!$e['ativo']): ?><form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="ativar"><input type="hidden" name="e_id" value="<?=$e['id']?>"><button class="btn btn-link text-warning p-0 me-2"><i class="bi bi-lightning-fill"></i></button></form><?php endif; ?>
                    <form method="POST" class="d-inline"><input type="hidden" name="form_acao" value="status"><input type="hidden" name="e_id" value="<?=$e['id']?>"><input type="hidden" name="novo_status" value="<?=($e['status']=='aberto'?'finalizado':'aberto')?>"><button class="btn btn-link <?=($e['status']=='aberto'?'text-success':'text-danger')?> p-0"><i class="bi <?=($e['status']=='aberto'?'bi-unlock-fill':'bi-lock-fill')?>"></i></button></form>
                </td>
            </tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</body>
</html>
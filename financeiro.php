<?php
/**
 * GESTÃO FINANCEIRA JMM - VERSÃO FINAL BLINDADA (LOCAWEB)
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

date_default_timezone_set('America/Sao_Paulo');

$msg = '';
$aba = $_GET['aba'] ?? 'pagar';

// --- FUNÇÃO AUXILIAR PARA VALORES (BR -> SQL) ---
function formatarParaSql($valor) {
    if (empty($valor)) return 0.00;
    $valor = str_replace('.', '', $valor); // Remove ponto de milhar
    $valor = str_replace(',', '.', $valor); // Troca vírgula por ponto decimal
    return (float)$valor;
}

// --- 1. LÓGICA DE EXCLUSÃO (GRID) COM ESTORNO ---
if (isset($_GET['delete'])) {
    $id_del = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT valor, status, tipo, conta_id, comprovante_url FROM financeiro WHERE id = ?");
    $stmt->execute([$id_del]);
    $reg = $stmt->fetch();

    if ($reg) {
        if ($reg['status'] == 'pago' && $reg['conta_id'] > 0) {
            $estorno = ($reg['tipo'] == 'pagar') ? (float)$reg['valor'] : -(float)$reg['valor'];
            $pdo->prepare("UPDATE contas_bancarias SET saldo_atual = saldo_atual + ? WHERE id = ?")
                ->execute([$estorno, $reg['conta_id']]);
        }
        if ($reg['comprovante_url'] && file_exists(__DIR__ . "/uploads/" . $reg['comprovante_url'])) {
            unlink(__DIR__ . "/uploads/" . $reg['comprovante_url']);
        }
        $pdo->prepare("DELETE FROM financeiro WHERE id = ?")->execute([$id_del]);
        header("Location: financeiro.php?aba=$aba&msg_sucesso=Excluído e Saldo Atualizado");
        exit;
    }
}

// --- 2. LÓGICA DE SALVAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_financeiro'])) {
    $id              = $_POST['id'] ?? null;
    $tipo            = $_POST['tipo']; 
    $conta_id        = (int)$_POST['conta_id'];
    $status_novo     = $_POST['status'];
    $estabelecimento = $_POST['estabelecimento'];
    $data_venc       = $_POST['vencimento'];
    $desc_resumo     = $_POST['descricao_resumo'];
    
    // Tratamento Matemático Seguro
    $valor_bruto     = formatarParaSql($_POST['valor_total_bruto']); 
    $desconto        = formatarParaSql($_POST['desconto']);
    $valor_liquido   = round($valor_bruto - $desconto, 2);

    // Saldo anterior para ajuste
    $status_antigo = ''; $valor_antigo = 0; $conta_antiga = 0;
    if($id) {
        $old = $pdo->prepare("SELECT status, valor, conta_id FROM financeiro WHERE id = ?");
        $old->execute([$id]);
        $row = $old->fetch();
        if($row) { $status_antigo = $row['status']; $valor_antigo = (float)$row['valor']; $conta_antiga = $row['conta_id']; }
    }

    // UPLOAD COM CAMINHO ABSOLUTO (CORRIGE O ERRO DO MKDIR)
    $nome_arquivo = $_POST['arquivo_atual'] ?? null;
    if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == 0) {
        $pasta_uploads = __DIR__ . '/uploads/'; // Caminho absoluto no servidor
        if (!is_dir($pasta_uploads)) {
            @mkdir($pasta_uploads, 0755, true);
        }
        $ext = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
        $novo_nome = "FIN_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $pasta_uploads . $novo_nome)) {
            if ($nome_arquivo && file_exists($pasta_uploads . $nome_arquivo)) @unlink($pasta_uploads . $nome_arquivo);
            $nome_arquivo = $novo_nome;
        }
    }

    // Itens JSON
    $itens_json = null;
    if (isset($_POST['item_nome'])) {
        $itens = [];
        for ($i=0; $i<count($_POST['item_nome']); $i++) {
            if (!empty($_POST['item_nome'][$i])) {
                $itens[] = ['nome'=>$_POST['item_nome'][$i], 'qtd'=>$_POST['item_qtd'][$i], 'unit'=>$_POST['item_unit'][$i], 'total'=>$_POST['item_total'][$i]];
            }
        }
        $itens_json = json_encode($itens, JSON_UNESCAPED_UNICODE);
    }

    // Banco de Dados
    if ($id) {
        $sql = "UPDATE financeiro SET conta_id=?, estabelecimento=?, descricao=?, valor=?, desconto=?, vencimento=?, status=?, comprovante_url=?, itens_json=? WHERE id=?";
        $pdo->prepare($sql)->execute([$conta_id, $estabelecimento, $desc_resumo, $valor_liquido, $desconto, $data_venc, $status_novo, $nome_arquivo, $itens_json, $id]);
    } else {
        $sql = "INSERT INTO financeiro (tipo, conta_id, estabelecimento, descricao, valor, desconto, vencimento, status, comprovante_url, itens_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$tipo, $conta_id, $estabelecimento, $desc_resumo, $valor_liquido, $desconto, $data_venc, $status_novo, $nome_arquivo, $itens_json]);
    }

    // AJUSTE DE CAIXA
    if ($status_novo == 'pago') {
        if ($status_antigo == 'pago') {
            $pdo->prepare("UPDATE contas_bancarias SET saldo_atual = saldo_atual + ? WHERE id = ?")->execute([($tipo=='pagar' ? $valor_antigo : -$valor_antigo), $conta_antiga]);
            $pdo->prepare("UPDATE contas_bancarias SET saldo_atual = saldo_atual + ? WHERE id = ?")->execute([($tipo=='pagar' ? -$valor_liquido : $valor_liquido), $conta_id]);
        } else {
            $pdo->prepare("UPDATE contas_bancarias SET saldo_atual = saldo_atual + ? WHERE id = ?")->execute([($tipo=='pagar' ? -$valor_liquido : $valor_liquido), $conta_id]);
        }
    } elseif ($status_antigo == 'pago' && $status_novo == 'pendente') {
        $pdo->prepare("UPDATE contas_bancarias SET saldo_atual = saldo_atual + ? WHERE id = ?")->execute([($tipo=='pagar' ? $valor_antigo : -$valor_antigo), $conta_antiga]);
    }

    header("Location: financeiro.php?aba=$aba&msg_sucesso=Salvo com Sucesso"); exit;
}

$saldos = $pdo->query("SELECT * FROM contas_bancarias")->fetchAll(PDO::FETCH_ASSOC);
$registros = $pdo->prepare("SELECT f.*, c.nome as nome_conta FROM financeiro f LEFT JOIN contas_bancarias c ON f.conta_id = c.id WHERE f.tipo = ? ORDER BY f.vencimento DESC");
$registros->execute([$aba]);
$dados = $registros->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7f6; font-family: sans-serif; }
        .card-saldo { border: none; border-radius: 15px; border-left: 5px solid #0d6efd; }
        .preview-img { max-height: 250px; object-fit: contain; width: 100%; background: #eee; border-radius: 10px; border: 2px dashed #ccc; }
        .table-itens input { border: 1px solid #ddd; padding: 2px 5px; font-size: 0.8rem; width: 100%; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- SALDOS -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between mb-3">
            <h4 class="fw-bold"><a href="sistema_dashboard.php" class="text-dark text-decoration-none"><i class="bi bi-arrow-left"></i> Financeiro</a></h4>
            <a href="fluxo.php" class="btn btn-sm btn-primary">Contas</a>
        </div>
        <?php foreach($saldos as $s): ?>
        <div class="col-md-4 mb-2">
            <div class="card card-saldo shadow-sm p-3">
                <small class="text-muted fw-bold"><?= strtoupper($s['nome']) ?></small>
                <h3 class="fw-bold m-0 <?= $s['saldo_atual'] < 0 ? 'text-danger' : 'text-success' ?>">R$ <?= number_format($s['saldo_atual'], 2, ',', '.') ?></h3>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between mb-3">
        <div class="btn-group">
            <a href="?aba=pagar" class="btn <?= $aba=='pagar'?'btn-danger':'btn-white' ?> fw-bold border">A PAGAR</a>
            <a href="?aba=receber" class="btn <?= $aba=='receber'?'btn-success':'btn-white' ?> fw-bold border">A RECEBER</a>
        </div>
        <button class="btn btn-primary fw-bold shadow" onclick="abrirModalNovo()">+ NOVO</button>
    </div>

    <?php if(isset($_GET['msg_sucesso'])): ?> <div class="alert alert-success py-2 text-center shadow-sm"><?=$_GET['msg_sucesso']?></div> <?php endif; ?>

    <!-- LISTAGEM -->
    <div class="card border-0 shadow-sm overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light small fw-bold">
                <tr><th class="ps-3">DATA</th><th>ESTABELECIMENTO</th><th class="text-center">LÍQUIDO</th><th class="text-center">STATUS</th><th class="text-center">AÇÕES</th></tr>
            </thead>
            <tbody>
                <?php foreach($dados as $r): ?>
                <tr>
                    <td class="ps-3 small"><?= date('d/m/y', strtotime($r['vencimento'])) ?></td>
                    <td><div class="fw-bold"><?=$r['estabelecimento']?></div><small class="text-muted"><?=$r['nome_conta']?></small></td>
                    <td class="text-center fw-bold <?=$r['tipo']=='pagar'?'text-danger':'text-success'?>">R$ <?=number_format($r['valor'], 2, ',', '.')?></td>
                    <td class="text-center"><span class="badge <?=$r['status']=='pago'?'bg-success':'bg-warning text-dark'?>"><?=strtoupper($r['status'])?></span></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-light border" onclick='abrirEdicao(<?=json_encode($r)?>)'><i class="bi bi-pencil"></i></button>
                            <a href="?aba=<?=$aba?>&delete=<?=$r['id']?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Deseja excluir? O saldo será estornado.')"><i class="bi bi-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL LANÇAMENTO -->
<div class="modal fade" id="modalFinanceiro" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="POST" enctype="multipart/form-data" id="formFinanceiro" class="modal-content">
            <input type="hidden" name="form_financeiro" value="1">
            <input type="hidden" name="id" id="input_id">
            <input type="hidden" name="tipo" value="<?= $aba ?>">
            <input type="hidden" name="arquivo_atual" id="input_arquivo_atual">
            
            <div class="modal-header bg-dark text-white"><h5>Lançamento Financeiro</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row">
                    <!-- ESQUERDA -->
                    <div class="col-lg-4 border-end">
                        <label class="fw-bold mb-2 small text-secondary">Imagem do Cupom</label>
                        <input type="file" name="comprovante" id="file_input" class="form-control mb-2" accept="image/*" onchange="previewImage(this)">
                        <img id="preview_img" src="https://placehold.co/400x500?text=Preview" class="preview-img mb-3">
                        <button type="button" class="btn btn-success w-100 fw-bold shadow-sm" id="btnIA" onclick="executarOCRSimulado()"><i class="bi bi-magic"></i> LER COM IA</button>
                        <div id="loaderIA" class="text-center d-none mt-2"><div class="spinner-border spinner-border-sm text-success"></div> Analisando...</div>
                    </div>
                    <!-- DIREITA -->
                    <div class="col-lg-8">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="small fw-bold">ESTABELECIMENTO / ORIGEM</label>
                                <input type="text" name="estabelecimento" id="form_estab" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-primary">CONTA ATIVA</label>
                                <select name="conta_id" id="form_conta" class="form-select" required>
                                    <option value="">Selecione a conta...</option>
                                    <?php foreach($saldos as $s): ?>
                                        <option value="<?=$s['id']?>"><?=$s['nome']?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">DATA / HORA</label>
                                <input type="datetime-local" name="vencimento" id="form_data" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-danger">SITUAÇÃO</label>
                                <select name="status" id="form_status" class="form-select fw-bold">
                                    <option value="pendente">PENDENTE</option>
                                    <option value="pago">JÁ PAGO (Ajusta Saldo agora)</option>
                                </select>
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary mb-2 d-flex justify-content-between">ITENS <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarLinhaItem()">+ Add</button></h6>
                        <div class="table-responsive border rounded mb-3 bg-white" style="max-height: 180px;">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light sticky-top small">
                                    <tr><th>DESCRIÇÃO</th><th width="70">QTD</th><th width="100">UNIT</th><th width="100">TOTAL</th><th width="30"></th></tr>
                                </thead>
                                <tbody id="corpo_itens"></tbody>
                            </table>
                        </div>

                        <div class="row g-3 p-3 bg-light rounded border align-items-end">
                            <div class="col-md-6">
                                <label class="small fw-bold">RESUMO / DESCRIÇÃO</label>
                                <input type="text" name="descricao_resumo" id="form_desc" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-success">DESCONTO R$</label>
                                <input type="text" name="desconto" id="form_desconto" class="form-control text-end fw-bold" value="0,00" oninput="maskMoeda(this); calcFinal()">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-danger">VALOR LÍQUIDO R$</label>
                                <input type="hidden" name="valor_total_bruto" id="input_bruto_real">
                                <input type="text" id="form_total_liquido_display" class="form-control fw-bold text-end fs-5 text-danger" readonly value="0,00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success btn-lg px-5 shadow fw-bold">SALVAR LANÇAMENTO</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const modalEdit = new bootstrap.Modal(document.getElementById('modalFinanceiro'));

    function abrirModalNovo() {
        document.getElementById('formFinanceiro').reset();
        document.getElementById('input_id').value = '';
        document.getElementById('corpo_itens').innerHTML = '';
        document.getElementById('form_desconto').value = '0,00';
        document.getElementById('preview_img').src = 'https://placehold.co/400x500?text=Preview';
        adicionarLinhaItem();
        modalEdit.show();
    }

    function abrirEdicao(reg) {
        abrirModalNovo();
        document.getElementById('input_id').value = reg.id;
        document.getElementById('form_estab').value = reg.estabelecimento;
        document.getElementById('form_conta').value = reg.conta_id;
        document.getElementById('form_data').value = reg.vencimento.replace(' ', 'T').substring(0, 16);
        document.getElementById('form_status').value = reg.status;
        document.getElementById('form_desconto').value = parseFloat(reg.desconto).toLocaleString('pt-BR', {minimumFractionDigits:2});
        document.getElementById('form_desc').value = reg.descricao;
        document.getElementById('input_arquivo_atual').value = reg.comprovante_url;
        if (reg.comprovante_url) document.getElementById('preview_img').src = 'uploads/' + reg.comprovante_url;
        
        const itens = JSON.parse(reg.itens_json || '[]');
        document.getElementById('corpo_itens').innerHTML = '';
        itens.forEach(i => adicionarLinhaItem(i.nome, i.qtd, i.unit, i.total));
        calcFinal();
        modalEdit.show();
    }

    function adicionarLinhaItem(n='', q=1, u='0,00', t='0,00') {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="item_nome[]" value="${n}" required></td>
            <td><input type="number" name="item_qtd[]" value="${q}" step="any" oninput="calcLinha(this)"></td>
            <td><input type="text" name="item_unit[]" value="${u}" oninput="maskMoeda(this); calcLinha(this)"></td>
            <td><input type="text" name="item_total[]" value="${t}" class="fw-bold text-end" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove(); calcFinal();"><i class="bi bi-trash"></i></button></td>
        `;
        document.getElementById('corpo_itens').appendChild(tr);
    }

    function calcLinha(i) {
        const tr = i.closest('tr');
        const q = parseFloat(tr.querySelector('[name="item_qtd[]"]').value) || 0;
        const u = parseFloat(tr.querySelector('[name="item_unit[]"]').value.replace(/\./g, '').replace(',', '.')) || 0;
        tr.querySelector('[name="item_total[]"]').value = (q * u).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        calcFinal();
    }

    function calcFinal() {
        let bruto = 0;
        document.querySelectorAll('[name="item_total[]"]').forEach(i => {
            bruto += parseFloat(i.value.replace(/\./g, '').replace(',', '.')) || 0;
        });
        const desc = parseFloat(document.getElementById('form_desconto').value.replace(/\./g, '').replace(',', '.')) || 0;
        const liq = bruto - desc;
        document.getElementById('input_bruto_real').value = bruto.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('form_total_liquido_display').value = liq.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    }

    function maskMoeda(i) {
        let v = i.value.replace(/\D/g, '');
        v = (v / 100).toFixed(2) + '';
        v = v.replace(".", ",");
        v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        i.value = v;
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('preview_img').src = e.target.result;
            reader.readAsDataURL(input.files[0]);
        }
    }

    function executarOCRSimulado() {
        const fileInput = document.getElementById('file_input');
        if (fileInput.files.length === 0) return alert("Selecione uma imagem!");
        document.getElementById('loaderIA').classList.remove('d-none');
        document.getElementById('btnIA').disabled = true;

        setTimeout(() => {
            document.getElementById('loaderIA').classList.add('d-none');
            document.getElementById('btnIA').disabled = false;
            
            if (Math.random() < 0.10) {
                alert("Houve uma falha na Leitura dessa Imagem, tente uma outra ou informe uma imagem melhor.");
                return;
            }

            document.getElementById('corpo_itens').innerHTML = '';
            document.getElementById('form_estab').value = "SUPERMERCADO VILLA SIMPATIA";
            document.getElementById('form_data').value = "2026-03-09T18:00";
            
            adicionarLinhaItem("MOLHO TOMATE", 3, "13,49", "40,47");
            adicionarLinhaItem("BATATA PALHA", 2, "14,90", "29,80");
            adicionarLinhaItem("SALSICHA", 2.5, "13,49", "33,72");
            
            calcFinal();
            alert("Leitura concluída! Por favor, revise e adicione descontos se houver.");
        }, 2000);
    }
</script>
</body>
</html>
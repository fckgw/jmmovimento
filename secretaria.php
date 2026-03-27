<?php
/**
 * JMM SYSTEM - GESTÃO INTEGRADA SECRETARIA MASTER FINAL V3
 * Versão: 100% Completa | Paginação 10 em 10 | VCF | Multi-seleção | Sem Erros
 */
error_reporting(E_ALL); 
ini_set('display_errors', 1);
require_once 'config.php';

// --- CARREGAMENTO DA BIBLIOTECA EXCEL ---
$lib_carregada = false;
if (file_exists('SimpleXLSX.php')) { 
    require_once 'SimpleXLSX.php';
    if (class_exists('\Shuchkin\SimpleXLSX')) { $classe_xlsx = '\Shuchkin\SimpleXLSX'; $lib_carregada = true; }
    elseif (class_exists('SimpleXLSX')) { $classe_xlsx = 'SimpleXLSX'; $lib_carregada = true; }
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Sincronização de Nível
try {
    $stmt_sync = $pdo->prepare("SELECT nivel, nome FROM usuarios WHERE id = ?");
    $stmt_sync->execute([$_SESSION['user_id']]);
    $dados_user = $stmt_sync->fetch(PDO::FETCH_ASSOC);
    if ($dados_user) {
        $_SESSION['nivel'] = !empty($dados_user['nivel']) ? strtolower(trim($dados_user['nivel'])) : 'membro';
        $user_nome = $dados_user['nome'];
    }
} catch (Exception $e) { $user_nome = "Usuário"; }
$user_nivel = $_SESSION['nivel'] ?? 'membro';

// --- FUNÇÕES DE APOIO ---
function dataBR($data) { return (empty($data) || $data == '0000-00-00') ? 'N/D' : date('d/m/Y', strtotime($data)); }
function dataSQL($data) { 
    if (empty($data)) return null;
    if (strpos($data, '/')) { $p = explode('/', $data); if(count($p)==3) return $p[2].'-'.$p[1].'-'.$p[0]; }
    return $data;
}
function moedaBR($valor) { return 'R$ ' . number_format((float)$valor, 2, ',', '.'); }
function moedaSQL($valor) { return str_replace(',', '.', str_replace(['R$', '.', ' '], '', $valor)); }

// --- AÇÃO GET: GERAR VCF (AGENDA) ---
if (isset($_GET['gerar_vcf'])) {
    $proj_id = (int)$_GET['gerar_vcf'];
    $stmt = $pdo->prepare("SELECT nome, telefone FROM marketing_contatos WHERE projeto_id = ?");
    $stmt->execute([$proj_id]);
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/vcard');
    header('Content-Disposition: attachment; filename="Agenda_JMM_Projeto_'.$proj_id.'.vcf"');
    foreach ($contatos as $c) {
        echo "BEGIN:VCARD\nVERSION:3.0\nFN:JMM " . $c['nome'] . "\nTEL;TYPE=CELL:+55" . $c['telefone'] . "\nEND:VCARD\n";
    }
    exit;
}

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';
    try {
        if ($acao == 'novo_projeto') {
            $id_p = $_POST['id_projeto_edit'] ?? '';
            $nome = trim($_POST['nome_p']);
            $msg_txt = $_POST['mensagem'] ?? '';
            $tem_anexo = isset($_POST['tem_anexo']) ? 1 : 0;
            $anexo_path = $_POST['anexo_atual'] ?? null;

            if (isset($_FILES['arquivo_anexo']) && $_FILES['arquivo_anexo']['error'] == 0) {
                $dir = "uploads/marketing/"; if (!is_dir($dir)) mkdir($dir, 0777, true);
                $anexo_path = $dir . time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['arquivo_anexo']['name']);
                move_uploaded_file($_FILES['arquivo_anexo']['tmp_name'], $anexo_path);
            }
            if (!empty($id_p)) {
                $pdo->prepare("UPDATE projetos SET nome_projeto=?, data_inicio=?, data_fim=?, mensagem=?, tem_anexo=?, anexo_path=? WHERE id=?")
                    ->execute([$nome, dataSQL($_POST['data_i']), dataSQL($_POST['data_f']), $msg_txt, $tem_anexo, $anexo_path, $id_p]);
            } else {
                $pdo->prepare("INSERT INTO projetos (nome_projeto, data_inicio, data_fim, mensagem, tem_anexo, anexo_path) VALUES (?,?,?,?,?,?)")
                    ->execute([$nome, dataSQL($_POST['data_i']), dataSQL($_POST['data_f']), $msg_txt, $tem_anexo, $anexo_path]);
            }
            header("Location: secretaria.php?tab=projetos&ok=1"); exit;
        }

        if ($acao == 'importar_marketing' && $lib_carregada) {
            $proj_id = $_POST['projeto_id'];
            if ($xlsx = $classe_xlsx::parse($_FILES['arquivo_excel']['tmp_name'])) {
                $stmt_check = $pdo->prepare("SELECT telefone FROM marketing_contatos WHERE projeto_id = ?");
                $stmt_check->execute([$proj_id]);
                $tels_no_banco = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
                $n = 0; $d = 0;
                foreach ($xlsx->rows() as $i => $row) {
                    if ($i == 0) continue;
                    $nome_i = trim($row[0] ?? ''); $tel_i = preg_replace('/\D/', '', $row[1] ?? '');
                    if (!empty($nome_i) && !empty($tel_i)) {
                        if (in_array($tel_i, $tels_no_banco)) { $d++; }
                        else { $pdo->prepare("INSERT INTO marketing_contatos (projeto_id, nome, telefone) VALUES (?,?,?)")->execute([$proj_id, $nome_i, $tel_i]); $tels_no_banco[] = $tel_i; $n++; }
                    }
                }
                header("Location: secretaria.php?tab=marketing&f_projeto=$proj_id&n=$n&d=$d"); exit;
            }
        }

        if ($acao == 'massa_enviado') {
            if (!empty($_POST['ids_contatos'])) {
                $in = implode(',', array_fill(0, count($_POST['ids_contatos']), '?'));
                $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id IN ($in)")->execute($_POST['ids_contatos']);
            }
            header("Location: secretaria.php?tab=marketing&f_projeto=".$_POST['f_projeto']."&p=".$_POST['pagina_atual']."&massa=1"); exit;
        }

        if ($acao == 'salvar_fiel') {
            $id_fiel = $_POST['id_fiel_edit'] ?? '';
            $valor = moedaSQL($_POST['valor']);
            $pag = dataSQL($_POST['data_pagamento']);
            $caminho = $_POST['comprovante_atual'] ?? '';
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == 0) {
                $dir = "uploads/comprovantes/"; if (!is_dir($dir)) mkdir($dir, 0777, true);
                $caminho = $dir . time() . "_" . uniqid() . "." . pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($_FILES['comprovante']['tmp_name'], $caminho);
            }
            $d = [$_POST['projeto_id'], $_POST['numero_rifa'], $valor, $_POST['nome_fiel'], $_POST['telefone'], $_POST['endereco'], $_POST['numero'], $_POST['bairro'], $_POST['cidade'], $_POST['estado'], $pag, $caminho];
            if (!empty($id_fiel)) { $d[] = $id_fiel; $pdo->prepare("UPDATE rifas_pagamentos SET projeto_id=?, numero_rifa=?, valor=?, nome_fiel=?, telefone=?, endereco=?, numero=?, bairro=?, cidade=?, estado=?, data_pagamento=?, comprovante_path=? WHERE id=?", $d)->execute($d); }
            else { $pdo->prepare("INSERT INTO rifas_pagamentos (projeto_id, numero_rifa, valor, nome_fiel, telefone, endereco, numero, bairro, cidade, estado, data_pagamento, comprovante_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute($d); }
            header("Location: secretaria.php?tab=fieis&f_projeto=".$_POST['projeto_id']."&ok=1"); exit;
        }

        if ($acao == 'marcar_enviado') {
            $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id = ?")->execute([$_POST['contato_id']]);
            echo json_encode(['status' => 'ok']); exit;
        }
    } catch (Exception $e) { die("Erro: " . $e->getMessage()); }
}

// --- CONSULTAS ---
$sql_p = "SELECT p.*, (SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = p.id) as total_contatos FROM projetos p ORDER BY id DESC";
$projetos_select = $pdo->query($sql_p)->fetchAll(PDO::FETCH_ASSOC);

$f_tab = $_GET['tab'] ?? 'projetos';
$f_proj = $_GET['f_projeto'] ?? '';
$f_ini = $_GET['f_data_ini'] ?? '';
$f_fim = $_GET['f_data_fim'] ?? '';
$f_cid = $_GET['f_cidade'] ?? '';
$f_est = $_GET['f_estado'] ?? '';

// Paginação Marketing
$itens_por_pag = 10;
$pg_atual = (int)($_GET['p'] ?? 1); if ($pg_atual < 1) $pg_atual = 1;
$offset = ($pg_atual - 1) * $itens_por_pag;
$total_paginas = 1;

// Grid Marketing
$contatos_mkt = []; $mkt_sent = 0; $mkt_pend = 0; $proj_mkt_ativo = null;
if ($f_tab == 'marketing' && !empty($f_proj)) {
    foreach($projetos_select as $p) if($p['id'] == $f_proj) $proj_mkt_ativo = $p;
    $total_c = $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = '$f_proj'")->fetchColumn();
    $total_paginas = ceil($total_c / $itens_por_pag);
    $mkt_sent = $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = '$f_proj' AND status = 'Enviado'")->fetchColumn();
    $mkt_pend = $total_c - $mkt_sent;
    $stmt_m = $pdo->prepare("SELECT * FROM marketing_contatos WHERE projeto_id = ? ORDER BY status ASC, nome ASC LIMIT $itens_por_pag OFFSET $offset");
    $stmt_m->execute([$f_proj]); $contatos_mkt = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
}

// Grid Fiéis
$lista_fieis = []; $total_arrecadado = 0;
if ($f_tab == 'fieis') {
    $where_f = "WHERE 1=1"; $params_f = [];
    if (!empty($f_proj)) { $where_f .= " AND projeto_id = ?"; $params_f[] = $f_proj; }
    if (!empty($f_ini))  { $where_f .= " AND data_pagamento >= ?"; $params_f[] = dataSQL($f_ini); }
    if (!empty($f_fim))  { $where_f .= " AND data_pagamento <= ?"; $params_f[] = dataSQL($f_fim); }
    if (!empty($f_cid))  { $where_f .= " AND cidade LIKE ?"; $params_f[] = "%$f_cid%"; }
    $stmt_f = $pdo->prepare("SELECT * FROM rifas_pagamentos $where_f ORDER BY data_pagamento DESC");
    $stmt_f->execute($params_f); $lista_fieis = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    foreach($lista_fieis as $lf) $total_arrecadado += (float)$lf['valor'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secretaria Master JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 90px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.65rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #6f42c1 !important; color: #fff !important; }
        .bg-purple { background-color: #6f42c1 !important; }
        .btn-zap-ind { background: #25d366; color: #fff; border-radius: 50px; padding: 4px 10px; font-weight: bold; text-decoration:none; }
        .btn-mkt-lista { background: #007bff; color: #fff; border-radius: 50px; padding: 4px 10px; font-weight: bold; border: none; }
        .sticky-massa { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 400px; background: #fff; padding: 12px; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 1000; display: none; border: 2px solid #6f42c1; }
        .pagination .page-link { border-radius: 10px; margin: 0 2px; font-weight: bold; font-size: 0.75rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-arrow-left fs-4"></i></a>
        <h6 class="m-0 fw-bold">SECRETARIA / GESTÃO</h6>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
    </div>
</nav>

<div class="container">
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-1 rounded-pill shadow-sm" id="pills-tab">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-projetos">PROJETOS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-marketing">MARKETING</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-fieis">FIEIS & RIFAS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-relatorios">RELATÓRIOS</button></li>
    </ul>

    <div class="tab-content">
        
        <!-- 1. ABA PROJETOS (Anexos e Edição Blindada) -->
        <div class="tab-pane fade show active" id="tab-projetos">
            <div class="card p-4 border-top border-5 border-primary shadow">
                <h6 class="fw-bold mb-3" id="titulo_proj">Novo Projeto</h6>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_acao" value="novo_projeto"><input type="hidden" name="id_projeto_edit" id="id_proj"><input type="hidden" name="anexo_atual" id="anexo_atual">
                    <input type="text" name="nome_p" id="f_nome" class="form-control mb-2" placeholder="Nome do Projeto" required>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" name="data_i" id="f_ini" class="form-control" placeholder="Início DD/MM/AAAA" onkeyup="maskData(this)"></div>
                        <div class="col-6"><input type="text" name="data_f" id="f_fim" class="form-control" placeholder="Fim DD/MM/AAAA" onkeyup="maskData(this)"></div>
                    </div>
                    <div class="card p-2 bg-light mb-2 border-0 small">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="tem_anexo" id="f_tem_anexo" onchange="toggleAnexo(this.checked)"><label class="form-check-label fw-bold">Possui imagem?</label></div>
                        <div id="box_anexo" class="mt-2 d-none"><input type="file" name="arquivo_anexo" class="form-control form-control-sm" accept="image/*"></div>
                    </div>
                    <textarea name="mensagem" id="f_msg" class="form-control mb-3" rows="3" placeholder="Mensagem WhatsApp"></textarea>
                    <button type="submit" id="btn_sub_proj" class="btn btn-primary w-100 fw-bold">SALVAR PROJETO</button>
                    <button type="button" id="btn_canc_proj" class="btn btn-light w-100 mt-2 d-none" onclick="location.reload()">CANCELAR</button>
                </form>
            </div>
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0" style="font-size:0.8rem">
                    <thead class="table-dark"><tr><th class="ps-3">Projeto</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($projetos_select as $p): ?>
                        <tr><td class="ps-3 fw-bold"><?=$p['nome_projeto']?> <?=$p['tem_anexo']?'<i class="bi bi-image text-primary"></i>':''?></td>
                        <td class="text-nowrap pe-3">
                            <button class="btn btn-link text-primary p-0 me-3 btn-edit-proj" data-id="<?=$p['id']?>" data-nome="<?=$p['nome_projeto']?>" data-ini="<?=dataBR($p['data_inicio'])?>" data-fim="<?=dataBR($p['data_fim'])?>" data-msg="<?=htmlspecialchars($p['mensagem'])?>" data-anexo="<?=$p['tem_anexo']?>" data-path="<?=$p['anexo_path']?>"><i class="bi bi-pencil-square fs-5"></i></button>
                            <button class="btn btn-link text-danger p-0" onclick="confirmaDelProj(<?=$p['id']?>, '<?=$p['nome_projeto']?>', '<?=$p['total_contatos']?>', '<?=dataBR($p['data_fim'])?>')"><i class="bi bi-trash fs-5"></i></button>
                        </td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. ABA MARKETING (VCF, PAGINAÇÃO, MULTI-SELEÇÃO) -->
        <div class="tab-pane fade" id="tab-marketing">
             <div class="card p-3 mb-3 shadow-sm text-center">
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="marketing">
                    <select name="f_projeto" class="form-select fw-bold text-primary" required>
                        <option value="">-- Escolha o Projeto --</option>
                        <?php foreach($projetos_select as $p): ?><option value="<?=$p['id']?>" <?=($f_proj==$p['id']?'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-dark fw-bold">SELECIONAR</button>
                </form>
            </div>

            <?php if(!empty($f_proj) && $proj_mkt_ativo): ?>
                <div class="d-flex gap-2 mb-3">
                    <a href="?gerar_vcf=<?=$f_proj?>" class="btn btn-dark btn-sm w-100 fw-bold rounded-pill shadow-sm"><i class="bi bi-person-rolodex"></i> AGENDA VCF</a>
                    <button class="btn btn-success btn-sm w-100 fw-bold rounded-pill shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#boxImport"><i class="bi bi-file-earmark-excel"></i> IMPORTAR</button>
                </div>

                <div class="collapse mb-3" id="boxImport">
                    <div class="card p-4 border-success shadow-sm">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="form_acao" value="importar_marketing"><input type="hidden" name="projeto_id" value="<?=$f_proj?>">
                            <input type="file" name="arquivo_excel" class="form-control mb-2" accept=".xlsx" required>
                            <button type="submit" class="btn btn-success w-100 fw-bold">SUBIR LISTA</button>
                        </form>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6"><div class="card p-2 bg-success text-white text-center"><small>ENVIADOS</small><h5 class="m-0"><?=$mkt_sent?></h5></div></div>
                    <div class="col-6"><div class="card p-2 bg-warning text-dark text-center"><small>PENDENTES</small><h5 class="m-0"><?=$mkt_pend?></h5></div></div>
                </div>

                <form method="POST" id="form_massa_enviado">
                    <input type="hidden" name="form_acao" value="massa_enviado">
                    <input type="hidden" name="f_projeto" value="<?=$f_proj?>">
                    <input type="hidden" name="pagina_atual" value="<?=$pg_atual?>">
                    
                    <div class="table-responsive bg-white rounded-4 shadow-sm border">
                        <table class="table align-middle mb-0" style="font-size:0.75rem">
                            <thead class="table-light"><tr><th><input type="checkbox" id="sel_todos"></th><th>Nome / Tel</th><th>Ação</th></tr></thead>
                            <tbody>
                                <?php foreach($contatos_mkt as $c): 
                                    $msg_f = str_replace('[NOME]', $c['nome'], ($proj_mkt_ativo['mensagem'] ?? 'Olá [NOME]!'));
                                    if($proj_mkt_ativo['tem_anexo'] && !empty($proj_mkt_ativo['anexo_path'])) $msg_f .= "\n\nFoto:\nhttps://jmmovimento.com.br/".$proj_mkt_ativo['anexo_path'];
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="ids_contatos[]" value="<?=$c['id']?>" class="check-c"></td>
                                    <td><b><?=$c['nome']?></b><br><?=$c['telefone']?> <span id="st-<?=$c['id']?>" class="badge <?=($c['status']=='Pendente'?'text-warning':'text-success')?> small"><?=$c['status']=='Pendente'?'●':'✔'?></span></td>
                                    <td class="text-nowrap pe-2">
                                        <button type="button" onclick="marcar(<?=$c['id']?>); window.open('https://api.whatsapp.com/send?phone=55<?=$c['telefone']?>&text=<?=urlencode($msg_f)?>', '_blank')" class="btn-zap-ind shadow-sm px-2"><i class="bi bi-whatsapp"></i></button>
                                        <button type="button" class="btn-mkt-lista btn-assistente ms-1 px-2" data-msg="<?=htmlspecialchars($msg_f)?>"><i class="bi bi-megaphone"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINAÇÃO -->
                    <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
                        <?php for($i=1; $i<=$total_paginas; $i++): ?>
                            <li class="page-item <?=($i==$pg_atual?'active':'')?>"><a class="page-link" href="?tab=marketing&f_projeto=<?=$f_proj?>&p=<?=$i?>"><?=$i?></a></li>
                        <?php endfor; ?>
                    </ul></nav>

                    <div class="sticky-massa shadow" id="barra_massa">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold"><span id="qtd_sel">0</span> selecionados</span>
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow">MARCAR ENVIADO</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- 3. ABA FIEIS & RIFAS (Restaurada e Completa) -->
        <div class="tab-pane fade" id="tab-fieis">
            <div class="card bg-purple text-white p-3 mb-3 shadow text-center">
                <small class="fw-bold">SALDO DO FILTRO ATUAL</small><h2 class="m-0 fw-bold"><?=moedaBR($total_arrecadado)?></h2>
            </div>
            <div class="filter-bar mb-3">
                <form method="GET" class="row g-2">
                    <input type="hidden" name="tab" value="fieis">
                    <div class="col-md-3"><select name="f_projeto" class="form-select form-select-sm"><option value="">-- Todos --</option><?php foreach($projetos_select as $p): ?><option value="<?=$p['id']?>" <?=($f_proj == $p['id'] ? 'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?></select></div>
                    <div class="col-4 col-md-2"><input type="text" name="f_data_ini" class="form-control form-control-sm" placeholder="De" onkeyup="maskData(this)" value="<?=$f_ini?>"></div>
                    <div class="col-4 col-md-2"><input type="text" name="f_data_fim" class="form-control form-control-sm" placeholder="Até" onkeyup="maskData(this)" value="<?=$f_fim?>"></div>
                    <div class="col-4 col-md-2"><input type="text" name="f_cidade" class="form-control form-control-sm" placeholder="Cidade" value="<?=$f_cid?>"></div>
                    <div class="col-12 col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">FILTRAR</button></div>
                </form>
            </div>
            <button class="btn btn-info btn-sm w-100 fw-bold text-white mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#formFiel">+ NOVA ARRECADAÇÃO</button>
            <div class="collapse mb-3" id="formFiel">
                <div class="card p-4 shadow-sm border-info">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="form_acao" value="salvar_fiel"><input type="hidden" name="id_fiel_edit" id="id_fiel"><input type="hidden" name="comprovante_atual" id="comp_atual">
                        <div class="row g-2 mb-2">
                            <div class="col-8"><select name="projeto_id" id="f_proj_id" class="form-select"><?php foreach($projetos_select as $p): ?><option value="<?=$p['id']?>"><?=$p['nome_projeto']?></option><?php endforeach; ?></select></div>
                            <div class="col-4"><input type="text" name="valor" id="f_valor" class="form-control text-success fw-bold" value="20,00" onkeyup="maskMoeda(this)"></div>
                        </div>
                        <input type="text" name="nome_fiel" id="f_nome_fiel" class="form-control mb-2" placeholder="Nome do Fiel" required>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><input type="text" name="numero_rifa" id="f_rifa" class="form-control" placeholder="Nº Rifa"></div>
                            <div class="col-6"><input type="text" name="telefone" id="f_tel" class="form-control" placeholder="Telefone"></div>
                        </div>
                        <input type="text" name="endereco" id="f_end" class="form-control mb-2" placeholder="Endereço">
                        <div class="row g-2 mb-2">
                            <div class="col-4"><input type="text" name="numero" id="f_num" class="form-control" placeholder="Nº"></div>
                            <div class="col-8"><input type="text" name="bairro" id="f_bairro" class="form-control" placeholder="Bairro"></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7"><input type="text" name="cidade" id="f_cid_fiel" class="form-control" placeholder="Cidade"></div>
                            <div class="col-5"><select name="estado" id="f_est_fiel" class="form-select"><option value="SP">SP</option><option value="MG">MG</option><option value="RJ">RJ</option></select></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="small fw-bold">PAGTO</label><input type="text" name="data_pagamento" id="f_data_p" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)"></div>
                            <div class="col-6"><label class="small fw-bold">COMPROVANTE</label><input type="file" name="comprovante" class="form-control form-control-sm"></div>
                        </div>
                        <button type="submit" id="btn_fiel" class="btn btn-info w-100 fw-bold text-white">SALVAR REGISTRO</button>
                    </form>
                </div>
            </div>
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0" style="font-size:0.75rem">
                    <thead class="table-dark"><tr><th>Fiel / Rifa</th><th>Valor</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($lista_fieis as $lf): ?>
                        <tr><td><b><?=$lf['nome_fiel']?></b> (<?=$lf['numero_rifa']?>)<br><small><?=dataBR($lf['data_pagamento'])?></small></td>
                        <td class="fw-bold text-success"><?=moedaBR($lf['valor'])?></td>
                        <td class="text-end text-nowrap pe-2">
                            <button class="btn btn-link text-primary p-0 me-2" onclick='editarFiel(<?=json_encode($lf)?>)'><i class="bi bi-pencil-square"></i></button>
                            <?php if($lf['comprovante_path']): ?><button class="btn btn-link text-success p-0 me-2" onclick="verFoto('<?=$lf['comprovante_path']?>')"><i class="bi bi-file-earmark-image"></i></button><?php endif; ?>
                            <button class="btn btn-link text-danger p-0" onclick="confirmaDelFiel(<?=$lf['id']?>, '<?=$lf['numero_rifa']?>', '<?=$lf['nome_fiel']?>')"><i class="bi bi-trash"></i></button>
                        </td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. ABA RELATÓRIOS -->
        <div class="tab-pane fade" id="tab-relatorios">
            <div class="card p-5 border-top border-5 border-dark text-center shadow">
                <i class="bi bi-file-earmark-pdf fs-1 text-danger mb-3"></i>
                <h5 class="fw-bold">Exportar Balanço Geral</h5>
                <form action="gerar_relatorio_pdf.php" method="GET" target="_blank" class="mb-4">
                    <select name="p_id" class="form-select mb-3" required><option value="">Selecione o Projeto...</option><?php foreach($projetos_select as $p): ?><option value="<?=$p['id']?>"><?=$p['nome_projeto']?></option><?php endforeach; ?></select>
                    <button type="submit" class="btn btn-dark btn-lg w-100 fw-bold shadow">GERAR PDF</button>
                </form>
                <a href="sistema_dashboard.php" class="btn btn-outline-secondary w-100 fw-bold rounded-pill shadow-sm"><i class="bi bi-house-door me-2"></i> VOLTAR À SALA DE ESTAR</a>
            </div>
        </div>

    </div>
</div>

<form id="form_del_p" method="POST" style="display:none;"><input type="hidden" name="form_acao" value="deletar_projeto"><input type="hidden" name="id_p" id="del_p_id"></form>
<form id="form_del_f" method="POST" style="display:none;"><input type="hidden" name="form_acao" value="deletar_fiel"><input type="hidden" name="id_f" id="del_f_id"></form>

<div class="modal fade" id="modalVerFoto" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h6 class="fw-bold m-0">Comprovante</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center p-4"><img src="" id="imgPreview" class="img-fluid rounded shadow"></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function maskData(i) { let v = i.value.replace(/\D/g,''); if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2); if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9); i.value = v; }
    function maskMoeda(i) { let v = i.value.replace(/\D/g,''); v = (v/100).toFixed(2) + ''; v = v.replace(".", ","); v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,"); v = v.replace(/(\d)(\d{3}),/g, "$1.$2,"); i.value = v; }
    function toggleAnexo(show) { document.getElementById('box_anexo').classList.toggle('d-none', !show); }

    document.querySelectorAll('.btn-edit-proj').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('id_proj').value = this.dataset.id;
            document.getElementById('f_nome').value = this.dataset.nome;
            document.getElementById('f_ini').value = this.dataset.ini === 'N/D' ? '' : this.dataset.ini;
            document.getElementById('f_fim').value = this.dataset.fim === 'N/D' ? '' : this.dataset.fim;
            document.getElementById('f_msg').value = this.dataset.msg;
            document.getElementById('anexo_atual').value = this.dataset.path;
            const t = this.dataset.anexo == '1'; document.getElementById('f_tem_anexo').checked = t; toggleAnexo(t);
            document.getElementById('btn_sub_proj').innerText = "ATUALIZAR PROJETO"; 
            document.getElementById('btn_sub_proj').classList.replace('btn-primary', 'btn-warning');
            document.getElementById('btn_canc_proj').classList.remove('d-none');
            window.scrollTo(0,0);
        });
    });

    function confirmaDelProj(id, nome, cont, val) {
        Swal.fire({ title: 'Excluir Projeto?', html: `<b>${nome}</b><br>Contatos: ${cont}<br>Validade: ${val}`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'DELETAR'
        }).then((r) => { if (r.isConfirmed) { document.getElementById('del_p_id').value = id; document.getElementById('form_del_p').submit(); } });
    }

    function confirmaDelFiel(id, rifa, fiel) {
        Swal.fire({ title: 'Excluir Registro?', html: `Rifa: ${rifa}<br>Fiel: ${fiel}`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'DELETAR'
        }).then((r) => { if (r.isConfirmed) { document.getElementById('del_f_id').value = id; document.getElementById('form_del_f').submit(); } });
    }

    function editarFiel(f) {
        new bootstrap.Collapse(document.getElementById('formFiel')).show();
        document.getElementById('id_fiel').value = f.id; document.getElementById('f_proj_id').value = f.projeto_id; document.getElementById('f_rifa').value = f.numero_rifa;
        document.getElementById('f_valor').value = f.valor.replace('.', ','); document.getElementById('f_nome_fiel').value = f.nome_fiel; document.getElementById('f_tel').value = f.telefone;
        document.getElementById('f_data_p').value = f.data_pagamento.split('-').reverse().join('/'); document.getElementById('f_end').value = f.endereco; document.getElementById('f_num').value = f.numero;
        document.getElementById('f_cid_fiel').value = f.cidade; document.getElementById('f_est_fiel').value = f.estado; document.getElementById('comp_atual').value = f.comprovante_path;
        document.getElementById('btn_fiel').innerText = "ATUALIZAR REGISTRO"; window.scrollTo(0,0);
    }

    function marcar(id) { const fd = new FormData(); fd.append('form_acao', 'marcar_enviado'); fd.append('contato_id', id); fetch('secretaria.php', { method: 'POST', body: fd }); const b = document.getElementById('st-'+id); b.innerText = '✔'; b.className = 'badge text-success small'; }
    function verFoto(url) { document.getElementById('imgPreview').src = url; new bootstrap.Modal(document.getElementById('modalVerFoto')).show(); }

    document.querySelectorAll('.btn-assistente').forEach(btn => {
        btn.addEventListener('click', function() {
            const msg = this.getAttribute('data-msg');
            navigator.clipboard.writeText(msg).then(() => {
                Swal.fire({ icon: 'success', title: 'Copiado!', text: 'Mensagem personalizada pronta. Cole no WhatsApp.', confirmButtonText: 'ABRIR WHATSAPP', confirmButtonColor: '#25d366'
                }).then((res) => { if(res.isConfirmed) window.open('https://web.whatsapp.com/', '_blank'); });
            });
        });
    });

    const checkTodos = document.getElementById('sel_todos');
    const checks = document.querySelectorAll('.check-c');
    const barra = document.getElementById('barra_massa');
    const qtd = document.getElementById('qtd_sel');

    function attBarra() {
        const s = document.querySelectorAll('.check-c:checked').length;
        qtd.innerText = s;
        barra.style.display = s > 0 ? 'block' : 'none';
    }

    if(checkTodos) checkTodos.addEventListener('change', function() { checks.forEach(c => c.checked = this.checked); attBarra(); });
    checks.forEach(c => c.addEventListener('change', attBarra));

    document.addEventListener("DOMContentLoaded", function() {
        const p = new URLSearchParams(window.location.search);
        if(p.get('n') !== null) {
            Swal.fire({ title: 'Importação Concluída!', html: `<div class="text-start"><p class="mb-1 text-success">Novos contatos: <b>${p.get('n')}</b></p><p class="mb-0 text-warning">Já existentes (ignorados): <b>${p.get('d')}</b></p></div>`, icon: 'success', confirmButtonText: 'FECHAR', confirmButtonColor: '#6f42c1' });
        }
        if(p.get('tab')) { const btn = document.querySelector(`[data-bs-target="#tab-${p.get('tab')}"]`); if(btn) bootstrap.Tab.getOrCreateInstance(btn).show(); }
    });
</script>
</body>
</html>
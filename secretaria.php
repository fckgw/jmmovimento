<?php
/**
 * JMM SYSTEM - GESTÃO INTEGRADA SECRETARIA MASTER FINAL
 * Versão: 100% Completa | Sem Abreviações | Correção de Array Offset Null
 */
error_reporting(E_ALL); 
ini_set('display_errors', 1);
require_once 'config.php';

// Biblioteca Excel
if (file_exists('SimpleXLSX.php')) { require_once 'SimpleXLSX.php'; }

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Sincronização de Nível e Nome
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

// --- FUNÇÕES AUXILIARES ---
function dataBR($data) { 
    if (empty($data) || $data == '0000-00-00') return 'N/D';
    return date('d/m/Y', strtotime($data)); 
}
function dataSQL($data) { 
    if (empty($data)) return null;
    if (strpos($data, '/')) {
        $p = explode('/', $data);
        if (count($p) == 3) return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    return $data;
}
function moedaBR($valor) { return 'R$ ' . number_format((float)$valor, 2, ',', '.'); }
function moedaSQL($valor) {
    $valor = str_replace(['R$', '.', ' '], '', $valor);
    return str_replace(',', '.', $valor);
}

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['form_acao'] ?? '';
    try {
        // 1. SALVAR PROJETO
        if ($acao == 'novo_projeto') {
            $id_p = $_POST['id_projeto_edit'] ?? '';
            $nome_p = trim($_POST['nome_p']);
            $ini = dataSQL($_POST['data_i']);
            $fim = dataSQL($_POST['data_f']);
            $msg_txt = $_POST['mensagem'] ?? '';
            if (!empty($id_p)) {
                $pdo->prepare("UPDATE projetos SET nome_projeto=?, data_inicio=?, data_fim=?, mensagem=? WHERE id=?")->execute([$nome_p, $ini, $fim, $msg_txt, $id_p]);
            } else {
                $pdo->prepare("INSERT INTO projetos (nome_projeto, data_inicio, data_fim, mensagem) VALUES (?, ?, ?, ?)")->execute([$nome_p, $ini, $fim, $msg_txt]);
            }
            header("Location: secretaria.php?tab=projetos&ok=1"); exit;
        }

        // 2. DELETAR PROJETO
        if ($acao == 'deletar_projeto') {
            $id_del = (int)$_POST['id_p'];
            $pdo->prepare("DELETE FROM marketing_contatos WHERE projeto_id = ?")->execute([$id_del]);
            $pdo->prepare("DELETE FROM rifas_pagamentos WHERE projeto_id = ?")->execute([$id_del]);
            $pdo->prepare("DELETE FROM projetos WHERE id = ?")->execute([$id_del]);
            header("Location: secretaria.php?tab=projetos&del=1"); exit;
        }

        // 3. IMPORTAR EXCEL
        if ($acao == 'importar_marketing') {
            $proj_id = $_POST['projeto_id'];
            if (isset($_FILES['arquivo_excel']) && $_FILES['arquivo_excel']['error'] == 0) {
                $classe = class_exists('\Shuchkin\SimpleXLSX') ? '\Shuchkin\SimpleXLSX' : 'SimpleXLSX';
                if ($xlsx = $classe::parse($_FILES['arquivo_excel']['tmp_name'])) {
                    $cont = 0;
                    foreach ($xlsx->rows() as $i => $row) {
                        if ($i == 0) continue;
                        $nome = trim($row[0] ?? '');
                        $tel = preg_replace('/\D/', '', $row[1] ?? '');
                        if (!empty($nome) && !empty($tel)) {
                            $pdo->prepare("INSERT IGNORE INTO marketing_contatos (projeto_id, nome, telefone) VALUES (?, ?, ?)")->execute([$proj_id, $nome, $tel]);
                            $cont++;
                        }
                    }
                    header("Location: secretaria.php?tab=marketing&f_projeto=$proj_id&import=$cont"); exit;
                }
            }
        }

        // 4. SALVAR FIEL
        if ($acao == 'salvar_fiel') {
            $id_fiel = $_POST['id_fiel_edit'] ?? '';
            $proj_id = $_POST['projeto_id'];
            $n_rifa  = $_POST['numero_rifa'];
            $valor   = moedaSQL($_POST['valor']);
            $nome    = trim($_POST['nome_fiel']);
            $tel     = trim($_POST['telefone']);
            $pag     = dataSQL($_POST['data_pagamento']);
            $end     = $_POST['endereco'] ?? '';
            $num     = $_POST['numero'] ?? '';
            $bairro  = $_POST['bairro'] ?? '';
            $cidade  = $_POST['cidade'] ?? '';
            $estado  = $_POST['estado'] ?? 'SP';
            
            $caminho_file = $_POST['comprovante_atual'] ?? '';
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == 0) {
                $diretorio = "uploads/comprovantes/";
                if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
                $novo_nome = time() . "_" . uniqid() . "." . pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $diretorio . $novo_nome)) $caminho_file = $diretorio . $novo_nome;
            }

            if (!empty($id_fiel)) {
                $sql = "UPDATE rifas_pagamentos SET projeto_id=?, numero_rifa=?, valor=?, nome_fiel=?, telefone=?, endereco=?, numero=?, bairro=?, cidade=?, estado=?, data_pagamento=?, comprovante_path=? WHERE id=?";
                $pdo->prepare($sql)->execute([$proj_id, $n_rifa, $valor, $nome, $tel, $end, $num, $bairro, $cidade, $estado, $pag, $caminho_file, $id_fiel]);
            } else {
                $sql = "INSERT INTO rifas_pagamentos (projeto_id, numero_rifa, valor, nome_fiel, telefone, endereco, numero, bairro, cidade, estado, data_pagamento, comprovante_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([$proj_id, $n_rifa, $valor, $nome, $tel, $end, $num, $bairro, $cidade, $estado, $pag, $caminho_file]);
            }
            header("Location: secretaria.php?tab=fieis&ok=1&f_projeto=$proj_id"); exit;
        }

        // 5. DELETAR FIEL
        if ($acao == 'deletar_fiel') {
            $pdo->prepare("DELETE FROM rifas_pagamentos WHERE id = ?")->execute([$_POST['id_f']]);
            header("Location: secretaria.php?tab=fieis&del=1"); exit;
        }

        if ($acao == 'marcar_enviado') {
            $pdo->prepare("UPDATE marketing_contatos SET status = 'Enviado' WHERE id = ?")->execute([$_POST['contato_id']]);
            echo json_encode(['status' => 'ok']); exit;
        }
    } catch (Exception $e) { die("Erro: " . $e->getMessage()); }
}

// --- CONSULTAS ---
$sql_p = "SELECT p.*, (SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = p.id) as total_contatos FROM projetos p ORDER BY id DESC";
$projetos = $pdo->query($sql_p)->fetchAll(PDO::FETCH_ASSOC);

$f_tab    = $_GET['tab'] ?? 'projetos';
$f_proj   = $_GET['f_projeto'] ?? '';
$f_ini    = $_GET['f_data_ini'] ?? '';
$f_fim    = $_GET['f_data_fim'] ?? '';
$f_cidade = $_GET['f_cidade'] ?? '';
$f_estado = $_GET['f_estado'] ?? '';

// Filtro Fiéis
$where_f = "WHERE 1=1"; $params_f = [];
if (!empty($f_proj))   { $where_f .= " AND projeto_id = ?"; $params_f[] = $f_proj; }
if (!empty($f_ini))    { $where_f .= " AND data_pagamento >= ?"; $params_f[] = dataSQL($f_ini); }
if (!empty($f_fim))    { $where_f .= " AND data_pagamento <= ?"; $params_f[] = dataSQL($f_fim); }
if (!empty($f_cidade)) { $where_f .= " AND cidade LIKE ?"; $params_f[] = "%$f_cidade%"; }
if (!empty($f_estado)) { $where_f .= " AND estado = ?"; $params_f[] = $f_estado; }

$lista_fieis = []; $total_arrecadado = 0;
if ($f_tab == 'fieis') {
    $stmt_f = $pdo->prepare("SELECT * FROM rifas_pagamentos $where_f ORDER BY data_pagamento DESC");
    $stmt_f->execute($params_f);
    $lista_fieis = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
    foreach($lista_fieis as $lf) $total_arrecadado += (float)$lf['valor'];
}

// Filtro Marketing (Correção do Offset Null aqui)
$contatos_mkt = []; $mkt_sent = 0; $mkt_pend = 0; $proj_mkt_ativo = null;
if ($f_tab == 'marketing' && !empty($f_proj)) {
    // Busca o projeto selecionado dentro da lista de projetos carregada
    foreach($projetos as $p) {
        if($p['id'] == $f_proj) {
            $proj_mkt_ativo = $p;
            break;
        }
    }
    
    // Se o projeto for encontrado, carrega as estatísticas e contatos
    if($proj_mkt_ativo) {
        $mkt_sent = $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = '$f_proj' AND status = 'Enviado'")->fetchColumn();
        $mkt_pend = $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE projeto_id = '$f_proj' AND status = 'Pendente'")->fetchColumn();
        $stmt_m = $pdo->prepare("SELECT * FROM marketing_contatos WHERE projeto_id = ? ORDER BY status ASC, nome ASC");
        $stmt_m->execute([$f_proj]);
        $contatos_mkt = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secretaria Master - JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; padding-bottom: 70px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .nav-pills .nav-link { border-radius: 25px; font-weight: bold; font-size: 0.7rem; color: #555; background: #fff; margin: 2px; border: 1px solid #eee; }
        .nav-pills .nav-link.active { background-color: #6f42c1 !important; color: #fff !important; }
        .bg-purple { background-color: #6f42c1 !important; }
        .table-custom { font-size: 0.75rem; }
        .filter-bar { background: #fff; border-radius: 15px; padding: 15px; margin-bottom: 15px; border-left: 5px solid #6f42c1; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-zap-ind { background: #25d366; color: #fff; border-radius: 50px; padding: 4px 10px; font-size: 0.7rem; font-weight: bold; text-decoration:none; border: none; }
        .btn-mkt-lista { background: #007bff; color: #fff; border-radius: 50px; padding: 4px 10px; font-size: 0.7rem; font-weight: bold; border: none; }
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
        
        <!-- 1. ABA PROJETOS -->
        <div class="tab-pane fade show active" id="tab-projetos">
            <div class="card p-4 border-top border-5 border-primary shadow">
                <h6 class="fw-bold mb-3" id="titulo_form_proj">Novo Projeto JMM</h6>
                <form method="POST">
                    <input type="hidden" name="form_acao" value="novo_projeto">
                    <input type="hidden" name="id_projeto_edit" id="id_proj">
                    <input type="text" name="nome_p" id="f_nome" class="form-control mb-2" placeholder="Nome do Projeto" required>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="small fw-bold">INÍCIO</label><input type="text" name="data_i" id="f_ini" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)"></div>
                        <div class="col-6"><label class="small fw-bold">FIM</label><input type="text" name="data_f" id="f_fim" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)"></div>
                    </div>
                    <textarea name="mensagem" id="f_msg" class="form-control mb-3" rows="3" placeholder="Mensagem WhatsApp"></textarea>
                    <button type="submit" id="btn_sub_proj" class="btn btn-primary w-100 fw-bold shadow">SALVAR PROJETO</button>
                    <button type="button" id="btn_canc_proj" class="btn btn-light w-100 mt-2 d-none" onclick="location.reload()">CANCELAR</button>
                </form>
            </div>
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0 table-custom">
                    <thead class="table-dark"><tr><th class="ps-3">Projeto</th><th>Validade</th><th class="text-end pe-3">Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($projetos as $p): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-uppercase"><?=$p['nome_projeto']?></td>
                            <td><?=dataBR($p['data_inicio'])?> a <?=dataBR($p['data_fim'])?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <button type="button" class="btn btn-link text-primary p-0 me-3 btn-edit-proj" 
                                    data-id="<?=$p['id']?>" data-nome="<?=$p['nome_projeto']?>" 
                                    data-ini="<?=dataBR($p['data_inicio'])?>" data-fim="<?=dataBR($p['data_fim'])?>" 
                                    data-msg="<?=htmlspecialchars($p['mensagem'])?>">
                                    <i class="bi bi-pencil-square fs-5"></i>
                                </button>
                                <button type="button" class="btn btn-link text-danger p-0" onclick="confirmaDelProj(<?=$p['id']?>, '<?=$p['nome_projeto']?>', '<?=$p['total_contatos']?>', '<?=dataBR($p['data_fim'])?>')">
                                    <i class="bi bi-trash fs-5"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. ABA MARKETING -->
        <div class="tab-pane fade" id="tab-marketing">
             <div class="card p-3 mb-3 shadow-sm text-center">
                <label class="small fw-bold mb-1">PROJETO ATIVO</label>
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="marketing">
                    <select name="f_projeto" class="form-select fw-bold text-primary" required>
                        <option value="">-- Escolha o Projeto --</option>
                        <?php foreach($projetos as $p): ?><option value="<?=$p['id']?>" <?=($f_proj==$p['id']?'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-dark fw-bold px-4">SELECIONAR</button>
                </form>
            </div>

            <?php if(!empty($f_proj) && $proj_mkt_ativo): ?>
                <!-- Bloco de Importação -->
                <div class="card p-4 border-top border-5 border-success text-center shadow-sm mb-3">
                    <h6 class="fw-bold mb-3">Importar Excel para: <u><?php echo $proj_mkt_ativo['nome_projeto']; ?></u></h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="form_acao" value="importar_marketing">
                        <input type="hidden" name="projeto_id" value="<?=$f_proj?>">
                        <input type="file" name="arquivo_excel" class="form-control mb-2" accept=".xlsx" required>
                        <button type="submit" class="btn btn-success w-100 fw-bold shadow">SUBIR NOVA MASSA DE DADOS</button>
                    </form>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6"><div class="card p-3 bg-success text-white text-center shadow-sm"><small>ENVIADOS</small><h4 class="m-0 fw-bold"><?=$mkt_sent?></h4></div></div>
                    <div class="col-6"><div class="card p-3 bg-warning text-dark text-center shadow-sm"><small>PENDENTES</small><h4 class="m-0 fw-bold text-dark"><?=$mkt_pend?></h4></div></div>
                </div>

                <div class="table-responsive bg-white rounded-4 shadow-sm border">
                    <table class="table align-middle mb-0 table-custom">
                        <thead class="table-light"><tr><th class="ps-3">Nome / Telefone</th><th>Status</th><th class="text-center">Ação</th></tr></thead>
                        <tbody>
                            <?php foreach($contatos_mkt as $c): 
                                $msg_raw = $proj_mkt_ativo['mensagem'] ?? 'Olá [NOME]!';
                                $msg_f = str_replace('[NOME]', $c['nome'], $msg_raw);
                            ?>
                            <tr><td class="ps-3"><b><?=$c['nome']?></b><br><?=$c['telefone']?></td>
                            <td><span id="st-<?=$c['id']?>" class="badge <?=($c['status']=='Pendente'?'bg-warning text-dark':'bg-success')?>"><?=$c['status']?></span></td>
                            <td class="text-center text-nowrap">
                                <button type="button" onclick="marcar(<?=$c['id']?>); window.open('https://api.whatsapp.com/send?phone=55<?=$c['telefone']?>&text=<?=urlencode($msg_f)?>', '_blank')" class="btn-zap-ind shadow-sm"><i class="bi bi-person-fill"></i> INDIV.</button>
                                <button type="button" class="btn-mkt-lista btn-assistente ms-1 shadow-sm" data-msg="<?=htmlspecialchars($msg_f)?>"><i class="bi bi-megaphone-fill"></i> LISTA</button>
                            </td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-light text-center border p-5">
                    <i class="bi bi-arrow-up fs-1 text-muted d-block mb-2"></i>
                    Selecione um projeto acima para gerenciar o Marketing ou importar dados.
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. ABA FIEIS & RIFAS -->
        <div class="tab-pane fade" id="tab-fieis">
            <div class="card bg-purple text-white p-3 mb-3 shadow text-center">
                <small class="fw-bold">SALDO DO FILTRO ATUAL</small>
                <h2 class="m-0 fw-bold"><?=moedaBR($total_arrecadado)?></h2>
                <small class="opacity-75"><?=count($lista_fieis)?> contribuições</small>
            </div>

            <!-- FILTROS AVANÇADOS -->
            <div class="filter-bar">
                <form method="GET" class="row g-2">
                    <input type="hidden" name="tab" value="fieis">
                    <div class="col-md-3">
                        <label class="small fw-bold text-muted">PROJETO</label>
                        <select name="f_projeto" class="form-select form-select-sm">
                            <option value="">-- Todos --</option>
                            <?php foreach($projetos as $p): ?><option value="<?=$p['id']?>" <?=($f_proj == $p['id'] ? 'selected':'')?>><?=$p['nome_projeto']?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2"><label class="small fw-bold text-muted">INÍCIO</label><input type="text" name="f_data_ini" class="form-control form-control-sm" placeholder="DD/MM/AAAA" onkeyup="maskData(this)" value="<?=$f_ini?>"></div>
                    <div class="col-6 col-md-2"><label class="small fw-bold text-muted">FIM</label><input type="text" name="f_data_fim" class="form-control form-control-sm" placeholder="DD/MM/AAAA" onkeyup="maskData(this)" value="<?=$f_fim?>"></div>
                    <div class="col-8 col-md-3"><label class="small fw-bold text-muted">CIDADE</label><input type="text" name="f_cidade" class="form-control form-control-sm" placeholder="Cidade..." value="<?=$f_cidade?>"></div>
                    <div class="col-4 col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">FILTRAR</button></div>
                </form>
            </div>

            <!-- NOVO REGISTRO -->
            <button class="btn btn-info btn-sm w-100 fw-bold text-white mb-3 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formFiel">+ NOVA ARRECADAÇÃO / RIFA</button>
            <div class="collapse" id="formFiel">
                <div class="card p-4 border-top border-5 border-info shadow">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="form_acao" value="salvar_fiel">
                        <input type="hidden" name="id_fiel_edit" id="id_fiel">
                        <input type="hidden" name="comprovante_atual" id="comp_atual">
                        <div class="row g-2 mb-2">
                            <div class="col-8"><label class="small fw-bold">PROJETO</label><select name="projeto_id" id="f_proj_id" class="form-select"><?php foreach($projetos as $p): ?><option value="<?=$p['id']?>"><?=$p['nome_projeto']?></option><?php endforeach; ?></select></div>
                            <div class="col-4"><label class="small fw-bold">VALOR R$</label><input type="text" name="valor" id="f_valor" class="form-control text-success fw-bold" value="20,00" onkeyup="maskMoeda(this)"></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4"><label class="small fw-bold">Nº RIFA</label><input type="text" name="numero_rifa" id="f_rifa" class="form-control" required></div>
                            <div class="col-8"><label class="small fw-bold">NOME FIEL</label><input type="text" name="nome_fiel" id="f_nome_fiel" class="form-control" required></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-8"><label class="small fw-bold">TELEFONE</label><input type="text" name="telefone" id="f_tel" class="form-control" required></div>
                            <div class="col-4"><label class="small fw-bold">PAGTO</label><input type="text" name="data_pagamento" id="f_data_p" class="form-control" placeholder="DD/MM/AAAA" onkeyup="maskData(this)"></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-5"><input type="text" name="endereco" id="f_end" class="form-control" placeholder="Endereço"></div>
                            <div class="col-2"><input type="text" name="numero" id="f_num" class="form-control" placeholder="Nº"></div>
                            <div class="col-3"><input type="text" name="cidade" id="f_cid" class="form-control" placeholder="Cidade"></div>
                            <div class="col-2"><select name="estado" id="f_est" class="form-select"><option value="SP">SP</option><option value="MG">MG</option><option value="RJ">RJ</option></select></div>
                        </div>
                        <div class="mb-3"><label class="small fw-bold">COMPROVANTE</label><input type="file" name="comprovante" class="form-control"></div>
                        <button type="submit" id="btn_fiel" class="btn btn-info w-100 fw-bold text-white shadow">SALVAR DADOS</button>
                    </form>
                </div>
            </div>

            <!-- GRID FIEIS -->
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0 table-custom">
                    <thead class="table-dark"><tr><th class="ps-3">Data</th><th>Fiel / Cidade</th><th>Rifa</th><th>Valor</th><th class="text-end pe-3">Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($lista_fieis as $lf): ?>
                        <tr>
                            <td class="ps-3"><?=dataBR($lf['data_pagamento'])?></td>
                            <td><b><?=$lf['nome_fiel']?></b><br><small class="text-muted"><?=$lf['cidade']?>/<?=$lf['estado']?></small></td>
                            <td><span class="badge bg-light text-primary border"><?=$lf['numero_rifa']?></span></td>
                            <td class="fw-bold text-success"><?=moedaBR($lf['valor'])?></td>
                            <td class="text-end pe-3 text-nowrap">
                                <button class="btn btn-link text-primary p-0 me-2" onclick='editarFiel(<?=json_encode($lf)?>)'><i class="bi bi-pencil-square fs-5"></i></button>
                                <?php if($lf['comprovante_path']): ?><button class="btn btn-link text-success p-0 me-2" onclick="verFoto('<?=$lf['comprovante_path']?>')"><i class="bi bi-file-earmark-image fs-5"></i></button><?php endif; ?>
                                <button type="button" class="btn btn-link text-danger p-0" onclick="confirmaDelFiel(<?=$lf['id']?>, '<?=$lf['numero_rifa']?>', '<?=$lf['nome_fiel']?>')"><i class="bi bi-trash fs-5"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($lista_fieis)): ?><tr><td colspan="5" class="text-center p-4">Nenhum registro encontrado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. ABA RELATÓRIOS -->
        <div class="tab-pane fade" id="tab-relatorios">
            <div class="card p-5 border-top border-5 border-dark text-center shadow">
                <i class="bi bi-file-earmark-pdf fs-1 text-danger mb-3"></i>
                <h5 class="fw-bold text-uppercase">Exportar Balanço Geral</h5>
                <form action="gerar_relatorio_pdf.php" method="GET" target="_blank" class="mb-4">
                    <select name="p_id" class="form-select mb-3 shadow-sm" required>
                        <option value="">Selecione o Projeto...</option>
                        <?php foreach($projetos as $p): ?><option value="<?=$p['id']?>"><?=$p['nome_projeto']?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-dark btn-lg w-100 fw-bold shadow">GERAR PDF</button>
                </form>
                <a href="sistema_dashboard.php" class="btn btn-outline-secondary w-100 fw-bold rounded-pill"><i class="bi bi-house-door me-2"></i> VOLTAR À SALA DE ESTAR</a>
            </div>
        </div>

    </div>
</div>

<!-- FORMULÁRIOS OCULTOS -->
<form id="form_del_p" method="POST" style="display:none;"><input type="hidden" name="form_acao" value="deletar_projeto"><input type="hidden" name="id_p" id="del_p_id"></form>
<form id="form_del_f" method="POST" style="display:none;"><input type="hidden" name="form_acao" value="deletar_fiel"><input type="hidden" name="id_f" id="del_f_id"></form>

<!-- MODAL VISUALIZADOR -->
<div class="modal fade" id="modalVerFoto" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h6 class="fw-bold m-0">Comprovante</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-center p-4"><img src="" id="imgPreview" class="img-fluid rounded shadow"></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function maskData(i) { let v = i.value.replace(/\D/g,''); if(v.length > 2) v = v.substring(0,2) + '/' + v.substring(2); if(v.length > 5) v = v.substring(0,5) + '/' + v.substring(5,9); i.value = v; }
    function maskMoeda(i) { let v = i.value.replace(/\D/g,''); v = (v/100).toFixed(2) + ''; v = v.replace(".", ","); v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,"); v = v.replace(/(\d)(\d{3}),/g, "$1.$2,"); i.value = v; }
    
    // Edição de Projeto via Data Attributes
    document.querySelectorAll('.btn-edit-proj').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('id_proj').value = this.dataset.id;
            document.getElementById('f_nome').value = this.dataset.nome;
            document.getElementById('f_ini').value = this.dataset.ini === 'N/D' ? '' : this.dataset.ini;
            document.getElementById('f_fim').value = this.dataset.fim === 'N/D' ? '' : this.dataset.fim;
            document.getElementById('f_msg').value = this.dataset.msg;
            document.getElementById('btn_sub_proj').innerText = "ATUALIZAR PROJETO";
            document.getElementById('btn_canc_proj').classList.remove('d-none');
            window.scrollTo(0,0);
        });
    });

    function confirmaDelProj(id, nome, contatos, validade) {
        Swal.fire({ title: 'Excluir Projeto?', html: `Projeto: <b>${nome}</b><br>Contatos: <b>${contatos}</b><br>Validade: <b>${validade}</b>`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'SIM, DELETAR!'
        }).then((res) => { if (res.isConfirmed) { document.getElementById('del_p_id').value = id; document.getElementById('form_del_p').submit(); } });
    }

    function confirmaDelFiel(id, rifa, fiel) {
        Swal.fire({ title: 'Excluir Registro?', html: `Rifa Nº: <b>${rifa}</b><br>Fiel: <b>${fiel}</b>`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'SIM, DELETAR!'
        }).then((res) => { if (res.isConfirmed) { document.getElementById('del_f_id').value = id; document.getElementById('form_del_f').submit(); } });
    }

    function editarFiel(f) {
        new bootstrap.Collapse(document.getElementById('formFiel')).show();
        document.getElementById('id_fiel').value = f.id;
        document.getElementById('f_proj_id').value = f.projeto_id;
        document.getElementById('f_rifa').value = f.numero_rifa;
        document.getElementById('f_valor').value = f.valor.replace('.', ',');
        document.getElementById('f_nome_fiel').value = f.nome_fiel;
        document.getElementById('f_tel').value = f.telefone;
        document.getElementById('f_data_p').value = f.data_pagamento.split('-').reverse().join('/');
        document.getElementById('f_end').value = f.endereco;
        document.getElementById('f_num').value = f.numero;
        document.getElementById('f_cid').value = f.cidade;
        document.getElementById('f_est').value = f.estado;
        document.getElementById('comp_atual').value = f.comprovante_path;
        document.getElementById('btn_fiel').innerText = "ATUALIZAR REGISTRO";
        window.scrollTo(0,0);
    }

    function marcar(id) { const fd = new FormData(); fd.append('form_acao', 'marcar_enviado'); fd.append('contato_id', id); fetch('secretaria.php', { method: 'POST', body: fd }); const b = document.getElementById('st-'+id); b.innerText = 'Enviado'; b.className = 'badge bg-success'; }
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

    document.addEventListener("DOMContentLoaded", function() {
        const p = new URLSearchParams(window.location.search);
        if(p.get('tab')) { const btn = document.querySelector(`[data-bs-target="#tab-${p.get('tab')}"]`); if(btn) bootstrap.Tab.getOrCreateInstance(btn).show(); }
    });
</script>
</body>
</html>
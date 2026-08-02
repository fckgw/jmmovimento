<?php
/**
 * JMM SYSTEM - MÓDULO BI JOVEM (V11.0 - FULL FILTERS + DEMOGRAFIA + PDF LOGO)
 * FOCO: Comparativo por datas, sexo, encontros e interatividade total.
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 1. CAPTURA DE TODOS OS FILTROS ---
$f_encontro = $_GET['f_encontro'] ?? '';
$f_jovem    = $_GET['f_jovem'] ?? '';
$f_inicio   = $_GET['f_inicio'] ?? '';
$f_fim      = $_GET['f_fim'] ?? '';
$f_sexo     = $_GET['f_sexo'] ?? '';
$f_tel      = $_GET['f_tel'] ?? '';

// --- 2. ESTATÍSTICAS DEMOGRÁFICAS (BASE GERAL DE JOVENS) ---
$sql_demografia = $pdo->query("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN sexo = 'Masculino' THEN 1 ELSE 0 END) as masc,
    SUM(CASE WHEN sexo = 'Feminino' THEN 1 ELSE 0 END) as fem 
    FROM jovens")->fetch(PDO::FETCH_ASSOC);

// --- 3. ESTATÍSTICAS DE ENCONTROS ---
$sql_enc_stats = $pdo->query("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN status = 'aberto' THEN 1 ELSE 0 END) as abertos,
    SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) as fechados 
    FROM encontros")->fetch(PDO::FETCH_ASSOC);

$total_fechados = (int)$sql_enc_stats['fechados'];

// --- 4. TEXTO DE FILTROS PARA O PDF ---
$filtros_ativos = [];
if($f_encontro) $filtros_ativos[] = "Encontro ID: $f_encontro";
if($f_jovem)    $filtros_ativos[] = "Jovem: $f_jovem";
if($f_inicio)   $filtros_ativos[] = "Início: ".date('d/m/Y', strtotime($f_inicio));
if($f_fim)      $filtros_ativos[] = "Fim: ".date('d/m/Y', strtotime($f_fim));
if($f_sexo)     $filtros_ativos[] = "Sexo: $f_sexo";
if($f_tel)      $filtros_ativos[] = "Tel: $f_tel";

$texto_filtro_pdf = empty($filtros_ativos) ? "Relatório Geral (Sem filtros)" : "Filtros aplicados: " . implode(" | ", $filtros_ativos);
$nome_arquivo_pdf = "BI_JMM_" . date('d-m-Y_H-i');

// --- 5. QUERY DINÂMICA PARA OS GRÁFICOS (APLICA OS FILTROS) ---
$where = " WHERE e.status = 'finalizado' ";
$params = [];
if($f_encontro) { $where .= " AND e.id = :f_encontro "; $params[':f_encontro'] = $f_encontro; }
if($f_jovem)    { $where .= " AND j.nome LIKE :f_jovem "; $params[':f_jovem'] = "%$f_jovem%"; }
if($f_inicio)   { $where .= " AND e.data_encontro >= :f_inicio "; $params[':f_inicio'] = $f_inicio; }
if($f_fim)      { $where .= " AND e.data_encontro <= :f_fim "; $params[':f_fim'] = $f_fim; }
if($f_sexo)     { $where .= " AND j.sexo = :f_sexo "; $params[':f_sexo'] = $f_sexo; }
if($f_tel)      { $where .= " AND j.telefone LIKE :f_tel "; $params[':f_tel'] = "%$f_tel%"; }

// Ranking Frequência
$st_f = $pdo->prepare("SELECT j.id, j.nome, COUNT(p.id) as total FROM jovens j INNER JOIN presencas p ON j.id = p.jovem_id INNER JOIN encontros e ON p.encontro_id = e.id $where GROUP BY j.id ORDER BY total DESC LIMIT 10");
$st_f->execute($params); $data_freq = $st_f->fetchAll(PDO::FETCH_ASSOC);

// Ranking Ausência
$data_aus = $pdo->query("SELECT j.id, j.nome, ($total_fechados - COUNT(p.id)) as faltas, COUNT(p.id) as pres FROM jovens j LEFT JOIN presencas p ON j.id = p.jovem_id LEFT JOIN encontros e ON p.encontro_id = e.id AND e.status = 'finalizado' GROUP BY j.id ORDER BY faltas DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Novos
$data_novos = $pdo->query("SELECT e.data_encontro, COUNT(p.id) as total FROM presencas p INNER JOIN encontros e ON p.encontro_id = e.id WHERE p.id IN (SELECT MIN(id) FROM presencas GROUP BY jovem_id) GROUP BY e.id ORDER BY e.data_encontro ASC")->fetchAll(PDO::FETCH_ASSOC);

// Aniversariantes
$niver_raw = $pdo->query("SELECT MONTH(data_nascimento) as mes, COUNT(*) as total FROM jovens WHERE data_nascimento IS NOT NULL GROUP BY mes ORDER BY mes")->fetchAll(PDO::FETCH_ASSOC);
$cont_niver = array_fill(1, 12, 0); foreach($niver_raw as $n) $cont_niver[$n['mes']] = (int)$n['total'];

$encontros_lista = $pdo->query("SELECT id, tema, data_encontro FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>BI JOVEM - JMM SYSTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        .logo-img { width: 90px; height: 90px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); object-fit: cover; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); background: #fff; padding: 20px; margin-bottom: 20px; }
        .chart-container { position: relative; height: 280px; width: 100%; }
        .filter-section { background: #fff; border-radius: 15px; padding: 20px; margin-bottom: 25px; border-left: 5px solid #0dcaf0; }
        label { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #666; }
        .stats-box { border-radius: 10px; padding: 10px; border: 1px solid #eee; text-align: center; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .card-custom { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
            .print-header { display: flex !important; flex-direction: column; align-items: center; text-align: center; margin-bottom: 20px; }
            .print-header img { width: 100px; height: 100px; border-radius: 50%; margin-bottom: 10px; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    
    <!-- HEADER WEB -->
    <div class="row align-items-center mb-4 no-print">
        <div class="col-md-6 d-flex align-items-center">
            <img src="Img/logo.jpg" class="logo-img me-3">
            <div><h2 class="fw-bold mb-0">BI JOVEM</h2><small class="text-uppercase fw-bold text-muted">Juventude da Matriz em Movimento</small></div>
        </div>
        <div class="col-md-6 text-end">
            <button onclick="exportarRelatorio()" class="btn btn-danger rounded-pill px-4 me-2"><i class="bi bi-file-earmark-pdf-fill"></i> EXPORTAR PDF</button>
            <a href="sistema_dashboard.php" class="btn btn-dark rounded-pill px-4">VOLTAR</a>
        </div>
    </div>

    <!-- HEADER PDF -->
    <div class="print-header">
        <img src="Img/logo.jpg">
        <h2 class="fw-bold">RELATÓRIO ESTATÍSTICO BI - JMM</h2>
        <div class="alert alert-light border w-100"><?= $texto_filtro_pdf ?></div>
    </div>

    <!-- PAINEL ESTATÍSTICO (DEMOGRAFIA + ENCONTROS) -->
    <div class="card-custom border-top border-info border-4">
        <div class="row g-3 text-center">
            <div class="col-md-4 border-end">
                <label class="d-block mb-2">Base Demográfica</label>
                <div class="d-flex justify-content-center gap-4">
                    <div class="stats-box"><i class="bi bi-gender-male text-primary h4"></i><br><b><?= $sql_demografia['masc'] ?></b><br><small>Homens</small></div>
                    <div class="stats-box"><i class="bi bi-gender-female text-danger h4"></i><br><b><?= $sql_demografia['fem'] ?></b><br><small>Mulheres</small></div>
                </div>
            </div>
            <div class="col-md-4 border-end">
                <label class="d-block mb-2">Engajamento Encontros</label>
                <div class="d-flex justify-content-center gap-3">
                    <div><span class="badge bg-success">Finalizados: <?= $sql_enc_stats['fechados'] ?></span></div>
                    <div><span class="badge bg-warning text-dark">Abertos: <?= $sql_enc_stats['abertos'] ?></span></div>
                </div>
                <h3 class="fw-bold mt-2 mb-0"><?= $sql_enc_stats['total'] ?></h3>
                <small class="fw-bold text-muted">TOTAL CRIADOS</small>
            </div>
            <div class="col-md-4">
                <label class="d-block mb-2">Total de Jovens</label>
                <h1 class="fw-bold text-dark mb-0"><?= $sql_demografia['total'] ?></h1>
                <small class="fw-bold text-muted">CADASTRADOS NA BASE</small>
            </div>
        </div>
    </div>

    <!-- FILTROS COMPLETOS (6 CAMPOS) -->
    <div class="filter-section no-print shadow-sm">
        <form method="GET" class="row g-3">
            <div class="col-md-4"><label>Encontro</label><select name="f_encontro" class="form-select form-select-sm"><option value="">Todos</option><?php foreach($encontros_lista as $e): ?><option value="<?= $e['id'] ?>" <?=($f_encontro==$e['id']?'selected':'')?>><?=date('d/m',strtotime($e['data_encontro']))?> - <?=$e['tema']?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label>Nome do Jovem</label><input type="text" name="f_jovem" class="form-control form-control-sm" value="<?=$f_jovem?>" placeholder="Buscar..."></div>
            <div class="col-md-2"><label>Sexo</label><select name="f_sexo" class="form-select form-select-sm"><option value="">Ambos</option><option value="Masculino" <?=($f_sexo=='Masculino'?'selected':'')?>>Masculino</option><option value="Feminino" <?=($f_sexo=='Feminino'?'selected':'')?>>Feminino</option></select></div>
            <div class="col-md-2"><label>Telefone</label><input type="text" name="f_tel" class="form-control form-control-sm" value="<?=$f_tel?>" placeholder="DDD + Número"></div>
            <div class="col-md-3"><label>Data Início (Comparativo)</label><input type="date" name="f_inicio" class="form-control form-control-sm" value="<?=$f_inicio?>"></div>
            <div class="col-md-3"><label>Data Fim (Comparativo)</label><input type="date" name="f_fim" class="form-control form-control-sm" value="<?=$f_fim?>"></div>
            <div class="col-md-6 d-flex align-items-end"><button type="submit" class="btn btn-info btn-sm w-100 text-white fw-bold"><i class="bi bi-funnel-fill"></i> APLICAR FILTROS E COMPARAR</button></div>
        </form>
    </div>

    <!-- GRÁFICOS INTERATIVOS -->
    <div class="row g-3">
        <div class="col-md-6"><div class="card-custom"><h6 class="fw-bold"><i class="bi bi-award text-warning"></i> TOP 10 FREQUÊNCIA</h6><div class="chart-container"><canvas id="chartFreq"></canvas></div></div></div>
        <div class="col-md-6"><div class="card-custom"><h6 class="fw-bold text-danger"><i class="bi bi-person-x-fill"></i> TOP 10 AUSÊNCIA</h6><div class="chart-container"><canvas id="chartAus"></canvas></div></div></div>
        <div class="col-md-6"><div class="card-custom"><h6 class="fw-bold text-success"><i class="bi bi-graph-up"></i> NOVOS MEMBROS NO PERÍODO</h6><div class="chart-container"><canvas id="chartNovos"></canvas></div></div></div>
        <div class="col-md-6"><div class="card-custom"><h6 class="fw-bold text-primary"><i class="bi bi-cake2"></i> ANIVERSARIANTES</h6><div class="chart-container"><canvas id="chartNiv"></canvas></div></div></div>
    </div>

    <!-- GRID DINÂMICO (AJAX) -->
    <div id="gridDinamico" class="card-custom d-none mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h5 class="fw-bold mb-0 text-uppercase" id="gridTitulo">DETALHES</h5><span id="gridInfo" class="badge bg-primary mt-2"></span></div>
            <button class="btn-close no-print" onclick="document.getElementById('gridDinamico').classList.add('d-none')"></button>
        </div>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-dark" id="gridCab"></thead><tbody id="gridCorpo"></tbody></table></div>
    </div>
</div>

<script>
Chart.defaults.maintainAspectRatio = false;

function exportarRelatorio() {
    const originalTitle = document.title;
    document.title = "<?= $nome_arquivo_pdf ?>";
    window.print();
    document.title = originalTitle;
}

const totalFechados = <?= $total_fechados ?>;

function prepararGrid(titulo, info, cabecalho) {
    const g = document.getElementById('gridDinamico');
    g.classList.remove('d-none');
    document.getElementById('gridTitulo').innerText = titulo;
    document.getElementById('gridInfo').innerText = info;
    document.getElementById('gridCab').innerHTML = cabecalho;
    document.getElementById('gridCorpo').innerHTML = '<tr><td colspan="5" class="text-center">Carregando...</td></tr>';
    g.scrollIntoView({ behavior: 'smooth' });
}

// 1. FREQUÊNCIA
new Chart(document.getElementById('chartFreq'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($i,$r){ return ($i+1).". ".explode(' ',$r['nome'])[0]; }, array_keys($data_freq), $data_freq)) ?>,
        datasets: [{ label: 'Check-ins', data: <?= json_encode(array_column($data_freq, 'total')) ?>, backgroundColor: '#ffc107' }]
    },
    options: { indexAxis: 'y', onClick: (e, el) => { if(el.length > 0) {
        const i = el[0].index; const d = <?= json_encode($data_freq) ?>[i];
        prepararGrid("HISTÓRICO: " + d.nome, `Frequência: ${d.total}/${totalFechados}`, `<tr><th>Data</th><th>Encontro</th><th>Tipo</th></tr>`);
        fetch(`bi_busca_presencas.php?jovem_id=${d.id}`).then(r=>r.json()).then(res=>{
            let h=''; res.forEach(p=>{ h+=`<tr><td>${p.data_formatada}</td><td>${p.tema}</td><td>${p.metodo.toUpperCase()}</td></tr>`; });
            document.getElementById('gridCorpo').innerHTML = h;
        });
    }}}
});

// 2. AUSÊNCIA
new Chart(document.getElementById('chartAus'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($i,$r){ return ($i+1).". ".explode(' ',$r['nome'])[0]; }, array_keys($data_aus), $data_aus)) ?>,
        datasets: [{ label: 'Faltas', data: <?= json_encode(array_column($data_aus, 'faltas')) ?>, backgroundColor: '#dc3545' }]
    },
    options: { indexAxis: 'y', onClick: (e, el) => { if(el.length > 0) {
        const i = el[0].index; const d = <?= json_encode($data_aus) ?>[i];
        prepararGrid("FALTAS: " + d.nome, `Ausente em ${d.faltas} encontros`, `<tr><th>Data</th><th>Encontro</th><th>Tipo</th></tr>`);
        fetch(`bi_busca_presencas.php?jovem_id=${d.id}`).then(r=>r.json()).then(res=>{
            let h=''; res.forEach(p=>{ h+=`<tr><td>${p.data_formatada}</td><td>${p.tema}</td><td>${p.metodo.toUpperCase()}</td></tr>`; });
            document.getElementById('gridCorpo').innerHTML = h || '<tr><td colspan="3">Nenhum registro.</td></tr>';
        });
    }}}
});

// 3. NOVOS
new Chart(document.getElementById('chartNovos'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($r){ return date('d/m', strtotime($r['data_encontro'])); }, $data_novos)) ?>,
        datasets: [{ label: 'Novos', data: <?= json_encode(array_column($data_novos, 'total')) ?>, borderColor: '#198754', fill: true, backgroundColor: 'rgba(25, 135, 84, 0.1)', tension: 0.3 }]
    },
    options: { onClick: (e, el) => { if(el.length > 0) {
        const i = el[0].index; const d = <?= json_encode($data_novos) ?>[i];
        prepararGrid("NOVOS EM " + d.data_encontro, `Total: ${d.total}`, `<tr><th>Nome</th><th>Sexo</th><th>WhatsApp</th></tr>`);
        fetch(`bi_busca_novos.php?data=${d.data_encontro}`).then(r=>r.json()).then(res=>{
            let h=''; res.forEach(j=>{ h+=`<tr><td>${j.nome}</td><td>${j.sexo}</td><td><a href="https://wa.me/55${j.tel_limpo}" target="_blank" class="btn btn-sm btn-success">WhatsApp</a></td></tr>`; });
            document.getElementById('gridCorpo').innerHTML = h;
        });
    }}}
});

// 4. ANIVERSARIANTES
new Chart(document.getElementById('chartNiv'), {
    type: 'bar',
    data: {
        labels: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
        datasets: [{ label: 'Qtd', data: <?= json_encode(array_values($cont_niver)) ?>, backgroundColor: '#0d6efd' }]
    },
    options: { onClick: (e, el) => { if(el.length > 0) {
        const mes = el[0].index + 1;
        prepararGrid("ANIVERSARIANTES DO MÊS " + mes, "", `<tr><th>Nome</th><th>Data</th><th>Idade</th><th>Ação</th></tr>`);
        fetch(`bi_busca_detalhes.php?mes=${mes}`).then(r=>r.json()).then(res=>{
            let h=''; res.forEach(j=>{ h+=`<tr><td>${j.nome}</td><td>${j.data_nasc_formatada}</td><td>${j.idade} anos</td><td><a href="https://wa.me/55${j.tel_limpo}" target="_blank" class="btn btn-sm btn-success">WhatsApp</a></td></tr>`; });
            document.getElementById('gridCorpo').innerHTML = h;
        });
    }}}
});
</script>
</body>
</html>
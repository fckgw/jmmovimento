<?php
/**
 * JMM SYSTEM - SAC v4.5
 * Filtro por Intervalo (Início/Fim) + Exportação Inteligente (XLS/PDF)
 * Agrupamento de Irmãos por Telefone
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Busca a lista de encontros para o select
$encontros = $pdo->query("SELECT id, tema, data_encontro FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);

$raw_jovens = [];
$encontro_selecionado = $_GET['encontro_id'] ?? '';
$modo_geral = isset($_GET['modo']) && $_GET['modo'] === 'geral';

// 1. BUSCA BRUTA DOS DADOS
if ($modo_geral) {
    $stmt = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC");
    $raw_jovens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $titulo_contexto = "CAMPANHA GERAL";
    $contexto_id = "geral";
} elseif ($encontro_selecionado) {
    $sql = "SELECT * FROM jovens WHERE id NOT IN (SELECT jovem_id FROM presencas WHERE encontro_id = ?) ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$encontro_selecionado]);
    $raw_jovens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $titulo_contexto = "AUSENTES";
    $contexto_id = $encontro_selecionado;
}

// 2. LÓGICA DE AGRUPAMENTO (IRMÃOS / DUPLICADOS)
$lista_jovens = [];
$mapa_telefones = [];

foreach ($raw_jovens as $jovem) {
    // Normaliza o telefone para comparação (remove tudo que não é número)
    $tel_limpo = preg_replace('/\D/', '', $jovem['telefone']);

    // Se não tiver telefone, adiciona normalmente sem agrupar
    if (empty($tel_limpo)) {
        $lista_jovens[] = $jovem;
        continue;
    }

    if (isset($mapa_telefones[$tel_limpo])) {
        // Se o telefone já existe, pegamos a referência do primeiro registro inserido
        $indexOriginal = $mapa_telefones[$tel_limpo];
        
        // Concatena o nome (Ex: João & Maria)
        $lista_jovens[$indexOriginal]['nome'] .= " & " . $jovem['nome'];
        
        // Se um deles for marcado como "Sim" na coluna Irmaos, garantimos que o registro final tenha essa marca
        if ($jovem['Irmaos'] === 'Sim') {
            $lista_jovens[$indexOriginal]['Irmaos'] = 'Sim';
        }
    } else {
        // Se é um telefone novo, armazena o índice atual e adiciona à lista
        $proximo_indice = count($lista_jovens);
        $mapa_telefones[$tel_limpo] = $proximo_indice;
        $lista_jovens[] = $jovem;
    }
}

function calcularIdade($dataNascimento) {
    if(!$dataNascimento || $dataNascimento == '0000-00-00') return "N/D";
    $nascimento = new DateTime($dataNascimento);
    $hoje = new DateTime();
    return $nascimento->diff($hoje)->y;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SAC - Operação JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card-sac { border-radius: 20px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .status-badge { font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 50px; text-transform: uppercase; }
        .bg-nao-enviado { background-color: #ffe5e5; color: #d63384; border: 1px solid #ffb3b3; }
        .bg-enviado { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .row-enviada { opacity: 0.6; }
        .btn-excel { background-color: #1d6f42; color: white; border: none; font-weight: bold; }
        .btn-pdf { background-color: #e63946; color: white; border: none; font-weight: bold; }
        .btn-bulk { background: linear-gradient(45deg, #25d366, #128c7e); color: white; border: none; font-weight: bold; }
        .input-range { max-width: 80px; text-align: center; font-weight: bold; }
        .badge-irmao { background-color: #6f42c1; color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
    </style>
</head>
<body class="pb-5">

<?php include 'navbar.php'; ?>

<div class="container">
    <div class="card p-4 card-sac mb-4 border-top border-5 border-danger shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-danger m-0"><i class="bi bi-heart-pulse-fill"></i> Operação SAC</h4>
            <a href="sac.php?modo=geral" class="btn <?= $modo_geral ? 'btn-dark' : 'btn-outline-primary' ?> btn-sm rounded-pill fw-bold">MODO GERAL</a>
        </div>
        
        <form method="GET" class="row g-2">
            <div class="col-8">
                <select name="encontro_id" class="form-select shadow-sm" onchange="this.form.submit()">
                    <option value="">Selecione o Encontro...</option>
                    <?php foreach($encontros as $e): ?>
                        <option value="<?=$e['id']?>" <?= $encontro_selecionado == $e['id'] ? 'selected' : '' ?>>
                            <?=date('d/m/y', strtotime($e['data_encontro']))?> - <?=$e['tema']?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-dark w-100 fw-bold shadow-sm">BUSCAR</button>
            </div>
        </form>
    </div>

    <?php if($encontro_selecionado || $modo_geral): ?>
        <div class="card p-3 card-sac mb-3 shadow-sm">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="small fw-bold text-muted">MENSAGEM:</label>
                    <textarea id="msg_sac" class="form-control" rows="3"><?php 
                        echo $modo_geral ? "Olá [NOME]! Tudo bem? Esperamos você no próximo JMM! 🙏❤" : "Olá [NOME]! Sentimos sua falta no JMM! Esperamos você no próximo! 🙏❤";
                    ?></textarea>
                </div>
                <div class="col-md-5">
                    <label class="small fw-bold text-danger"><i class="bi bi-filter-square"></i> SELECIONAR INTERVALO:</label>
                    <div class="d-flex align-items-center gap-2 bg-light p-2 rounded-3 border">
                        <span class="small fw-bold">De:</span>
                        <input type="number" id="range_inicio" class="form-control form-control-sm input-range" value="1" min="1">
                        <span class="small fw-bold">Até:</span>
                        <input type="number" id="range_fim" class="form-control form-control-sm input-range" value="18" min="1">
                        <button onclick="aplicarFiltroRange()" class="btn btn-danger btn-sm fw-bold px-3">FILTRAR</button>
                    </div>
                    <div class="small text-muted mt-2">Ex: 1 ao 18, depois 19 ao 37...</div>
                </div>
            </div>
            
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top pt-3 mt-3">
                <span class="badge bg-dark rounded-pill py-2 px-3" id="contador-visiveis">Aguardando Filtro...</span>
                <div class="d-flex gap-2">
                    <button onclick="exportarExcel()" class="btn btn-excel btn-sm rounded-pill px-3 shadow"><i class="bi bi-file-excel"></i> EXCEL</button>
                    <button onclick="exportarPDF()" class="btn btn-pdf btn-sm rounded-pill px-3 shadow"><i class="bi bi-file-pdf"></i> PDF</button>
                    <button onclick="salvarAgenda()" class="btn btn-primary btn-sm rounded-pill px-3 shadow"><i class="bi bi-person-plus"></i> AGENDA</button>
                    <button onclick="enviarEmMassa()" class="btn btn-bulk btn-sm rounded-pill px-3 shadow">WHATSAPP</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover bg-white border align-middle shadow-sm rounded-4 overflow-hidden">
                <thead class="table-dark small">
                    <tr>
                        <th class="ps-3 text-center" style="width: 60px;">Nº</th>
                        <th>STATUS</th>
                        <th>JOVEM / CONTATO</th>
                        <th>IDADE</th>
                        <th class="text-end pe-3">AÇÃO</th>
                    </tr>
                </thead>
                <tbody id="lista-jovens">
                    <?php $idx = 1; foreach($lista_jovens as $j): 
                        $tel_apenas_numeros = preg_replace('/\D/','',$j['telefone']);
                        $idade = calcularIdade($j['data_nascimento'] ?? '');
                        $dataFmt = ($j['data_nascimento'] && $j['data_nascimento'] != '0000-00-00') ? date('d/m/Y', strtotime($j['data_nascimento'])) : 'N/D';
                        $is_irmao = ($j['Irmaos'] === 'Sim');
                    ?>
                    <tr class="linha-jovem" 
                        id="row_<?=$j['id']?>"
                        data-index="<?=$idx?>"
                        data-id="<?=$j['id']?>" 
                        data-nome="<?=$j['nome']?>" 
                        data-nascimento="<?=$dataFmt?>"
                        data-idade="<?=$idade?>"
                        data-telefone="<?=$tel_apenas_numeros?>">
                        <td class="ps-3 fw-bold text-center text-danger border-end bg-light"><?=$idx++?></td>
                        <td><span id="status_<?=$j['id']?>" class="status-badge bg-nao-enviado">Pendente</span></td>
                        <td>
                            <div class="fw-bold small text-uppercase">
                                <?=$j['nome']?> 
                                <?php if($is_irmao): ?><span class="badge-irmao">IRMÃOS</span><?php endif; ?>
                            </div>
                            <div class="text-muted small"><?=$j['telefone']?></div>
                        </td>
                        <td class="small"><?=$idade?> anos</td>
                        <td class="text-end pe-3">
                            <button onclick="fazerOperacaoSac('<?=$tel_apenas_numeros?>', '<?=$j['nome']?>', <?=$j['id']?>)" class="btn btn-outline-success btn-sm rounded-pill"><i class="bi bi-whatsapp"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL PROGRESSO WHATSAPP -->
<div class="modal fade" id="modalBulk" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4 text-center border-0 shadow-lg" style="border-radius:20px;">
            <div class="spinner-border text-success mb-3 mx-auto"></div>
            <h5 class="fw-bold">Envio em Lote</h5>
            <div class="progress my-3" style="height:10px;">
                <div id="bulk-progress" class="progress-bar bg-success" style="width:0%"></div>
            </div>
            <button id="btn-next-bulk" onclick="processarFila()" class="btn btn-success fw-bold rounded-pill py-3 w-100">PRÓXIMO CONTATO</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const contextoID = "<?=$contexto_id?>";
    let filaEnvio = [];
    let modalBulk;

    document.addEventListener("DOMContentLoaded", () => {
        if(contextoID) {
            carregarEstadoInicial();
            modalBulk = new bootstrap.Modal(document.getElementById('modalBulk'));
            aplicarFiltroRange(); 
        }
    });

    // Filtra as linhas da tabela baseado no intervalo numérico (Nº da primeira coluna)
    function aplicarFiltroRange() {
        const inicio = parseInt(document.getElementById('range_inicio').value);
        const fim = parseInt(document.getElementById('range_fim').value);
        const linhas = document.querySelectorAll('.linha-jovem');
        let visiveis = 0;

        linhas.forEach(tr => {
            const indexAtual = parseInt(tr.getAttribute('data-index'));
            if (indexAtual >= inicio && indexAtual <= fim) {
                tr.style.display = "";
                visiveis++;
            } else {
                tr.style.display = "none";
            }
        });
        document.getElementById('contador-visiveis').innerText = `Mostrando: ${inicio} até ${fim} (${visiveis} registros)`;
    }

    // Exportação para Excel (.xls) das linhas que estão visíveis após o filtro
    function exportarExcel() {
        const visiveis = document.querySelectorAll('.linha-jovem:not([style*="display: none"])');
        let tableData = `<table border="1"><tr style="background-color:#1d6f42;color:white;"><th>Nº</th><th>NOME</th><th>NASCIMENTO</th><th>IDADE</th><th>TELEFONE</th></tr>`;

        visiveis.forEach(tr => {
            tableData += `<tr>
                <td>${tr.getAttribute('data-index')}</td>
                <td>${tr.getAttribute('data-nome').toUpperCase()}</td>
                <td>${tr.getAttribute('data-nascimento')}</td>
                <td>${tr.getAttribute('data-idade')}</td>
                <td>${tr.getAttribute('data-telefone')}</td>
            </tr>`;
        });
        tableData += `</table>`;

        const template = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>${tableData}</body></html>`;
        const blob = new Blob([template], { type: 'application/vnd.ms-excel' });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = `JMM_Lista_SAC_${document.getElementById('range_inicio').value}_a_${document.getElementById('range_fim').value}.xls`;
        a.click();
    }

    // Exportação para PDF usando jsPDF e AutoTable
    function exportarPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const ini = document.getElementById('range_inicio').value;
        const fim = document.getElementById('range_fim').value;
        
        doc.setFontSize(16);
        doc.text(`Lista SAC JMM - Intervalo ${ini} a ${fim}`, 14, 15);
        
        const rows = [];
        const visiveis = document.querySelectorAll('.linha-jovem:not([style*="display: none"])');
        
        visiveis.forEach(tr => {
            rows.push([
                tr.getAttribute('data-index'),
                tr.getAttribute('data-nome').toUpperCase(),
                tr.getAttribute('data-nascimento'),
                tr.getAttribute('data-idade'),
                tr.getAttribute('data-telefone')
            ]);
        });

        doc.autoTable({
            head: [['Nº', 'NOME', 'NASCIMENTO', 'IDADE', 'TELEFONE']],
            body: rows,
            startY: 25,
            styles: { fontSize: 9 },
            headStyles: { fillColor: [200, 0, 0] }
        });
        doc.save(`PDF_JMM_SAC_${ini}_${fim}.pdf`);
    }

    // Gera arquivo de contatos VCF para importar no celular
    function salvarAgenda() {
        const visiveis = document.querySelectorAll('.linha-jovem:not([style*="display: none"])');
        let vcf = "";
        visiveis.forEach(tr => {
            vcf += `BEGIN:VCARD\nVERSION:3.0\nFN:${tr.getAttribute('data-nome')}_JMM\nTEL;TYPE=CELL:${tr.getAttribute('data-telefone')}\nEND:VCARD\n`;
        });
        const blob = new Blob([vcf], { type: 'text/vcard' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `Agenda_Lote_JMM.vcf`;
        a.click();
    }

    // Abre o WhatsApp e copia a mensagem para o Clipboard
    async function fazerOperacaoSac(tel, nome, id) {
        let msg = document.getElementById('msg_sac').value.replace('[NOME]', nome);
        try { 
            await navigator.clipboard.writeText(msg); 
        } catch(e) {
            console.log("Erro ao copiar: ", e);
        }
        window.open(`https://wa.me/55${tel}?text=${encodeURIComponent(msg)}`, '_blank');
        marcarComoEnviado(id);
    }

    // Prepara a fila para envio em massa (apenas dos visíveis no filtro)
    function enviarEmMassa() {
        filaEnvio = [];
        document.querySelectorAll('.linha-jovem:not([style*="display: none"])').forEach(tr => {
            const id = tr.getAttribute('data-id');
            const statusText = document.getElementById('status_'+id).innerText;
            if(statusText !== "ENVIADO") {
                filaEnvio.push({
                    id: id, 
                    nome: tr.getAttribute('data-nome'), 
                    tel: tr.getAttribute('data-telefone')
                });
            }
        });

        if(filaEnvio.length > 0) { 
            modalBulk.show(); 
            atualizarUIBulk(); 
        } else { 
            alert("Todos os jovens deste intervalo já constam como ENVIADOS!"); 
        }
    }

    function processarFila() {
        if(filaEnvio.length > 0) {
            const j = filaEnvio.shift();
            fazerOperacaoSac(j.tel, j.nome, j.id);
            atualizarUIBulk();
        } else { 
            modalBulk.hide();
            alert("Fim da fila de envio!");
        }
    }

    function atualizarUIBulk() {
        const totalLote = document.querySelectorAll('.linha-jovem:not([style*="display: none"])').length;
        const progresso = ((totalLote - filaEnvio.length) / totalLote * 100);
        document.getElementById('bulk-progress').style.width = progresso + "%";
        
        if(filaEnvio.length === 0) {
            document.getElementById('btn-next-bulk').innerText = "CONCLUÍDO (FECHAR)";
        } else {
            document.getElementById('btn-next-bulk').innerText = "PRÓXIMO CONTATO (" + filaEnvio.length + " restantes)";
        }
    }

    // Atualiza visualmente a linha e salva no LocalStorage para não perder ao atualizar a página
    function marcarComoEnviado(id) {
        const el = document.getElementById('status_'+id);
        if(el) {
            el.innerText = "ENVIADO";
            el.classList.replace('bg-nao-enviado', 'bg-enviado');
            document.getElementById('row_'+id).classList.add('row-enviada');
            
            let key = "sac_contexto_" + contextoID;
            let hist = JSON.parse(localStorage.getItem(key)) || [];
            if(!hist.includes(id.toString())) { 
                hist.push(id.toString()); 
                localStorage.setItem(key, JSON.stringify(hist)); 
            }
        }
    }

    function carregarEstadoInicial() {
        let key = "sac_contexto_" + contextoID;
        let hist = JSON.parse(localStorage.getItem(key)) || [];
        hist.forEach(id => {
            const el = document.getElementById('status_'+id);
            if(el) {
                el.innerText = "ENVIADO";
                el.classList.replace('bg-nao-enviado', 'bg-enviado');
                const row = document.getElementById('row_'+id);
                if(row) row.classList.add('row-enviada');
            }
        });
    }

    function resetarEnvios() {
        if(confirm("Deseja realmente limpar o histórico de envios deste filtro?")) { 
            localStorage.removeItem("sac_contexto_"+contextoID); 
            location.reload(); 
        }
    }
</script>
</body>
</html>
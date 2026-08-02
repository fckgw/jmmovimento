<?php
/**
 * JMM SYSTEM - SAC AUSENTES v1.3
 * CORREÇÃO: Erro de PHP Null em preg_replace
 * FOCO: Agrupamento de Irmãos + Exportação PDF/Excel Estável
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// 1. Localizar o encontro Ativo
$stmt_enc = $pdo->query("SELECT id, tema, data_encontro FROM encontros WHERE ativo = 1 LIMIT 1");
$enc_ativo = $stmt_enc->fetch(PDO::FETCH_ASSOC);

$lista_final = [];
$total_ausentes_geral = 0;

if ($enc_ativo) {
    // 2. Buscar jovens que NÃO fizeram check-in
    $sql = "SELECT * FROM jovens WHERE id NOT IN (SELECT jovem_id FROM presencas WHERE encontro_id = ?) ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enc_ativo['id']]);
    $ausentes_bruto = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mapa_telefones = [];
    foreach ($ausentes_bruto as $j) {
        // CORREÇÃO DO ERRO DEPRECATED: Adicionado ?? '' para nunca passar null ao preg_replace
        $tel_original = $j['telefone'] ?? '';
        $tel_limpo = preg_replace('/\D/', '', $tel_original);
        
        // Critério: DDD + 9 números (Total 11 dígitos)
        if (strlen($tel_limpo) === 11) {
            if (isset($mapa_telefones[$tel_limpo])) {
                $idx = $mapa_telefones[$tel_limpo];
                $lista_final[$idx]['nome'] .= " & " . $j['nome'];
            } else {
                $mapa_telefones[$tel_limpo] = count($lista_final);
                
                $data_nasc = $j['data_nascimento'] ?? null;
                $idade = 'N/D';
                if ($data_nasc && $data_nasc != '0000-00-00') {
                    $idade = (new DateTime())->diff(new DateTime($data_nasc))->y . " anos";
                }

                $lista_final[] = [
                    'id' => $j['id'],
                    'nome' => $j['nome'],
                    'telefone' => $tel_original,
                    'tel_limpo' => $tel_limpo,
                    'data_nascimento' => ($data_nasc && $data_nasc != '0000-00-00') ? date('d/m/Y', strtotime($data_nasc)) : 'N/D',
                    'idade' => $idade
                ];
            }
        }
    }
    $total_ausentes_geral = count($lista_final);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SAC Ausentes - JMM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card-sac { border-radius: 20px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .bg-enviado { background-color: #d1e7dd !important; color: #0f5132 !important; opacity: 0.7; }
        .input-range { max-width: 80px; text-align: center; font-weight: bold; }
        .btn-excel { background-color: #1d6f42; color: white; border: none; font-weight: bold; }
        .btn-pdf { background-color: #e63946; color: white; border: none; font-weight: bold; }
    </style>
</head>
<body class="pb-5">

<div class="container py-4">
    
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="sistema_dashboard.php" class="btn btn-white rounded-pill shadow-sm fw-bold"><i class="bi bi-arrow-left"></i> VOLTAR</a>
        <img src="Img/logo.jpg" id="img-logo" width="60" class="rounded-circle shadow border border-2 border-white">
        <div class="text-end">
            <h4 class="fw-bold m-0 text-uppercase">SAC Ausentes</h4>
            <span class="badge bg-danger shadow-sm">TOTAL: <?= $total_ausentes_geral ?> JOVENS</span>
        </div>
    </div>

    <?php if(!$enc_ativo): ?>
        <div class="alert alert-warning rounded-4 shadow-sm border-0">Nenhum encontro ativo.</div>
    <?php else: ?>

        <div class="card p-4 card-sac mb-4 border-top border-5 border-danger shadow-sm">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="small fw-bold text-muted text-uppercase">Intervalo do Filtro:</label>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <input type="number" id="range_inicio" class="form-control form-control-sm input-range" value="1">
                        <span class="fw-bold">até</span>
                        <input type="number" id="range_fim" class="form-control form-control-sm input-range" value="18">
                        <button onclick="aplicarFiltro()" class="btn btn-danger btn-sm fw-bold px-3">FILTRAR</button>
                    </div>
                </div>
                <div class="col-md-7 text-md-end text-center">
                    <button onclick="exportarExcel()" class="btn btn-excel btn-sm rounded-pill px-3 shadow-sm me-2"><i class="bi bi-file-earmark-excel"></i> EXCEL</button>
                    <button onclick="exportarPDF()" class="btn btn-pdf btn-sm rounded-pill px-3 shadow-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="mt-3">
                <label class="small fw-bold text-muted">MENSAGEM PADRÃO:</label>
                <textarea id="msg_sac" class="form-control mt-1 shadow-sm" rows="2">Olá [NOME]! Sentimos sua falta no JMM! Esperamos você no próximo encontro! 🙏❤</textarea>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover bg-white border align-middle shadow-sm rounded-4 overflow-hidden">
                <thead class="table-dark small text-uppercase">
                    <tr>
                        <th class="ps-3 text-center" style="width: 60px;">Nº</th>
                        <th>Jovem / Contato</th>
                        <th class="text-center">Idade</th>
                        <th class="text-end pe-3">Ação</th>
                    </tr>
                </thead>
                <tbody id="corpo-ausentes">
                    <?php 
                    $idx = 1; 
                    foreach($lista_final as $j): 
                    ?>
                    <tr class="linha-jovem" 
                        data-index="<?=$idx?>" 
                        id="row_<?=$j['id']?>" 
                        data-nome="<?=$j['nome']?>" 
                        data-tel="<?=$j['tel_limpo']?>"
                        data-nascimento="<?=$j['data_nascimento']?>"
                        data-idade="<?=$j['idade']?>">
                        <td class="ps-3 text-center fw-bold text-danger border-end bg-light"><?=$idx++?></td>
                        <td>
                            <div class="fw-bold text-uppercase small"><?=$j['nome']?></div>
                            <div class="text-muted small" style="font-size: 11px;"><?=$j['telefone']?></div>
                            <span id="status_<?=$j['id']?>" class="badge bg-light text-dark border" style="font-size: 9px;">PENDENTE</span>
                        </td>
                        <td class="text-center small"><?=$j['idade']?></td>
                        <td class="text-end pe-3">
                            <button onclick="abrirZap('<?=$j['tel_limpo']?>', '<?=$j['nome']?>', <?=$j['id']?>)" class="btn btn-outline-success btn-sm rounded-circle"><i class="bi bi-whatsapp"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
    const encontroTema = "<?= $enc_ativo['tema'] ?? 'SAC AUSENTES' ?>";
    const encontroData = "<?= date('d/m/Y', strtotime($enc_ativo['data_encontro'] ?? 'now')) ?>";

    function aplicarFiltro() {
        const ini = parseInt(document.getElementById('range_inicio').value);
        const fim = parseInt(document.getElementById('range_fim').value);
        document.querySelectorAll('.linha-jovem').forEach(tr => {
            const index = parseInt(tr.getAttribute('data-index'));
            tr.style.display = (index >= ini && index <= fim) ? "" : "none";
        });
    }

    async function abrirZap(tel, nome, id) {
        let msg = document.getElementById('msg_sac').value.replace('[NOME]', nome);
        try { await navigator.clipboard.writeText(msg); } catch(e) {}
        window.open(`https://wa.me/55${tel}?text=${encodeURIComponent(msg)}`, '_blank');
        marcarComoEnviado(id);
    }

    function marcarComoEnviado(id) {
        const tr = document.getElementById('row_' + id);
        const badge = document.getElementById('status_' + id);
        if(badge) {
            badge.innerText = "ENVIADO";
            badge.className = "badge bg-success text-white border";
        }
        if(tr) tr.classList.add('bg-enviado');
        
        let hist = JSON.parse(localStorage.getItem('sac_aus_' + id)) || [];
        // Lógica simplificada de histórico por ID
        localStorage.setItem('sac_aus_' + id, 'true');
    }

    function exportarExcel() {
        let tableData = "<table><tr style='background:#1d6f42;color:#fff;'><th>Nº</th><th>NOME</th><th>NASCIMENTO</th><th>IDADE</th><th>TELEFONE</th></tr>";
        document.querySelectorAll('.linha-jovem').forEach(tr => {
            if(tr.style.display !== "none") {
                tableData += `<tr>
                    <td>${tr.getAttribute('data-index')}</td>
                    <td>${tr.getAttribute('data-nome').toUpperCase()}</td>
                    <td>${tr.getAttribute('data-nascimento')}</td>
                    <td>${tr.getAttribute('data-idade')}</td>
                    <td>${tr.getAttribute('data-tel')}</td>
                </tr>`;
            }
        });
        tableData += "</table>";

        const template = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>${tableData}</body></html>`;
        const blob = new Blob([template], { type: 'application/vnd.ms-excel' });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "Ausentes_JMM_Export.xls";
        a.click();
    }

    function exportarPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const dataAgora = new Date().toLocaleString('pt-BR');

        // Logo
        const img = document.getElementById('img-logo');
        if(img) doc.addImage(img, 'JPEG', 14, 10, 15, 15);

        doc.setFontSize(16);
        doc.setTextColor(180, 0, 0);
        doc.text("LISTA DE AUSENTES - JMM SYSTEM", 32, 16);
        
        doc.setFontSize(9);
        doc.setTextColor(100);
        doc.text(`Encontro: ${encontroTema} (${encontroData})`, 32, 22);
        doc.text(`Gerado em: ${dataAgora}`, 140, 22);

        const rows = [];
        document.querySelectorAll('.linha-jovem').forEach(tr => {
            if(tr.style.display !== "none") {
                rows.push([
                    tr.getAttribute('data-index'),
                    tr.getAttribute('data-nome').toUpperCase(),
                    tr.getAttribute('data-nascimento'),
                    tr.getAttribute('data-idade'),
                    tr.getAttribute('data-tel')
                ]);
            }
        });

        doc.autoTable({
            head: [['Nº', 'NOME', 'NASC.', 'IDADE', 'TELEFONE']],
            body: rows,
            startY: 30,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [40, 40, 40] },
            didDrawPage: (data) => {
                doc.setFontSize(8);
                doc.setTextColor(150);
                doc.text("JMM System - Operação SAC Ausentes", 14, doc.internal.pageSize.height - 10);
                doc.text("Página " + data.pageNumber, 180, doc.internal.pageSize.height - 10);
            }
        });

        doc.save("SAC_Ausentes_JMM.pdf");
    }

    window.onload = () => {
        aplicarFiltro();
        document.querySelectorAll('.linha-jovem').forEach(tr => {
            const id = tr.getAttribute('id').replace('row_', '');
            if(localStorage.getItem('sac_aus_' + id)) {
                tr.classList.add('bg-enviado');
                const badge = document.getElementById('status_' + id);
                if(badge) {
                    badge.innerText = "ENVIADO";
                    badge.className = "badge bg-success text-white border";
                }
            }
        });
    }
</script>
</body>
</html>
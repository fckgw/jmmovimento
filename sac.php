<?php
/**
 * JMM SYSTEM - SAC (SISTEMA DE APOIO AO CRISTÃO) v2.1
 * Controle de Operação com Mensagem Padrão JMM
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$encontros = $pdo->query("SELECT id, tema, data_encontro FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);

$ausentes = [];
$encontro_selecionado = $_GET['encontro_id'] ?? '';

if ($encontro_selecionado) {
    // Busca jovens que não estão na lista de presença do encontro selecionado
    $sql = "SELECT * FROM jovens 
            WHERE id NOT IN (SELECT jovem_id FROM presencas WHERE encontro_id = ?) 
            ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$encontro_selecionado]);
    $ausentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .status-badge { font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 50px; text-transform: uppercase; cursor: pointer; }
        .bg-nao-enviado { background-color: #ffe5e5; color: #d63384; border: 1px solid #ffb3b3; }
        .bg-enviado { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .row-enviada { opacity: 0.7; transition: 0.3s; }
        textarea.form-control { border-radius: 15px; font-size: 0.9rem; line-height: 1.4; border: 1px solid #ddd; }
    </style>
</head>
<body class="pb-5">

<?php include 'navbar.php'; ?>

<div class="container">
    <!-- FILTRO DE ENCONTRO -->
    <div class="card p-4 card-sac mb-4 border-top border-5 border-danger">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-danger m-0"><i class="bi bi-heart-pulse-fill"></i> Operação SAC</h4>
            <?php if($encontro_selecionado): ?>
                <button onclick="resetarEnvios()" class="btn btn-outline-secondary btn-sm rounded-pill fw-bold">Limpar Envios</button>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="row g-2">
            <div class="col-8">
                <select name="encontro_id" class="form-select shadow-sm" required onchange="this.form.submit()">
                    <option value="">Selecionar Encontro...</option>
                    <?php foreach($encontros as $e): ?>
                        <option value="<?=$e['id']?>" <?= $encontro_selecionado == $e['id'] ? 'selected' : '' ?>>
                            <?=date('d/m/y', strtotime($e['data_encontro']))?> - <?=$e['tema']?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-dark w-100 shadow-sm fw-bold">FILTRAR</button>
            </div>
        </form>
    </div>

    <?php if($encontro_selecionado): ?>
        <!-- CONFIGURAÇÃO DA MENSAGEM PADRÃO -->
        <div class="card p-3 card-sac mb-3 shadow-sm">
            <label class="fw-bold mb-2 small text-muted text-uppercase">Texto da Mensagem (use [NOME]):</label>
            <!-- O texto abaixo já contém as quebras de linha para o WhatsApp -->
            <textarea id="msg_sac" class="form-control mb-3 shadow-sm" rows="5">Olá [NOME]! A Paz de Jesus, e o amor de Maria,

Sentimos sua falta no nosso ultimo encontro JMM! 

Esperamos você no próximo, não falta não! 🙏❤</textarea>
            
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-secondary rounded-pill"><?=count($ausentes)?> jovens ausentes</span>
                <span class="small text-muted fw-bold" id="contador-envios">0 de <?=count($ausentes)?> enviados</span>
            </div>
        </div>

        <!-- GRID DE JOVENS AUSENTES -->
        <div class="table-responsive">
            <table class="table table-hover bg-white border align-middle shadow-sm rounded-4 overflow-hidden">
                <thead class="table-dark small">
                    <tr>
                        <th class="ps-3">STATUS</th>
                        <th>JOVEM / CONTATO</th>
                        <th class="text-end pe-3">ENVIAR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ausentes as $a): ?>
                    <tr id="row_<?=$a['id']?>">
                        <td class="ps-3">
                            <span id="status_<?=$a['id']?>" 
                                  onclick="marcarComoManual(<?=$a['id']?>)"
                                  class="status-badge bg-nao-enviado">
                                Não enviado
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold small text-uppercase"><?=$a['nome']?></div>
                            <div class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-telephone"></i> <?=$a['telefone']?></div>
                        </td>
                        <td class="text-end pe-3">
                            <button onclick="fazerOperacaoSac('<?=preg_replace('/\D/','',$a['telefone'])?>', '<?=$a['nome']?>', <?=$a['id']?>)" 
                                    class="btn btn-success btn-sm rounded-pill px-3 shadow-sm fw-bold">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-2">Selecione um encontro acima para listar os jovens que faltaram.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    const encontroAtivo = "<?=$encontro_selecionado?>";

    document.addEventListener("DOMContentLoaded", () => {
        if(encontroAtivo) carregarEstadoInicial();
    });

    function fazerOperacaoSac(telefone, nome, id) {
        let msgBase = document.getElementById('msg_sac').value;
        // Substituição do Nome
        let msgFinal = msgBase.replace('[NOME]', nome);
        
        // O encodeURIComponent cuida de transformar as quebras de linha do textarea em códigos que o WhatsApp entende
        let url = "https://wa.me/55" + telefone + "?text=" + encodeURIComponent(msgFinal);
        
        window.open(url, '_blank');
        
        marcarComoEnviado(id);
    }

    function marcarComoEnviado(id) {
        const elStatus = document.getElementById('status_' + id);
        const elRow = document.getElementById('row_' + id);
        
        if(elStatus) {
            elStatus.innerText = "Enviado";
            elStatus.classList.remove('bg-nao-enviado');
            elStatus.classList.add('bg-enviado');
            elRow.classList.add('row-enviada');
            salvarNoHistorico(id);
            atualizarContador();
        }
    }

    function marcarComoManual(id) {
        const elStatus = document.getElementById('status_' + id);
        if(elStatus.innerText === "ENVIADO") {
            removerDoHistorico(id);
            location.reload(); 
        } else {
            marcarComoEnviado(id);
        }
    }

    function salvarNoHistorico(id) {
        let key = "sac_encontro_" + encontroAtivo;
        let historico = JSON.parse(localStorage.getItem(key)) || [];
        if(!historico.includes(id)) {
            historico.push(id);
            localStorage.setItem(key, JSON.stringify(historico));
        }
    }

    function removerDoHistorico(id) {
        let key = "sac_encontro_" + encontroAtivo;
        let historico = JSON.parse(localStorage.getItem(key)) || [];
        historico = historico.filter(item => item !== id);
        localStorage.setItem(key, JSON.stringify(historico));
    }

    function carregarEstadoInicial() {
        let key = "sac_encontro_" + encontroAtivo;
        let historico = JSON.parse(localStorage.getItem(key)) || [];
        historico.forEach(id => {
            if(document.getElementById('status_' + id)) {
                marcarComoEnviado(id);
            }
        });
        atualizarContador();
    }

    function atualizarContador() {
        let total = <?=count($ausentes)?>;
        let key = "sac_encontro_" + encontroAtivo;
        let enviados = (JSON.parse(localStorage.getItem(key)) || []).length;
        document.getElementById('contador-envios').innerText = enviados + " de " + total + " enviados";
    }

    function resetarEnvios() {
        if(confirm("Deseja marcar todos como 'NÃO ENVIADO' para este encontro?")) {
            let key = "sac_encontro_" + encontroAtivo;
            localStorage.removeItem(key);
            location.reload();
        }
    }
</script>

</body>
</html>
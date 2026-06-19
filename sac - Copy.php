<?php
/**
 * JMM SYSTEM - SAC (SISTEMA DE APOIO AO CRISTÃO) v3.0
 * Operação Encontro + Operação Geral (Massa)
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Busca todos os encontros para o Select
$encontros = $pdo->query("SELECT id, tema, data_encontro FROM encontros ORDER BY data_encontro DESC")->fetchAll(PDO::FETCH_ASSOC);

$lista_jovens = [];
$encontro_selecionado = $_GET['encontro_id'] ?? '';
$modo_geral = isset($_GET['modo']) && $_GET['modo'] === 'geral';

// Lógica de Busca
if ($modo_geral) {
    // MODO GERAL: Busca todos os jovens do banco
    $stmt = $pdo->query("SELECT * FROM jovens ORDER BY nome ASC");
    $lista_jovens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $titulo_contexto = "CAMPANHA GERAL (TODOS)";
    $contexto_id = "geral";
} elseif ($encontro_selecionado) {
    // MODO ENCONTRO: Busca ausentes
    $sql = "SELECT * FROM jovens 
            WHERE id NOT IN (SELECT jovem_id FROM presencas WHERE encontro_id = ?) 
            ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$encontro_selecionado]);
    $lista_jovens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $titulo_contexto = "AUSENTES DO ENCONTRO";
    $contexto_id = $encontro_selecionado;
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
        .btn-bulk { background: linear-gradient(45deg, #25d366, #128c7e); color: white; border: none; font-weight: bold; }
        .btn-modo-geral { background-color: #6f42c1; color: white; border: none; font-weight: bold; }
        .btn-modo-geral:hover { background-color: #59359a; color: white; }
    </style>
</head>
<body class="pb-5">

<?php include 'navbar.php'; ?>

<div class="container">
    <!-- FILTRO E SELEÇÃO DE MODO -->
    <div class="card p-4 card-sac mb-4 border-top border-5 border-danger">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-danger m-0"><i class="bi bi-heart-pulse-fill"></i> Operação SAC</h4>
            <div class="d-flex gap-2">
                <a href="sac.php?modo=geral" class="btn <?= $modo_geral ? 'btn-dark' : 'btn-outline-primary' ?> btn-sm rounded-pill fw-bold">
                    <i class="bi bi-people-fill"></i> MODO GERAL
                </a>
                <?php if($encontro_selecionado || $modo_geral): ?>
                    <button onclick="resetarEnvios()" class="btn btn-outline-secondary btn-sm rounded-pill fw-bold">Limpar Envios</button>
                <?php endif; ?>
            </div>
        </div>
        
        <form method="GET" class="row g-2">
            <div class="col-8">
                <select name="encontro_id" class="form-select shadow-sm" onchange="this.form.submit()">
                    <option value="">Filtrar por Encontro...</option>
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

    <?php if($encontro_selecionado || $modo_geral): ?>
        <!-- CONFIGURAÇÃO DA MENSAGEM -->
        <div class="card p-3 card-sac mb-3 shadow-sm">
            <div class="d-flex justify-content-between mb-2">
                <label class="fw-bold small text-muted text-uppercase">Texto da Mensagem (use [NOME]):</label>
                <span class="badge bg-primary px-3"><?= $titulo_contexto ?></span>
            </div>
            
            <textarea id="msg_sac" class="form-control mb-3 shadow-sm" rows="5"><?php 
                if($modo_geral) {
                    echo "Olá [NOME]! Passando para desejar uma semana abençoada. Esperamos você no próximo JMM! 🙏❤";
                } else {
                    echo "Olá [NOME]! A Paz de Jesus, e o amor de Maria,\n\nSentimos sua falta no nosso ultimo encontro JMM! \n\nEsperamos você no próximo, não falta não! 🙏❤";
                }
            ?></textarea>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-secondary rounded-pill"><?=count($lista_jovens)?> jovens na lista</span>
                    <span class="small text-muted fw-bold ms-2" id="contador-envios">0 de <?=count($lista_jovens)?> enviados</span>
                </div>
                <button onclick="enviarEmMassa()" class="btn btn-bulk btn-sm rounded-pill px-4 shadow">
                    <i class="bi bi-send-check-fill"></i> ENVIAR P/ TODOS
                </button>
            </div>
        </div>

        <!-- LISTA DE JOVENS -->
        <div class="table-responsive">
            <table class="table table-hover bg-white border align-middle shadow-sm rounded-4 overflow-hidden">
                <thead class="table-dark small">
                    <tr>
                        <th class="ps-3">STATUS</th>
                        <th>JOVEM / CONTATO</th>
                        <th class="text-end pe-3">AÇÃO</th>
                    </tr>
                </thead>
                <tbody id="lista-jovens">
                    <?php foreach($lista_jovens as $j): ?>
                    <tr id="row_<?=$j['id']?>" 
                        data-id="<?=$j['id']?>" 
                        data-nome="<?=$j['nome']?>" 
                        data-telefone="<?=preg_replace('/\D/','',$j['telefone'])?>">
                        <td class="ps-3">
                            <span id="status_<?=$j['id']?>" 
                                  onclick="marcarComoManual(<?=$j['id']?>)"
                                  class="status-badge bg-nao-enviado">
                                Não enviado
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold small text-uppercase"><?=$j['nome']?></div>
                            <div class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-telephone"></i> <?=$j['telefone']?></div>
                        </td>
                        <td class="text-end pe-3">
                            <button onclick="fazerOperacaoSac('<?=preg_replace('/\D/','',$j['telefone'])?>', '<?=$j['nome']?>', <?=$j['id']?>)" 
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
        <!-- TELA INICIAL VAZIA -->
        <div class="text-center py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <i class="bi bi-chat-heart text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 fw-bold">Bem-vindo ao Operação SAC</h5>
                    <p class="text-muted">Escolha uma opção para começar o contato com os jovens:</p>
                    <div class="d-grid gap-2">
                        <a href="?modo=geral" class="btn btn-primary rounded-pill shadow-sm py-3 fw-bold">
                            MODO GERAL: Enviar para toda a Base de Dados
                        </a>
                        <div class="text-muted my-2">-- OU --</div>
                        <p class="small">Selecione um <b>encontro específico</b> acima para falar com quem faltou.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL DE CONTROLE DE FILA -->
<div class="modal fade" id="modalBulk" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-success mb-3" role="status"></div>
                <h5 class="fw-bold">Envio Sequencial Ativo</h5>
                <p id="bulk-info" class="text-muted small">Iniciando...</p>
                <div class="progress mb-4" style="height: 10px; border-radius: 10px;">
                    <div id="bulk-progress" class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
                <div class="d-grid gap-2">
                    <button id="btn-next-bulk" onclick="processarFila()" class="btn btn-success fw-bold rounded-pill py-3">
                        ABRIR PRÓXIMO WHATSAPP
                    </button>
                    <button onclick="location.reload()" class="btn btn-light btn-sm text-muted">Parar Campanha</button>
                </div>
                <p class="mt-3 x-small text-muted" style="font-size: 0.7rem;">
                    * A cada clique, o nome será trocado e a mensagem copiada para sua área de transferência.
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const contextoID = "<?=$contexto_id?>"; // Pode ser o ID do encontro ou 'geral'
    let filaEnvio = [];
    let modalBulk;

    document.addEventListener("DOMContentLoaded", () => {
        if(contextoID) {
            carregarEstadoInicial();
            modalBulk = new bootstrap.Modal(document.getElementById('modalBulk'));
        }
    });

    async function fazerOperacaoSac(telefone, nome, id) {
        let msgBase = document.getElementById('msg_sac').value;
        let msgFinal = msgBase.replace('[NOME]', nome);
        
        // COPIA PARA MEMÓRIA (CLIPBOARD)
        try {
            await navigator.clipboard.writeText(msgFinal);
        } catch (err) { console.error('Erro ao copiar texto'); }

        let url = "https://wa.me/55" + telefone + "?text=" + encodeURIComponent(msgFinal);
        window.open(url, '_blank');
        
        marcarComoEnviado(id);
    }

    function enviarEmMassa() {
        filaEnvio = [];
        const linhas = document.querySelectorAll('#lista-jovens tr');
        
        linhas.forEach(linha => {
            const id = linha.getAttribute('data-id');
            const status = document.getElementById('status_' + id).innerText;
            if(status !== "ENVIADO") {
                filaEnvio.push({
                    id: id,
                    nome: linha.getAttribute('data-nome'),
                    telefone: linha.getAttribute('data-telefone')
                });
            }
        });

        if(filaEnvio.length === 0) {
            alert("Não há jovens pendentes de envio nesta lista.");
            return;
        }

        if(confirm("Deseja iniciar o envio para " + filaEnvio.length + " jovens?")) {
            modalBulk.show();
            atualizarUIBulk();
        }
    }

    function processarFila() {
        if(filaEnvio.length > 0) {
            const atual = filaEnvio.shift();
            fazerOperacaoSac(atual.telefone, atual.nome, atual.id);
            atualizarUIBulk();
        } else {
            document.getElementById('bulk-info').innerText = "Todos os envios foram processados!";
            document.getElementById('btn-next-bulk').disabled = true;
            setTimeout(() => { location.reload(); }, 1500);
        }
    }

    function atualizarUIBulk() {
        const totalPagina = <?=count($lista_jovens)?>;
        const pendentes = filaEnvio.length;
        const enviados = totalPagina - pendentes;
        const progresso = (enviados / totalPagina) * 100;

        document.getElementById('bulk-info').innerText = pendentes + " jovens restantes na fila.";
        document.getElementById('bulk-progress').style.width = progresso + "%";
        
        if(pendentes === 0) {
            document.getElementById('btn-next-bulk').innerText = "CONCLUIR";
        }
    }

    function marcarComoEnviado(id) {
        const elStatus = document.getElementById('status_' + id);
        const elRow = document.getElementById('row_' + id);
        if(elStatus) {
            elStatus.innerText = "Enviado";
            elStatus.classList.replace('bg-nao-enviado', 'bg-enviado');
            elRow.classList.add('row-enviada');
            salvarNoHistorico(id);
            atualizarContador();
        }
    }

    function salvarNoHistorico(id) {
        let key = "sac_contexto_" + contextoID;
        let historico = JSON.parse(localStorage.getItem(key)) || [];
        if(!historico.includes(id)) {
            historico.push(id);
            localStorage.setItem(key, JSON.stringify(historico));
        }
    }

    function carregarEstadoInicial() {
        let key = "sac_contexto_" + contextoID;
        let historico = JSON.parse(localStorage.getItem(key)) || [];
        historico.forEach(id => {
            if(document.getElementById('status_' + id)) marcarComoEnviado(id);
        });
        atualizarContador();
    }

    function atualizarContador() {
        let total = <?=count($lista_jovens)?>;
        let key = "sac_contexto_" + contextoID;
        let enviados = (JSON.parse(localStorage.getItem(key)) || []).length;
        if(document.getElementById('contador-envios'))
            document.getElementById('contador-envios').innerText = enviados + " de " + total + " enviados";
    }

    function resetarEnvios() {
        if(confirm("Deseja resetar o status de envio para este modo?")) {
            localStorage.removeItem("sac_contexto_" + contextoID);
            location.reload();
        }
    }
</script>

</body>
</html>
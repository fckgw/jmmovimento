<?php
/**
 * JMM SYSTEM - CHECK-IN FACIAL DO JOVEM
 */
require_once 'config.php';
$e_id = $_GET['e'] ?? null;
if (!$e_id) die("Erro: Link inválido. Escaneie o QR Code novamente.");

$st = $pdo->prepare("SELECT * FROM encontros WHERE id = ?");
$st->execute([$e_id]);
$enc = $st->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in JMM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0d6efd; color: #fff; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .box { background: #fff; color: #333; border-radius: 25px; padding: 30px; width: 100%; max-width: 400px; text-align: center; }
        #video { width: 100%; border-radius: 15px; display: none; background: #000; transform: scaleX(-1); }
        #canvas { display: none; }
        .step { display: none; } .step.active { display: block; }
    </style>
</head>
<body class="p-3">

<div class="box shadow-lg">
    <img src="Img/logo.jpg" width="60" class="mb-3 rounded-circle border shadow-sm">
    <h6 class="text-muted small fw-bold mb-1">ENCONTRO JMM</h6>
    <h4 class="fw-bold text-primary mb-1"><?=$enc['tema']?></h4>
    <p class="small text-muted mb-4"><i class="bi bi-geo-alt"></i> <?=$enc['local_encontro']?> | <?=date('d/m/Y')?></p>

    <!-- PASSO 1: IDENTIFICAÇÃO -->
    <div id="step1" class="step active">
        <label class="fw-bold small mb-2">QUEM É VOCÊ? (BUSQUE POR NOME/CELULAR)</label>
        <input type="text" id="inputBusca" class="form-control form-control-lg mb-3 shadow-sm" placeholder="Ex: Felipe ou (11)...">
        <div id="lista" class="list-group text-start shadow-sm" style="max-height: 200px; overflow-y:auto"></div>
    </div>

    <!-- PASSO 2: SELFIE -->
    <div id="step2" class="step">
        <h6 class="fw-bold mb-3">Tire uma Selfie para o Check-in!</h6>
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas"></canvas>
        <button id="btnCapturar" class="btn btn-primary w-100 btn-lg rounded-pill mt-3 shadow fw-bold">CAPTURAR MINHA FOTO</button>
    </div>

    <!-- PASSO 3: SUCESSO -->
    <div id="step3" class="step py-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
        <h3 class="fw-bold mt-3">Presença Confirmada!</h3>
        <p class="text-muted">A Paz de Jesus e o Amor de Maria!</p>
    </div>
</div>

<script>
let video = document.getElementById('video');
let canvas = document.getElementById('canvas');
let idSelecionado = null;

document.getElementById('inputBusca').onkeyup = function() {
    let q = this.value;
    if(q.length < 3) { document.getElementById('lista').innerHTML = ''; return; }
    fetch('acoes_presenca.php?buscar=' + q)
    .then(r => r.json()).then(dados => {
        let html = '';
        if(dados.length === 0) {
            html = `<button class="list-group-item list-group-item-warning fw-bold small" onclick="autoCad()">Não estou na lista. Clique para cadastrar!</button>`;
        } else {
            dados.forEach(d => {
                html += `<button class="list-group-item list-group-item-action small fw-bold py-2" onclick="escolher(${d.id})">${d.nome}</button>`;
            });
        }
        document.getElementById('lista').innerHTML = html;
    });
};

function escolher(id) {
    idSelecionado = id;
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step2').classList.add('active');
    ligarCamera();
}

function autoCad() {
    let n = document.getElementById('inputBusca').value;
    let t = prompt("Informe seu WhatsApp:");
    if(n && t) {
        const fd = new FormData(); fd.append('nome', n); fd.append('tel', t);
        fetch('acoes_presenca.php?cadastrar_rapido=1', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => { if(res.id) escolher(res.id); });
    }
}

function ligarCamera() {
    video.style.display = 'block';
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } }).then(s => video.srcObject = s);
}

document.getElementById('btnCapturar').onclick = function() {
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    let img = canvas.toDataURL('image/jpeg');
    const fd = new FormData();
    fd.append('jovem_id', idSelecionado);
    fd.append('encontro_id', <?=$e_id?>);
    fd.append('foto', img);
    fetch('acoes_presenca.php?salvar=1', { method: 'POST', body: fd }).then(() => {
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step3').classList.add('active');
    });
};
</script>
</body>
</html>
<nav class="navbar navbar-light bg-white shadow-sm mb-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="sistema_dashboard.php" class="btn btn-outline-dark border-0"><i class="bi bi-grid-3x3-gap-fill fs-5"></i></a>
        <img src="Img/logo.jpg" height="35" class="rounded-circle border">
        <div class="d-none d-md-block text-center fw-bold text-muted small">JMM SYSTEM - GESTÃO MASTER</div>
        <small class="fw-bold text-muted"><?= mb_strtoupper($_SESSION['user_nome'] ?? 'USUÁRIO') ?></small>
    </div>
</nav>

<div class="container mb-4">
    <ul class="nav nav-pills nav-fill bg-white p-1 rounded shadow-sm border">
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'gincana.php' ? 'active' : '' ?>" href="gincana.php">CHAMADA</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'encontros.php' ? 'active' : '' ?>" href="encontros.php">ENCONTROS</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'jovens.php' ? 'active' : '' ?>" href="jovens.php">JOVENS</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ata.php' ? 'active' : '' ?>" href="ata.php">ATA</a></li>
        
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sac.php' ? 'active' : '' ?>" href="sac.php">SAC</a></li>
    </ul>
</div>
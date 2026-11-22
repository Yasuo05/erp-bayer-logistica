<?php
require_once dirname(base_path(), 2) . '/core/erp_marco.php';
$u = auth_user();
// Usuarios internos (entraron por el ERP): el portal se muestra dentro del marco común del ERP (core/erp_marco.php).
// Usuario Bayer (proveedor externo, no entra al ERP) y pantalla de login: barra propia del portal, con los colores del ERP.
$dhMarcoErp = $u && ($u['role'] ?? '') !== 'BAYER' && !empty($_SESSION['usuario']);
$dhHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>';
$dhCss = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    url('/assets/css/app.css') . '?v=' . @filemtime(base_path('public/assets/css/app.css')),
];
if ($dhMarcoErp):
    $dhRuta = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    $dhTitulos = ['documentos' => 'Documentos', 'guias' => 'Guías de remisión', 'stock' => 'Stock Bayer', 'dashboard' => 'Panel del portal'];
    $dhTitulo = 'Panel del portal';
    foreach ($dhTitulos as $clave => $nombre) if (str_ends_with($dhRuta, $clave)) $dhTitulo = $nombre;
    if (http_response_code() === 404) $dhTitulo = 'Página no encontrada';
    if (http_response_code() === 403) $dhTitulo = 'Sin permiso';
    erp_marco_cabecera([
        'pdo'      => db(),
        'usuario'  => $_SESSION['usuario'],
        'titulo'   => $dhTitulo,
        'embebido' => true,
        'css'      => $dhCss,
        'head'     => $dhHead,
    ]);
    echo '<div class="dh-pagina">';
else: ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e(config('app.name'))?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php foreach ($dhCss as $hoja): ?><link rel="stylesheet" href="<?=e($hoja)?>">
    <?php endforeach; ?>
    <?=$dhHead?>
</head>
<body class="dh-portal">
<?php if($u): ?>
<nav class="dh-portal-nav">
    <div class="dh-portal-nav-inner">
        <a class="dh-portal-brand" href="<?=url('/dashboard')?>">
            <span class="dh-portal-logo"><img src="<?=e(erp_marco_base())?>/assets/img/bayer.png" alt="Bayer" onerror="this.style.display='none'"></span>
            <span><strong>BAYER · PUCCHÚN</strong><small>Portal de información para Bayer</small></span>
        </a>
        <div class="dh-portal-user">
            <span class="dh-portal-avatar"><?=e(strtoupper(mb_substr((string)$u['nombre'], 0, 2)))?></span>
            <span class="dh-portal-user-info"><strong><?=e($u['nombre'])?></strong><small><?=e($u['role'])?></small></span>
            <form method="post" action="<?=url('/logout')?>" class="m-0">
                <?=csrf_field()?>
                <button class="dh-portal-salir" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i> Salir</button>
            </form>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-fluid py-4 px-lg-4">
<?php endif; ?>
<?php if($m=flash('success')): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?=e($m)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($m=flash('error')): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?=e($m)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

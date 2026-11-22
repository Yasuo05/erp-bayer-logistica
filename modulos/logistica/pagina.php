<?php
/** Marco común para páginas G7, igual que Comercial comparte operaciones.php. */
declare(strict_types=1);

$paginas = [
    'despachos' => ['envios.php', 'Envíos y entregas', 'Prepara la mercadería y consulta el seguimiento de cada entrega.'],
    'inicio' => ['panel.php', 'Panel de control', 'Consulta el resumen de compras, inventarios y logística de la sede.'],
    'stock' => ['stock.php', 'Stock por sedes', 'Consulta existencias, reservas y disponibilidad por almacén.'],
    'kardex' => ['kardex.php', 'Movimientos (Kardex)', 'Consulta entradas, salidas y movimientos de inventario.'],
    'conteos' => ['inventario.php', 'Inventario físico / Ajustes', 'Registra conteos y consulta las diferencias de inventario.'],
    'compras' => ['compras.php', 'Órdenes de compra', 'Consulta las órdenes de compra y su recepción.'],
    'recepciones' => ['recepciones.php', 'Recepción de mercadería', 'Registra y consulta los ingresos de mercadería desde proveedores.'],
    'traslados' => ['traslados.php', 'Traslados entre sedes', 'Consulta salidas, tránsito y recepción entre almacenes.'],
    'pagos' => ['cuentas_por_pagar.php', 'Cuentas por pagar', 'Consulta las obligaciones y los pagos a proveedores.'],
    'auditoria' => ['auditoria.php', 'Auditoría de operaciones', 'Consulta el historial de operaciones del módulo.'],
];
if (!isset($g7Pagina) || !is_string($g7Pagina) || !isset($paginas[$g7Pagina])) {
    http_response_code(404);
    exit('Página no disponible.');
}

require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/crud_base.php';
require_once __DIR__ . '/../../core/erp_marco.php';
require_once __DIR__ . '/stock_lib.php';
require_once __DIR__ . '/workflow.php';
requerir_login();

/** Los errores también conservan el marco y el estado HTTP de la página. */
function g7_pagina_error(int $status, string $message): never {
    http_response_code($status);
    erp_marco_cabecera([
        'pdo' => $GLOBALS['pdo'], 'usuario' => usuario_actual() ?? [],
        'titulo' => $status === 403 ? 'Acceso no permitido' : 'Envío no disponible',
        'subtitulo' => $message, 'ruta' => BASE_URL . '/modulos/logistica/envios.php',
    ]);
    echo '<a class="btn btn-sec" href="' . h(BASE_URL . '/modulos/logistica/envios.php') . '">Volver a envíos</a>';
    require __DIR__ . '/../../core/layout_footer.php';
    exit;
}
if (!g7_permiso_persistido($pdo, 'ver')) g7_pagina_error(403, 'Tu cuenta no tiene acceso a esta página.');

$shipmentId = null;
$productId = null;
[$rutaPagina, $titulo, $subtitulo] = $paginas[$g7Pagina];
if (!empty($g7EsExpediente)) {
    $shipmentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($shipmentId === false) g7_pagina_error(400, 'Selecciona un envío válido para abrir su expediente.');
    try {
        $envio = g7_envio_cargar($pdo, $shipmentId);
        $titulo = $envio['codigo'];
        $subtitulo = $envio['cliente'] . ' · ' . ($envio['documento_venta'] ?: 'Envío');
    } catch (ApiError $error) {
        g7_pagina_error($error->statusCode, $error->getMessage());
    }
}
if ($g7Pagina === 'kardex' && isset($_GET['producto'])) {
    $productId = filter_var($_GET['producto'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($productId === false) g7_pagina_error(400, 'El filtro de producto no es válido.');
}
$rutas = array_map(static fn(array $pagina): string => $pagina[0], $paginas);
$rutas['expediente'] = 'envio.php';
$pageConfig = ['page' => $g7Pagina, 'shipmentId' => $shipmentId, 'productId' => $productId, 'routes' => $rutas];
erp_marco_cabecera([
    'pdo' => $pdo, 'usuario' => usuario_actual() ?? [],
    'titulo' => $titulo, 'subtitulo' => $subtitulo,
    'ruta' => BASE_URL . '/modulos/logistica/' . $rutaPagina,
]);
?>
<meta name="g7-csrf" content="<?= h(csrf_token()) ?>">
<script id="g7-capabilities" type="application/json"><?= json_encode(['ver' => puede('ver'), 'crear' => puede('crear'), 'editar' => puede('editar')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script id="g7-page-config" type="application/json"><?= json_encode($pageConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<link rel="stylesheet" href="static/app.css?v=<?= filemtime(__DIR__ . '/static/app.css') ?>">
<div class="g7-container g7-ui">
  <div class="g7-content" id="content" tabindex="-1" role="region" aria-label="<?= h($titulo) ?>">
    <div class="loading-screen" role="status"><div class="loading-spin"></div><p>Cargando <?= h(mb_strtolower($titulo)) ?>…</p></div>
  </div>
</div>
<div id="modalMount" class="g7-ui"></div>
<div id="toastMount" class="g7-ui" aria-live="polite" aria-atomic="true"></div>
<script src="static/app.js?v=<?= filemtime(__DIR__ . '/static/app.js') ?>" defer></script>
<?php require __DIR__ . '/../../core/layout_footer.php'; ?>

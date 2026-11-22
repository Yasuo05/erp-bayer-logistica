<?php
/**
 * @var array      $entorno
 * @var array|null $resumen conteos de comprobantes (null si la base aún no está lista)
 */

$secciones = array_filter(config('menu'), fn ($seccion) => $seccion['titulo'] !== null);

// Flujo del comprobante (infografía 18)
$flujo = ['Venta aprobada', 'Comprobante', 'Serie y correlativo', 'Cálculos', 'XML', 'Firma y envío', 'Respuesta', 'CDR', 'PDF'];

$indicadores = $resumen ? [
    ['Comprobantes emitidos', $resumen['total'], url('/consultas/descargas'), 'file-text', ''],
    ['Listos para enviar', $resumen['listos'], url('/envio/estado') . '?estado=firmado', 'send', ''],
    ['Aceptados', $resumen['aceptados'], url('/envio/estado') . '?estado=aceptado', 'circle-check', 'kpi-ok'],
    ['Requieren atención', $resumen['incidencias'], url('/envio/rechazos'), 'triangle-alert', 'kpi-alerta'],
] : [];
?>
<div class="page">
    <section class="hero">
        <div class="hero-texto">
            <span class="hero-etiqueta"><?= e(config('app.erp')) ?> · Comercial multisede</span>
            <h1>Facturación electrónica</h1>
            <p>Emisión, envío y consulta de facturas, boletas y notas electrónicas.</p>
        </div>
        <?php if ($indicadores): ?>
            <div class="hero-kpis">
                <?php foreach ($indicadores as [$etiqueta, $valor, $enlace, $icono, $clase]): ?>
                    <a class="kpi <?= $clase ?>" href="<?= $enlace ?>">
                        <span class="kpi-icono"><?= icono($icono, 18) ?></span>
                        <strong><?= (int) $valor ?></strong>
                        <span class="kpi-etiqueta"><?= e($etiqueta) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icono('arrow-right') ?> Flujo del comprobante</h2>
        </div>
        <div class="card-body">
            <ol class="flujo">
                <?php foreach ($flujo as $i => $paso): ?>
                    <li><span class="flujo-num"><?= $i + 1 ?></span><?= e($paso) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <h2 class="section-heading">Secciones del módulo</h2>
    <div class="grid-cards">
        <?php foreach ($secciones as $seccion): ?>
            <section class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icono($seccion['icono']) ?> <?= e($seccion['titulo']) ?></h3>
                </div>
                <div class="card-body">
                    <p class="muted"><?= e($seccion['descripcion']) ?></p>
                    <ul class="lista-enlaces">
                        <?php foreach ($seccion['items'] as $item): ?>
                            <li>
                                <a href="<?= url($item['ruta']) ?>">
                                    <?= icono($item['icono'], 16) ?>
                                    <span><?= e($item['titulo']) ?></span>
                                    <?= icono('arrow-right', 14) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <footer class="estado-sistema">
        <span class="<?= $entorno['mysql']['ok'] ? 'ok' : 'error' ?>">
            <?= icono($entorno['mysql']['ok'] ? 'circle-check' : 'circle-x', 15) ?>
            <?= e($entorno['mysql']['ok'] ? 'Base de datos conectada' : $entorno['mysql']['mensaje']) ?>
        </span>
        <span class="muted">PHP <?= e($entorno['php']) ?></span>
    </footer>
</div>

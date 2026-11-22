<?php
/**
 * @var array                $ventas     página actual de ventas pendientes
 * @var string               $buscar
 * @var \App\Core\Paginacion $paginacion
 * @var string|null          $error
 * @var string|null          $flash
 */
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('inbox', 22) ?></div>
            <div>
                <h1>Ventas por facturar</h1>
                <p>Ventas confirmadas en Comercial (ERP) pendientes de comprobante.</p>
            </div>
        </div>
    </div>

    <?php if (!empty($flash)): ?><div class="notice notice-ok"><?= e($flash) ?></div><?php endif ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="notice notice-error"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif ?>
    <?php if ($error): ?><div class="notice notice-error">No se pudo leer la base de datos: <?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div><?php endif ?>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icono('inbox') ?> Bandeja de ventas aprobadas</h2>
            <form method="get" action="<?= url('/emision/ventas-por-facturar') ?>" class="busqueda-rapida" role="search">
                <input type="search" name="buscar" value="<?= e($buscar) ?>" maxlength="100" placeholder="Cliente, RUC/DNI o n.º de venta" aria-label="Buscar ventas">
                <button class="btn btn-small" type="submit"><?= icono('search', 15) ?> Buscar</button>
                <?php if ($buscar !== ''): ?><a class="btn btn-small" href="<?= url('/emision/ventas-por-facturar') ?>">Limpiar</a><?php endif ?>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Venta</th><th>Fecha</th><th>Cliente</th><th>Documento</th><th>Base imponible</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><?= e($v['numero'] ?? ('#' . (int) $v['id'])) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($v['fecha']))) ?></td>
                            <td class="celda-cliente"><?= e($v['cliente']) ?></td>
                            <td><?= e($v['tipo_documento'] . ' ' . $v['numero_documento']) ?></td>
                            <td>S/ <?= number_format((float) $v['subtotal'], 2) ?></td>
                            <td><a class="btn btn-primary" href="<?= url('/emision/emitir') ?>?venta_id=<?= (int) $v['id'] ?>">Revisar y emitir</a></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$ventas && !$error): ?>
                        <tr><td colspan="6" class="empty-cell"><?= $buscar !== '' ? 'No hay ventas pendientes que coincidan con la búsqueda.' : 'No hay ventas pendientes de facturar.' ?></td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
        <?php require BASE_PATH . '/app/Views/parciales/paginacion.php'; ?>
    </section>
    <p class="muted">El IGV se calcula con la tasa configurada en el sistema.</p>
</div>

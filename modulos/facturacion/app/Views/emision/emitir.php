<?php
/**
 * @var array       $ventas   ventas pendientes de comprobante
 * @var array|null  $venta    venta elegida (FacturacionRepository::venta)
 * @var array|null  $revision importes, tasa y serie con que se emitiría (FacturacionService::revisarVenta)
 * @var string|null $error
 * @var string|null $flashError
 */

$soles = static fn ($valor): string => 'S/ ' . number_format((float) $valor, 2);
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('file-pen-line', 22) ?></div>
            <div>
                <h1>Emitir comprobante</h1>
                <p>Selecciona una venta pendiente, revisa sus importes y confirma la emisión.</p>
            </div>
        </div>
    </div>

    <?php if ($flashError): ?><div class="notice notice-error"><?= e($flashError) ?></div><?php endif ?>
    <?php if ($error): ?><div class="notice notice-error">No se pudo preparar la revisión: <?= e($error) ?></div><?php endif ?>

    <?php if ($venta && $revision): ?>
        <?php
        $importes = $revision['importes'];
        $porcentaje = rtrim(rtrim(number_format($revision['tasa'] * 100, 2, '.', ''), '0'), '.');
        $tipoTexto = $revision['tipo'] === 'factura' ? 'Factura' : 'Boleta';
        ?>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icono('receipt') ?> Revisión de la venta <?= e($venta['numero'] ?? ('#' . (int) $venta['id'])) ?></h2>
            </div>
            <div class="card-body detail-grid">
                <div><span class="muted">Cliente</span><strong><?= e($venta['cliente']) ?></strong></div>
                <div><span class="muted">Documento</span><strong><?= e($venta['tipo_documento'] . ' ' . $venta['numero_documento']) ?></strong></div>
                <div><span class="muted">Comprobante</span><strong><?= e($tipoTexto) ?></strong></div>
                <div>
                    <span class="muted">Serie y número</span>
                    <?php if ($revision['serie']): ?>
                        <strong><?= e($revision['serie']['serie'] . '-' . str_pad((string) $revision['serie']['siguiente'], 8, '0', STR_PAD_LEFT)) ?></strong>
                        <span class="cell-sub">Número previsto; se confirma al emitir.</span>
                    <?php else: ?>
                        <strong class="texto-error">No hay una serie activa</strong>
                    <?php endif ?>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Descripción</th><th>Cantidad</th><th>Precio unitario</th><th>Descuento</th><th>Subtotal</th><th>IGV</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($importes['lineas'] as $linea): ?>
                            <tr>
                                <td><?= e($linea['descripcion']) ?></td>
                                <td><?= e((string) (float) $linea['cantidad']) ?></td>
                                <td><?= $soles($linea['precio_unitario']) ?></td>
                                <td><?= $soles($linea['descuento'] ?? 0) ?></td>
                                <td><?= $soles($linea['subtotal']) ?></td>
                                <td><?= $soles($linea['impuesto']) ?></td>
                                <td><?= $soles($linea['total']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body revision-pie">
                <dl class="resumen-importes">
                    <div><dt>Op. gravada</dt><dd><?= $soles($importes['subtotal']) ?></dd></div>
                    <div><dt>IGV (<?= e($porcentaje) ?>%)</dt><dd><?= $soles($importes['impuesto']) ?></dd></div>
                    <div class="resumen-total"><dt>Importe total</dt><dd><?= $soles($importes['total']) ?></dd></div>
                </dl>

                <form method="post" action="<?= url('/emision/emitir') ?>" class="confirmar-emision">
                    <input type="hidden" name="venta_id" value="<?= (int) $venta['id'] ?>">
                    <input type="hidden" name="tipo" value="<?= e($revision['tipo']) ?>">
                    <!-- Si los importes cambian antes de confirmar, la emisión se rechaza y se vuelve a revisar -->
                    <input type="hidden" name="total_revisado" value="<?= e(number_format($importes['total'], 2, '.', '')) ?>">
                    <input type="hidden" name="tasa_revisada" value="<?= e(number_format($revision['tasa'], 10, '.', '')) ?>">
                    <input type="hidden" name="huella_revision" value="<?= e($revision['huella']) ?>">
                    <label class="check-filtro">
                        <input type="checkbox" name="confirmar" value="1" required>
                        Revisé el cliente y los importes
                    </label>
                    <div class="confirmar-acciones">
                        <a class="btn" href="<?= url('/emision/ventas-por-facturar') ?>">Cancelar</a>
                        <button class="btn btn-primary" type="submit"<?= $revision['serie'] ? '' : ' disabled' ?>>Confirmar y emitir <?= e(strtolower($tipoTexto)) ?></button>
                    </div>
                </form>
            </div>
        </section>
        <p class="muted">Al emitir se reserva el correlativo y el comprobante se guarda como borrador con estos importes; luego se genera y firma su XML.</p>
    <?php else: ?>
        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('inbox') ?> Elige una venta pendiente</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Venta</th><th>Cliente</th><th>Documento</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($ventas as $v): ?>
                            <tr>
                                <td><?= e($v['numero'] ?? ('#' . (int) $v['id'])) ?></td>
                                <td><?= e($v['cliente']) ?></td>
                                <td><?= e($v['tipo_documento'] . ' ' . $v['numero_documento']) ?></td>
                                <td><?= $soles($v['subtotal']) ?></td>
                                <td><a class="btn btn-primary" href="<?= url('/emision/emitir') ?>?venta_id=<?= (int) $v['id'] ?>">Revisar</a></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if (!$ventas): ?>
                            <tr><td colspan="5" class="empty-cell">No hay ventas pendientes. Las ventas se registran y confirman en el módulo Comercial del ERP.</td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif ?>
</div>

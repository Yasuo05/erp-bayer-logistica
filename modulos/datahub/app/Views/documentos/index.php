<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1 text-primary fw-bold">Documentos</h2>
        <p class="text-muted mb-0">Historial y auditoría de comprobantes</p>
    </div>
    <div>
        <a href="<?= url('/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Dashboard
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Número</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Sucursal</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Importe (PEN)</th>
                        <th class="text-center pe-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-primary"><?= e($r['numero']) ?></td>
                                <td class="text-nowrap text-muted"><?= fmt_date($r['fecha']) ?></td>
                                <td>
                                    <div class="text-dark fw-medium text-truncate" style="max-width: 280px;" title="<?= e($r['cliente']) ?>">
                                        <?= e($r['cliente']) ?>
                                    </div>
                                </td>
                                <td class="text-secondary"><?= e($r['vendedor']) ?></td>
                                <td class="text-secondary"><?= e($r['sucursal']) ?></td>
                                <td class="text-center"><?= (int)$r['items'] ?></td>
                                <td class="text-end"><?= fmt_num($r['cantidad']) ?></td>
                                <td class="text-end fw-bold text-dark"><?= fmt_money($r['importe']) ?></td>
                                <td class="text-center pe-3"><?= status_badge($r['estado_registro']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No se encontraron registros de comprobantes.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top-0 text-muted small py-2 px-3">
        Mostrando <?= count($rows) ?> registros
    </div>
</div>

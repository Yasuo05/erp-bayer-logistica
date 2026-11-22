<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1 text-primary fw-bold">Stock</h2>
        <p class="text-muted mb-0">Historial y auditoría de existencias</p>
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
                        <th class="ps-3">Fecha</th>
                        <th>Almacén</th>
                        <th class="text-center">Items (SKUs)</th>
                        <th class="text-end">Cantidad Total</th>
                        <th class="text-center pe-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="ps-3 text-nowrap text-muted"><?= fmt_date($r['fecha_stock']) ?></td>
                                <td>
                                    <div class="text-dark fw-bold"><?= e($r['almacen']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= (int)$r['items'] ?> SKUs</span>
                                </td>
                                <td class="text-end fw-bold text-success"><?= fmt_num($r['cantidad']) ?> <span class="fw-normal text-muted small">und</span></td>
                                <td class="text-center pe-3"><?= status_badge($r['estado_registro']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No se encontraron registros de tomas de stock.</td>
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

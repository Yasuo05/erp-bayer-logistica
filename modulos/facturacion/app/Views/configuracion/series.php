<?php
$tipos = [
    'factura' => 'Factura',
    'boleta' => 'Boleta',
    'nota_credito' => 'Nota de crédito',
    'nota_debito' => 'Nota de débito',
];
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('hash', 22) ?></div>
            <div>
                <h1>Series y correlativos</h1>
                <p>Control de la numeración documental del módulo de facturación.</p>
            </div>
        </div>
    </div>

    <?php if ($flash): ?><div class="notice notice-ok"><?= e($flash) ?></div><?php endif ?>
    <?php if ($flashError): ?><div class="notice notice-error"><?= e($flashError) ?></div><?php endif ?>
    <?php if ($error): ?><div class="notice notice-error">No se pudieron cargar las series: <?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div><?php endif ?>

    <div class="grid-2 series-layout">
        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('file-plus-2', 18) ?> Nueva serie</h2></div>
            <div class="card-body">
                <form method="post" action="<?= url('/configuracion/series/crear') ?>" class="form-grid">
                    <label class="field">Tipo de comprobante
                        <select name="tipo" required>
                            <?php foreach ($tipos as $valor => $etiqueta): ?>
                                <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>
                    <label class="field">Serie
                        <input name="serie" maxlength="4" required placeholder="F001" pattern="[A-Za-z0-9]{1,4}" autocomplete="off">
                    </label>
                    <label class="field">Correlativo inicial
                        <input type="number" name="siguiente" min="1" step="1" value="1" required>
                    </label>
                    <label class="field">Sede
                        <select name="sucursal_id"><option value="">Todas las sedes (serie general)</option><?php foreach (\App\Core\Database::conexion()->query('SELECT id,nombre FROM sucursales WHERE estado=1 ORDER BY nombre') as $sede): ?><option value="<?= (int) $sede['id'] ?>"><?= e($sede['nombre']) ?></option><?php endforeach ?></select>
                    </label>
                    <label class="field field-wide">Descripción
                        <input name="descripcion" maxlength="120" placeholder="Ej.: Facturación principal">
                    </label>
                    <div class="field field-wide"><button class="btn btn-primary" type="submit">Crear serie</button></div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('info', 18) ?> Reglas de numeración</h2></div>
            <div class="card-body">
                <ul class="lista-simple">
                    <li>La combinación de tipo y serie no puede repetirse.</li>
                    <li>El siguiente correlativo nunca puede quedar por debajo del último número emitido.</li>
                    <li>Los números asignados no se reutilizan, aunque el comprobante sea anulado.</li>
                    <li>Solo una serie activa puede utilizarse durante la emisión.</li>
                    <li>Cambiar el siguiente correlativo exige un motivo; el cambio queda registrado con fecha y usuario.</li>
                </ul>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-header"><h2 class="card-title"><?= icono('hash', 18) ?> Series configuradas</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tipo</th><th>Serie</th><th>Sede</th><th>Descripción</th><th>Último emitido</th><th>Siguiente</th><th>Uso</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($series as $item): ?>
                    <tr>
                        <td><?= e($tipos[$item['tipo']] ?? $item['tipo']) ?></td>
                        <td><strong><?= e($item['serie']) ?></strong></td>
                        <td><?= e($item['sede'] ?? 'Todas') ?></td>
                        <td><input class="series-description" name="descripcion" maxlength="120" value="<?= e($item['descripcion']) ?>" placeholder="Sin descripción" form="series-update-<?= (int) $item['id'] ?>" aria-label="Descripción de <?= e($item['serie']) ?>"></td>
                        <td><?= (int) $item['ultimo_emitido'] > 0 ? e(str_pad((string) $item['ultimo_emitido'], 8, '0', STR_PAD_LEFT)) : '—' ?></td>
                        <td>
                            <form id="series-update-<?= (int) $item['id'] ?>" method="post" action="<?= url('/configuracion/series/actualizar') ?>" class="series-edit-form">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input class="series-number" type="number" name="siguiente" min="<?= max(1, (int) $item['ultimo_emitido'] + 1) ?>" step="1" value="<?= (int) $item['siguiente'] ?>" required aria-label="Siguiente correlativo de <?= e($item['serie']) ?>">
                                <input class="series-motivo" name="motivo" maxlength="500" placeholder="Motivo si cambias el número" aria-label="Motivo del cambio de correlativo de <?= e($item['serie']) ?>">
                                <button class="btn btn-small" type="submit">Guardar</button>
                            </form>
                        </td>
                        <td><?= (int) $item['comprobantes'] ?> comprobante(s)</td>
                        <td><span class="badge <?= (int) $item['activo'] === 1 ? 'badge-ok' : 'badge-muted' ?>"><?= (int) $item['activo'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                        <td>
                            <form method="post" action="<?= url('/configuracion/series/estado') ?>"<?= (int) $item['activo'] === 1 ? ' data-confirmar="¿Desactivar la serie ' . e($item['serie']) . '? No podrá usarse para emitir hasta que la actives."' : '' ?>>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="activo" value="<?= (int) $item['activo'] === 1 ? '0' : '1' ?>">
                                <button class="btn btn-small" type="submit"><?= (int) $item['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$series && !$error): ?><tr><td colspan="9" class="empty-cell">Todavía no hay series configuradas.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icono('history', 18) ?> Historial de cambios de correlativos</h2>
            <span class="muted">Últimos <?= count($cambios) ?> registros</span>
        </div>
        <?php if ($errorCambios): ?>
            <div class="card-body"><div class="notice notice-error">No se pudo cargar el historial: <?= e($errorCambios) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Fecha</th><th>Serie</th><th>Anterior</th><th>Nuevo</th><th>Motivo</th><th>Usuario</th></tr></thead>
                    <tbody>
                    <?php foreach ($cambios as $cambio): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime($cambio['creado_en']))) ?></td>
                            <td><strong><?= e($cambio['serie']) ?></strong><span class="cell-sub"><?= e($tipos[$cambio['tipo']] ?? $cambio['tipo']) ?></span></td>
                            <td><?= $cambio['valor_anterior'] === null ? '<span class="muted">—</span>' : e(str_pad((string) $cambio['valor_anterior'], 8, '0', STR_PAD_LEFT)) ?></td>
                            <td><strong><?= e(str_pad((string) $cambio['valor_nuevo'], 8, '0', STR_PAD_LEFT)) ?></strong></td>
                            <td class="celda-motivo"><?= e($cambio['motivo']) ?></td>
                            <td><?= e($cambio['usuario']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$cambios): ?><tr><td colspan="6" class="empty-cell">Todavía no hay cambios registrados.</td></tr><?php endif ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
</div>


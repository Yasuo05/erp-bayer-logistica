<?php
/**
 * @var string      $titulo      "Notas de crédito" o "Notas de débito"
 * @var string      $tipo        nota_credito | nota_debito
 * @var array       $comprobantes notas emitidas de este tipo
 * @var array       $referencias facturas y boletas que pueden recibir una nota (FacturacionRepository::referenciasParaNota)
 * @var array       $motivos     código => descripción (catálogo 09 o 10 de SUNAT, config/sunat.php)
 * @var string|null $error
 * @var string|null $flash
 * @var string|null $flashError
 */

$esCredito = $tipo === 'nota_credito';
$soles = static fn ($valor): string => 'S/ ' . number_format((float) $valor, 2);
$numero = static fn ($serie, $numero): string => $serie . '-' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
// En notas de crédito solo se ofrecen comprobantes con saldo por acreditar
$elegibles = $esCredito ? array_values(array_filter($referencias, static fn (array $r): bool => $r['saldo'] > 0)) : $referencias;
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono($esCredito ? 'file-minus-2' : 'file-plus-2', 22) ?></div>
            <div>
                <h1><?= e($titulo) ?></h1>
                <p><?= $esCredito ? 'Disminuye el valor de una factura o boleta aceptada.' : 'Incrementa el valor de una factura o boleta aceptada.' ?></p>
            </div>
        </div>
    </div>

    <?php if ($flash): ?><div class="notice notice-ok"><?= e($flash) ?></div><?php endif ?>
    <?php if ($flashError): ?><div class="notice notice-error"><?= e($flashError) ?></div><?php endif ?>
    <?php if ($error): ?><div class="notice notice-error">No se pudieron cargar los datos: <?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div><?php endif ?>

    <div class="grid-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('file-pen-line') ?> Emitir <?= $esCredito ? 'nota de crédito' : 'nota de débito' ?></h2></div>
            <div class="card-body">
                <?php if ($elegibles): ?>
                    <form method="post" action="<?= url('/comprobantes/nota/emitir') ?>" class="form-grid" id="form-nota" data-confirmar="¿Emitir la nota? Se le asignará un número que no podrá reutilizarse.">
                        <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                        <label class="field field-wide">Comprobante de referencia
                            <select name="referencia_id" required>
                                <option value="">Selecciona una factura o boleta aceptada</option>
                                <?php foreach ($elegibles as $ref): ?>
                                    <option value="<?= (int) $ref['id'] ?>" data-saldo="<?= e(number_format($ref['saldo'], 2, '.', '')) ?>">
                                        <?= e(strtoupper($ref['tipo']) . ' ' . $numero($ref['serie'], $ref['numero']) . ' · ' . $ref['cliente'] . ' · Total ' . $soles($ref['total'])
                                            . ($esCredito ? ' · Saldo ' . $soles($ref['saldo']) : '')) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </label>
                        <label class="field">Motivo (catálogo SUNAT)
                            <select name="codigo_motivo" required>
                                <?php foreach ($motivos as $codigo => $descripcion): ?>
                                    <option value="<?= e((string) $codigo) ?>"><?= e($codigo . ' · ' . $descripcion) ?></option>
                                <?php endforeach ?>
                            </select>
                        </label>
                        <label class="field">Monto base del ajuste (sin IGV, S/)
                            <input name="monto" type="number" min="0.01" step="0.01" required>
                            <?php if ($esCredito): ?><span class="muted" data-saldo-texto>Elige un comprobante para ver su saldo.</span><?php endif ?>
                        </label>
                        <label class="field field-wide">Descripción del ajuste
                            <textarea name="motivo" maxlength="500" required placeholder="Ej. Descuento acordado por pronto pago"></textarea>
                        </label>
                        <div class="field field-wide"><button class="btn btn-primary" type="submit">Emitir nota</button></div>
                    </form>
                <?php else: ?>
                    <div class="empty-cell">
                        <?= $esCredito
                            ? 'No hay facturas ni boletas aceptadas con saldo por acreditar.'
                            : 'No hay facturas ni boletas aceptadas por SUNAT.' ?>
                        Solo los comprobantes aceptados pueden recibir notas.
                    </div>
                <?php endif ?>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('info') ?> Condiciones</h2></div>
            <div class="card-body">
                <ul class="lista-simple">
                    <li>Solo se emiten sobre facturas o boletas <strong>aceptadas</strong> por SUNAT (con o sin observaciones).</li>
                    <?php if ($esCredito): ?>
                        <li>La suma de notas de crédito no puede superar la base imponible del comprobante; las notas anuladas no cuentan.</li>
                    <?php else: ?>
                        <li>El motivo debe ser uno del catálogo 10 de SUNAT: intereses por mora, aumento en el valor o penalidades.</li>
                    <?php endif ?>
                    <li>El IGV se calcula con la misma tasa del comprobante original.</li>
                    <li>La serie de la nota empieza con la letra del comprobante: <strong>F</strong> para facturas y <strong>B</strong> para boletas.</li>
                </ul>
            </div>
        </section>
    </div>

    <h2 class="section-heading">Notas emitidas</h2>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Número</th><th>Cliente</th><th>Comprobante relacionado</th><th>Motivo</th><th>Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($comprobantes as $c): ?>
                        <tr>
                            <td><strong><?= e($numero($c['serie'], $c['numero'])) ?></strong></td>
                            <td class="celda-cliente"><?= e($c['cliente']) ?></td>
                            <td><?= $c['serie_referencia'] ? e($numero($c['serie_referencia'], $c['numero_referencia'])) : '—' ?></td>
                            <td class="celda-motivo">
                                <?php if (!empty($c['codigo_motivo'])): ?><span class="cell-sub"><?= e($c['codigo_motivo'] . ' · ' . ($motivos[$c['codigo_motivo']] ?? 'Motivo SUNAT')) ?></span><?php endif ?>
                                <?= e($c['motivo'] ?? '') ?>
                            </td>
                            <td><?= $soles($c['total']) ?></td>
                            <td><span class="badge <?= e(clase_estado_comprobante($c['estado'])) ?>"><?= e(estado_comprobante($c['estado'])) ?></span></td>
                            <td><a class="btn btn-small" href="<?= url('/comprobantes/ver') ?>?id=<?= (int) $c['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$comprobantes): ?><tr><td colspan="7" class="empty-cell">Todavía no hay notas emitidas.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php if ($esCredito && $elegibles): ?>
<script>
(() => {
  // El monto no puede superar el saldo del comprobante elegido (el servidor lo vuelve a comprobar)
  const form = document.getElementById('form-nota');
  const referencia = form.querySelector('[name="referencia_id"]');
  const monto = form.querySelector('[name="monto"]');
  const texto = form.querySelector('[data-saldo-texto]');
  referencia.addEventListener('change', () => {
    const saldo = referencia.selectedOptions[0]?.dataset.saldo;
    if (saldo) {
      monto.max = saldo;
      texto.textContent = 'Saldo disponible: S/ ' + Number(saldo).toFixed(2) + ' (sin IGV).';
    } else {
      monto.removeAttribute('max');
      texto.textContent = 'Elige un comprobante para ver su saldo.';
    }
  });
})();
</script>
<?php endif ?>

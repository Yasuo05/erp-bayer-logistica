<?php
/**
 * Tabla de comprobantes con acceso a sus archivos (PDF, XML, CDR) y paginación.
 * La usan Consulta y descarga, Facturas y Boletas.
 *
 * @var array                $comprobantes ConsultaRepository::buscar()
 * @var \App\Core\Paginacion $paginacion
 * @var string|null          $error
 * @var array                $tipos        tipo => etiqueta
 * @var bool                 $hayFiltros
 */

// Estados en los que el comprobante aún no pasó por Envío (no puede tener CDR)
$sinEnviar = ['borrador_local', 'generado_xml', 'firmado', 'anulado_local'];

/** Por qué un archivo todavía no está disponible (se muestra a la vista, no solo al pasar el cursor) */
$motivoSinCdr = static fn (string $estado): string => match (true) {
    in_array($estado, $sinEnviar, true) => 'CDR: aún no enviado',
    $estado === 'enviado' => 'CDR: esperando respuesta',
    $estado === 'pendiente_reintento' => 'CDR: sin respuesta (falla técnica)',
    default => 'CDR: la respuesta no lo trae',
};
?>
<section class="card">
    <div class="card-header">
        <h2 class="card-title"><?= icono('file-text') ?> Comprobantes</h2>
        <div class="resultados-acciones">
            <span class="muted"><?= $paginacion->total ?> <?= $paginacion->total === 1 ? 'resultado' : 'resultados' ?></span>
            <?php if ($paginacion->total > \App\Services\ConsultaComprobantesService::LIMITE_EXPORTACION): ?>
                <span class="badge badge-warn" title="El Excel trae como máximo <?= number_format(\App\Services\ConsultaComprobantesService::LIMITE_EXPORTACION) ?> filas: ajusta los filtros para exportar el resto.">Excel: solo los primeros <?= number_format(\App\Services\ConsultaComprobantesService::LIMITE_EXPORTACION) ?></span>
            <?php endif ?>
            <?php if ($paginacion->total > 0): ?>
                <?php // Mismos filtros de la búsqueda (en Facturas y Boletas incluye el tipo fijo) ?>
                <a class="btn btn-small" href="<?= url('/consultas/exportar') ?>?<?= e(http_build_query(array_filter($filtros, static fn ($v): bool => $v !== ''))) ?>"
                   title="Descargar en Excel los comprobantes de esta búsqueda"><?= icono('download', 15) ?> Exportar a Excel</a>
            <?php endif ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Comprobante</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Archivos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comprobantes as $c): ?>
                    <?php
                    $id = (int) $c['id'];
                    $pendientes = [];
                    if (!$c['xml_archivo']) {
                        $pendientes[] = 'XML: aún no generado';
                    } elseif (!$c['xml_hash']) {
                        $pendientes[] = 'XML: sin firmar';
                    }
                    if (!$c['cdr_envio_id']) {
                        $pendientes[] = $motivoSinCdr($c['estado']);
                    }
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($c['serie'] . '-' . str_pad((string) $c['numero'], 8, '0', STR_PAD_LEFT)) ?></strong>
                            <span class="cell-sub"><?= e($tipos[$c['tipo']] ?? $c['tipo']) ?></span>
                        </td>
                        <td><?= e(date('d/m/Y H:i', strtotime($c['fecha_emision']))) ?></td>
                        <td class="celda-cliente">
                            <?= e($c['cliente']) ?>
                            <span class="cell-sub"><?= e($c['tipo_documento'] . ' ' . $c['numero_documento']) ?></span>
                        </td>
                        <td><strong>S/ <?= number_format((float) $c['total'], 2) ?></strong></td>
                        <td>
                            <div class="estado-celda">
                                <span class="badge <?= e(clase_estado_comprobante($c['estado'])) ?>"><?= e(estado_comprobante($c['estado'])) ?></span>
                                <?php if ($c['origen_respuesta'] === 'simulado'): ?>
                                    <span class="badge badge-simulado" title="La respuesta la generó el simulador: no fue enviado a SUNAT">Simulado</span>
                                <?php endif ?>
                            </div>
                        </td>
                        <td>
                            <div class="acciones-archivo">
                                <a class="btn" href="<?= url('/comprobantes/ver') ?>?id=<?= $id ?>" title="Ver el detalle del comprobante"><?= icono('eye', 15) ?> Ver</a>
                                <a class="btn" href="<?= url('/consultas/trazabilidad') ?>?id=<?= $id ?>"
                                   title="Ver la trazabilidad: todo lo que ocurrió con este comprobante" aria-label="Ver la trazabilidad"><?= icono('history', 15) ?></a>

                                <a class="btn" href="<?= url('/consultas/pdf') ?>?id=<?= $id ?>" target="_blank" rel="noopener"
                                   title="Abrir el PDF en una pestaña nueva"><?= icono('file-text', 15) ?> PDF</a>
                                <a class="btn" href="<?= url('/consultas/pdf') ?>?id=<?= $id ?>&amp;descargar=1"
                                   title="Descargar el PDF" aria-label="Descargar el PDF"><?= icono('download', 15) ?></a>

                                <?php if ($c['xml_archivo']): ?>
                                    <a class="btn" href="<?= url('/comprobantes/xml/descargar') ?>?id=<?= $id ?>"
                                       title="<?= e($c['xml_hash'] ? 'XML firmado: ' . $c['xml_archivo'] : 'XML sin firmar: ' . $c['xml_archivo']) ?>"><?= icono('file-code', 15) ?> XML</a>
                                <?php else: ?>
                                    <span class="btn btn-disabled" title="Todavía no se generó el XML de este comprobante"><?= icono('file-code', 15) ?> XML</span>
                                <?php endif ?>

                                <?php if ($c['cdr_envio_id']): ?>
                                    <a class="btn" href="<?= url('/envio/cdr') ?>?envio=<?= (int) $c['cdr_envio_id'] ?>"
                                       title="<?= $c['origen_respuesta'] === 'simulado' ? 'Descargar el CDR simulado: no lo emitió SUNAT' : 'Descargar el CDR: constancia de recepción de SUNAT del último envío' ?>"><?= icono('file-check', 15) ?> CDR</a>
                                <?php else: ?>
                                    <span class="btn btn-disabled" title="<?= e($motivoSinCdr($c['estado'])) ?>"><?= icono('file-check', 15) ?> CDR</span>
                                <?php endif ?>
                            </div>
                            <?php if ($pendientes): ?>
                                <span class="archivos-pendientes"><?= e(implode(' · ', $pendientes)) ?></span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>

                <?php if (!$comprobantes && !$error): ?>
                    <tr>
                        <td colspan="6" class="empty-cell">
                            <?= $hayFiltros ? 'No hay comprobantes que coincidan con los filtros. Prueba quitando alguno.' : 'Todavía no hay comprobantes emitidos.' ?>
                        </td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php require BASE_PATH . '/app/Views/parciales/paginacion.php'; ?>
</section>

<?php
/**
 * @var array       $filtros     filtros validados (ver ConsultaController::trazabilidad)
 * @var array|null  $comprobante comprobante elegido con ?id= (ConsultaRepository::resumen), o null
 * @var array       $eventos     ConsultaRepository::eventos()
 * @var string|null $error
 * @var array       $tipos       tipo => etiqueta
 * @var \App\Core\Paginacion|null $paginacion vista general por páginas; null en el historial de un comprobante
 */

use App\Controllers\ConsultaController;

$etapas = ConsultaController::ETAPAS;
$hayFiltros = $filtros['numero'] !== '' || $filtros['etapa'] !== '' || $filtros['incidencias']
    || $filtros['desde'] !== '' || $filtros['hasta'] !== '';
$total = $paginacion ? $paginacion->total : count($eventos);
$numero = static fn (array $f): string => $f['serie'] . '-' . str_pad((string) $f['numero'], 8, '0', STR_PAD_LEFT);

// Título de cada evento: cambios de estado del comprobante y etapas técnicas del envío
$titulosEvento = [
    'borrador_local'       => 'Comprobante emitido',
    'generado_xml'         => 'XML generado',
    'firmado'              => 'XML firmado',
    'enviado'              => 'Enviado',
    'aceptado'             => 'Aceptado',
    'observado'            => 'Observado',
    'rechazado'            => 'Rechazado',
    'pendiente_reintento'  => 'Pendiente de reintento',
    'anulado'              => 'Anulado',
    'anulado_local'        => 'Anulado antes del envío',
    'solicitud'            => 'Envío solicitado',
    'reintento'            => 'Reintento solicitado',
    'reintento_habilitado' => 'Reintento habilitado',
    'recuperacion'         => 'Intento interrumpido recuperado',
    'observacion_revisada' => 'Observación revisada',
    'consulta_ticket'      => 'Consulta de ticket',
    'respuesta'            => 'Respuesta recibida',
    'persistencia'         => 'Error al guardar la respuesta',
    'cdr'                  => 'CDR guardado',
    'cdr_ausente'          => 'Respuesta sin CDR',
];
$tituloEvento = static fn (string $etapa): string => $titulosEvento[$etapa] ?? ucfirst(str_replace('_', ' ', $etapa));
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('history', 22) ?></div>
            <div>
                <h1>Trazabilidad</h1>
                <p>Historial de cada comprobante: emisión, XML, firma, envíos, respuestas y CDR en una sola línea de tiempo.</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="notice notice-error">No se pudo consultar la trazabilidad: <?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div>
    <?php endif ?>

    <?php if ($comprobante): ?>
        <!-- Historial de un comprobante -->
        <section class="card">
            <div class="card-header traza-encabezado">
                <div>
                    <h2 class="card-title"><?= icono('file-text') ?> <?= e(($tipos[$comprobante['tipo']] ?? $comprobante['tipo']) . ' ' . $numero($comprobante)) ?></h2>
                </div>
                <div class="estado-celda">
                    <span class="badge <?= e(clase_estado_comprobante($comprobante['estado'])) ?>"><?= e(estado_comprobante($comprobante['estado'])) ?></span>
                    <?php if ($comprobante['origen_respuesta'] === 'simulado'): ?>
                        <span class="badge badge-simulado" title="La respuesta la generó el simulador: no fue enviado a SUNAT">Simulado</span>
                    <?php endif ?>
                </div>
            </div>
            <div class="card-body traza-datos">
                <div><span class="muted">Cliente</span><strong><?= e($comprobante['cliente']) ?></strong></div>
                <div><span class="muted">Documento</span><strong><?= e($comprobante['tipo_documento'] . ' ' . $comprobante['numero_documento']) ?></strong></div>
                <div><span class="muted">Fecha de emisión</span><strong><?= e(date('d/m/Y H:i', strtotime($comprobante['fecha_emision']))) ?></strong></div>
                <div><span class="muted">Total</span><strong>S/ <?= number_format((float) $comprobante['total'], 2) ?></strong></div>
                <div class="traza-acciones">
                    <a class="btn" href="<?= url('/comprobantes/ver') ?>?id=<?= (int) $comprobante['id'] ?>"><?= icono('eye', 15) ?> Ver comprobante</a>
                    <a class="btn" href="<?= url('/consultas/pdf') ?>?id=<?= (int) $comprobante['id'] ?>" target="_blank" rel="noopener"><?= icono('file-text', 15) ?> PDF</a>
                    <a class="btn" href="<?= url('/consultas/trazabilidad') ?>"><?= icono('history', 15) ?> Ver todos los eventos</a>
                </div>
            </div>
        </section>
    <?php else: ?>
        <!-- Filtros de la trazabilidad general -->
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icono('funnel') ?> Filtros</h2>
            </div>
            <div class="card-body">
                <form method="get" action="<?= url('/consultas/trazabilidad') ?>" class="filtros">
                    <label class="field">Serie y número
                        <input name="numero" value="<?= e($filtros['numero']) ?>" maxlength="20" placeholder="Ej. F001-1 o B001" autocomplete="off">
                    </label>

                    <label class="field">Etapa
                        <select name="etapa">
                            <option value="">Todas</option>
                            <?php foreach ($etapas as $valor => [$etiqueta]): ?>
                                <option value="<?= e($valor) ?>"<?= $filtros['etapa'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>

                    <label class="field">Desde
                        <input type="date" name="desde" value="<?= e($filtros['desde']) ?>">
                    </label>

                    <label class="field">Hasta
                        <input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>">
                    </label>

                    <label class="check-filtro">
                        <input type="checkbox" name="incidencias" value="1"<?= $filtros['incidencias'] ? ' checked' : '' ?>>
                        Solo observaciones y errores
                    </label>

                    <div class="filtros-acciones">
                        <?php if ($hayFiltros): ?>
                            <a class="btn" href="<?= url('/consultas/trazabilidad') ?>"><?= icono('x', 16) ?> Limpiar</a>
                        <?php endif ?>
                        <button class="btn btn-primary" type="submit"><?= icono('search', 16) ?> Buscar</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif ?>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icono('history') ?> Línea de tiempo</h2>
            <span class="muted">
                <?= $total ?> <?= $total === 1 ? 'evento' : 'eventos' ?> ·
                <?= $comprobante ? 'del más antiguo al más reciente' : 'los más recientes primero' ?>
            </span>
        </div>

        <?php if ($eventos): ?>
            <ol class="timeline">
                <?php $diaAnterior = null; ?>
                <?php foreach ($eventos as $ev): ?>
                    <?php $momento = strtotime($ev['creado_en']); $dia = date('d/m/Y', $momento); ?>
                    <?php if ($dia !== $diaAnterior): $diaAnterior = $dia; ?>
                        <li class="timeline-dia"><?= e($dia) ?></li>
                    <?php endif ?>
                    <li class="timeline-item nivel-<?= e($ev['nivel']) ?>">
                        <span class="timeline-punto" aria-hidden="true"></span>
                        <div class="timeline-contenido">
                            <div class="timeline-cabecera">
                                <strong><?= e($tituloEvento($ev['etapa'])) ?></strong>
                                <?php if ($ev['fuente'] === 'envio'): ?>
                                    <span class="timeline-fuente">Envío · intento <?= (int) $ev['intento'] ?></span>
                                    <?php if ($ev['origen'] === 'simulado'): ?>
                                        <span class="badge badge-simulado">Simulado</span>
                                    <?php endif ?>
                                <?php else: ?>
                                    <span class="timeline-fuente">Comprobante</span>
                                <?php endif ?>
                                <time datetime="<?= e(date('c', $momento)) ?>"><?= e(date('H:i:s', $momento)) ?></time>
                            </div>
                            <?php if (!$comprobante): ?>
                                <a class="timeline-comprobante" href="<?= url('/consultas/trazabilidad') ?>?id=<?= (int) $ev['comprobante_id'] ?>"
                                   title="Ver el historial completo de este comprobante">
                                    <?= e(($tipos[$ev['tipo']] ?? $ev['tipo']) . ' ' . $numero($ev)) ?> · <?= e($ev['cliente']) ?>
                                </a>
                            <?php endif ?>
                            <p class="timeline-detalle">
                                <?php if ($ev['codigo']): ?><code><?= e($ev['codigo']) ?></code><?php endif ?>
                                <?= e($ev['detalle']) ?>
                            </p>
                            <?php if (!empty($ev['usuario_nombre']) || !empty($ev['sede_nombre'])): ?>
                                <p class="muted timeline-actor">
                                    <?php if (!empty($ev['usuario_nombre'])): ?>Usuario: <?= e($ev['usuario_nombre']) ?><?= !empty($ev['usuario_id']) ? ' · ID '.e($ev['usuario_id']) : '' ?><?php endif ?>
                                    <?php if (!empty($ev['usuario_nombre']) && !empty($ev['sede_nombre'])): ?> · <?php endif ?>
                                    <?php if (!empty($ev['sede_nombre'])): ?>Sede: <?= e($ev['sede_nombre']) ?><?= !empty($ev['sede_id']) ? ' · ID '.e($ev['sede_id']) : '' ?><?php endif ?>
                                </p>
                            <?php endif ?>
                        </div>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php elseif (!$error): ?>
            <div class="empty-cell">
                <?= $comprobante ? 'Este comprobante aún no tiene eventos registrados.' : ($hayFiltros ? 'No hay eventos que coincidan con los filtros.' : 'Todavía no hay eventos registrados.') ?>
            </div>
        <?php endif ?>
        <?php if ($paginacion): ?>
            <?php require BASE_PATH . '/app/Views/parciales/paginacion.php'; ?>
        <?php endif ?>
    </section>

    <p class="muted traza-nota">Usuario y sede se muestran cuando el ERP integra el contexto autenticado en la sesión. Las acciones anteriores o sin ese contexto permanecen sin atribución.</p>
</div>

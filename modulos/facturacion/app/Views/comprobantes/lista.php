<?php
/**
 * Facturas o boletas: misma búsqueda y acceso a archivos que Consulta y descarga, filtrada por tipo.
 *
 * @var string               $titulo       "Facturas" o "Boletas"
 * @var string               $tipo         factura | boleta
 * @var array                $filtros
 * @var array                $comprobantes página actual de resultados
 * @var \App\Core\Paginacion $paginacion
 * @var string|null          $error
 * @var array                $tipos
 * @var array                $estados
 * @var string|null          $flash
 * @var string|null          $flashError
 */

$accion = $tipo === 'factura' ? '/comprobantes/facturas' : '/comprobantes/boletas';
$mostrarTipo = false;
$serie = $tipo === 'factura' ? 'F' : 'B';
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono($tipo === 'factura' ? 'file-text' : 'receipt', 22) ?></div>
            <div>
                <h1><?= e($titulo) ?></h1>
                <p><?= $tipo === 'factura' ? 'Facturas emitidas' : 'Boletas emitidas' ?>, su estado y sus archivos.</p>
            </div>
        </div>
        <a class="btn btn-primary" href="<?= url('/emision/ventas-por-facturar') ?>">Ir a emitir</a>
    </div>

    <?php if ($flash): ?><div class="notice notice-ok"><?= e($flash) ?></div><?php endif ?>
    <?php if ($flashError): ?><div class="notice notice-error"><?= e($flashError) ?></div><?php endif ?>
    <?php if ($error): ?><div class="notice notice-error">No se pudo consultar la base de datos: <?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div><?php endif ?>

    <details class="reglas-emision card">
        <summary><?= icono('info', 16) ?> Reglas de emisión de <?= e(mb_strtolower($titulo)) ?></summary>
        <ul class="lista-simple">
            <?php if ($tipo === 'factura'): ?>
                <li>Se emite a clientes con <strong>RUC</strong> (11 dígitos). Un cliente con RUC no puede recibir boleta.</li>
            <?php else: ?>
                <li>Se emite a clientes con <strong>DNI</strong> (8 dígitos). Un cliente con RUC debe recibir factura.</li>
            <?php endif ?>
            <li>Parte de una <strong>venta aprobada</strong>; cada venta se factura una sola vez.</li>
            <li>Antes de emitir se revisan las líneas, el IGV y el total; si cambian después de la revisión, no se emite.</li>
            <li>La serie empieza con <strong><?= $serie ?></strong> y el correlativo se asigna al emitir; un número usado no se reutiliza, aunque el comprobante se anule.</li>
            <li>Se guardan los datos del emisor, del cliente y la tasa de IGV del momento de la emisión: cambios posteriores no alteran el documento.</li>
            <li>Ciclo: borrador → XML generado y validado → XML firmado y verificado → envío → respuesta y CDR.</li>
            <li>Antes del envío puede anularse localmente; después, los ajustes se hacen con notas de crédito o débito sobre el comprobante aceptado.</li>
        </ul>
    </details>

    <?php require BASE_PATH . '/app/Views/consultas/_filtros.php'; ?>
    <?php require BASE_PATH . '/app/Views/consultas/_resultados.php'; ?>
</div>

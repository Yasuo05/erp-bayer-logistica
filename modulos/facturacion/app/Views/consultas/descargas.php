<?php
/**
 * @var array                $filtros      filtros validados (ConsultaComprobantesService::filtros)
 * @var array                $comprobantes página actual de resultados
 * @var \App\Core\Paginacion $paginacion
 * @var string|null          $error
 * @var array                $tipos        tipo => etiqueta
 * @var array                $estados      estado => etiqueta (config/estados.php)
 * @var string|null          $flashError   error al generar un PDF (viene de ConsultaController::pdf)
 */

$accion = '/consultas/descargas';
$mostrarTipo = true;
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('file-down', 22) ?></div>
            <div>
                <h1>Consulta y descarga</h1>
                <p>Busca cualquier comprobante emitido y descarga sus archivos.</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="notice notice-error">No se pudo consultar la base de datos: <?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div>
    <?php endif ?>
    <?php if ($flashError): ?>
        <div class="notice notice-error"><?= e($flashError) ?></div>
    <?php endif ?>

    <?php require __DIR__ . '/_filtros.php'; ?>
    <?php require __DIR__ . '/_resultados.php'; ?>
</div>

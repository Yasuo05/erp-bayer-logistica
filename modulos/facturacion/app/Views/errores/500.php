<?php
/**
 * @var string      $referencia código del error en storage/logs (Registro)
 * @var string|null $detalle    mensaje técnico, solo en local (APP_DEBUG=true)
 */
?>
<div class="page">
    <section class="card">
        <div class="empty">
            <?= icono('triangle-alert', 36) ?>
            <p><strong>Ocurrió un error inesperado</strong></p>
            <p class="muted">No se completó la operación. Si el problema continúa, comunica este código a soporte:</p>
            <p><code class="codigo-error"><?= e($referencia) ?></code></p>
            <?php if ($detalle): ?><p class="muted"><small><?= e($detalle) ?></small></p><?php endif ?>
            <p><a class="btn btn-primary" href="<?= url('/') ?>"><?= icono('layout-dashboard', 16) ?> Ir al inicio</a></p>
        </div>
    </section>
</div>

<div class="page">
    <section class="card">
        <div class="empty">
            <?= icono('triangle-alert', 36) ?>
            <p><strong>El formulario no es válido o la sesión expiró</strong></p>
            <p class="muted">Por seguridad no se procesó la operación. Vuelve a la página, recárgala y envía el formulario otra vez.</p>
            <p><a class="btn btn-primary" href="<?= url('/') ?>"><?= icono('layout-dashboard', 16) ?> Ir al inicio</a></p>
        </div>
    </section>
</div>

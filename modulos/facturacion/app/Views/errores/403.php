<?php /** @var string $mensaje */ ?>
<div class="page">
    <section class="card">
        <div class="card-body">
            <h1>Sin permiso</h1>
            <p><?= e($mensaje ?? 'Tu rol no permite realizar esta operación.') ?></p>
            <p><a class="btn" href="<?= url('/') ?>">Volver al inicio de Facturación</a></p>
        </div>
    </section>
</div>

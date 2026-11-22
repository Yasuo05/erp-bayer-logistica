<?php
/** @var array $item opción del menú (config/menu.php) con su sección */

$conexion = $item['conexion'] ?? [];
?>
<div class="page">
    <nav class="breadcrumb" aria-label="Ubicación">
        <a href="<?= url('/') ?>">Facturación</a>
        <span>/</span> <?= e($item['seccion']) ?>
        <span>/</span> <strong><?= e($item['titulo']) ?></strong>
    </nav>

    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono($item['icono'], 22) ?></div>
            <div>
                <h1><?= e($item['titulo']) ?></h1>
                <p><?= e($item['descripcion']) ?></p>
            </div>
        </div>
        <span class="badge badge-warn">Pendiente de implementar</span>
    </div>

    <div class="<?= $conexion ? 'grid-2' : '' ?>">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icono('file-text') ?> Qué hará esta sección</h2>
            </div>
            <div class="card-body">
                <ul class="lista-check">
                    <?php foreach ($item['funciones'] as $funcion): ?>
                        <li><?= icono('circle-check', 16) ?> <?= e($funcion) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <?php if ($conexion): ?>
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icono('building-2') ?> Se conecta con</h2>
                </div>
                <div class="card-body conexiones">
                    <?php foreach (['recibe' => ['Recibe', 'arrow-down-to-line'], 'entrega' => ['Entrega', 'upload']] as $tipo => [$etiqueta, $icono]): ?>
                        <?php if (!empty($conexion[$tipo])): ?>
                            <div>
                                <div class="conexion-titulo"><?= icono($icono, 16) ?> <?= $etiqueta ?></div>
                                <ul class="lista-conexion">
                                    <?php foreach ($conexion[$tipo] as [$grupo, $dato]): ?>
                                        <li><span class="chip"><?= e($grupo) ?></span> <?= e($dato) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <section class="card">
        <div class="empty">
            <?= icono('construction', 36) ?>
            <p><strong>Sección en construcción</strong></p>
            <p class="muted">La estructura está lista; la funcionalidad se implementará en las siguientes fases del proyecto.</p>
        </div>
    </section>
</div>

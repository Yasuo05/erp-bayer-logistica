<?php
/**
 * @var array $parametros ParametrosService::estado()
 */

$f = $parametros['facturacion'];
$envio = $parametros['envio'];
$firma = $parametros['firma'];
$base = $parametros['base'];
$sesion = $parametros['sesion'];
$registro = $parametros['registro'];
$porcentaje = rtrim(rtrim(number_format($f['tasa_igv'] * 100, 2, '.', ''), '0'), '.');
$tiposEtiqueta = \App\Controllers\ConsultaController::TIPOS;
$pendientes = array_filter($base['migraciones'], static fn (array $m): bool => !$m['aplicada']);

// Estado → [clase de la etiqueta, texto]
$vigencia = [
    'vigente'    => ['badge-ok', 'Vigente'],
    'por_vencer' => ['badge-warn', 'Por vencer'],
    'vencido'    => ['badge-error', 'Vencido'],
];
?>
<div class="page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('settings', 22) ?></div>
            <div>
                <h1>Parámetros de facturación</h1>
                <p>Configuración con la que trabaja el módulo y estado de sus componentes.</p>
            </div>
        </div>
    </div>

    <div class="notice notice-info">
        Esta pantalla es <strong>solo de consulta</strong>. Los valores se cambian en el archivo <code>.env</code> del servidor;
        las contraseñas y tokens nunca se muestran.
    </div>

    <div class="grid-2 parametros">
        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('receipt') ?> Facturación</h2></div>
            <div class="card-body">
                <dl class="datos-parametro">
                    <div><dt>Tasa de IGV para nuevas emisiones</dt><dd><?= e($porcentaje) ?>%</dd></div>
                    <div><dt>Moneda</dt><dd><?= e($f['moneda']) ?> (soles)</dd></div>
                    <div><dt>Entorno</dt><dd><?= e($f['entorno']) ?><?= $f['depurar'] ? ' · errores visibles' : '' ?></dd></div>
                    <div><dt>Validación con esquemas XSD</dt><dd>
                        <?php if ($f['xsd']): ?><span class="badge badge-ok">Activa</span>
                        <?php else: ?><span class="badge badge-muted">Sin esquemas</span> <span class="muted">solo estructura y datos</span><?php endif ?>
                    </dd></div>
                    <?php /* NUEVO: extensión zip de PHP */ ?>
                    <div><dt>Extensión zip de PHP</dt><dd>
                        <?php if ($f['zip']): ?><span class="badge badge-ok">Activa</span>
                        <?php else: ?><span class="badge badge-error">Falta</span> <span class="muted">sin ella no funcionan Excel ni el CDR</span><?php endif ?>
                    </dd></div>
                </dl>
                <h3 class="subtitulo-parametro">Tipos de comprobante (catálogo 01 de SUNAT)</h3>
                <div class="chips-parametro">
                    <?php foreach ($f['tipos'] as $tipo => $codigo): ?>
                        <span><span class="chip"><?= e($codigo) ?></span> <?= e($tiposEtiqueta[$tipo] ?? $tipo) ?></span>
                    <?php endforeach ?>
                </div>
                <p class="muted nota-parametro">Cada comprobante guarda la tasa con la que se emitió: cambiarla solo afecta a las nuevas emisiones.</p>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('send') ?> Envío electrónico</h2></div>
            <div class="card-body">
                <dl class="datos-parametro">
                    <div><dt>Modo de envío</dt><dd>
                        <?php if ($envio['simulado']): ?><span class="badge badge-simulado">Simulado</span> <span class="muted">no se transmite a SUNAT</span>
                        <?php else: ?><span class="badge badge-ok">Proveedor externo</span><?php endif ?>
                    </dd></div>
                    <div><dt>Servidor del proveedor</dt><dd><?= $envio['servidor'] ? e($envio['servidor']) : '<span class="muted">No configurado</span>' ?></dd></div>
                    <div><dt>Token de acceso</dt><dd><?= $envio['token'] ? 'Configurado' : '<span class="muted">No configurado</span>' ?></dd></div>
                    <div><dt>Tiempo máximo de espera</dt><dd><?= (int) $envio['timeout'] ?> segundos</dd></div>
                    <div><dt>Intentos por comprobante</dt><dd>Máximo 5</dd></div>
                </dl>
                <p class="muted nota-parametro">El envío real se activará cuando se defina la integración con SUNAT (OSE/PSE).</p>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('file-check') ?> Certificado de firma digital</h2></div>
            <div class="card-body">
                <?php if ($firma['error']): ?>
                    <div class="notice notice-error"><?= e($firma['error']) ?></div>
                <?php else: ?>
                    <dl class="datos-parametro">
                        <div><dt>Estado</dt><dd>
                            <span class="badge <?= e($vigencia[$firma['vigencia']][0]) ?>"><?= e($vigencia[$firma['vigencia']][1]) ?></span>
                            <span class="muted"><?= $firma['dias'] >= 0 ? 'vence en ' . (int) $firma['dias'] . ' días' : 'venció hace ' . abs((int) $firma['dias']) . ' días' ?></span>
                        </dd></div>
                        <div><dt>Titular</dt><dd><?= e($firma['titular']) ?></dd></div>
                        <div><dt>Emitido por</dt><dd><?= e($firma['emisor']) ?></dd></div>
                        <div><dt>Válido desde</dt><dd><?= e(date('d/m/Y', $firma['desde'])) ?></dd></div>
                        <div><dt>Válido hasta</dt><dd><?= e(date('d/m/Y', $firma['hasta'])) ?></dd></div>
                        <div><dt>Archivo</dt><dd><code><?= e($firma['archivo']) ?></code></dd></div>
                    </dl>
                    <?php if ($firma['autofirmado']): ?>
                        <div class="notice notice-info">Certificado <strong>de prueba</strong> (autofirmado): sirve para el entorno local, pero SUNAT no lo acepta.</div>
                    <?php endif ?>
                <?php endif ?>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('database') ?> Base de datos</h2></div>
            <div class="card-body">
                <dl class="datos-parametro">
                    <div><dt>Conexión</dt><dd>
                        <?php if ($base['ok']): ?><span class="badge badge-ok">Conectada</span> <?= e($base['nombre']) ?> en <?= e($base['servidor']) ?>
                        <?php else: ?><span class="badge badge-error">Sin conexión</span> <?= e($base['mensaje']) ?><?php endif ?>
                    </dd></div>
                    <div><dt>Estructura de la base</dt><dd>
                        <?= count($base['migraciones']) - count($pendientes) ?> de <?= count($base['migraciones']) ?> componentes presentes
                        <?php if ($pendientes): ?><span class="badge badge-warn">Faltan <?= count($pendientes) ?></span><?php endif ?>
                    </dd></div>
                </dl>
                <ul class="lista-migraciones">
                    <?php foreach ($base['migraciones'] as $m): ?>
                        <li class="<?= $m['aplicada'] ? 'aplicada' : 'pendiente' ?>">
                            <?= icono($m['aplicada'] ? 'circle-check' : 'circle-x', 15) ?>
                            <?= e($m['descripcion']) ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </section>
    </div>

    <div class="grid-2 parametros">
        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('triangle-alert') ?> Registro de errores</h2></div>
            <div class="card-body">
                <dl class="datos-parametro">
                    <div><dt>Errores este mes</dt><dd>
                        <?php if ($registro['errores'] === 0): ?><span class="badge badge-ok">Ninguno</span>
                        <?php else: ?><span class="badge badge-error"><?= (int) $registro['errores'] ?></span><?php endif ?>
                    </dd></div>
                    <div><dt>Avisos de PHP</dt><dd><?= (int) $registro['avisos'] ?></dd></div>
                    <div><dt>Último registro</dt><dd><?= $registro['ultimo'] ? e(date('d/m/Y H:i', strtotime($registro['ultimo']))) : '<span class="muted">—</span>' ?></dd></div>
                    <div><dt>Archivo</dt><dd><code><?= e($registro['archivo']) ?></code></dd></div>
                </dl>
                <p class="muted nota-parametro">Cada error inesperado se guarda con un código (ej. E-20261022-7F3A1C) que también ve el usuario, para encontrarlo rápido.</p>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title"><?= icono('user-round') ?> Sesión</h2></div>
            <div class="card-body">
                <dl class="datos-parametro">
                    <div><dt>Usuario</dt><dd><?= e($sesion['usuario'] ?? '—') ?></dd></div>
                    <div><dt>Rol</dt><dd><?= e($sesion['rol'] ?? '—') ?></dd></div>
                    <div><dt>Sede</dt><dd><?= e($sesion['sede'] ?? '—') ?></dd></div>
                </dl>
                <p class="muted nota-parametro">Valores simulados (<code>config/app.php</code>) hasta que el Grupo 4 entregue la sesión real del ERP.</p>
            </div>
        </section>
    </div>
</div>
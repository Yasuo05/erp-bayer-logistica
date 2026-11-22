<?php
/**
 * Pie de un listado paginado: «Mostrando X–Y de Z», enlaces de página y tamaño de página.
 *
 * @var \App\Core\Paginacion $paginacion
 */

use App\Core\Paginacion;

$consultaActual = $_GET;
?>
<?php if ($paginacion->total > 0): ?>
    <nav class="paginacion" aria-label="Paginación">
        <span class="muted">Mostrando <?= $paginacion->desde() ?>–<?= $paginacion->hasta() ?> de <?= $paginacion->total ?></span>

        <?php if ($paginacion->paginas > 1): ?>
            <div class="paginacion-enlaces">
                <?php if ($paginacion->pagina > 1): ?>
                    <a class="btn btn-small" href="<?= e($paginacion->enlace($paginacion->pagina - 1, $consultaActual)) ?>" aria-label="Página anterior">‹ Anterior</a>
                <?php endif ?>
                <?php foreach ($paginacion->numeros() as $numero): ?>
                    <?php if ($numero === null): ?>
                        <span class="paginacion-salto">…</span>
                    <?php elseif ($numero === $paginacion->pagina): ?>
                        <span class="btn btn-small paginacion-actual" aria-current="page"><?= $numero ?></span>
                    <?php else: ?>
                        <a class="btn btn-small" href="<?= e($paginacion->enlace($numero, $consultaActual)) ?>"><?= $numero ?></a>
                    <?php endif ?>
                <?php endforeach ?>
                <?php if ($paginacion->pagina < $paginacion->paginas): ?>
                    <a class="btn btn-small" href="<?= e($paginacion->enlace($paginacion->pagina + 1, $consultaActual)) ?>" aria-label="Página siguiente">Siguiente ›</a>
                <?php endif ?>
            </div>
        <?php endif ?>

        <form method="get" class="paginacion-tamano">
            <?php foreach (Paginacion::filtrosDe($consultaActual) as $clave => $valor): ?>
                <input type="hidden" name="<?= e((string) $clave) ?>" value="<?= e($valor) ?>">
            <?php endforeach ?>
            <label>Por página
                <select name="por_pagina" onchange="this.form.submit()">
                    <?php foreach (Paginacion::TAMANOS as $tamano): ?>
                        <option value="<?= $tamano ?>"<?= $tamano === $paginacion->porPagina ? ' selected' : '' ?>><?= $tamano ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <noscript><button class="btn btn-small" type="submit">Aplicar</button></noscript>
        </form>
    </nav>
<?php endif ?>

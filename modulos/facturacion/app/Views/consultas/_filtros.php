<?php
/**
 * Filtros de búsqueda de comprobantes (Consulta y descarga, Facturas, Boletas).
 *
 * @var array  $filtros     filtros validados (ConsultaComprobantesService::filtros)
 * @var array  $tipos       tipo => etiqueta
 * @var array  $estados     estado => etiqueta
 * @var string $accion      ruta a la que se envía el formulario
 * @var bool   $mostrarTipo false en los listados de un solo tipo
 */

$hayFiltros = ($mostrarTipo && $filtros['tipo'] !== '') || $filtros['numero'] !== '' || $filtros['cliente'] !== ''
    || $filtros['estado'] !== '' || $filtros['desde'] !== '' || $filtros['hasta'] !== '';
?>
<section class="card">
    <div class="card-header">
        <h2 class="card-title"><?= icono('funnel') ?> Filtros</h2>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url($accion) ?>" class="filtros">
            <?php if ($mostrarTipo): ?>
                <label class="field">Tipo de comprobante
                    <select name="tipo">
                        <option value="">Todos</option>
                        <?php foreach ($tipos as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>"<?= $filtros['tipo'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
            <?php endif ?>

            <label class="field">Serie y número
                <input name="numero" value="<?= e($filtros['numero']) ?>" maxlength="20" placeholder="Ej. F001-1 o F001" autocomplete="off">
            </label>

            <label class="field">Cliente
                <input name="cliente" value="<?= e($filtros['cliente']) ?>" maxlength="160" placeholder="Nombre, RUC o DNI" autocomplete="off">
            </label>

            <label class="field">Estado
                <select name="estado">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>"<?= $filtros['estado'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
                    <?php endforeach ?>
                </select>
            </label>

            <label class="field">Desde
                <input type="date" name="desde" value="<?= e($filtros['desde']) ?>">
            </label>

            <label class="field">Hasta
                <input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>">
            </label>

            <div class="filtros-acciones">
                <?php if ($hayFiltros): ?>
                    <a class="btn" href="<?= url($accion) ?>"><?= icono('x', 16) ?> Limpiar</a>
                <?php endif ?>
                <button class="btn btn-primary" type="submit"><?= icono('search', 16) ?> Buscar</button>
            </div>
        </form>
    </div>
</section>

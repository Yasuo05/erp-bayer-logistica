<?php
// Rol BAYER: no entra a las pantallas internas de auditoría; sus enlaces descargan lo publicado (Excel)
$esBayer = has_role('BAYER');
$enlace = fn(string $ruta, string $tipo) => $esBayer ? url('/export?type=' . $tipo . '&format=xlsx') : url($ruta);
?>
<div class="pd">

    <!-- ============ Cabecera ejecutiva + filtro de periodo ============ -->
    <header class="pd-head">
        <div class="pd-head-text">
            <h1 class="pd-title">Panel gerencial Pucchún</h1>
            <p class="pd-subtitle">Ventas, inventario y publicación hacia Bayer en una sola vista.</p>
        </div>
        <div class="pd-head-controls">
            <div class="pd-period">
                <label for="periodFilter" class="pd-period-label"><i class="bi bi-calendar3"></i> Periodo</label>
                <select id="periodFilter" class="form-select form-select-sm" aria-label="Filtrar por periodo">
                    <option value="all" selected>Histórico completo</option>
                </select>
            </div>
        </div>
    </header>

    <!-- ============ KPIs principales ============ -->
    <section class="row g-3 mb-3" aria-label="Indicadores principales">

        <!-- Facturación -->
        <div class="col-12 col-md-6 col-xl-3">
            <article class="pd-kpi pd-kpi-hero h-100">
                <div class="pd-kpi-label"><i class="bi bi-cash-coin"></i> Facturación</div>
                <div class="pd-kpi-value" id="kpiFact" data-original="<?= e(fmt_money($kpis['facturacion_total_pen'])) ?>"><?= fmt_money($kpis['facturacion_total_pen']) ?></div>
                <div class="pd-kpi-sub" id="kpiFactSub">Histórico completo</div>
            </article>
        </div>

        <!-- Ticket promedio -->
        <div class="col-12 col-md-6 col-xl-3">
            <article class="pd-kpi h-100">
                <div class="pd-kpi-label"><i class="bi bi-receipt"></i> Ticket promedio</div>
                <div class="pd-kpi-value" id="kpiTicket" data-original="<?= e(fmt_money($kpis['ticket_promedio'])) ?>"><?= fmt_money($kpis['ticket_promedio']) ?></div>
                <div class="pd-kpi-sub" id="kpiTicketSub">Monto promedio por comprobante</div>
            </article>
        </div>

        <!-- Stock físico -->
        <div class="col-12 col-md-6 col-xl-3">
            <article class="pd-kpi h-100">
                <div class="pd-kpi-label"><i class="bi bi-boxes"></i> Stock físico total</div>
                <div class="pd-kpi-value pd-kpi-value-ok"><?= fmt_num($kpis['stock_unidades']) ?> <span class="pd-unit">und</span></div>
                <div class="pd-kpi-sub">
                    <?= (int)$kpis['stock_skus'] ?> productos en <?= (int)$kpis['almacenes_activos'] ?> almacenes
                    <a class="pd-link" href="<?= $enlace('/stock', 'stock') ?>"><?= $esBayer ? 'Descargar' : 'Ver detalle' ?></a>
                </div>
            </article>
        </div>

        <!-- Alerta de stock crítico -->
        <div class="col-12 col-md-6 col-xl-3">
            <a href="#semaforo" class="pd-kpi pd-kpi-alert h-100 d-block text-decoration-none" id="kpiAlertCard" aria-label="Ir al semáforo de reposición">
                <div class="pd-kpi-label"><i class="bi bi-exclamation-triangle-fill"></i> Reposición urgente</div>
                <div class="pd-kpi-value" id="kpiAlertNum"><?= (int)($kpis['alertas_stock_minimo'] ?? 0) ?></div>
                <div class="pd-kpi-sub" id="kpiAlertSub">productos en o bajo su stock mínimo</div>
                <ul class="pd-alert-mini" id="kpiAlertList"></ul>
            </a>
        </div>
    </section>

    <?php if (!empty($operacion)): $t = $operacion['total']; ?>
    <!-- ============ Operación interna del ERP (no visible para Bayer) ============ -->
    <section class="mb-4" aria-label="Operación interna">
        <div class="pd-card" style="padding:18px 20px">
            <h2 style="font-size:17px;margin:0 0 4px">Operación interna por sede</h2>
            <p class="text-muted" style="font-size:13px;margin:0 0 12px">Ventas, cobranzas, cuentas por cobrar, caja, bancos, compras y cuentas por pagar del ERP. Información interna: no se comparte con Bayer.</p>
            <div style="overflow:auto"><table class="table table-sm" style="width:100%;min-width:860px">
                <thead><tr><th>Sede</th><th class="text-end">Ventas</th><th class="text-end">Cobrado</th><th class="text-end">Por cobrar</th><th class="text-end">Caja (abierta)</th><th class="text-end">Bancos</th><th class="text-end">Compras</th><th class="text-end">Por pagar</th></tr></thead>
                <tbody>
                <?php foreach ($operacion['sedes'] as $r): ?>
                    <tr><td><?= e($r['nombre']) ?> <span class="text-muted">(<?= (int)$r['n_ventas'] ?> ventas)</span></td><td class="text-end"><?= fmt_money($r['ventas']) ?></td><td class="text-end"><?= fmt_money($r['cobrado']) ?></td><td class="text-end"><?= fmt_money($r['cxc_saldo']) ?></td><td class="text-end"><?= fmt_money($r['caja']) ?></td><td class="text-end"><?= fmt_money($r['bancos']) ?></td><td class="text-end"><?= fmt_money($r['compras']) ?></td><td class="text-end"><?= fmt_money($r['cxp_saldo']) ?></td></tr>
                <?php endforeach; ?>
                    <tr style="font-weight:700"><td>Total</td><td class="text-end"><?= fmt_money($t['ventas']) ?></td><td class="text-end"><?= fmt_money($t['cobrado']) ?></td><td class="text-end"><?= fmt_money($t['cxc_saldo']) ?></td><td class="text-end"><?= fmt_money($t['caja']) ?></td><td class="text-end"><?= fmt_money($t['bancos']) ?></td><td class="text-end"><?= fmt_money($t['compras']) ?></td><td class="text-end"><?= fmt_money($t['cxp_saldo']) ?></td></tr>
                </tbody></table></div>
            <?php if ($operacion['medios']): ?><p style="font-size:13px;margin:10px 0 0">Cobranzas por medio de pago: <?php foreach ($operacion['medios'] as $m): ?><b><?= e($m['medio_pago']) ?></b> <?= fmt_money($m['total']) ?> (<?= (int)$m['n'] ?>) &nbsp; <?php endforeach; ?></p><?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ Pulso operativo (accesos a auditoría) ============ -->
    <section class="pd-pulse mb-4" aria-label="Resumen operativo">
        <a class="pd-pulse-item" href="<?= $enlace('/documentos', 'documents') ?>" title="Ver auditoría de documentos">
            <span class="pd-pulse-name"><i class="bi bi-file-earmark-text"></i> Documentos</span>
            <span class="pd-pulse-num"><?= fmt_num($kpis['documentos']) ?></span>
            <span class="pd-pulse-meta"><b class="text-ok"><?= fmt_num($kpis['documentos_publicados']) ?></b> publicados, <?= fmt_num($kpis['documentos_pendientes']) ?> pendientes</span>
        </a>
        <a class="pd-pulse-item" href="<?= $enlace('/guias', 'guides') ?>" title="Ver auditoría de guías">
            <span class="pd-pulse-name"><i class="bi bi-truck"></i> Guías</span>
            <span class="pd-pulse-num"><?= fmt_num($kpis['guias']) ?></span>
            <span class="pd-pulse-meta"><b class="text-ok"><?= fmt_num($kpis['guias_publicadas']) ?></b> publicadas, <?= fmt_num($kpis['guias_borrador']) ?> en borrador</span>
        </a>
        <div class="pd-pulse-item">
            <span class="pd-pulse-name"><i class="bi bi-people"></i> Clientes</span>
            <span class="pd-pulse-num"><?= fmt_num($kpis['clientes']) ?></span>
            <span class="pd-pulse-meta">Cartera comercial activa</span>
        </div>
        <div class="pd-pulse-item">
            <span class="pd-pulse-name"><i class="bi bi-cloud-check"></i> Enviados a Bayer</span>
            <span class="pd-pulse-num"><?= fmt_num($kpis['publicados']) ?></span>
            <span class="pd-pulse-meta">Documentos, guías y stock</span>
        </div>
    </section>

    <!-- ============ Tendencia + Sedes ============ -->
    <section class="row g-3 mb-3">
        <div class="col-lg-7">
            <article class="pd-panel h-100">
                <div class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title">Evolución de la facturación</h2>
                        <p class="pd-panel-desc" id="trendDesc">Ventas mensuales en soles</p>
                    </div>
                    <span class="pd-delta" id="trendDelta" hidden></span>
                </div>
                <div class="pd-chart pd-chart-lg">
                    <canvas id="salesChart" role="img" aria-label="Gráfico de línea con la facturación mensual en soles"></canvas>
                    <div class="pd-empty" id="salesEmpty" hidden>Sin ventas en este periodo</div>
                </div>
            </article>
        </div>
        <div class="col-lg-5">
            <article class="pd-panel h-100">
                <div class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title">Ventas por sede</h2>
                        <p class="pd-panel-desc">Facturación neta en soles</p>
                    </div>
                    <span class="pd-scope" id="noteSedes" hidden>Acumulado histórico</span>
                </div>
                <div class="pd-chart pd-chart-lg">
                    <canvas id="sedesChart" role="img" aria-label="Gráfico de barras con la facturación por sede"></canvas>
                    <div class="pd-empty" id="sedesEmpty" hidden>Sin datos por sede</div>
                </div>
            </article>
        </div>
    </section>

    <!-- ============ Catálogo + Top 10 ============ -->
    <section class="row g-3 mb-4">
        <div class="col-lg-5">
            <article class="pd-panel h-100">
                <div class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title">Cuánto vendemos de Bayer</h2>
                        <p class="pd-panel-desc">Productos oficiales frente a genéricos y terceros</p>
                    </div>
                    <span class="pd-scope" id="noteCatalog" hidden>Acumulado histórico</span>
                </div>
                <div class="pd-chart pd-chart-md">
                    <canvas id="catalogChart" role="img" aria-label="Gráfico circular: participación de ventas Bayer frente a otros"></canvas>
                    <div class="pd-empty" id="catalogEmpty" hidden>Sin datos de catálogo</div>
                </div>
                <div class="pd-legend" id="catalogLegend"></div>
            </article>
        </div>
        <div class="col-lg-7">
            <article class="pd-panel h-100">
                <div class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title">Los 10 productos que más se venden</h2>
                        <p class="pd-panel-desc">
                            <span class="pd-dot pd-dot-bayer"></span> Bayer oficial
                            <span class="pd-dot pd-dot-other ms-2"></span> Genérico u otros
                        </p>
                    </div>
                    <div class="pd-seg" role="group" aria-label="Medir por">
                        <button type="button" class="pd-seg-btn is-active" data-top-metric="cantidad">Unidades</button>
                        <button type="button" class="pd-seg-btn" data-top-metric="monto">Soles</button>
                    </div>
                </div>
                <span class="pd-scope" id="noteTop" hidden>Acumulado histórico</span>
                <div class="pd-chart pd-chart-md pd-chart-top">
                    <canvas id="topChart" role="img" aria-label="Gráfico de barras con los diez productos más vendidos"></canvas>
                    <div class="pd-empty" id="topEmpty" hidden>Sin ventas de productos</div>
                </div>
            </article>
        </div>
    </section>

    <!-- ============ Semáforo de reposición ============ -->
    <section class="pd-panel mb-4" id="semaforo" aria-labelledby="semaforoTitle">
        <div class="pd-panel-head">
            <div>
                <h2 class="pd-panel-title" id="semaforoTitle">Semáforo de reposición</h2>
                <p class="pd-panel-desc">Productos que están por debajo o cerca de su stock mínimo en cada almacén</p>
            </div>
            <div class="pd-seg" role="group" aria-label="Filtrar por urgencia">
                <button type="button" class="pd-seg-btn is-active" data-alert-level="todos">Todos <span id="cntTodos">0</span></button>
                <button type="button" class="pd-seg-btn" data-alert-level="critico"><i class="pd-led pd-led-crit"></i> Crítico <span id="cntCrit">0</span></button>
                <button type="button" class="pd-seg-btn" data-alert-level="alerta"><i class="pd-led pd-led-warn"></i> Alerta <span id="cntWarn">0</span></button>
            </div>
        </div>
        <ul class="pd-alerts" id="alertList"></ul>
        <div class="pd-alerts-foot">
            <button type="button" class="pd-more" id="alertMore" hidden></button>
            <span class="pd-hint"><b class="pd-hint-crit">Crítico</b>: sin stock o menos de la mitad del mínimo. <b class="pd-hint-warn">Alerta</b>: cerca del mínimo.</span>
        </div>
    </section>

    <!-- ============ Feed de actividad ============ -->
    <section class="pd-panel mb-4" aria-labelledby="feedTitle">
        <div class="pd-panel-head">
            <div>
                <h2 class="pd-panel-title" id="feedTitle">Últimos comprobantes</h2>
                <p class="pd-panel-desc">Movimiento más reciente de ventas</p>
            </div>
            <a href="<?= $enlace('/documentos', 'documents') ?>" class="pd-link-btn"><?= $esBayer ? 'Descargar documentos publicados' : 'Ver todos los documentos' ?></a>
        </div>

        <ul class="pd-feed" id="feedList">
            <?php if (!empty($ultimos_documentos)): ?>
                <?php foreach ($ultimos_documentos as $doc): ?>
                    <?php $sede = $doc['sede'] ?? ($doc['sucursal'] ?? ($doc['almacen'] ?? '')); ?>
                    <li class="pd-feed-item" data-fecha="<?= e($doc['fecha']) ?>">
                        <div class="pd-feed-main">
                            <div class="pd-feed-num"><?= e($doc['numero']) ?></div>
                            <div class="pd-feed-client" title="<?= e($doc['cliente']) ?>"><?= e($doc['cliente']) ?></div>
                            <div class="pd-feed-meta">
                                <?= fmt_date($doc['fecha']) ?>
                                <?php if (!empty($sede)): ?>
                                    <span class="pd-feed-sede"><i class="bi bi-geo-alt"></i> <?= e($sede) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pd-feed-side">
                            <div class="pd-feed-amount"><?= fmt_money($doc['monto_total']) ?></div>
                            <div class="pd-feed-status">
                                <span title="Estado comercial"><?= status_badge($doc['estado_comercial']) ?></span>
                                <span title="Estado en Bayer"><?= status_badge($doc['estado_registro']) ?></span>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <div class="pd-feed-empty" id="feedEmpty" <?= !empty($ultimos_documentos) ? 'hidden' : '' ?>>No hay comprobantes en este periodo.</div>
    </section>

</div>

<script>
window.dashboardData = {
    kpis: <?= json_encode($kpis, JSON_UNESCAPED_UNICODE) ?>,
    series: <?= json_encode(array_reverse($series), JSON_UNESCAPED_UNICODE) ?>,
    top: <?= json_encode($top, JSON_UNESCAPED_UNICODE) ?>,
    ultimos: <?= json_encode($ultimos_documentos, JSON_UNESCAPED_UNICODE) ?>,
    inventario_alertas: <?= json_encode($inventario_alertas ?? [], JSON_UNESCAPED_UNICODE) ?>,
    ventas_por_sede: <?= json_encode($ventas_sede ?? [], JSON_UNESCAPED_UNICODE) ?>,
    catalogo_split: <?= json_encode($catalogo_split ?? [], JSON_UNESCAPED_UNICODE) ?>,
    selected_periodo: <?= json_encode($selected_periodo ?? null, JSON_UNESCAPED_UNICODE) ?>
};
</script>
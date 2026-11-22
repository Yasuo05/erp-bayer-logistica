(function () {
    'use strict';

    const D = window.dashboardData;
    if (!D || typeof Chart === 'undefined') return;

    /* ------------------------------------------------------------------
       Constantes y utilidades
       ------------------------------------------------------------------ */
    const C = {
        primary: '#006699',
        primarySoft: '#7fb2cc',
        bayer: '#168c5b',
        text: '#17324d',
        muted: '#5d7288',
        crit: '#dc2626',
        warn: '#d97706',
        grid: 'rgba(0, 40, 80, 0.06)',
        tooltip: '#0b2e4f'
    };
    const FONT = '"Manrope", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
    const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const MESES_LARGO = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    const $ = (id) => document.getElementById(id);
    const num = (v) => { const n = Number(v); return isFinite(n) ? n : 0; };
    const pick = (o, keys, def) => {
        for (const k of keys) {
            if (o && o[k] !== undefined && o[k] !== null && o[k] !== '') return o[k];
        }
        return def;
    };
    const truthy = (v) => v === true || v === 1 || v === '1' || v === 'true' || v === 't' || v === 'si' || v === 'sí';
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtNum = (v) => num(v).toLocaleString('es-PE');
    const money = (v) => 'S/ ' + num(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const moneyShort = (v) => {
        if (v >= 1000000) return 'S/ ' + (v / 1000000).toFixed(1) + 'M';
        if (v >= 1000) return 'S/ ' + (v / 1000).toFixed(0) + 'k';
        return 'S/ ' + v;
    };
    const cut = (s, n) => (s.length > n ? s.slice(0, n - 1) + '…' : s);

    // Convierte "2026-08", "2026-08-15", "15/08/2026" → "2026-08"
    function ym(v) {
        const s = String(v == null ? '' : v);
        let m = s.match(/(\d{4})[-\/](\d{1,2})/);
        if (m) return m[1] + '-' + m[2].padStart(2, '0');
        m = s.match(/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/);
        if (m) return m[3] + '-' + m[2].padStart(2, '0');
        return null;
    }
    const ymShort = (k) => { const p = k.split('-'); return MESES[+p[1] - 1] + ' ' + p[0]; };
    const ymLong = (k) => { const p = k.split('-'); return MESES_LARGO[+p[1] - 1] + ' ' + p[0]; };

    Chart.defaults.font.family = FONT;
    Chart.defaults.color = C.muted;

    const tooltipBase = {
        backgroundColor: C.tooltip,
        titleFont: { size: 12, weight: '700' },
        bodyFont: { size: 13 },
        padding: 10,
        cornerRadius: 8,
        displayColors: false
    };

    /* ------------------------------------------------------------------
       Normalización de datos (tolerante a nombres de campo distintos)
       ------------------------------------------------------------------ */
    const PERIOD_KEYS = ['periodo', 'mes', 'fecha'];

    const series = (D.series || []).map((x) => {
        const docs = pick(x, ['documentos', 'cantidad_documentos', 'num_documentos', 'docs'], null);
        return {
            key: ym(pick(x, PERIOD_KEYS, '')),
            raw: String(pick(x, PERIOD_KEYS, '')),
            monto: num(pick(x, ['monto_pen', 'monto', 'facturacion'], 0)),
            docs: docs === null ? null : num(docs)
        };
    }).sort((a, b) => String(a.key).localeCompare(String(b.key)));

    const periodKeys = series.map((r) => r.key).filter(Boolean);

    const sedesRaw = Array.isArray(D.ventas_por_sede) ? D.ventas_por_sede : [];
    const topRaw = Array.isArray(D.top) ? D.top : [];
    const catRaw = D.catalogo_split || [];

    const alerts = (Array.isArray(D.inventario_alertas) ? D.inventario_alertas : []).map((a) => {
        const stock = num(pick(a, ['stock', 'stock_actual', 'stock_fisico', 'cantidad', 'saldo'], 0));
        const min = num(pick(a, ['stock_minimo', 'minimo', 'min', 'stock_min'], 0));
        const nivel = String(pick(a, ['nivel', 'severidad', 'semaforo', 'prioridad', 'estado'], '')).toLowerCase();
        let lvl;
        if (/crit|rojo|alta/.test(nivel)) lvl = 'critico';
        else if (/alert|amb|media|warn/.test(nivel)) lvl = 'alerta';
        else lvl = (stock <= 0 || (min > 0 && stock <= min * 0.5)) ? 'critico' : 'alerta';
        return {
            producto: String(pick(a, ['producto', 'nombre', 'producto_nombre', 'descripcion', 'sku'], 'Producto sin nombre')),
            almacen: String(pick(a, ['almacen', 'almacen_nombre', 'sede', 'sucursal'], 'Almacén')),
            stock: stock,
            min: min,
            lvl: lvl,
            ratio: min > 0 ? stock / min : 0
        };
    }).sort((a, b) => (a.lvl === b.lvl ? a.ratio - b.ratio : (a.lvl === 'critico' ? -1 : 1)));

    /* ------------------------------------------------------------------
       Estado y filtro de periodo
       ------------------------------------------------------------------ */
    const state = { filter: 'all', topMetric: 'cantidad', alertLevel: 'todos', alertExpanded: false };

    function buildPeriodSelect() {
        const sel = $('periodFilter');
        if (!sel) return;
        let html = '<option value="all">Histórico completo</option>';
        if (periodKeys.length > 3) html += '<option value="last3">Últimos 3 meses</option>';
        if (periodKeys.length > 6) html += '<option value="last6">Últimos 6 meses</option>';
        if (periodKeys.length) {
            html += '<optgroup label="Por mes">';
            periodKeys.slice().reverse().forEach((k) => {
                html += '<option value="m:' + k + '">' + ymLong(k) + '</option>';
            });
            html += '</optgroup>';
        }
        sel.innerHTML = html;

        const urlP = new URLSearchParams(window.location.search).get('periodo') || (D.selected_periodo || '');
        if (urlP && (periodKeys.includes(urlP) || urlP === 'last3' || urlP === 'last6')) {
            const val = (urlP === 'last3' || urlP === 'last6') ? urlP : 'm:' + urlP;
            state.filter = val;
            sel.value = val;
        } else {
            sel.value = 'all';
            state.filter = 'all';
        }

        sel.addEventListener('change', () => { state.filter = sel.value; render(); });
    }

    // null = sin filtro; si no, Set con los meses incluidos
    function activeKeys() {
        const f = state.filter;
        if (f === 'all') return null;
        if (f.indexOf('m:') === 0) return new Set([f.slice(2)]);
        const n = f === 'last3' ? 3 : 6;
        return new Set(periodKeys.slice(-n));
    }

    function filterLabel() {
        const f = state.filter;
        if (f === 'all') return 'Histórico completo';
        if (f === 'last3') return 'Últimos 3 meses';
        if (f === 'last6') return 'Últimos 6 meses';
        return ymLong(f.slice(2));
    }

    function badgeLabel() {
        if (state.filter === 'all') return 'Acumulado histórico';
        return filterLabel();
    }

    function updateCardBadge(id, hasData = true) {
        const el = $(id);
        if (!el) return;
        el.textContent = badgeLabel();
        el.hidden = !hasData;
    }

    // Filtra filas que traen periodo; si no lo traen, devuelve todo como fallback seguro
    function scoped(rows) {
        if (!Array.isArray(rows) || rows.length === 0) return { rows: [], aware: false, scoped: false };
        const set = activeKeys();
        const aware = rows.some((r) => ym(pick(r, PERIOD_KEYS, '')) !== null);
        if (!set || !aware) return { rows: rows, aware: aware, scoped: false };
        const filtered = rows.filter((r) => {
            const k = ym(pick(r, PERIOD_KEYS, ''));
            return k !== null && set.has(k);
        });
        return { rows: filtered, aware: true, scoped: true };
    }

    function toggleNote(id, show) {
        const el = $(id);
        if (el) el.hidden = !show;
    }

    function setEmpty(id, empty) {
        const el = $(id);
        if (el) el.hidden = !empty;
    }

    /* ------------------------------------------------------------------
       Gráficos
       ------------------------------------------------------------------ */
    let salesChart, sedesChart, catalogChart, topChart;
    let catalogCenter = { pct: '—', label: 'Bayer' };

    function createCharts() {
        // 1. Tendencia de facturación
        const sc = $('salesChart');
        if (sc) {
            salesChart = new Chart(sc, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Facturación (PEN)',
                        data: [],
                        borderColor: C.primary,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: C.primary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        backgroundColor: function (context) {
                            const chart = context.chart;
                            const area = chart.chartArea;
                            if (!area) return 'rgba(0, 102, 153, 0.12)';
                            const g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                            g.addColorStop(0, 'rgba(0, 102, 153, 0.28)');
                            g.addColorStop(1, 'rgba(0, 102, 153, 0.00)');
                            return g;
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, tooltipBase, {
                            callbacks: { label: (c) => 'Facturación: ' + money(c.raw) }
                        })
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: C.grid },
                            border: { display: false },
                            ticks: { font: { size: 11 }, callback: (v) => moneyShort(v) }
                        },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // 2. Ventas por sede
        const se = $('sedesChart');
        if (se) {
            sedesChart = new Chart(se, {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'Facturación (PEN)', data: [], backgroundColor: [], borderRadius: 6, borderSkipped: false, maxBarThickness: 46 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, tooltipBase, {
                            callbacks: {
                                label: (c) => money(c.raw),
                                afterLabel: (c) => {
                                    const total = c.dataset.data.reduce((a, b) => a + b, 0);
                                    return total ? 'Es el ' + ((c.raw / total) * 100).toFixed(1) + '% del total' : '';
                                }
                            }
                        })
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: C.grid },
                            border: { display: false },
                            ticks: { font: { size: 11 }, callback: (v) => moneyShort(v) }
                        },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // 3. Participación Bayer vs otros (donut con porcentaje al centro)
        const ca = $('catalogChart');
        if (ca) {
            const centerPlugin = {
                id: 'pdCenter',
                afterDraw(chart) {
                    const a = chart.chartArea;
                    if (!a) return;
                    const cx = (a.left + a.right) / 2;
                    const cy = (a.top + a.bottom) / 2;
                    const g = chart.ctx;
                    g.save();
                    g.textAlign = 'center';
                    g.textBaseline = 'middle';
                    g.fillStyle = C.text;
                    g.font = '800 30px ' + FONT;
                    g.fillText(catalogCenter.pct, cx, cy - 8);
                    g.fillStyle = C.muted;
                    g.font = '600 12px ' + FONT;
                    g.fillText(catalogCenter.label, cx, cy + 16);
                    g.restore();
                }
            };
            catalogChart = new Chart(ca, {
                type: 'doughnut',
                data: { labels: ['Bayer oficial', 'Genérico y otros'], datasets: [{ data: [0, 0], backgroundColor: [C.bayer, C.primary], borderColor: '#ffffff', borderWidth: 3, hoverOffset: 6 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, tooltipBase, {
                            callbacks: {
                                label: (c) => c.label + ': ' + money(c.raw),
                                afterLabel: (c) => {
                                    const total = c.dataset.data.reduce((a, b) => a + b, 0);
                                    return total ? ((c.raw / total) * 100).toFixed(1) + '% de las ventas' : '';
                                }
                            }
                        })
                    }
                },
                plugins: [centerPlugin]
            });
        }

        // 4. Top 10 productos
        const tc = $('topChart');
        if (tc) {
            topChart = new Chart(tc, {
                type: 'bar',
                data: { labels: [], datasets: [{ label: 'Unidades', data: [], backgroundColor: [], borderRadius: 5, borderSkipped: false, barPercentage: 0.78 }] },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: Object.assign({}, tooltipBase, {
                            callbacks: {
                                title: (items) => items.length ? topView[items[0].dataIndex].nombre : '',
                                label: (c) => 'Cantidad: ' + fmtNum(topView[c.dataIndex].cantidad) + ' und.',
                                afterLabel: (c) => {
                                    const it = topView[c.dataIndex];
                                    return [
                                        'Facturado: ' + money(it.monto),
                                        it.bayer ? 'Producto Bayer oficial' : 'Genérico u otros'
                                    ];
                                }
                            }
                        })
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: C.grid },
                            border: { display: false },
                            ticks: {
                                font: { size: 11 },
                                callback: (v) => state.topMetric === 'monto' ? moneyShort(v) : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                color: C.text,
                                font: { size: 11, weight: '600' },
                                callback: function (value) { return cut(this.getLabelForValue(value), 28); }
                            }
                        }
                    }
                }
            });
        }
    }

    let topView = [];

    /* ------------------------------------------------------------------
       Render por sección
       ------------------------------------------------------------------ */
    function renderKpis() {
        const set = activeKeys();
        const fact = $('kpiFact');
        const tick = $('kpiTicket');
        const factSub = $('kpiFactSub');
        const tickSub = $('kpiTicketSub');
        const label = filterLabel();

        if (!set) {
            if (fact) fact.textContent = fact.dataset.original;
            if (tick) tick.textContent = tick.dataset.original;
            if (factSub) factSub.textContent = label;
            if (tickSub) tickSub.textContent = 'Monto promedio por comprobante';
            return;
        }

        const rows = series.filter((r) => rowIn(r.key, set));
        const total = rows.reduce((a, r) => a + r.monto, 0);
        if (fact) fact.textContent = money(total);
        if (factSub) factSub.textContent = label;

        const withDocs = rows.length && rows.every((r) => r.docs !== null);
        const docs = withDocs ? rows.reduce((a, r) => a + r.docs, 0) : 0;
        if (tick && tickSub) {
            if (withDocs && docs > 0) {
                tick.textContent = money(total / docs);
                tickSub.textContent = 'En ' + fmtNum(docs) + ' comprobantes · ' + label;
            } else {
                tick.textContent = tick.dataset.original;
                tickSub.textContent = 'Promedio histórico (no varía con el periodo)';
            }
        }
    }

    function rowIn(key, set) { return !set || (key !== null && set.has(key)); }

    function renderTrend() {
        if (!salesChart) return;
        let rows = series;
        let highlight = null;
        const f = state.filter;

        if (f.indexOf('m:') === 0) {
            const k = f.slice(2);
            rows = series.filter((r) => r.key !== null && r.key <= k).slice(-12);
            highlight = k;
        } else if (f !== 'all') {
            const set = activeKeys();
            rows = series.filter((r) => rowIn(r.key, set));
        }

        const labels = rows.map((r) => (r.key ? ymShort(r.key) : r.raw));
        salesChart.data.labels = labels;
        const ds = salesChart.data.datasets[0];
        ds.data = rows.map((r) => r.monto);
        ds.pointRadius = rows.map((r) => (highlight && r.key === highlight ? 7 : 4));
        ds.pointBackgroundColor = rows.map((r) => (highlight && r.key === highlight ? C.bayer : C.primary));
        salesChart.update();
        setEmpty('salesEmpty', rows.length === 0);

        const desc = $('trendDesc');
        if (desc) desc.textContent = highlight
            ? 'Ventas en soles hasta ' + ymLong(highlight)
            : 'Ventas mensuales en soles · ' + filterLabel();

        // Variación contra el mes anterior
        const dl = $('trendDelta');
        if (!dl) return;
        let idx = rows.length - 1;
        if (highlight) idx = rows.findIndex((r) => r.key === highlight);
        if (idx >= 1 && rows[idx - 1].monto > 0) {
            const pct = ((rows[idx].monto - rows[idx - 1].monto) / rows[idx - 1].monto) * 100;
            const cls = Math.abs(pct) < 0.05 ? 'is-flat' : (pct > 0 ? 'is-up' : 'is-down');
            const icon = cls === 'is-up' ? 'bi-arrow-up-right' : (cls === 'is-down' ? 'bi-arrow-down-right' : 'bi-dash');
            dl.className = 'pd-delta ' + cls;
            dl.innerHTML = '<i class="bi ' + icon + '"></i> ' + Math.abs(pct).toFixed(1).replace('.', ',') + '% vs mes anterior';
            dl.hidden = false;
        } else {
            dl.hidden = true;
        }
    }

    function renderSedes() {
        if (!sedesChart) return;
        const source = (Array.isArray(D.ventas_por_sede) && D.ventas_por_sede.length) ? D.ventas_por_sede : sedesRaw;
        const sc = scoped(source);

        const map = {};
        sc.rows.forEach((item) => {
            const name = String(item.sede_nombre || item.sede || item.nombre || 'Sin sede');
            const val = num(item.total_facturado_pen !== undefined ? item.total_facturado_pen : (item.monto_pen || item.monto_total || item.monto || 0));
            map[name] = (map[name] || 0) + val;
        });

        // Fallback seguro si sc.rows no tuvo datos pero source contiene datos acumulados
        if (Object.keys(map).length === 0 && source.length > 0 && !sc.aware) {
            source.forEach((item) => {
                const name = String(item.sede_nombre || item.sede || item.nombre || 'Sin sede');
                const val = num(item.total_facturado_pen !== undefined ? item.total_facturado_pen : (item.monto_pen || item.monto_total || item.monto || 0));
                map[name] = (map[name] || 0) + val;
            });
        }

        const list = Object.keys(map).map((k) => ({ name: k, v: map[k] }))
            .filter((x) => x.v > 0)
            .sort((a, b) => b.v - a.v);

        sedesChart.data.labels = list.map((x) => x.name);
        const ds = sedesChart.data.datasets[0];
        ds.data = list.map((x) => x.v);
        ds.backgroundColor = list.map((x, i) => (i === 0 ? C.primary : C.primarySoft));
        sedesChart.update();
        setEmpty('sedesEmpty', list.length === 0);

        updateCardBadge('noteSedes', list.length > 0);
    }

    function renderCatalog() {
        if (!catalogChart) return;
        const source = (Array.isArray(D.catalogo_split) && D.catalogo_split.length) ? D.catalogo_split : (Array.isArray(catRaw) ? catRaw : []);
        const sc = scoped(source);

        let bayer = 0, otros = 0;
        let bayerLabel = 'Bayer Oficial', otrosLabel = 'Genéricos / Terceros';

        sc.rows.forEach((r) => {
            const isBayer = r.es_bayer == 1 || /bayer/i.test(r.fabricante || r.portafolio || r.categoria || r.nombre || '');
            const v = num(r.monto_total_pen !== undefined ? r.monto_total_pen : (r.monto_pen || r.monto || r.facturacion || 0));
            if (isBayer) {
                bayer += v;
                if (r.fabricante) bayerLabel = String(r.fabricante);
            } else {
                otros += v;
                if (r.fabricante) otrosLabel = String(r.fabricante);
            }
        });

        // Fallback seguro si sc.rows no tuvo datos pero source contiene datos acumulados
        if (bayer === 0 && otros === 0 && source.length > 0 && !sc.aware) {
            source.forEach((r) => {
                const isBayer = r.es_bayer == 1 || /bayer/i.test(r.fabricante || r.portafolio || r.categoria || r.nombre || '');
                const v = num(r.monto_total_pen !== undefined ? r.monto_total_pen : (r.monto_pen || r.monto || r.facturacion || 0));
                if (isBayer) {
                    bayer += v;
                    if (r.fabricante) bayerLabel = String(r.fabricante);
                } else {
                    otros += v;
                    if (r.fabricante) otrosLabel = String(r.fabricante);
                }
            });
        }

        const total = bayer + otros;
        catalogChart.data.labels = [bayerLabel, otrosLabel];
        catalogChart.data.datasets[0].data = [bayer, otros];
        catalogCenter = { pct: total ? Math.round((bayer / total) * 100) + '%' : '—', label: 'es Bayer' };
        catalogChart.update();
        setEmpty('catalogEmpty', total === 0);

        const lg = $('catalogLegend');
        if (lg) {
            const pc = (v) => (total ? ((v / total) * 100).toFixed(1).replace('.', ',') + '%' : '0%');
            lg.innerHTML =
                '<div class="pd-legend-item is-bayer"><div class="pd-legend-name"><span class="pd-dot pd-dot-bayer"></span>' + esc(bayerLabel) + '</div><div class="pd-legend-val">' + money(bayer) + '</div><div class="pd-legend-pct">' + pc(bayer) + ' del total</div></div>' +
                '<div class="pd-legend-item"><div class="pd-legend-name"><span class="pd-dot pd-dot-other"></span>' + esc(otrosLabel) + '</div><div class="pd-legend-val">' + money(otros) + '</div><div class="pd-legend-pct">' + pc(otros) + ' del total</div></div>';
        }

        updateCardBadge('noteCatalog', total > 0);
    }

    function renderTop() {
        if (!topChart) return;
        const source = (Array.isArray(D.top) && D.top.length) ? D.top : topRaw;
        const sc = scoped(source);
        const map = {};

        sc.rows.forEach((r) => {
            const name = String(pick(r, ['nombre', 'producto', 'descripcion'], 'Producto'));
            if (!map[name]) map[name] = { nombre: name, cantidad: 0, monto: 0, bayer: false };
            map[name].cantidad += num(r.cantidad);
            map[name].monto += num(r.monto_pen !== undefined ? r.monto_pen : r.monto);
            if (truthy(r.es_bayer)) map[name].bayer = true;
        });

        // Fallback seguro si sc.rows no tuvo datos pero source contiene datos acumulados
        if (Object.keys(map).length === 0 && source.length > 0 && !sc.aware) {
            source.forEach((r) => {
                const name = String(pick(r, ['nombre', 'producto', 'descripcion'], 'Producto'));
                if (!map[name]) map[name] = { nombre: name, cantidad: 0, monto: 0, bayer: false };
                map[name].cantidad += num(r.cantidad);
                map[name].monto += num(r.monto_pen !== undefined ? r.monto_pen : r.monto);
                if (truthy(r.es_bayer)) map[name].bayer = true;
            });
        }

        const metric = state.topMetric;
        topView = Object.keys(map).map((k) => map[k]).sort((a, b) => b[metric] - a[metric]).slice(0, 10);

        topChart.data.labels = topView.map((x) => (x.bayer ? '★ ' : '') + x.nombre);
        const ds = topChart.data.datasets[0];
        ds.label = metric === 'monto' ? 'Facturado (PEN)' : 'Unidades';
        ds.data = topView.map((x) => x[metric]);
        ds.backgroundColor = topView.map((x) => (x.bayer ? C.bayer : C.primary));
        topChart.update();
        setEmpty('topEmpty', topView.length === 0);

        updateCardBadge('noteTop', topView.length > 0);
    }

    function renderFeed() {
        const set = activeKeys();
        const items = document.querySelectorAll('#feedList .pd-feed-item');
        let visible = 0;
        items.forEach((li) => {
            const k = ym(li.getAttribute('data-fecha'));
            const show = !set || (k !== null && set.has(k));
            li.hidden = !show;
            if (show) visible++;
        });
        const empty = $('feedEmpty');
        if (empty) empty.hidden = visible > 0;
    }

    /* ------------------------------------------------------------------
       Semáforo de reposición (no depende del periodo: es el estado de hoy)
       ------------------------------------------------------------------ */
    function renderAlertKpi() {
        const card = $('kpiAlertCard');
        const numEl = $('kpiAlertNum');
        const sub = $('kpiAlertSub');
        const list = $('kpiAlertList');
        if (!card || !numEl) return;
        if (!alerts.length) return; // se conserva el dato calculado por el servidor

        const crit = alerts.filter((a) => a.lvl === 'critico');
        card.classList.remove('is-critical', 'is-warning', 'is-clear');

        if (crit.length) {
            card.classList.add('is-critical');
            numEl.textContent = crit.length;
            sub.textContent = crit.length === 1 ? 'producto crítico: reponer ya' : 'productos críticos: reponer ya';
            list.innerHTML = crit.slice(0, 3).map((a) =>
                '<li><span title="' + esc(a.producto) + '">' + esc(a.producto) + '</span><span>' + fmtNum(a.stock) + ' und / ' + fmtNum(a.min) + ' und</span></li>'
            ).join('');
        } else {
            card.classList.add('is-warning');
            numEl.textContent = alerts.length;
            sub.textContent = 'productos cerca de su stock mínimo';
            list.innerHTML = '';
        }
    }

    function renderAlerts() {
        const ul = $('alertList');
        if (!ul) return;
        const total = alerts.length;
        const nCrit = alerts.filter((a) => a.lvl === 'critico').length;
        const nWarn = total - nCrit;
        if ($('cntTodos')) $('cntTodos').textContent = total;
        if ($('cntCrit')) $('cntCrit').textContent = nCrit;
        if ($('cntWarn')) $('cntWarn').textContent = nWarn;

        if (!total) {
            ul.innerHTML = '<li class="pd-alerts-empty"><i class="bi bi-check-circle-fill"></i> Todo en orden: ningún producto está bajo su stock mínimo.</li>';
            const more = $('alertMore'); if (more) more.hidden = true;
            return;
        }

        const filtered = alerts.filter((a) => state.alertLevel === 'todos' || a.lvl === state.alertLevel);
        const LIMIT = 6;
        const shown = state.alertExpanded ? filtered : filtered.slice(0, LIMIT);

        ul.innerHTML = shown.map((a) => {
            const crit = a.lvl === 'critico';
            const pct = Math.max(2, Math.min(100, Math.round(a.ratio * 100)));
            const falta = Math.max(a.min - a.stock, 0);
            return '<li class="pd-alert-row">' +
                '<div><div class="pd-alert-prod" title="' + esc(a.producto) + '">' + esc(a.producto) + '</div>' +
                '<div class="pd-alert-wh"><i class="bi bi-building"></i> ' + esc(a.almacen) + '</div></div>' +
                '<div class="pd-alert-gauge"><div class="pd-bar" role="img" aria-label="Stock al ' + pct + '% del mínimo"><div class="pd-bar-fill ' + (crit ? 'is-crit' : '') + '" style="width:' + pct + '%"></div></div>' +
                '<div class="pd-alert-nums"><span>Hay <b>' + fmtNum(a.stock) + ' und</b> de <b>' + fmtNum(a.min) + ' und</b> mínimo</span>' +
                (falta > 0 ? '<span><span class="opacity-50">·</span> Faltan <b>' + fmtNum(falta) + ' und</b></span>' : '') + '</div></div>' +
                '<span class="pd-badge ' + (crit ? 'pd-badge-crit' : 'pd-badge-warn') + '"><i class="pd-led ' + (crit ? 'pd-led-crit' : 'pd-led-warn') + '"></i>' + (crit ? 'CRÍTICO' : 'ALERTA') + '</span>' +
                '</li>';
        }).join('') || '<li class="pd-alerts-empty">No hay productos en este nivel.</li>';

        const more = $('alertMore');
        if (more) {
            if (filtered.length > LIMIT) {
                more.hidden = false;
                more.textContent = state.alertExpanded ? 'Mostrar menos' : 'Ver los ' + (filtered.length - LIMIT) + ' restantes';
            } else {
                more.hidden = true;
            }
        }
    }

    /* ------------------------------------------------------------------
       Orquestación
       ------------------------------------------------------------------ */
    function render() {
        renderKpis();
        renderTrend();
        renderSedes();
        renderCatalog();
        renderTop();
        renderFeed();
    }

    function bindControls() {
        document.querySelectorAll('[data-top-metric]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.topMetric = btn.getAttribute('data-top-metric');
                document.querySelectorAll('[data-top-metric]').forEach((b) => b.classList.toggle('is-active', b === btn));
                renderTop();
            });
        });
        document.querySelectorAll('[data-alert-level]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.alertLevel = btn.getAttribute('data-alert-level');
                state.alertExpanded = false;
                document.querySelectorAll('[data-alert-level]').forEach((b) => b.classList.toggle('is-active', b === btn));
                renderAlerts();
            });
        });
        const more = $('alertMore');
        if (more) more.addEventListener('click', () => { state.alertExpanded = !state.alertExpanded; renderAlerts(); });
    }

    function init() {
        buildPeriodSelect();
        createCharts();
        bindControls();
        renderAlertKpi();
        renderAlerts();
        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
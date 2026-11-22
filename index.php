<?php
// index.php
require_once __DIR__ . '/core/crud_base.php';
requerir_login();
$titulo = 'Dashboard General';
$menu = require __DIR__ . '/core/menu.php';

// Consultas estadísticas rápidas
$totalProd = 0;
$totalCli  = 0;
$totalHomo = 0;
$totalSuc  = 0;
$pctHomo   = 0;

$distribucionCategorias = [];
$distribucionAlmacenes  = [];

try {
    $totalProd = (int)$pdo->query("SELECT COUNT(*) FROM `productos`")->fetchColumn();
    $totalCli  = (int)$pdo->query("SELECT COUNT(*) FROM `clientes`")->fetchColumn();
    $totalHomo = (int)$pdo->query("SELECT COUNT(*) FROM `homologacion_productos_bayer`")->fetchColumn();
    $totalSuc  = (int)$pdo->query("SELECT COUNT(*) FROM `sucursales`")->fetchColumn();

    // Validación previa al envío a Bayer con datos reales (no transmite nada)
    $validacionBayer = $pdo->query("SELECT
        (SELECT COUNT(*) FROM productos WHERE estado = 1) AS activos,
        (SELECT COUNT(DISTINCT h.producto_id) FROM homologacion_productos_bayer h JOIN productos p ON p.id = h.producto_id WHERE p.estado = 1) AS homologados,
        (SELECT COUNT(DISTINCT h.producto_id) FROM homologacion_productos_bayer h JOIN productos p ON p.id = h.producto_id
          WHERE p.estado = 1 AND COALESCE(TRIM(h.material_id), '') <> '') AS con_material,
        (SELECT COUNT(DISTINCT h.producto_id) FROM homologacion_productos_bayer h JOIN productos p ON p.id = h.producto_id
          WHERE p.estado = 1 AND COALESCE(TRIM(h.material_id), '') <> '' AND h.unidad_bayer_id IS NOT NULL) AS listos")->fetch();

    if ($totalProd > 0) {
        $pctHomo = min(100, round(($totalHomo / $totalProd) * 100));
    }

    // Datos para gráfico de productos por categoría
    $st = $pdo->query("
        SELECT c.nombre, COUNT(p.id) as cantidad 
        FROM categorias_producto c 
        LEFT JOIN productos p ON p.categoria_id = c.id 
        GROUP BY c.id, c.nombre
        ORDER BY cantidad DESC
    ");
    $distribucionCategorias = $st->fetchAll();

    // Datos de almacenes por sucursal
    $st = $pdo->query("
        SELECT s.nombre as sucursal, COUNT(a.id) as almacenes
        FROM sucursales s
        LEFT JOIN almacenes a ON a.sucursal_id = s.id
        GROUP BY s.id, s.nombre
    ");
    $distribucionAlmacenes = $st->fetchAll();

} catch (Exception $e) {
    // Manejo seguro en caso de tablas vacías
}

$desc = [
    'Catálogo'        => 'Gestión de productos comerciales, marcas y categorías agrícolas.',
    'Clientes'        => 'Padrón de clientes, agricultores y tipos de documento fiscal.',
    'Ubicación'       => 'Jerarquía territorial por departamento, provincia y distrito (Ubigeo).',
    'Organización'    => 'Estructura corporativa: empresas, sucursales, almacenes y vendedores.',
    'Homologaciones'  => 'Matriz de equivalencias entre códigos internos y el estándar Bayer.'
];

$iconos = [
    'Catálogo'       => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
    'Clientes'       => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    'Ubicación'      => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
    'Organización'   => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    'Homologaciones' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>'
];

require __DIR__ . '/core/layout_header.php';
?>

<!-- Librería de gráficos interactivos Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="bento-dashboard">

  <!-- TARJETA HERO BENTO: Estado de Homologación Bayer con Simulador -->
  <div class="bento-card bento-hero col-span-8">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
      <div>
        <span class="module-chip bento-tag">Ecosistema Bayer · Pucchún</span>
        <h3>Estado de Homologación de Maestros</h3>
        <p>Monitoreo en tiempo real de equivalencias de catálogo listas para transmisión de datos a Bayer.</p>
      </div>
      <div style="display:flex; gap:10px; align-items:center;">
        <button type="button" class="btn btn-sm btn-simular" onclick="simularTransmisionBayer()" id="btn-simular">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          <span>Validar envío a Bayer</span>
        </button>
        <div style="background:rgba(255,255,255,0.1); padding:8px 14px; border-radius:12px; text-align:right;">
          <span style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#A3CADF; font-weight:700;">Cobertura</span>
          <div style="font-size:22px; font-weight:800; color:var(--bayer-green-bright);"><?= $pctHomo ?>%</div>
        </div>
      </div>
    </div>

    <!-- Barra de progreso interactiva -->
    <div class="progress-container" style="margin: 18px 0 12px;">
      <div class="progress-bar-fill" id="hero-progress-bar" style="width: <?= max(5, $pctHomo) ?>%;"></div>
    </div>

    <!-- Caja de simulación de transmisión (oculta por defecto) -->
    <div id="simulacion-caja" class="simulacion-caja" style="display:none;">
      <div class="simulacion-header">
        <span class="simulacion-dot"></span>
        <strong id="simulacion-paso-texto">Iniciando validación de transmisión...</strong>
        <span class="simulacion-pct" id="simulacion-pct">0%</span>
      </div>
      <div class="simulacion-bar">
        <div class="simulacion-bar-inner" id="simulacion-bar-inner"></div>
      </div>
    </div>

    <div class="hero-stats-row">
      <div class="hero-stat-badge">
        <span class="hero-stat-num" id="stat-homo"><?= $totalHomo ?></span>
        <span class="hero-stat-label">Productos homologados</span>
      </div>
      <div class="hero-stat-badge">
        <span class="hero-stat-num"><?= max(0, $totalProd - $totalHomo) ?></span>
        <span class="hero-stat-label">Pendientes de homologar</span>
      </div>
      <div class="hero-stat-badge">
        <span class="hero-stat-num"><?= $totalProd ?></span>
        <span class="hero-stat-label">Total en catálogo</span>
      </div>
      <div>
        <a href="<?= BASE_URL ?>/modulos/homologaciones/productos_bayer.php" class="btn" style="background:#ffffff; color:var(--bayer-navy); padding:9px 16px; font-size:13px;">
          Gestionar Homologaciones →
        </a>
      </div>
    </div>
  </div>

  <!-- TARJETA KPI BENTO: Gráfico Donut de Cobertura -->
  <div class="bento-card col-span-4" style="display:flex; flex-direction:column; justify-content:space-between;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <div>
        <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-light); letter-spacing:0.8px;">Distribución</span>
        <h4 style="margin:2px 0 0; color:var(--bayer-navy); font-size:16px;">Calidad de Catálogo</h4>
      </div>
      <span class="badge-status badge-activo" style="font-size:11px;"><?= $pctHomo ?>% Listo</span>
    </div>

    <div style="position:relative; height:150px; width:100%; display:flex; align-items:center; justify-content:center;">
      <canvas id="chartDonutCobertura"></canvas>
      <div style="position:absolute; text-align:center; pointer-events:none;">
        <div style="font-size:22px; font-weight:800; color:var(--bayer-navy); line-height:1;"><?= $pctHomo ?>%</div>
        <small style="font-size:10.5px; color:var(--text-muted); font-weight:700;">HOMOLOGADO</small>
      </div>
    </div>

    <div style="display:flex; justify-content:space-around; border-top:1px solid var(--border-subtle); padding-top:12px; margin-top:12px; font-size:12px;">
      <span style="display:flex; align-items:center; gap:6px;">
        <span style="width:8px; height:8px; border-radius:50%; background:#2E8B3D;"></span> Homologados (<?= $totalHomo ?>)
      </span>
      <span style="display:flex; align-items:center; gap:6px;">
        <span style="width:8px; height:8px; border-radius:50%; background:#CBD5E1;"></span> Pendientes (<?= max(0, $totalProd - $totalHomo) ?>)
      </span>
    </div>
  </div>

  <!-- TARJETA BENTO: Gráfico de Productos por Categoría Agrícola -->
  <div class="bento-card col-span-8">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <div>
        <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-light); letter-spacing:0.8px;">Catálogo Agrícola</span>
        <h4 style="margin:2px 0 0; color:var(--bayer-navy); font-size:16px;">Distribución de Productos por Categoría</h4>
      </div>
      <a href="<?= BASE_URL ?>/modulos/catalogo/productos.php" style="color:var(--bayer-green); font-size:12.5px; font-weight:700; text-decoration:none;">Ver productos →</a>
    </div>
    <div style="height:190px; width:100%;">
      <canvas id="chartBarrasCategorias"></canvas>
    </div>
  </div>

  <!-- TARJETA KPI BENTO: Clientes y Red Comercial -->
  <div class="bento-card kpi-card col-span-4">
    <div class="kpi-header">
      <div>
        <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-light); letter-spacing:0.8px;">Padrón</span>
        <div class="kpi-num"><?= $totalCli ?></div>
        <div class="kpi-sub">
          <span style="color:var(--bayer-green); font-weight:700;">Fundos y Clientes</span> activos
        </div>
      </div>
      <div class="kpi-icon icon-green">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      </div>
    </div>
    <div style="border-top:1px solid var(--border-subtle); margin-top:16px; padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
      <span style="font-size:12px; color:var(--text-muted);"><?= $totalSuc ?> sucursal(es) operativas</span>
      <a href="<?= BASE_URL ?>/modulos/clientes/clientes.php" style="color:var(--bayer-green); font-weight:700; font-size:12px; text-decoration:none;">Ver clientes →</a>
    </div>
  </div>

  <!-- TARJETAS MODULARES BENTO (ACCESOS RÁPIDOS) -->
  <?php foreach ($menu as $grupo => $items): 
    $primero = array_key_first($items);
    $icono = $iconos[$grupo] ?? $iconos['Catálogo'];
    $cantMod = count($items);
  ?>
    <a class="bento-card bento-nav-card col-span-4" href="<?= BASE_URL ?>/modulos/<?= $primero ?>">
      <div>
        <div class="card-head">
          <div class="kpi-icon icon-navy" style="width:38px; height:38px;">
            <?= $icono ?>
          </div>
          <span style="font-size:11px; font-weight:700; color:var(--text-light); background:#F1F5F9; padding:3px 8px; border-radius:12px;">
            <?= $cantMod ?> submódulos
          </span>
        </div>
        <h4><?= h($grupo) ?></h4>
        <p><?= h($desc[$grupo] ?? 'Gestión de registros maestros.') ?></p>
      </div>
      <div class="card-foot">
        <span>Abrir módulo</span>
        <span>→</span>
      </div>
    </a>
  <?php endforeach; ?>

</div>

<!-- SCRIPT DE INICIALIZACIÓN DE GRÁFICOS Y SIMULADOR -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  // 1. Gráfico Donut de Cobertura de Homologación
  const ctxDonut = document.getElementById('chartDonutCobertura');
  if (ctxDonut) {
    new Chart(ctxDonut, {
      type: 'doughnut',
      data: {
        labels: ['Homologados Bayer', 'Pendientes'],
        datasets: [{
          data: [<?= $totalHomo ?>, <?= max(0, $totalProd - $totalHomo) ?>],
          backgroundColor: ['#2E8B3D', '#E2E8F0'],
          hoverBackgroundColor: ['#236E30', '#CBD5E1'],
          borderWidth: 0,
          cutout: '76%'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${ctx.label}: ${ctx.raw} productos (${Math.round((ctx.raw / <?= max(1, $totalProd) ?>) * 100)}%)`
            }
          }
        }
      }
    });
  }

  // 2. Gráfico de Barras de Categorías
  const ctxBarras = document.getElementById('chartBarrasCategorias');
  if (ctxBarras) {
    const cats = <?= json_encode(array_column($distribucionCategorias, 'nombre')) ?>;
    const cants = <?= json_encode(array_map('intval', array_column($distribucionCategorias, 'cantidad'))) ?>;

    new Chart(ctxBarras, {
      type: 'bar',
      data: {
        labels: cats,
        datasets: [{
          label: 'Productos registrados',
          data: cants,
          backgroundColor: '#10384F',
          hoverBackgroundColor: '#2E8B3D',
          borderRadius: 6,
          barPercentage: 0.55
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1, color: '#64748B' },
            grid: { color: '#F1F5F9' }
          },
          x: {
            ticks: { color: '#64748B', font: { size: 11.5, weight: '600' } },
            grid: { display: false }
          }
        }
      }
    });
  }
});

// 7. SIMULADOR DE TRANSMISIÓN DE DATOS A BAYER
function simularTransmisionBayer() {
  const btn = document.getElementById('btn-simular');
  const caja = document.getElementById('simulacion-caja');
  const barInner = document.getElementById('simulacion-bar-inner');
  const texto = document.getElementById('simulacion-paso-texto');
  const pct = document.getElementById('simulacion-pct');

  if (!caja) return;

  btn.disabled = true;
  caja.style.display = 'block';

  // Conteos reales calculados en el servidor. Es una validación previa: no se transmite nada a Bayer.
  const v = <?= json_encode(array_map('intval', $validacionBayer ?? ['activos' => 0, 'homologados' => 0, 'con_material' => 0, 'listos' => 0])) ?>;
  const sinHomologar = v.activos - v.homologados, sinMaterial = v.homologados - v.con_material, sinUnidad = v.con_material - v.listos;
  const pasos = [
    { p: 25, txt: `Paso 1/4: ${v.homologados} de ${v.activos} productos activos tienen homologación Bayer` + (sinHomologar ? ` (${sinHomologar} sin homologar).` : '.') },
    { p: 55, txt: `Paso 2/4: ${v.con_material} con Material ID Bayer` + (sinMaterial ? ` (${sinMaterial} sin Material ID).` : '.') },
    { p: 85, txt: `Paso 3/4: ${v.listos} con unidad de medida Bayer homologada` + (sinUnidad ? ` (${sinUnidad} sin unidad).` : '.') },
    { p: 100, txt: `Paso 4/4: ${v.listos} de ${v.activos} productos listos para compartir con Bayer. Validación previa: no se transmitió información.` }
  ];
  const completo = v.activos > 0 && v.listos === v.activos;

  let i = 0;
  function avanzar() {
    if (i < pasos.length) {
      const step = pasos[i];
      barInner.style.width = step.p + '%';
      pct.textContent = step.p + '%';
      texto.textContent = step.txt;
      i++;
      setTimeout(avanzar, 700);
    } else {
      btn.disabled = false;
      mostrarToast(completo
        ? `Validación completa: los ${v.listos} productos activos están listos para Bayer.`
        : `Validación con observaciones: ${v.listos} de ${v.activos} productos listos. Revisa las homologaciones pendientes.`,
        completo ? 'ok' : 'err', 6000);
      setTimeout(() => {
        caja.style.display = 'none';
        barInner.style.width = '0%';
      }, 5000);
    }
  }
  avanzar();
}
</script>

<?php require __DIR__ . '/core/layout_footer.php'; ?>

<?php
/**
 * Marco común del ERP: barra superior con el menú, selector de sede, usuario y migas de pan.
 *
 * Lo usan las páginas del ERP (core/layout_header.php) y los módulos que tienen su propio enrutador
 * (Facturación y Portal Bayer), para que todo el sistema se vea y se navegue igual.
 * No depende de los helpers de ningún módulo: recibe la conexión y el usuario como parámetros.
 *
 * Menú (core/menu.php): grupo => [ruta relativa a /modulos/ => nombre]. Una clave que empieza con «#»
 * es un subtítulo dentro del grupo (p. ej. '#emision' => 'Emisión').
 */

if (!function_exists('erp_marco_e')) {

function erp_marco_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** URL base del ERP (la carpeta que contiene /modulos/), deducida del script en ejecución. */
function erp_marco_base(): string {
    if (defined('BASE_URL')) return BASE_URL;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    return str_contains($script, '/modulos/') ? substr($script, 0, strpos($script, '/modulos/')) : rtrim(dirname($script), '/');
}

/** Ruta actual relativa a /modulos/ (sin query), p. ej. «facturacion/public/comprobantes/facturas». */
function erp_marco_ruta_actual(?string $ruta = null): string {
    $ruta = str_replace('\\', '/', $ruta ?? (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    $pos = strpos($ruta, '/modulos/');
    return $pos === false ? '' : substr($ruta, $pos + 9);
}

/** Grupo y opción activos del menú para la ruta actual: coincidencia exacta o, si no hay, el prefijo más largo. */
function erp_marco_activo(array $menu, string $rel): array {
    $mejor = ['', '', 0];
    foreach ($menu as $grupo => $items) {
        foreach ($items as $ruta => $_) {
            if ($ruta[0] === '#') continue;
            $r = rtrim($ruta, '/');
            if ($rel === $r || $rel === $ruta) return [$grupo, $ruta];
            if ($r !== '' && str_starts_with($rel, $r . '/') && strlen($r) > $mejor[2]) $mejor = [$grupo, $ruta, strlen($r)];
        }
    }
    if ($mejor[0] !== '') return [$mejor[0], ''];
    // Sin opción propia (p. ej. el detalle de un comprobante): el grupo cuyo módulo coincide
    $modulo = explode('/', $rel)[0] ?? '';
    foreach ($menu as $grupo => $items) {
        foreach ($items as $ruta => $_) {
            if ($ruta[0] !== '#' && $modulo !== '' && explode('/', $ruta)[0] === $modulo && in_array($modulo, ['facturacion', 'datahub'], true)) return [$grupo, ''];
        }
    }
    return ['', ''];
}

/** Sedes que puede usar el usuario y sede activa (misma regla que core/sede.php). */
function erp_marco_sedes(PDO $pdo, array $u): array {
    $st = $pdo->prepare('SELECT s.id, s.nombre FROM usuario_sucursal us JOIN sucursales s ON s.id = us.sucursal_id WHERE us.usuario_id = ? AND s.estado = 1 ORDER BY s.nombre');
    $st->execute([(int)($u['id'] ?? 0)]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    $restringida = (bool)$filas;
    if (!$filas) $filas = $pdo->query('SELECT id, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    $activa = $_SESSION['sede_activa'] ?? null;
    $ids = array_map(fn($f) => (int)$f['id'], $filas);
    if ($activa !== null && !in_array((int)$activa, $ids, true)) $activa = null;
    if ($activa === null && $restringida && $ids) $activa = $ids[0];
    return [$filas, $activa === null ? null : (int)$activa, $restringida];
}

/**
 * Abre la página: <head>, barra superior y migas de pan.
 * Opciones: pdo, usuario, titulo, ruta (ruta actual completa; por defecto REQUEST_URI),
 *           css (hojas propias del módulo), head (HTML extra en <head>),
 *           embebido (true en los módulos con su propio diseño: no se imprime el título del ERP),
 *           subtitulo (solo páginas del ERP).
 */
function erp_marco_cabecera(array $o): void {
    $base = erp_marco_base();
    $menu = require __DIR__ . '/menu.php';
    $u = $o['usuario'] ?? [];
    $titulo = (string)($o['titulo'] ?? 'Inicio');
    $embebido = !empty($o['embebido']);
    $rel = erp_marco_ruta_actual($o['ruta'] ?? null);
    [$grupoActual, $itemActual] = erp_marco_activo($menu, $rel);
    $ini = strtoupper(mb_substr((string)($u['nombre'] ?? 'AD'), 0, 2));
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); // mismo código CSRF que usa el ERP
    [$sedes, $sedeActual, $restringida] = erp_marco_sedes($o['pdo'], $u);
    $e = 'erp_marco_e';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($titulo) ?> · Bayer-Pucchún ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php if ($embebido): ?>
<?php foreach ((array)($o['css'] ?? []) as $hoja): ?><link rel="stylesheet" href="<?= $e($hoja) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/erp_marco.css?v=<?= @filemtime(__DIR__ . '/../assets/css/erp_marco.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css?v=6">
<?php endif; ?>
<?= $o['head'] ?? '' ?>
</head>
<body class="bento-layout<?= $embebido ? ' erp-embebido' : '' ?>">

<header class="top-nav erp-marco">
  <div class="top-nav-inner">
    <div class="nav-brand">
      <a href="<?= $base ?>/index.php" class="brand-link">
        <div class="brand-logo-wrap"><img src="<?= $base ?>/assets/img/bayer.png" alt="Bayer" onerror="this.style.display='none'"></div>
        <div class="brand-text"><span class="brand-title">BAYER · PUCCHÚN</span><span class="brand-tag">ERP</span></div>
      </a>
    </div>

    <nav class="nav-links" id="nav-links">
      <a class="nav-item <?= $grupoActual === '' ? 'activo' : '' ?>" href="<?= $base ?>/index.php">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        <span>Inicio</span>
      </a>
      <?php foreach ($menu as $grupo => $items): $act = $grupo === $grupoActual; ?>
        <div class="nav-dropdown <?= $act ? 'activo' : '' ?>">
          <button type="button" class="dropdown-trigger <?= $act ? 'activo' : '' ?>">
            <span><?= $e($grupo) ?></span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>
          <div class="dropdown-menu">
            <div class="dropdown-header"><?= $e($grupo) ?></div>
            <?php foreach ($items as $ruta => $nombre): ?>
              <?php if ($ruta[0] === '#'): ?>
                <div class="dropdown-subheader"><?= $e($nombre) ?></div>
              <?php else: ?>
                <a class="dropdown-item <?= $ruta === $itemActual ? 'item-activo' : '' ?>" href="<?= $base ?>/modulos/<?= $ruta ?>"><?= $e($nombre) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </nav>

    <div class="nav-user-area">
      <button type="button" class="nav-toggle-btn" id="nav-toggle-btn" aria-label="Abrir menú" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      <div class="system-status" title="Conexión con base de datos activa"><span class="status-pulse"></span><span class="status-label">Online</span></div>
      <form method="post" action="<?= $base ?>/cambiar_sede.php" class="sede-selector" title="Sede activa: filtra la operación y se informa a todos los módulos">
        <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
        <input type="hidden" name="volver" value="<?= $e($_SERVER['REQUEST_URI'] ?? '') ?>">
        <select name="sede" class="sede-select" onchange="this.form.submit()" aria-label="Sede activa">
          <?php if (!$restringida): ?><option value="" <?= $sedeActual === null ? 'selected' : '' ?>>Todas las sedes</option><?php endif; ?>
          <?php foreach ($sedes as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sedeActual === (int)$s['id'] ? 'selected' : '' ?>><?= $e($s['nombre']) ?></option><?php endforeach; ?>
        </select>
      </form>
      <div class="user-pill">
        <div class="avatar-circle"><?= $e($ini) ?></div>
        <div class="user-info"><span class="user-name"><?= $e($u['nombre'] ?? 'Usuario') ?></span><span class="user-role"><?= $e($u['rol'] ?? '') ?></span></div>
      </div>
      <a class="logout-btn" href="<?= $base ?>/logout.php" title="Cerrar sesión">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
    </div>
  </div>
</header>

<main class="main-container">
  <div class="page-header-bar erp-marco">
    <div class="breadcrumb-trail">
      <a href="<?= $base ?>/index.php">ERP</a>
      <?php if ($grupoActual): ?><span class="sep">/</span><span class="group-crumb"><?= $e($grupoActual) ?></span><?php endif; ?>
      <span class="sep">/</span><span class="current-crumb"><?= $e($titulo) ?></span>
    </div>
  </div>
<?php if (!$embebido): ?>
  <section class="page-content">
    <div class="page-headline">
      <span class="module-chip"><?= $e($grupoActual ?: 'Panel Central') ?></span>
      <h1 class="page-title"><?= $e($titulo) ?></h1>
      <p class="page-subtitle"><?= $e($o['subtitulo'] ?? 'Administra los datos maestros de negocio compartidos con el ecosistema Bayer.') ?></p>
    </div>
<?php else: ?>
  <section class="erp-modulo">
<?php endif;
}

/** Cierra la página de un módulo embebido (las páginas del ERP usan core/layout_footer.php). */
function erp_marco_pie(): void {
    ?>
  </section>
</main>
<script>
// Menú del ERP en pantallas angostas (mismo comportamiento que assets/js/app.js)
(function () {
  const btn = document.getElementById('nav-toggle-btn'), nav = document.getElementById('nav-links');
  if (!btn || !nav) return;
  btn.addEventListener('click', (e) => { e.stopPropagation(); btn.setAttribute('aria-expanded', nav.classList.toggle('open') ? 'true' : 'false'); });
  nav.querySelectorAll('.dropdown-trigger').forEach((t) => t.addEventListener('click', (e) => {
    if (window.innerWidth > 1440) return;
    e.preventDefault(); e.stopPropagation();
    const p = t.closest('.nav-dropdown'), abierto = p.classList.contains('open');
    nav.querySelectorAll('.nav-dropdown.open').forEach((d) => d.classList.remove('open'));
    if (!abierto) p.classList.add('open');
  }));
  document.addEventListener('click', (e) => {
    if (nav.classList.contains('open') && !nav.contains(e.target) && !btn.contains(e.target)) { nav.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
  });
})();
</script>
</body>
</html>
<?php
}

}

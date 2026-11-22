<?php
/**
 * Auditoría y trazabilidad unificada (G4 · Inf. 6 y 8). Une en una sola consulta las bitácoras de toda la plataforma:
 *  - auditoria_acciones (plataforma, maestros, caja, Data Hub)
 *  - comercial_auditoria (cotizaciones, pedidos, ventas, cobranzas)
 *  - g7_auditoria (compras, stock, despachos, pagos)
 *  - facturacion_eventos (ciclo tributario de cada comprobante)
 */
require_once __DIR__ . '/../../core/crud_base.php';
requerir_login();
if (!in_array(usuario_actual()['rol'] ?? '', ['administrador', 'supervisor', 'gerencia'], true)) { http_response_code(403); exit('Sin acceso a la auditoría.'); }

$modulo = $_GET['modulo'] ?? '';
// Fechas por defecto según el reloj de la base (los registros se guardan con NOW() de MySQL)
$hoy = (string)$pdo->query('SELECT CURDATE()')->fetchColumn();
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-d', strtotime($hoy . ' -30 days'));
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : $hoy;
$q = trim((string)($_GET['q'] ?? ''));

// Cada bitácora usa su propia collation: todo el texto se normaliza a utf8mb4_unicode_ci para poder unirlas
$t = fn(string $expr) => "CONVERT($expr USING utf8mb4) COLLATE utf8mb4_unicode_ci";
$sql = "SELECT * FROM (
    SELECT a.fecha_hora fecha, {$t("CONCAT('Plataforma · ', a.modulo)")} modulo, {$t('a.accion')} accion, a.entidad_id referencia,
           {$t('u.nombre')} usuario, {$t('LEFT(CAST(a.metadata_json AS CHAR), 300)')} detalle, {$t('a.resultado')} resultado
      FROM auditoria_acciones a LEFT JOIN usuarios u ON u.id = a.usuario_id
    UNION ALL
    SELECT c.fecha, {$t("'Comercial'")}, {$t("CONCAT(c.entidad, ' · ', c.accion)")}, c.entidad_id, {$t('u.nombre')},
           {$t("CONCAT_WS(' ', CONCAT(COALESCE(c.estado_anterior, '—'), ' → ', COALESCE(c.estado_nuevo, '—')), c.motivo)")}, {$t("'ok'")}
      FROM comercial_auditoria c LEFT JOIN usuarios u ON u.id = c.usuario_id
    UNION ALL
    SELECT g.fecha, {$t("'Logística'")}, {$t('g.accion')}, NULL, {$t('u.nombre')}, {$t('LEFT(g.detalle, 300)')}, {$t("'ok'")}
      FROM g7_auditoria g LEFT JOIN usuarios u ON u.id = g.usuario_id
    UNION ALL
    SELECT e.creado_en, {$t("'Facturación'")}, {$t("CONCAT('comprobante · ', e.estado)")}, e.comprobante_id,
           {$t("JSON_UNQUOTE(JSON_EXTRACT(e.metadatos, '$.usuario_nombre'))")}, {$t('LEFT(e.detalle, 300)')}, {$t("'ok'")}
      FROM facturacion_eventos e
) t WHERE t.fecha >= ? AND t.fecha < DATE_ADD(?, INTERVAL 1 DAY)";
$args = [$desde, $hasta];
if ($modulo !== '') { $sql .= ' AND t.modulo LIKE ?'; $args[] = $modulo . '%'; }
if ($q !== '') { $sql .= ' AND (t.accion LIKE ? OR t.detalle LIKE ? OR t.usuario LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
$sql .= ' ORDER BY t.fecha DESC LIMIT 300';
$st = $pdo->prepare($sql);
$st->execute($args);
$filas = $st->fetchAll();

$titulo = 'Auditoría';
require __DIR__ . '/../../core/layout_header.php';
?>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:20px;padding:18px 22px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <label class="form-group"><span class="field-label">Módulo</span><select class="custom-select" name="modulo"><option value="">Todos</option><?php foreach (['Plataforma', 'Comercial', 'Logística', 'Facturación'] as $m): ?><option <?= $modulo === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></select></label>
    <label class="form-group"><span class="field-label">Desde</span><input class="custom-input" type="date" name="desde" value="<?= h($desde) ?>"></label>
    <label class="form-group"><span class="field-label">Hasta</span><input class="custom-input" type="date" name="hasta" value="<?= h($hasta) ?>"></label>
    <label class="form-group"><span class="field-label">Buscar</span><input class="custom-input" name="q" value="<?= h($q) ?>" placeholder="Acción, usuario o detalle"></label>
    <button class="btn btn-sec">Filtrar</button>
  </form>
  <p class="small-note" style="margin:10px 0 0">Une la bitácora de la plataforma (maestros, caja, sesiones), Comercial, Logística y Facturación. Se muestran hasta 300 registros.</p>
</div>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden"><div style="overflow:auto">
<table class="detalle-tabla" style="width:100%;min-width:900px;border-collapse:collapse"><thead><tr><th>Fecha</th><th>Módulo</th><th>Acción</th><th>Ref.</th><th>Usuario</th><th>Detalle</th><th>Resultado</th></tr></thead><tbody>
<?php foreach ($filas as $f): ?><tr><td><?= h($f['fecha']) ?></td><td><?= h($f['modulo']) ?></td><td><?= h($f['accion']) ?></td><td><?= h($f['referencia'] ?? '') ?></td><td><?= h($f['usuario'] ?? '—') ?></td><td style="max-width:420px;word-break:break-word" class="small-note"><?= h($f['detalle'] ?? '') ?></td><td><?= h($f['resultado'] ?? '') ?></td></tr><?php endforeach; ?>
<?php if (!$filas): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#64748b">Sin registros en el periodo.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../../core/layout_footer.php';

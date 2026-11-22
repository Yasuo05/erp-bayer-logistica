<?php
require_once __DIR__ . '/../../core/crud_base.php';
requerir_login();
if (!puede('ver')) { http_response_code(403); exit('Sin acceso.'); }

function caja_url() { return BASE_URL . '/modulos/comercial/cajas.php'; }
function caja_flash($t, $m) { $_SESSION['caja_flash'] = [$t, $m]; }
function caja_importe($v, string $campo, bool $permitirCero = false): float {
    $n = is_numeric(str_replace(',', '.', (string)$v)) ? round((float)str_replace(',', '.', (string)$v), 2) : -1;
    if ($n < 0 || (!$permitirCero && $n <= 0) || $n > 999999999) throw new RuntimeException("$campo: indica un importe válido.");
    return $n;
}
/** Efectivo que debería haber en la sesión: fondo inicial + ingresos - egresos. */
function caja_saldo(PDO $pdo, int $sesionId): float {
    $st = $pdo->prepare("SELECT s.fondo_inicial
        + COALESCE((SELECT SUM(CASE WHEN m.tipo = 'ingreso' THEN m.importe ELSE -m.importe END) FROM caja_movimientos m WHERE m.sesion_id = s.id), 0)
        FROM caja_sesiones s WHERE s.id = ?");
    $st->execute([$sesionId]);
    return round((float)$st->fetchColumn(), 2);
}
function caja_sesion_abierta(PDO $pdo, int $sesionId): array {
    $st = $pdo->prepare('SELECT * FROM caja_sesiones WHERE id = ? FOR UPDATE');
    $st->execute([$sesionId]);
    $s = $st->fetch();
    if (!$s || $s['estado'] !== 'abierta') throw new RuntimeException('La sesión de caja no está abierta.');
    return $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    try {
        $a = $_POST['accion'] ?? '';
        $usuario = (int)usuario_actual()['id'];
        if ($a === 'crear') {
            if (!puede('crear')) throw new RuntimeException('Sin permiso para crear cajas.');
            $s = (int)($_POST['sucursal_id'] ?? 0);
            $cod = trim($_POST['codigo'] ?? '');
            $nom = trim($_POST['nombre'] ?? '');
            $mon = $_POST['moneda'] ?? 'PEN';
            if (!$s || $cod === '' || $nom === '' || !in_array($mon, ['PEN', 'USD'], true)) throw new RuntimeException('Completa los datos de caja.');
            sede_exigir($s);
            $pdo->prepare('INSERT INTO cajas(sucursal_id,codigo,nombre,moneda,estado,creado_por) VALUES(?,?,?,?,"activa",?)')->execute([$s, $cod, $nom, $mon, $usuario]);
            auditar('caja', 'crear_caja', (int)$pdo->lastInsertId(), ['codigo' => $cod, 'sucursal_id' => $s]);
            caja_flash('ok', 'Caja creada.');
        } elseif ($a === 'abrir') {
            if (!puede('editar')) throw new RuntimeException('Sin permiso para abrir caja.');
            $id = (int)$_POST['caja_id'];
            $fondo = caja_importe($_POST['fondo_inicial'] ?? 0, 'Fondo inicial', true);
            $st = $pdo->prepare('SELECT COUNT(*) FROM caja_sesiones WHERE caja_id = ? AND estado = "abierta"');
            $st->execute([$id]);
            if ($st->fetchColumn() > 0) throw new RuntimeException('Esta caja ya tiene una sesión abierta.');
            $pdo->prepare('INSERT INTO caja_sesiones(caja_id,responsable_id,apertura_en,fondo_inicial,estado) VALUES(?,?,NOW(),?,"abierta")')->execute([$id, $usuario, $fondo]);
            auditar('caja', 'abrir_caja', (int)$pdo->lastInsertId(), ['caja_id' => $id, 'fondo_inicial' => $fondo]);
            caja_flash('ok', 'Sesión de caja abierta.');
        } elseif ($a === 'egreso') {
            if (!puede('editar')) throw new RuntimeException('Sin permiso para registrar egresos.');
            $sesionId = (int)($_POST['sesion_id'] ?? 0);
            $importe = caja_importe($_POST['importe'] ?? 0, 'Importe');
            $concepto = trim($_POST['concepto'] ?? '');
            if ($concepto === '' || mb_strlen($concepto) > 255) throw new RuntimeException('Indica el concepto del egreso (máx. 255 caracteres).');
            $pdo->beginTransaction();
            caja_sesion_abierta($pdo, $sesionId);
            $saldo = caja_saldo($pdo, $sesionId);
            if ($importe > $saldo + 0.001) throw new RuntimeException('El egreso supera el efectivo disponible en caja (' . number_format($saldo, 2) . ').');
            $pdo->prepare('INSERT INTO caja_movimientos(sesion_id,tipo,fecha,importe,concepto,creado_por) VALUES(?,"egreso",NOW(),?,?,?)')->execute([$sesionId, $importe, $concepto, $usuario]);
            $pdo->commit();
            auditar('caja', 'egreso', (int)$pdo->lastInsertId(), ['sesion_id' => $sesionId, 'importe' => $importe, 'concepto' => $concepto]);
            caja_flash('ok', 'Egreso registrado.');
        } elseif ($a === 'cerrar') {
            if (!puede('editar')) throw new RuntimeException('Sin permiso para cerrar caja.');
            $sesionId = (int)($_POST['sesion_id'] ?? 0);
            $contado = caja_importe($_POST['efectivo_contado'] ?? '', 'Efectivo contado', true);
            $obs = trim($_POST['observaciones'] ?? '');
            $pdo->beginTransaction();
            caja_sesion_abierta($pdo, $sesionId);
            $esperado = caja_saldo($pdo, $sesionId);
            if (abs($contado - $esperado) > 0.004 && $obs === '') throw new RuntimeException('Hay una diferencia de ' . number_format($contado - $esperado, 2) . ' en el arqueo: explica el motivo en observaciones.');
            $pdo->prepare('UPDATE caja_sesiones SET estado="cerrada", cierre_en=NOW(), cerrado_por=?, saldo_esperado=?, efectivo_contado=?, observaciones=? WHERE id=?')
                ->execute([$usuario, $esperado, $contado, $obs ?: null, $sesionId]);
            $pdo->commit();
            auditar('caja', 'cerrar_caja', $sesionId, ['esperado' => $esperado, 'contado' => $contado, 'diferencia' => round($contado - $esperado, 2), 'observaciones' => $obs]);
            caja_flash('ok', 'Caja cerrada. Esperado ' . number_format($esperado, 2) . ' · contado ' . number_format($contado, 2) . ' · diferencia ' . number_format($contado - $esperado, 2) . '.');
        } else {
            throw new RuntimeException('Acción no válida.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        caja_flash('err', $e instanceof PDOException ? 'No se pudo completar la operación.' : $e->getMessage());
    }
    header('Location: ' . caja_url());
    exit;
}

$titulo = 'Caja';
require __DIR__ . '/../../core/layout_header.php';
$f = $_SESSION['caja_flash'] ?? null;
unset($_SESSION['caja_flash']);
if ($f): ?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($f[1])?>,<?=json_encode($f[0])?>));</script><?php endif;
$s = array_values(array_filter(sedes_permitidas(), fn($x) => sede_activa() === null || (int)$x['id'] === sede_activa())); // sedes del usuario (G4)
[$sedeSql, $sedeArg] = sede_filtro('c.sucursal_id');
$c = $pdo->prepare('SELECT c.*, s.nombre sucursal, (SELECT estado FROM caja_sesiones x WHERE x.caja_id=c.id ORDER BY x.id DESC LIMIT 1) sesion FROM cajas c JOIN sucursales s ON s.id=c.sucursal_id AND 1=1' . $sedeSql . ' ORDER BY c.id DESC'); $c->execute($sedeArg); $c = $c->fetchAll();
$abiertas = $pdo->prepare('SELECT cs.*, c.codigo, c.nombre caja, c.moneda, c.sucursal_id, u.nombre responsable FROM caja_sesiones cs JOIN cajas c ON c.id=cs.caja_id JOIN usuarios u ON u.id=cs.responsable_id WHERE cs.estado="abierta" AND 1=1' . $sedeSql . ' ORDER BY cs.id'); $abiertas->execute($sedeArg); $abiertas = $abiertas->fetchAll();
$cerradas = $pdo->prepare('SELECT cs.*, c.codigo, c.moneda FROM caja_sesiones cs JOIN cajas c ON c.id=cs.caja_id WHERE cs.estado="cerrada" AND 1=1' . $sedeSql . ' ORDER BY cs.cierre_en DESC LIMIT 10'); $cerradas->execute($sedeArg); $cerradas = $cerradas->fetchAll();
$card = 'background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:20px';
?>
<div class="comercial-card" style="<?=$card?>"><div style="padding:22px"><?php if (puede('crear')): ?><form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="crear"><label class="form-group"><span class="field-label">Sucursal</span><select class="custom-select" name="sucursal_id"><?php foreach ($s as $x): ?><option value="<?=$x['id']?>"><?=h($x['codigo'] . ' · ' . $x['nombre'])?></option><?php endforeach; ?></select></label><label class="form-group"><span class="field-label">Código</span><input class="custom-input" name="codigo" required></label><label class="form-group"><span class="field-label">Nombre</span><input class="custom-input" name="nombre" required></label><label class="form-group"><span class="field-label">Moneda</span><select class="custom-select" name="moneda"><option>PEN</option><option>USD</option></select></label><button class="btn btn-primary-action">Crear caja</button></form><?php endif; ?></div></div>

<div class="comercial-card" style="<?=$card?>;overflow:hidden"><table class="detalle-tabla" style="width:100%;border-collapse:collapse"><thead><tr><th>Código</th><th>Nombre</th><th>Sucursal</th><th>Moneda</th><th>Estado</th><th>Sesión</th><th></th></tr></thead><tbody><?php foreach ($c as $x): ?><tr><td><?=h($x['codigo'])?></td><td><?=h($x['nombre'])?></td><td><?=h($x['sucursal'])?></td><td><?=h($x['moneda'])?></td><td><?=h($x['estado'])?></td><td><?=h($x['sesion'] ?? 'sin sesión')?></td><td><?php if ($x['estado'] === 'activa' && $x['sesion'] !== 'abierta' && puede('editar')): ?><form method="post" style="display:flex;gap:5px"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="abrir"><input type="hidden" name="caja_id" value="<?=$x['id']?>"><input class="custom-input" style="width:100px" name="fondo_inicial" type="number" step="0.01" min="0" value="0" title="Fondo inicial"><button class="btn btn-sec">Abrir</button></form><?php endif; ?></td></tr><?php endforeach; if (!$c): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#64748b">Aún no hay cajas.</td></tr><?php endif; ?></tbody></table></div>

<?php foreach ($abiertas as $ses):
    $mov = $pdo->prepare('SELECT m.*, u.nombre usuario FROM caja_movimientos m JOIN usuarios u ON u.id=m.creado_por WHERE m.sesion_id=? ORDER BY m.fecha, m.id');
    $mov->execute([$ses['id']]);
    $movs = $mov->fetchAll();
    $ingresos = array_sum(array_map(fn($m) => $m['tipo'] === 'ingreso' ? (float)$m['importe'] : 0, $movs));
    $egresos = array_sum(array_map(fn($m) => $m['tipo'] === 'egreso' ? (float)$m['importe'] : 0, $movs));
    $esperado = round((float)$ses['fondo_inicial'] + $ingresos - $egresos, 2);
    // Cobros de la sucursal durante la sesión por medio de pago (solo el efectivo entra físicamente a caja)
    $med = $pdo->prepare('SELECT medio_pago, COUNT(*) n, SUM(importe) total FROM cobranzas WHERE sucursal_id=? AND estado="confirmada" AND fecha >= ? GROUP BY medio_pago');
    $med->execute([$ses['sucursal_id'], $ses['apertura_en']]);
    $medios = $med->fetchAll();
?>
<div class="comercial-card" style="<?=$card?>;overflow:hidden">
  <div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px">Sesión abierta · <?=h($ses['codigo'] . ' ' . $ses['caja'])?></h2><p class="small-note" style="margin:4px 0 0">Abierta el <?=h($ses['apertura_en'])?> por <?=h($ses['responsable'])?></p></div>
  <div style="padding:18px 22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
    <?php foreach ([['Fondo inicial', $ses['fondo_inicial']], ['Ingresos', $ingresos], ['Egresos', $egresos], ['Efectivo esperado', $esperado]] as [$etq, $val]): ?>
      <div><div class="small-note"><?=h($etq)?></div><div style="font-size:20px;font-weight:800;color:#10384f"><?=h($ses['moneda'])?> <?=number_format($val, 2)?></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($medios): ?><div style="padding:0 22px 12px" class="small-note">Cobros de la sucursal en esta sesión: <?php foreach ($medios as $m): ?><strong><?=h($m['medio_pago'])?></strong> <?=number_format($m['total'], 2)?> (<?=$m['n']?>) &nbsp; <?php endforeach; ?></div><?php endif; ?>
  <div style="overflow:auto"><table class="detalle-tabla" style="width:100%;border-collapse:collapse"><thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Usuario</th><th>Importe</th></tr></thead><tbody>
  <?php foreach ($movs as $m): ?><tr><td><?=h($m['fecha'])?></td><td><?=h($m['tipo'])?></td><td><?=h($m['concepto'])?></td><td><?=h($m['usuario'])?></td><td style="color:<?=$m['tipo'] === 'egreso' ? '#b91c1c' : '#15803d'?>"><?=$m['tipo'] === 'egreso' ? '−' : '+'?> <?=number_format($m['importe'], 2)?></td></tr><?php endforeach;
  if (!$movs): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:#64748b">Sin movimientos en esta sesión.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if (puede('editar')): ?>
  <div style="padding:18px 22px;display:flex;gap:28px;flex-wrap:wrap;border-top:1px solid #e2e8f0">
    <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="egreso"><input type="hidden" name="sesion_id" value="<?=$ses['id']?>">
      <label class="form-group"><span class="field-label">Egreso: concepto</span><input class="custom-input" name="concepto" maxlength="255" required></label>
      <label class="form-group"><span class="field-label">Importe</span><input class="custom-input" style="width:110px" name="importe" type="number" step="0.01" min="0.01" required></label>
      <button class="btn btn-sec">Registrar egreso</button></form>
    <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="cerrar"><input type="hidden" name="sesion_id" value="<?=$ses['id']?>">
      <label class="form-group"><span class="field-label">Arqueo: efectivo contado</span><input class="custom-input" style="width:130px" name="efectivo_contado" type="number" step="0.01" min="0" required></label>
      <label class="form-group"><span class="field-label">Observaciones</span><input class="custom-input" name="observaciones" placeholder="Obligatorio si hay diferencia"></label>
      <button class="btn btn-primary-action">Cerrar caja</button></form>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($cerradas): ?>
<div class="comercial-card" style="<?=$card?>;overflow:hidden"><div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px">Últimos cierres</h2></div><div style="overflow:auto"><table class="detalle-tabla" style="width:100%;border-collapse:collapse"><thead><tr><th>Caja</th><th>Apertura</th><th>Cierre</th><th>Esperado</th><th>Contado</th><th>Diferencia</th><th>Observaciones</th></tr></thead><tbody>
<?php foreach ($cerradas as $x): ?><tr><td><?=h($x['codigo'])?></td><td><?=h($x['apertura_en'])?></td><td><?=h($x['cierre_en'])?></td><td><?=number_format($x['saldo_esperado'], 2)?></td><td><?=number_format($x['efectivo_contado'], 2)?></td><td style="font-weight:800;color:<?=abs((float)$x['diferencia']) > 0.004 ? '#b91c1c' : '#15803d'?>"><?=number_format($x['diferencia'], 2)?></td><td><?=h($x['observaciones'] ?? '')?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../../core/layout_footer.php';

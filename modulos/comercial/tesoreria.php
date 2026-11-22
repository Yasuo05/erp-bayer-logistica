<?php
require_once __DIR__ . '/../../core/crud_base.php';
require_once __DIR__ . '/tesoreria_lib.php';
requerir_login();
if (!puede('ver')) { http_response_code(403); exit('Sin acceso.'); }

function tes_url() { return BASE_URL . '/modulos/comercial/tesoreria.php'; }
function tes_flash($t, $m) { $_SESSION['tes_flash'] = [$t, $m]; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    try {
        if (($_POST['accion'] ?? '') !== 'crear_cuenta') throw new RuntimeException('Acción no válida.');
        if (!puede('crear')) throw new RuntimeException('Sin permiso para registrar cuentas.');
        $suc = (int)($_POST['sucursal_id'] ?? 0);
        sede_exigir($suc);
        $codigo = trim($_POST['codigo'] ?? ''); $nombre = trim($_POST['nombre'] ?? ''); $banco = trim($_POST['banco'] ?? ''); $numero = trim($_POST['numero_cuenta'] ?? '');
        $moneda = $_POST['moneda'] ?? 'PEN';
        $inicial = round((float)str_replace(',', '.', $_POST['saldo_inicial'] ?? '0'), 2);
        if ($codigo === '' || $nombre === '' || $banco === '' || $numero === '' || !in_array($moneda, ['PEN', 'USD'], true) || $inicial < 0) throw new RuntimeException('Completa los datos de la cuenta (el saldo inicial no puede ser negativo).');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO tesoreria_cuentas (sucursal_id, codigo, nombre, tipo, banco, numero_cuenta, moneda, estado, creado_por) VALUES (?,?,?,'bancaria',?,?,?,'activa',?)")
            ->execute([$suc, $codigo, $nombre, $banco, $numero, $moneda, usuario_actual()['id']]);
        $cuentaId = (int)$pdo->lastInsertId();
        if ($inicial > 0) tes_movimiento($pdo, ['id' => $cuentaId, 'moneda' => $moneda], 'ingreso', 'apertura', $inicial, 'APERTURA', 'Saldo inicial de la cuenta');
        $pdo->commit();
        auditar('tesoreria', 'crear_cuenta', $cuentaId, ['codigo' => $codigo, 'sucursal_id' => $suc, 'saldo_inicial' => $inicial]);
        tes_flash('ok', 'Cuenta registrada.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        tes_flash('err', $e instanceof PDOException ? ($e->getCode() == 23000 ? 'Ya existe una cuenta con ese código o número.' : 'No se pudo registrar la cuenta.') : $e->getMessage());
    }
    header('Location: ' . tes_url()); exit;
}

$titulo = 'Tesorería';
require __DIR__ . '/../../core/layout_header.php';
$f = $_SESSION['tes_flash'] ?? null; unset($_SESSION['tes_flash']);
if ($f): ?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($f[1])?>,<?=json_encode($f[0])?>));</script><?php endif;
[$sedeSql, $sedeArg] = sede_filtro('t.sucursal_id');
$st = $pdo->prepare("SELECT t.*, s.nombre sede,
        COALESCE((SELECT SUM(CASE m.tipo WHEN 'ingreso' THEN m.importe ELSE -m.importe END) FROM tesoreria_movimientos m WHERE m.cuenta_id = t.id AND m.estado = 'confirmado'), 0) saldo
    FROM tesoreria_cuentas t JOIN sucursales s ON s.id = t.sucursal_id WHERE 1=1{$sedeSql} ORDER BY s.nombre, t.codigo");
$st->execute($sedeArg); $cuentas = $st->fetchAll();
$st = $pdo->prepare("SELECT m.*, t.codigo cuenta, u.nombre usuario FROM tesoreria_movimientos m JOIN tesoreria_cuentas t ON t.id = m.cuenta_id LEFT JOIN usuarios u ON u.id = m.creado_por WHERE 1=1{$sedeSql} ORDER BY m.id DESC LIMIT 100");
$st->execute($sedeArg); $movs = $st->fetchAll();
$sedes = array_values(array_filter(sedes_permitidas(), fn($x) => sede_activa() === null || (int)$x['id'] === sede_activa()));
$card = 'background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:20px';
?>
<?php if (puede('crear')): ?>
<div class="comercial-card" style="<?=$card?>;padding:22px"><form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="crear_cuenta">
  <label class="form-group"><span class="field-label">Sede</span><select class="custom-select" name="sucursal_id"><?php foreach ($sedes as $x): ?><option value="<?=$x['id']?>"><?=h($x['nombre'])?></option><?php endforeach; ?></select></label>
  <label class="form-group"><span class="field-label">Código</span><input class="custom-input" name="codigo" required maxlength="20" style="width:120px"></label>
  <label class="form-group"><span class="field-label">Nombre</span><input class="custom-input" name="nombre" required maxlength="120" placeholder="Cuenta corriente soles"></label>
  <label class="form-group"><span class="field-label">Banco</span><input class="custom-input" name="banco" required maxlength="80" style="width:140px"></label>
  <label class="form-group"><span class="field-label">N.º de cuenta</span><input class="custom-input" name="numero_cuenta" required maxlength="34"></label>
  <label class="form-group"><span class="field-label">Moneda</span><select class="custom-select" name="moneda"><option>PEN</option><option>USD</option></select></label>
  <label class="form-group"><span class="field-label">Saldo inicial</span><input class="custom-input" name="saldo_inicial" type="number" step="0.01" min="0" value="0" style="width:120px"></label>
  <button class="btn btn-primary-action">Registrar cuenta</button></form>
  <p class="small-note" style="margin:10px 0 0">Las cobranzas por transferencia, depósito o tarjeta ingresan aquí al confirmarse; los pagos a proveedores por transferencia o depósito (Logística) salen de aquí. El efectivo se gestiona en Caja.</p></div>
<?php endif; ?>
<div class="comercial-card" style="<?=$card?>;overflow:hidden"><table class="detalle-tabla" style="width:100%;border-collapse:collapse"><thead><tr><th>Sede</th><th>Código</th><th>Cuenta</th><th>Banco</th><th>N.º</th><th>Moneda</th><th>Saldo</th></tr></thead><tbody>
<?php foreach ($cuentas as $c): ?><tr><td><?=h($c['sede'])?></td><td><?=h($c['codigo'])?></td><td><?=h($c['nombre'])?></td><td><?=h($c['banco'])?></td><td><?=h($c['numero_cuenta'])?></td><td><?=h($c['moneda'])?></td><td><strong><?=number_format($c['saldo'], 2)?></strong></td></tr><?php endforeach; ?>
<?php if (!$cuentas): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:#64748b">Aún no hay cuentas bancarias.</td></tr><?php endif; ?></tbody></table></div>
<div class="comercial-card" style="<?=$card?>;overflow:hidden"><div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px">Movimientos</h2></div><div style="overflow:auto"><table class="detalle-tabla" style="width:100%;min-width:820px;border-collapse:collapse"><thead><tr><th>Fecha</th><th>Cuenta</th><th>Origen</th><th>Referencia</th><th>Concepto</th><th>Usuario</th><th>Importe</th></tr></thead><tbody>
<?php foreach ($movs as $m): ?><tr><td><?=h($m['fecha'])?></td><td><?=h($m['cuenta'])?></td><td><?=h(str_replace('_', ' ', $m['origen']))?></td><td><?=h($m['referencia'])?></td><td><?=h($m['concepto'])?></td><td><?=h($m['usuario'] ?? '')?></td><td style="color:<?=$m['tipo'] === 'egreso' ? '#b91c1c' : '#15803d'?>"><?=$m['tipo'] === 'egreso' ? '−' : '+'?> <?=number_format($m['importe'], 2)?></td></tr><?php endforeach; ?>
<?php if (!$movs): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:#64748b">Sin movimientos.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../../core/layout_footer.php';

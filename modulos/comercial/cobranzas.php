<?php
require_once __DIR__ . '/../../core/crud_base.php';
require_once __DIR__ . '/cxc_lib.php';
require_once __DIR__ . '/tesoreria_lib.php';
requerir_login();
if (!puede('ver')) { http_response_code(403); exit('Sin acceso.'); }

function cob_url() { return BASE_URL . '/modulos/comercial/cobranzas.php'; }
function cob_flash($t, $m) { $_SESSION['cob_flash'] = [$t, $m]; }
function cob_numero(PDO $p, $s) {
    $st = $p->prepare('SELECT id,serie,ultimo_numero FROM comercial_correlativos WHERE sucursal_id=? AND documento="cobranza" ORDER BY id LIMIT 1 FOR UPDATE');
    $st->execute([$s]);
    $r = $st->fetch();
    if (!$r) {
        $serie = 'COB' . str_pad($s, 3, '0', STR_PAD_LEFT);
        $p->prepare('INSERT INTO comercial_correlativos(sucursal_id,documento,serie,ultimo_numero) VALUES(?,"cobranza",?,1)')->execute([$s, $serie]);
        return $serie . '-00000001';
    }
    $n = $r['ultimo_numero'] + 1;
    $p->prepare('UPDATE comercial_correlativos SET ultimo_numero=? WHERE id=?')->execute([$n, $r['id']]);
    return $r['serie'] . '-' . str_pad($n, 8, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    try {
        $accion = $_POST['accion'] ?? '';
        $usuario = (int)usuario_actual()['id'];
        if ($accion === 'confirmar') {
            if (!puede('editar')) throw new RuntimeException('Sin permiso para confirmar cobranzas.');
            $id = (int)$_POST['id'];
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM cobranzas WHERE id=? FOR UPDATE');
            $st->execute([$id]);
            $c = $st->fetch();
            if (!$c || $c['estado'] !== 'borrador') throw new RuntimeException('Solo se confirman cobranzas en borrador.');
            if ($c['medio_pago'] === 'efectivo') {
                $st = $pdo->prepare('SELECT cs.id FROM caja_sesiones cs JOIN cajas ca ON ca.id=cs.caja_id WHERE ca.sucursal_id=? AND cs.estado="abierta" ORDER BY cs.id DESC LIMIT 1 FOR UPDATE');
                $st->execute([$c['sucursal_id']]);
                $sesion = $st->fetchColumn();
                if (!$sesion) throw new RuntimeException('Abre una caja de la sucursal antes de confirmar un cobro en efectivo.');
                $pdo->prepare('INSERT INTO caja_movimientos(sesion_id,tipo,fecha,importe,concepto,cobranza_id,creado_por) VALUES(?,"ingreso",NOW(),?,?,?,?)')
                    ->execute([$sesion, $c['importe'], 'Cobranza ' . $c['numero'], $id, $usuario]);
            }
            if ($c['medio_pago'] !== 'efectivo') {
                // Transferencia, depósito o tarjeta: el dinero entra a la cuenta bancaria de la sede (Tesorería)
                $cuenta = tes_cuenta_sede($pdo, (int)$c['sucursal_id'], $c['moneda']);
                tes_movimiento($pdo, $cuenta, 'ingreso', 'cobranza', (float)$c['importe'], $c['numero'], 'Cobranza ' . $c['numero'] . ($c['referencia'] ? ' · ' . $c['referencia'] : ''), ['cobranza_id' => $id]);
            }
            $pdo->prepare('UPDATE cobranzas SET estado="confirmada" WHERE id=?')->execute([$id]);
            $pdo->prepare('INSERT INTO comercial_auditoria(usuario_id,entidad,entidad_id,accion,estado_anterior,estado_nuevo) VALUES(?,"cobranza",?,"confirmada","borrador","confirmada")')->execute([$usuario, $id]);
            $pdo->commit();
            cob_flash('ok', 'Cobranza confirmada.');
        } elseif ($accion === 'anular') {
            if (!puede('editar')) throw new RuntimeException('Sin permiso para anular cobranzas.');
            $id = (int)($_POST['id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            if ($motivo === '' || mb_strlen($motivo) > 255) throw new RuntimeException('Indica el motivo de la anulación (máx. 255 caracteres).');
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM cobranzas WHERE id=? FOR UPDATE');
            $st->execute([$id]);
            $c = $st->fetch();
            if (!$c || $c['estado'] === 'anulada') throw new RuntimeException('La cobranza no existe o ya está anulada.');
            $reverso = '';
            if ($c['estado'] === 'confirmada' && $c['medio_pago'] === 'efectivo') {
                // El efectivo ya entró a caja: se devuelve con un egreso que revierte el ingreso, en la misma sesión abierta
                $st = $pdo->prepare('SELECT m.id, m.sesion_id, s.estado FROM caja_movimientos m JOIN caja_sesiones s ON s.id=m.sesion_id WHERE m.cobranza_id=? FOR UPDATE');
                $st->execute([$id]);
                $mov = $st->fetch();
                if ($mov) {
                    if ($mov['estado'] !== 'abierta') throw new RuntimeException('La caja donde ingresó este cobro ya se cerró: registra la devolución como egreso en la caja abierta.');
                    $st = $pdo->prepare("SELECT s.fondo_inicial + COALESCE(SUM(CASE WHEN m.tipo='ingreso' THEN m.importe ELSE -m.importe END),0) FROM caja_sesiones s LEFT JOIN caja_movimientos m ON m.sesion_id=s.id WHERE s.id=? GROUP BY s.id");
                    $st->execute([$mov['sesion_id']]);
                    if ((float)$st->fetchColumn() + 0.001 < (float)$c['importe']) throw new RuntimeException('La caja no tiene efectivo suficiente para devolver este cobro.');
                    $pdo->prepare('INSERT INTO caja_movimientos(sesion_id,tipo,fecha,importe,concepto,reversa_de_id,creado_por) VALUES(?,"egreso",NOW(),?,?,?,?)')
                        ->execute([$mov['sesion_id'], $c['importe'], 'Anulación de cobranza ' . $c['numero'], $mov['id'], $usuario]);
                    $reverso = ' Se registró el egreso de devolución en caja.';
                }
            }
            if ($c['estado'] === 'confirmada' && $c['medio_pago'] !== 'efectivo') {
                $st = $pdo->prepare("SELECT m.*, t.moneda cuenta_moneda FROM tesoreria_movimientos m JOIN tesoreria_cuentas t ON t.id = m.cuenta_id WHERE m.cobranza_id = ? AND m.estado = 'confirmado' FOR UPDATE");
                $st->execute([$id]);
                if ($ing = $st->fetch()) {
                    tes_movimiento($pdo, ['id' => $ing['cuenta_id'], 'moneda' => $ing['moneda']], 'egreso', 'reversion', (float)$ing['importe'], $c['numero'] . '-ANUL', 'Anulación de cobranza ' . $c['numero'] . ': ' . $motivo, ['reversa_de_id' => $ing['id']]);
                    $reverso = ' Se registró la reversión en Tesorería.';
                }
            }
            $pdo->prepare('UPDATE cobranzas SET estado="anulada", anulado_por=?, anulado_en=NOW(), motivo_anulacion=? WHERE id=?')->execute([$usuario, $motivo, $id]);
            $pdo->prepare('INSERT INTO comercial_auditoria(usuario_id,entidad,entidad_id,accion,estado_anterior,estado_nuevo,motivo) VALUES(?,"cobranza",?,"anulada",?,"anulada",?)')->execute([$usuario, $id, $c['estado'], $motivo]);
            $pdo->commit();
            cob_flash('ok', 'Cobranza ' . $c['numero'] . ' anulada; el saldo de la venta se restituyó.' . $reverso);
        } else {
            if (!puede('crear')) throw new RuntimeException('Sin permiso para registrar cobranzas.');
            $venta = (int)($_POST['venta_id'] ?? 0);
            $importe = round((float)str_replace(',', '.', $_POST['importe'] ?? '0'), 2);
            $medio = $_POST['medio_pago'] ?? '';
            if ($importe <= 0 || !in_array($medio, ['efectivo', 'transferencia', 'deposito', 'tarjeta'], true)) throw new RuntimeException('Completa importe y medio de pago.');
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM ventas WHERE id=? AND estado="confirmada" FOR UPDATE');
            $st->execute([$venta]);
            $v = $st->fetch();
            if (!$v) throw new RuntimeException('Selecciona una venta confirmada.');

            // Reparto del importe: contra la venta (contado) o contra sus cuotas, de la más antigua a la más reciente (crédito)
            $aplicaciones = [];
            if ($v['condicion_pago'] === 'credito') {
                $cuotas = cxc_cuotas_pendientes($pdo, $venta);
                if (!$cuotas) throw new RuntimeException('La venta a crédito no tiene una cuenta por cobrar activa.');
                // Las notas de crédito aceptadas reducen la deuda (las de débito la aumentan)
                $saldo = round(array_sum(array_map(fn($q) => (float)$q['pendiente'], $cuotas)) - cxc_notas_venta($pdo, $venta), 2);
                if ($importe > $saldo + 0.001) throw new RuntimeException('El importe supera el saldo de la cuenta por cobrar (' . number_format($saldo, 2) . ').');
                $resto = $importe;
                foreach ($cuotas as $q) {
                    if ($resto <= 0) break;
                    $aplicar = round(min($resto, (float)$q['pendiente']), 2);
                    if ($aplicar <= 0) continue;
                    $aplicaciones[] = ['cuota_id' => (int)$q['id'], 'importe' => $aplicar];
                    $resto = round($resto - $aplicar, 2);
                }
            } else {
                $pagado = $pdo->prepare('SELECT COALESCE(SUM(a.importe),0) FROM cobranza_aplicaciones a JOIN cobranzas c ON c.id=a.cobranza_id WHERE a.venta_contado_id=? AND c.estado<>"anulada"');
                $pagado->execute([$venta]);
                if ($importe > (float)$v['total'] - (float)$pagado->fetchColumn() - cxc_notas_venta($pdo, $venta) + 0.001) throw new RuntimeException('El importe supera el saldo de la venta.');
                $aplicaciones[] = ['venta_contado_id' => $venta, 'importe' => $importe];
            }

            sede_exigir((int)$v['sucursal_id']);
            // Comprobante vigente de la venta (G1): la cobranza queda vinculada a la factura o boleta que se paga
            $st = $pdo->prepare('SELECT id FROM facturacion_comprobantes WHERE venta_erp_vigente = ? LIMIT 1');
            $st->execute([$venta]);
            $comprobanteId = $st->fetchColumn() ?: null;
            $numero = cob_numero($pdo, $v['sucursal_id']);
            $clave = sprintf('%08s-%04s-%04s-%04s-%012s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
            $pdo->prepare('INSERT INTO cobranzas(sucursal_id,cliente_id,comprobante_id,numero,fecha,moneda,importe,medio_pago,clave_operacion,referencia,estado,creado_por) VALUES(?,?,?,?,NOW(),?,?,?,?,?,"borrador",?)')
                ->execute([$v['sucursal_id'], $v['cliente_id'], $comprobanteId, $numero, $v['moneda'], $importe, $medio, $clave, trim($_POST['referencia'] ?? '') ?: null, $usuario]);
            $id = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO cobranza_aplicaciones(cobranza_id,cuota_id,venta_contado_id,importe) VALUES(?,?,?,?)');
            foreach ($aplicaciones as $a) {
                $ins->execute([$id, $a['cuota_id'] ?? null, $a['venta_contado_id'] ?? null, $a['importe']]);
            }
            $pdo->prepare('INSERT INTO comercial_auditoria(usuario_id,entidad,entidad_id,accion,estado_nuevo) VALUES(?,"cobranza",?,"creada","borrador")')->execute([$usuario, $id]);
            $pdo->commit();
            cob_flash('ok', 'Cobranza ' . $numero . ' registrada como borrador.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        cob_flash('err', $e instanceof PDOException ? 'No se pudo registrar la cobranza.' : $e->getMessage());
    }
    header('Location: ' . cob_url());
    exit;
}

$titulo = 'Cobranzas';
require __DIR__ . '/../../core/layout_header.php';
$f = $_SESSION['cob_flash'] ?? null;
unset($_SESSION['cob_flash']);
if ($f): ?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($f[1])?>,<?=json_encode($f[0])?>));</script><?php endif;
// Solo se ofrecen ventas confirmadas que todavía tienen saldo
[$sedeSql, $sedeArg] = sede_filtro('v.sucursal_id'); // sede activa (G4)
$ventas = $pdo->prepare('SELECT * FROM (SELECT v.id, v.numero, v.total, v.moneda, v.condicion_pago, cl.razon_social, ' . cxc_sql_saldo_venta() . ' AS saldo
    FROM ventas v JOIN clientes cl ON cl.id = v.cliente_id WHERE v.estado = "confirmada"' . $sedeSql . ') x WHERE saldo > 0.004 ORDER BY id DESC');
$ventas->execute($sedeArg);
$ventas = $ventas->fetchAll();
[$sedeSqlC, $sedeArgC] = sede_filtro('c.sucursal_id');
// Cada cobranza muestra el comprobante (G1) que está pagando
$filas = $pdo->prepare("SELECT c.*, cl.razon_social cliente, CONCAT(fc.serie, '-', LPAD(fc.numero, 8, '0')) comprobante, fc.estado comprobante_estado
    FROM cobranzas c JOIN clientes cl ON cl.id=c.cliente_id LEFT JOIN facturacion_comprobantes fc ON fc.id = c.comprobante_id
    WHERE 1=1{$sedeSqlC} ORDER BY c.id DESC");
$filas->execute($sedeArgC);
$filas = $filas->fetchAll();
?>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:20px"><div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px">Registrar cobranza</h2></div><div style="padding:22px">
<?php if ($ventas && puede('crear')): ?>
<form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label class="form-group"><span class="field-label">Venta</span><select name="venta_id" class="custom-select"><?php foreach ($ventas as $v): ?><option value="<?=$v['id']?>"><?=h($v['numero'] . ' · ' . $v['razon_social'] . ' · ' . $v['condicion_pago'] . ' · saldo ' . $v['moneda'] . ' ' . number_format($v['saldo'], 2))?></option><?php endforeach; ?></select></label>
<label class="form-group"><span class="field-label">Importe</span><input class="custom-input" name="importe" type="number" step="0.01" min="0.01" required></label>
<label class="form-group"><span class="field-label">Medio</span><select name="medio_pago" class="custom-select"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="deposito">Depósito</option><option value="tarjeta">Tarjeta</option></select></label>
<label class="form-group"><span class="field-label">Referencia</span><input class="custom-input" name="referencia" maxlength="100"></label>
<button class="btn btn-primary-action">Registrar</button></form>
<p class="small-note" style="margin-top:10px">En ventas a crédito el importe se aplica a las cuotas pendientes, empezando por la de vencimiento más próximo.</p>
<?php else: ?><p class="small-note">No hay ventas confirmadas con saldo pendiente.</p><?php endif; ?>
</div></div>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden"><div style="overflow:auto"><table class="detalle-tabla" style="width:100%;border-collapse:collapse"><thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Comprobante</th><th>Medio</th><th>Estado</th><th>Importe</th><th></th></tr></thead><tbody>
<?php foreach ($filas as $r): ?><tr><td><?=h($r['numero'])?></td><td><?=h($r['fecha'])?></td><td><?=h($r['cliente'])?></td><td><?=h($r['comprobante'] ?? 'sin comprobante')?><?php if (!empty($r['comprobante_estado'])): ?><div class="small-note"><?=h($r['comprobante_estado'])?></div><?php endif; ?></td><td><?=h($r['medio_pago'])?></td><td><?=h($r['estado'])?></td><td><?=h($r['moneda'])?> <?=number_format($r['importe'], 2)?></td><td><?php if ($r['estado'] === 'borrador' && puede('editar')): ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="confirmar"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sec">Confirmar</button></form><?php endif; ?><?php if ($r['estado'] !== 'anulada' && puede('editar')): ?><form method="post" style="display:flex;gap:5px;margin-top:6px" onsubmit="return confirm('¿Anular la cobranza <?=h($r['numero'])?>? Se restituirá el saldo de la venta.')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="<?=$r['id']?>"><input class="custom-input" style="width:150px" name="motivo" placeholder="Motivo" maxlength="255" required><button class="btn btn-muted">Anular</button></form><?php endif; ?><?php if ($r['estado'] === 'anulada'): ?><div class="small-note">Motivo: <?=h($r['motivo_anulacion'] ?? '')?></div><?php endif; ?></td></tr><?php endforeach;
if (!$filas): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:#64748b">Aún no hay cobranzas.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../../core/layout_footer.php';

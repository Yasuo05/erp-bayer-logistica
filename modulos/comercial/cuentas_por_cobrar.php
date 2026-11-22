<?php
require_once __DIR__ . '/../../core/crud_base.php';
requerir_login();
if (!puede('ver')) { http_response_code(403); exit('Sin acceso.'); }

$titulo = 'Cuentas por cobrar';
require __DIR__ . '/../../core/layout_header.php';

// Lo cobrado por cuota cuenta solo cobranzas confirmadas; las que están en borrador se muestran aparte
$cuotas = $pdo->query("SELECT cc.cuenta_id, cc.id, cc.numero_cuota, cc.vencimiento, cc.importe,
        COALESCE(SUM(CASE WHEN c.estado = 'confirmada' THEN a.importe END), 0) AS cobrado,
        COALESCE(SUM(CASE WHEN c.estado = 'borrador' THEN a.importe END), 0) AS por_confirmar
    FROM cuenta_cuotas cc
    LEFT JOIN cobranza_aplicaciones a ON a.cuota_id = cc.id
    LEFT JOIN cobranzas c ON c.id = a.cobranza_id
    GROUP BY cc.id ORDER BY cc.cuenta_id, cc.vencimiento, cc.numero_cuota")->fetchAll();
$porCuenta = [];
foreach ($cuotas as $q) $porCuenta[$q['cuenta_id']][] = $q;

require_once __DIR__ . '/cxc_lib.php';
[$sedeSql, $sedeArg] = sede_filtro('v.sucursal_id'); // sede activa (G4)
$cuentas = $pdo->prepare("SELECT cx.id, cx.importe_original, cx.estado, cx.creado_en, v.numero venta, v.moneda, v.fecha,
        cl.razon_social cliente, s.nombre sucursal, " . cxc_sql_notas_venta() . " AS notas
    FROM cuentas_por_cobrar cx
    JOIN ventas v ON v.id = cx.venta_id
    JOIN clientes cl ON cl.id = v.cliente_id
    JOIN sucursales s ON s.id = v.sucursal_id
    WHERE 1=1{$sedeSql}
    ORDER BY cx.id DESC");
$cuentas->execute($sedeArg);
$cuentas = $cuentas->fetchAll();

$hoy = date('Y-m-d');
$total = ['original' => 0, 'cobrado' => 0, 'saldo' => 0, 'vencido' => 0];
foreach ($cuentas as &$cx) {
    $cx['cuotas'] = $porCuenta[$cx['id']] ?? [];
    $cx['cobrado'] = array_sum(array_column($cx['cuotas'], 'cobrado'));
    $cx['por_confirmar'] = array_sum(array_column($cx['cuotas'], 'por_confirmar'));
    // Saldo = crédito − cobrado − notas de crédito aceptadas (+ notas de débito)
    $cx['notas'] = round((float)$cx['notas'], 2);
    $cx['saldo'] = round((float)$cx['importe_original'] - $cx['cobrado'] - $cx['notas'], 2);
    $cx['vencido'] = 0;
    $cx['proximo'] = null;
    foreach ($cx['cuotas'] as $q) {
        $pend = round((float)$q['importe'] - (float)$q['cobrado'], 2);
        if ($pend <= 0) continue;
        if ($q['vencimiento'] < $hoy) $cx['vencido'] += $pend;
        if ($cx['proximo'] === null) $cx['proximo'] = $q['vencimiento'];
    }
    if ($cx['estado'] === 'anulada') $cx['situacion'] = ['Anulada', '#64748b'];
    elseif ($cx['saldo'] <= 0) $cx['situacion'] = ['Pagada', '#15803d'];
    elseif ($cx['vencido'] > 0) $cx['situacion'] = ['Vencida', '#b91c1c'];
    else $cx['situacion'] = ['Al día', '#1d4ed8'];
    if ($cx['estado'] !== 'anulada') {
        $total['original'] += (float)$cx['importe_original'];
        $total['cobrado'] += $cx['cobrado'];
        $total['saldo'] += $cx['saldo'];
        $total['vencido'] += $cx['vencido'];
    }
}
unset($cx);
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px">
  <?php foreach ([['Crédito otorgado', $total['original']], ['Cobrado', $total['cobrado']], ['Saldo por cobrar', $total['saldo']], ['Vencido', $total['vencido']]] as [$etq, $val]): ?>
  <div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px 18px"><div class="small-note"><?=h($etq)?></div><div style="font-size:22px;font-weight:800;color:#10384f">S/ <?=number_format($val, 2)?></div></div>
  <?php endforeach; ?>
</div>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden"><div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px"><?=count($cuentas)?> cuentas por cobrar</h2><p class="small-note" style="margin:4px 0 0">Se generan al confirmar una venta a crédito. Los cobros se registran en Cobranzas.</p></div>
<div style="overflow:auto"><table class="detalle-tabla" style="width:100%;min-width:860px;border-collapse:collapse"><thead><tr><th>Venta</th><th>Cliente</th><th>Sucursal</th><th>Cuotas</th><th>Próximo venc.</th><th>Original</th><th>Cobrado</th><th>Saldo</th><th>Situación</th></tr></thead><tbody>
<?php foreach ($cuentas as $cx): ?>
<tr>
  <td><strong><?=h($cx['venta'])?></strong><div class="small-note"><?=h($cx['fecha'])?></div></td>
  <td><?=h($cx['cliente'])?></td>
  <td><?=h($cx['sucursal'])?></td>
  <td><?php foreach ($cx['cuotas'] as $q): $pend = round((float)$q['importe'] - (float)$q['cobrado'], 2); ?>
    <div class="small-note">N.º <?=h($q['numero_cuota'])?> · vence <?=h($q['vencimiento'])?> · <?=number_format($q['importe'], 2)?> · pendiente <?=number_format(max(0, $pend), 2)?></div>
  <?php endforeach; ?></td>
  <td><?=h($cx['proximo'] ?? '—')?></td>
  <td><?=h($cx['moneda'])?> <?=number_format($cx['importe_original'], 2)?></td>
  <td><?=h($cx['moneda'])?> <?=number_format($cx['cobrado'], 2)?><?php if ($cx['por_confirmar'] > 0): ?><div class="small-note">+ <?=number_format($cx['por_confirmar'], 2)?> por confirmar</div><?php endif; ?><?php if (abs($cx['notas']) > 0.004): ?><div class="small-note"><?=$cx['notas'] > 0 ? 'Nota de crédito' : 'Nota de débito'?>: <?=number_format(abs($cx['notas']), 2)?></div><?php endif; ?></td>
  <td><strong><?=h($cx['moneda'])?> <?=number_format($cx['saldo'], 2)?></strong><?php if ($cx['saldo'] < -0.004): ?><div class="small-note">Saldo a favor del cliente</div><?php endif; ?></td>
  <td><span style="font-weight:800;color:<?=$cx['situacion'][1]?>"><?=h($cx['situacion'][0])?></span></td>
</tr>
<?php endforeach; if (!$cuentas): ?><tr><td colspan="9" style="text-align:center;padding:30px;color:#64748b">Aún no hay cuentas por cobrar.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../../core/layout_footer.php';

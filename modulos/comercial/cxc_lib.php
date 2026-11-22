<?php
/**
 * Saldos de ventas y cuentas por cobrar (G8).
 * - Venta al contado: se cobra contra la venta (cobranza_aplicaciones.venta_contado_id).
 * - Venta a crédito: se cobra contra sus cuotas (cobranza_aplicaciones.cuota_id).
 * Las cobranzas anuladas no cuentan; las que están en borrador sí reservan saldo.
 * Notas de Facturación (G1 → G8): una nota de crédito ACEPTADA sobre el comprobante de la venta reduce lo que
 * el cliente debe; una nota de débito aceptada lo aumenta. Solo cuentan las aceptadas u observadas por SUNAT,
 * que son las que tienen validez tributaria. Si el saldo queda negativo, es un saldo a favor del cliente.
 */

/** Expresión SQL del ajuste neto por notas aceptadas de la venta ($v = alias de ventas): positivo reduce la deuda. */
function cxc_sql_notas_venta(string $v = 'v'): string {
    return "(SELECT COALESCE(SUM(CASE nota.tipo WHEN 'nota_credito' THEN nota.total WHEN 'nota_debito' THEN -nota.total ELSE 0 END), 0)
          FROM facturacion_comprobantes nota JOIN facturacion_comprobantes origen ON origen.id = nota.comprobante_referenciado_id
         WHERE origen.venta_erp_id = {$v}.id AND nota.estado IN ('aceptado','observado'))";
}

/** Ajuste neto por notas aceptadas de una venta. */
function cxc_notas_venta(PDO $pdo, int $ventaId): float {
    $st = $pdo->prepare('SELECT ' . cxc_sql_notas_venta() . ' FROM ventas v WHERE v.id = ?');
    $st->execute([$ventaId]);
    return round((float)$st->fetchColumn(), 2);
}

/** Expresión SQL del saldo pendiente de una venta ($v = alias de ventas), ya descontadas las notas aceptadas. */
function cxc_sql_saldo_venta(string $v = 'v'): string {
    return "(CASE WHEN {$v}.condicion_pago = 'credito' THEN
        (SELECT COALESCE(SUM(cc.importe), 0) FROM cuentas_por_cobrar cx
           JOIN cuenta_cuotas cc ON cc.cuenta_id = cx.id
          WHERE cx.venta_id = {$v}.id AND cx.estado = 'activa')
      - (SELECT COALESCE(SUM(a.importe), 0) FROM cuentas_por_cobrar cx
           JOIN cuenta_cuotas cc ON cc.cuenta_id = cx.id
           JOIN cobranza_aplicaciones a ON a.cuota_id = cc.id
           JOIN cobranzas c ON c.id = a.cobranza_id
          WHERE cx.venta_id = {$v}.id AND cx.estado = 'activa' AND c.estado <> 'anulada')
      ELSE {$v}.total
      - (SELECT COALESCE(SUM(a.importe), 0) FROM cobranza_aplicaciones a
           JOIN cobranzas c ON c.id = a.cobranza_id
          WHERE a.venta_contado_id = {$v}.id AND c.estado <> 'anulada')
      END) - " . cxc_sql_notas_venta($v);
}

/** Cuotas de una venta a crédito con lo pendiente de cada una, de la más antigua a la más reciente. */
function cxc_cuotas_pendientes(PDO $pdo, int $ventaId): array {
    $st = $pdo->prepare("SELECT cc.id, cc.numero_cuota, cc.vencimiento, cc.importe,
            cc.importe - COALESCE((SELECT SUM(a.importe) FROM cobranza_aplicaciones a
                JOIN cobranzas c ON c.id = a.cobranza_id
               WHERE a.cuota_id = cc.id AND c.estado <> 'anulada'), 0) AS pendiente
          FROM cuentas_por_cobrar cx
          JOIN cuenta_cuotas cc ON cc.cuenta_id = cx.id
         WHERE cx.venta_id = ? AND cx.estado = 'activa'
         ORDER BY cc.vencimiento, cc.numero_cuota
         FOR UPDATE");
    $st->execute([$ventaId]);
    return $st->fetchAll();
}

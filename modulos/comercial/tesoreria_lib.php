<?php
/**
 * Tesorería (G8): el dinero que no pasa por caja.
 *  - Cobranza por transferencia, depósito o tarjeta (G8) → ingreso en la cuenta bancaria de la sede.
 *  - Pago a proveedor por transferencia o depósito (G7 · cuentas por pagar) → egreso en la cuenta de la sede del almacén.
 *  - Anular una cobranza confirmada → egreso de reversión vinculado al ingreso original.
 * Los movimientos nacen confirmados porque provienen de una operación ya confirmada en su módulo.
 */

/** Cuenta bancaria activa de la sede en la moneda indicada, o excepción con la indicación para registrarla. */
function tes_cuenta_sede(PDO $pdo, int $sucursalId, string $moneda = 'PEN'): array {
    $st = $pdo->prepare("SELECT * FROM tesoreria_cuentas WHERE sucursal_id = ? AND moneda = ? AND tipo = 'bancaria' AND estado = 'activa' ORDER BY id LIMIT 1 FOR UPDATE");
    $st->execute([$sucursalId, $moneda]);
    $cuenta = $st->fetch();
    if (!$cuenta) throw new RuntimeException("La sede no tiene una cuenta bancaria activa en $moneda. Regístrala en Comercial › Tesorería.");
    return $cuenta;
}

/** Saldo confirmado de una cuenta. */
function tes_saldo(PDO $pdo, int $cuentaId): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(CASE tipo WHEN 'ingreso' THEN importe ELSE -importe END), 0) FROM tesoreria_movimientos WHERE cuenta_id = ? AND estado = 'confirmado'");
    $st->execute([$cuentaId]);
    return round((float)$st->fetchColumn(), 2);
}

/**
 * Registra un movimiento confirmado. $extra admite cobranza_id, g7_cuenta_pagar_id, reversa_de_id.
 * La referencia debe ser única por cuenta (número del documento de origen).
 */
function tes_movimiento(PDO $pdo, array $cuenta, string $tipo, string $origen, float $importe, string $referencia, string $concepto, array $extra = []): int {
    $usuario = (int)(usuario_actual()['id'] ?? 0);
    $clave = sprintf('%08s-%04s-%04s-%04s-%012s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
    $pdo->prepare("INSERT INTO tesoreria_movimientos (cuenta_id, moneda, clave_operacion, fecha, tipo, origen, importe, referencia, concepto, estado,
            cobranza_id, g7_cuenta_pagar_id, reversa_de_id, creado_por, confirmado_por, confirmado_en)
        VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'confirmado', ?, ?, ?, ?, ?, NOW())")
        ->execute([$cuenta['id'], $cuenta['moneda'], $clave, $tipo, $origen, round($importe, 2), mb_substr($referencia, 0, 100), mb_substr($concepto, 0, 255),
            $extra['cobranza_id'] ?? null, $extra['g7_cuenta_pagar_id'] ?? null, $extra['reversa_de_id'] ?? null, $usuario, $usuario]);
    return (int)$pdo->lastInsertId();
}

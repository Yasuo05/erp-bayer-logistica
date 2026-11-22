<?php
declare(strict_types=1);

/**
 * Inventario de Logística (G7): saldos, reservas y kardex.
 * La usan la API de Logística y Comercial (G8) para reservar stock al confirmar una venta.
 * Requiere core/auth.php cargado (usuario_actual).
 */

class ApiError extends RuntimeException {
    public int $statusCode;
    public function __construct(string $message, int $statusCode = 400) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }
}

function code_gen(string $prefix, int $id): string {
    return sprintf('%s-%s-%04d', $prefix, date('Y'), $id);
}

function g7_exists(PDO $pdo, string $table, int $id): void {
    $st = $pdo->prepare("SELECT 1 FROM `$table` WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
        throw new ApiError("No existe el registro seleccionado: $table #$id", 404);
    }
}

function g7_audit(PDO $pdo, string $action, array $detail): void {
    $userId = usuario_actual()['id'] ?? null;
    $detail['sede_id']=$detail['sede_id']??(function_exists('sede_activa')?sede_activa():null);
    if (!empty($detail['despacho_id'])) {
        $scope=$pdo->prepare('SELECT a.sucursal_id FROM g7_despachos d JOIN almacenes a ON a.id=d.almacen_id WHERE d.id=?');$scope->execute([$detail['despacho_id']]);
        $detail['sede_id']=$scope->fetchColumn()?:$detail['sede_id'];
    }
    $st = $pdo->prepare("INSERT INTO g7_auditoria (usuario_id, accion, detalle) VALUES (?, ?, ?)");
    $st->execute([$userId, $action, json_encode($detail, JSON_UNESCAPED_UNICODE)]);
}

/** Sin sede documentada los registros históricos no se muestran en una sesión restringida. */
function g7_auditoria_visible(PDO $pdo): array {
    $sede=function_exists('sede_activa')?sede_activa():null;
    $st=$sede===null?$pdo->query('SELECT * FROM g7_auditoria ORDER BY id DESC LIMIT 100'):$pdo->prepare("SELECT * FROM g7_auditoria WHERE JSON_EXTRACT(detalle,'$.sede_id')=? ORDER BY id DESC LIMIT 100");
    if ($sede!==null) $st->execute([$sede]);
    return $st->fetchAll();
}

function g7_balance(PDO $pdo, int $product, int $warehouse): array {
    $st = $pdo->prepare("SELECT * FROM g7_saldos WHERE producto_id = ? AND almacen_id = ? FOR UPDATE");
    $st->execute([$product, $warehouse]);
    $r = $st->fetch();
    if (!$r) {
        g7_exists($pdo, 'productos', $product);
        g7_exists($pdo, 'almacenes', $warehouse);
        $ins = $pdo->prepare("INSERT INTO g7_saldos (producto_id, almacen_id) VALUES (?, ?)");
        $ins->execute([$product, $warehouse]);
        $st->execute([$product, $warehouse]);
        $r = $st->fetch();
    }
    return $r;
}

function g7_mutate_stock(
    PDO $pdo,
    int $product,
    int $warehouse,
    float $delta,
    string $typ,
    string $document = '',
    string $party = '',
    ?float $cost = null,
    string $notes = '',
    float $reservedDelta = 0.0
): float {
    $b = g7_balance($pdo, $product, $warehouse);
    $qtyActual = (float)$b['cantidad'];
    $resActual = (float)$b['reservado'];
    $quantity = round($qtyActual + $delta, 3);
    $reserve = round($resActual + $reservedDelta, 3);

    if ($quantity < -1e-8 || $reserve < -1e-8 || $reserve > $quantity + 1e-8) {
        throw new ApiError('Stock disponible insuficiente o se afectarían unidades reservadas', 409);
    }

    $quantity = max(0.0, $quantity);
    $reserve = max(0.0, $reserve);
    $oldCost = (float)$b['costo_promedio'];
    $newCost = $oldCost;
    if ($cost !== null && $delta > 0) {
        $newCost = $quantity > 0 ? round(($qtyActual * $oldCost + $delta * $cost) / $quantity, 5) : $oldCost;
    }

    $upd = $pdo->prepare("UPDATE g7_saldos SET cantidad = ?, reservado = ?, costo_promedio = ? WHERE id = ?");
    $upd->execute([$quantity, $reserve, $newCost, $b['id']]);

    if (abs($delta) > 1e-8) {
        $userId = usuario_actual()['id'] ?? null;
        $kardex = $pdo->prepare("INSERT INTO g7_kardex (producto_id, almacen_id, tipo, entrada, salida, saldo, costo_unitario, documento, tercero, observacion, actor_usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $kardex->execute([
            $product,
            $warehouse,
            $typ,
            max(0.0, $delta),
            max(0.0, -$delta),
            $quantity,
            $cost !== null ? $cost : $oldCost,
            $document,
            $party,
            $notes,
            $userId
        ]);
    }

    return $quantity;
}

/**
 * Venta confirmada (G8) → Logística (G7): reserva el stock y crea un despacho en preparación por cada línea.
 * Sale del primer almacén activo de la sucursal de la venta. Debe llamarse dentro de la transacción
 * que confirma la venta: si no hay stock suficiente, lanza la excepción y no se confirma nada.
 * Devuelve los códigos de despacho creados.
 */
function g7_reservar_venta(PDO $pdo, int $ventaId): array {
    require_once __DIR__.'/workflow.php';
    if (!$pdo->inTransaction()) throw new ApiError('La reserva comercial requiere una transacción activa.',409);
    $st = $pdo->prepare("SELECT v.id, v.numero, v.sucursal_id, v.cliente_id, cl.razon_social, s.direccion
        FROM ventas v JOIN clientes cl ON cl.id = v.cliente_id JOIN sucursales s ON s.id = v.sucursal_id WHERE v.id = ?");
    $st->execute([$ventaId]);
    $venta = $st->fetch();
    if (!$venta) throw new ApiError('No se encontró la venta para preparar su despacho', 404);

    $st = $pdo->prepare("SELECT id, nombre FROM almacenes WHERE sucursal_id = ? AND estado = 1 ORDER BY id LIMIT 1");
    $st->execute([$venta['sucursal_id']]);
    $almacen = $st->fetch();
    if (!$almacen) throw new ApiError('La sucursal de la venta no tiene un almacén activo para despachar', 409);
    g7_exigir_almacen($pdo,(int)$almacen['id']);

    $st = $pdo->prepare("SELECT vd.linea,vd.producto_id,vd.descripcion,vd.cantidad,vd.unidad_id,p.unidad_base_id FROM venta_detalles vd JOIN productos p ON p.id=vd.producto_id WHERE vd.venta_id = ? ORDER BY vd.linea");
    $st->execute([$ventaId]);
    $lineas = $st->fetchAll();
    if (!$lineas) throw new ApiError('La venta no tiene líneas para despachar', 409);
    foreach ($lineas as $l) {
        g7_milesimas_bd($l['cantidad']);
        if ((int)$l['unidad_id']!==(int)$l['unidad_base_id']) throw new ApiError('La unidad comercial debe coincidir con la unidad base del inventario antes de reservar.',409);
    }

    // Se valida toda la venta antes de reservar: el pedido debe caber completo en el almacén
    $porProducto = [];
    foreach ($lineas as $l) $porProducto[(int)$l['producto_id']] = ($porProducto[(int)$l['producto_id']] ?? 0) + (float)$l['cantidad'];
    foreach ($porProducto as $producto => $cantidad) {
        $b = g7_balance($pdo, $producto, (int)$almacen['id']);
        $disponible = round((float)$b['cantidad'] - (float)$b['reservado'], 3);
        if ($disponible + 1e-8 < $cantidad) {
            $nombre = array_values(array_filter($lineas, fn($l) => (int)$l['producto_id'] === $producto))[0]['descripcion'];
            throw new ApiError("Stock insuficiente de «{$nombre}» en {$almacen['nombre']}: disponible " . rtrim(rtrim(number_format($disponible, 3, '.', ''), '0'), '.')
                . ', requerido ' . rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.') . '.', 409);
        }
    }

    $ins = $pdo->prepare("INSERT INTO g7_despachos (codigo, producto_id, almacen_id, cliente, cliente_id, cantidad, documento_venta, venta_id, externo_id, destino, transporte)
        VALUES ('TEMP', ?, ?, ?, ?, ?, ?, ?, ?, ?, '')");
    $codigos = [];
    foreach ($lineas as $l) {
        $ins->execute([(int)$l['producto_id'], (int)$almacen['id'], mb_substr($venta['razon_social'], 0, 150), (int)$venta['cliente_id'], (float)$l['cantidad'],
            $venta['numero'], $ventaId, $venta['numero'] . '#' . $l['linea'], mb_substr((string)($venta['direccion'] ?? ''), 0, 150)]);
        $id = (int)$pdo->lastInsertId();
        $ref = code_gen('DP', $id);
        $pdo->prepare("UPDATE g7_despachos SET codigo = ? WHERE id = ?")->execute([$ref, $id]);
        $pdo->prepare('INSERT INTO g7_envios(despacho_id) VALUES(?)')->execute([$id]);
        g7_mutate_stock($pdo, (int)$l['producto_id'], (int)$almacen['id'], 0.0, 'RESERVA', $ref, $venta['razon_social'], null, 'Reserva por venta ' . $venta['numero'], (float)$l['cantidad']);
        $codigos[] = $ref;
    }
    g7_audit($pdo, 'DESPACHO_POR_VENTA', ['venta' => $venta['numero'], 'despachos' => $codigos]);
    return $codigos;
}

/**
 * Anulación de una venta (G8) → Logística (G7): cancela sus despachos en preparación y libera la reserva.
 * Si alguno ya salió del almacén no se puede anular la venta: corresponde una devolución.
 * Debe llamarse dentro de la transacción que anula la venta. Devuelve los códigos cancelados.
 */
function g7_cancelar_despachos_venta(PDO $pdo, int $ventaId): array {
    if (!$pdo->inTransaction()) throw new ApiError('La cancelación comercial requiere una transacción activa.',409);
    $st = $pdo->prepare("SELECT * FROM g7_despachos WHERE venta_id = ? FOR UPDATE");
    $st->execute([$ventaId]);
    $despachos = $st->fetchAll();
    $salidos = array_filter($despachos, fn($d) => in_array($d['estado'], ['EN_RUTA', 'ENTREGADO'], true));
    if ($salidos) {
        throw new ApiError('La mercadería ya salió del almacén (' . implode(', ', array_column($salidos, 'codigo')) . '): no se puede anular la venta, registra una devolución.', 409);
    }
    $cancelados = [];
    foreach ($despachos as $d) {
        if ($d['estado'] !== 'PREPARACION') continue;
        g7_exigir_almacen($pdo,(int)$d['almacen_id']);
        g7_mutate_stock($pdo, (int)$d['producto_id'], (int)$d['almacen_id'], 0.0, 'RESERVA_ANULADA', $d['codigo'], '', null, 'Venta anulada: se liberó la reserva', -(float)$d['cantidad']);
        $pdo->prepare("UPDATE g7_despachos SET estado = 'CANCELADO' WHERE id = ?")->execute([$d['id']]);
        $pdo->prepare("UPDATE g7_envios SET estado_logistica='CANCELADO',version=version+1,actualizado_en=NOW() WHERE despacho_id=?")->execute([$d['id']]);
        $pdo->prepare("INSERT INTO g7_envio_eventos(despacho_id,version,accion,detalle,usuario_id) SELECT despacho_id,version,'CANCELAR_POR_VENTA',?,? FROM g7_envios WHERE despacho_id=?")->execute([json_encode(['venta_id'=>$ventaId],JSON_UNESCAPED_UNICODE),usuario_actual()['id'],$d['id']]);
        $cancelados[] = $d['codigo'];
    }
    if ($cancelados) g7_audit($pdo, 'DESPACHO_CANCELADO_POR_VENTA', ['venta_id' => $ventaId, 'despachos' => $cancelados]);
    return $cancelados;
}

/**
 * Multisede (G4 → G7). Almacenes que el usuario puede operar según su sede activa:
 * null = sin restricción (sede «Todas»); si no, los almacenes activos de la sede activa.
 */
function g7_almacenes_permitidos(PDO $pdo): ?array {
    if (!function_exists('sede_activa') || !function_exists('sedes_permitidas') || sedes_permitidas()===[]) return [];
    if (($sede = sede_activa()) === null) return null;
    $st = $pdo->prepare('SELECT id FROM almacenes WHERE sucursal_id = ?');
    $st->execute([$sede]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Exige que el almacén pertenezca a la sede activa (y a las sedes del usuario). */
function g7_exigir_almacen(PDO $pdo, int $almacenId): void {
    $st = $pdo->prepare('SELECT sucursal_id FROM almacenes WHERE id = ?');
    $st->execute([$almacenId]);
    $sucursal = $st->fetchColumn();
    if ($sucursal === false) throw new ApiError('Almacén no encontrado', 404);
    try { sede_exigir((int)$sucursal); } catch (RuntimeException $e) { throw new ApiError($e->getMessage(), 403); }
}

/** Deja solo las filas de almacenes permitidos (campos almacen_id, origen_id/destino_id o el id indicado). */
function g7_filtrar_por_sede(PDO $pdo, array $filas, string $campo = 'almacen_id'): array {
    $permitidos = g7_almacenes_permitidos($pdo);
    if ($permitidos === null) return $filas;
    return array_values(array_filter($filas, function ($f) use ($permitidos, $campo) {
        if (isset($f['origen_id']) || isset($f['destino_id'])) {
            return in_array((int)($f['origen_id'] ?? 0), $permitidos, true) || in_array((int)($f['destino_id'] ?? 0), $permitidos, true);
        }
        return !isset($f[$campo]) || in_array((int)$f[$campo], $permitidos, true);
    }));
}

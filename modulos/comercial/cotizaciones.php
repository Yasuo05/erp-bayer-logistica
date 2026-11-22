<?php
require_once __DIR__ . '/../../core/crud_base.php';

requerir_login();
if (!puede('ver')) {
    http_response_code(403);
    exit('Sin acceso.');
}

const COTIZACION_ESTADOS = ['borrador', 'emitida', 'aceptada', 'rechazada', 'vencida', 'anulada'];

function cotizacion_url(array $params = []): string {
    $base = BASE_URL . '/modulos/comercial/cotizaciones.php';
    return $params ? $base . '?' . http_build_query($params) : $base;
}

function cotizacion_flash(string $tipo, string $mensaje): void {
    $_SESSION['cotizaciones_flash'] = [$tipo, $mensaje];
}

function cotizacion_registrar_auditoria(PDO $pdo, int $usuarioId, int $cotizacionId, string $accion, ?string $anterior, ?string $nuevo, ?string $motivo = null): void {
    $st = $pdo->prepare('INSERT INTO comercial_auditoria
        (usuario_id, entidad, entidad_id, accion, estado_anterior, estado_nuevo, motivo)
        VALUES (?, "cotizacion", ?, ?, ?, ?, ?)');
    $st->execute([$usuarioId, $cotizacionId, $accion, $anterior, $nuevo, $motivo]);
}

function cotizacion_numero_siguiente(PDO $pdo, int $sucursalId): string {
    $st = $pdo->prepare('SELECT id, serie, ultimo_numero
        FROM comercial_correlativos
        WHERE sucursal_id = ? AND documento = "cotizacion"
        ORDER BY id ASC LIMIT 1 FOR UPDATE');
    $st->execute([$sucursalId]);
    $correlativo = $st->fetch();

    if (!$correlativo) {
        $serie = 'COT' . str_pad((string)$sucursalId, 3, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO comercial_correlativos (sucursal_id, documento, serie, ultimo_numero) VALUES (?, "cotizacion", ?, 1)')
            ->execute([$sucursalId, $serie]);
        return $serie . '-' . str_pad('1', 8, '0', STR_PAD_LEFT);
    }

    $siguiente = (int)$correlativo['ultimo_numero'] + 1;
    $pdo->prepare('UPDATE comercial_correlativos SET ultimo_numero = ? WHERE id = ?')
        ->execute([$siguiente, $correlativo['id']]);
    return $correlativo['serie'] . '-' . str_pad((string)$siguiente, 8, '0', STR_PAD_LEFT);
}

function cotizacion_decimal($valor, string $campo, float $minimo = 0.0): float {
    $valor = str_replace(',', '.', trim((string)$valor));
    if ($valor === '' || !is_numeric($valor)) {
        throw new RuntimeException("El campo {$campo} debe ser numérico.");
    }
    $numero = (float)$valor;
    if (!is_finite($numero) || $numero < $minimo) {
        throw new RuntimeException("El campo {$campo} no tiene un valor válido.");
    }
    return $numero;
}

function cotizacion_fecha_valida(string $fecha, string $campo): string {
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        throw new RuntimeException("{$campo} no es una fecha válida.");
    }
    return $fecha;
}

function cotizacion_estado_permitido(string $origen, string $destino): bool {
    $transiciones = [
        'borrador' => ['emitida', 'anulada'],
        'emitida' => ['aceptada', 'rechazada', 'vencida', 'anulada'],
    ];
    return in_array($destino, $transiciones[$origen] ?? [], true);
}

function cotizacion_estado_etiqueta(string $estado): string {
    return ucfirst($estado);
}

function cotizacion_estado_clase(string $estado): string {
    return 'estado-' . preg_replace('/[^a-z]/', '', $estado);
}

function cotizacion_cargar_lineas(PDO $pdo, int $cotizacionId): array {
    $st = $pdo->prepare('SELECT d.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre, u.abreviatura AS unidad_abreviatura
        FROM cotizacion_detalles d
        INNER JOIN productos p ON p.id = d.producto_id
        INNER JOIN unidades_medida u ON u.id = d.unidad_id
        WHERE d.cotizacion_id = ?
        ORDER BY d.linea');
    $st->execute([$cotizacionId]);
    return $st->fetchAll();
}

function cotizacion_cargar_lineas_post(PDO $pdo, array $post): array {
    $productos = $post['producto_id'] ?? [];
    $cantidades = $post['cantidad'] ?? [];
    $precios = $post['precio_unitario'] ?? [];
    $descuentos = $post['descuento_linea'] ?? [];
    $tasas = $post['tasa_impuesto'] ?? [];
    if (!is_array($productos) || !is_array($cantidades) || !is_array($precios) || !is_array($descuentos) || !is_array($tasas)) {
        throw new RuntimeException('El detalle de la cotización no es válido.');
    }
    if (count($productos) > 50) {
        throw new RuntimeException('Una cotización puede contener como máximo 50 líneas.');
    }

    $lineas = [];
    foreach ($productos as $indice => $productoId) {
        $productoId = (int)$productoId;
        if ($productoId <= 0) {
            continue;
        }
        $cantidad = cotizacion_decimal($cantidades[$indice] ?? '', 'Cantidad', 0.0001);
        $precio = cotizacion_decimal($precios[$indice] ?? '', 'Precio unitario');
        $descuento = cotizacion_decimal($descuentos[$indice] ?? '0', 'Descuento');
        $tasa = cotizacion_decimal($tasas[$indice] ?? '0', 'Tasa de impuesto');
        if ($tasa > 100) {
            throw new RuntimeException('La tasa de impuesto no puede exceder 100%.');
        }

        $st = $pdo->prepare('SELECT p.id, p.codigo, p.nombre, p.unidad_base_id
            FROM productos p WHERE p.id = ? AND p.estado = 1');
        $st->execute([$productoId]);
        $producto = $st->fetch();
        if (!$producto) {
            throw new RuntimeException('Uno de los productos no existe o está inactivo.');
        }

        $subtotal = round($cantidad * $precio, 2);
        if ($descuento > $subtotal) {
            throw new RuntimeException('El descuento de una línea no puede superar su subtotal.');
        }
        $impuesto = round(($subtotal - $descuento) * $tasa / 100, 2);
        $total = round($subtotal - $descuento + $impuesto, 2);
        $lineas[] = [
            'producto_id' => (int)$producto['id'],
            'unidad_id' => (int)$producto['unidad_base_id'],
            'codigo_producto' => $producto['codigo'],
            'descripcion' => $producto['nombre'],
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'descuento' => $descuento,
            'subtotal' => $subtotal,
            'tasa_impuesto' => $tasa,
            'impuesto' => $impuesto,
            'total' => $total,
        ];
    }
    if (!$lineas) {
        throw new RuntimeException('Agrega al menos un producto a la cotización.');
    }
    return $lineas;
}

function cotizacion_totales(array $lineas): array {
    $subtotal = $descuento = $impuesto = $total = 0.0;
    foreach ($lineas as $linea) {
        $subtotal += $linea['subtotal'];
        $descuento += $linea['descuento'];
        $impuesto += $linea['impuesto'];
        $total += $linea['total'];
    }
    return [round($subtotal, 2), round($descuento, 2), round($impuesto, 2), round($total, 2)];
}

$usuarioId = (int)usuario_actual()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && !puede('editar')) {
                throw new RuntimeException('No tienes permiso para editar cotizaciones.');
            }
            if ($id === 0 && !puede('crear')) {
                throw new RuntimeException('No tienes permiso para crear cotizaciones.');
            }

            $sucursalId = (int)($_POST['sucursal_id'] ?? 0);
            $clienteId = (int)($_POST['cliente_id'] ?? 0);
            $vendedorId = (int)($_POST['vendedor_id'] ?? 0) ?: null;
            $fecha = cotizacion_fecha_valida(trim($_POST['fecha'] ?? ''), 'La fecha');
            $vigenteHasta = cotizacion_fecha_valida(trim($_POST['vigente_hasta'] ?? ''), 'La vigencia');
            $moneda = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
            $observaciones = trim($_POST['observaciones'] ?? '');
            if ($sucursalId <= 0 || $clienteId <= 0) {
                throw new RuntimeException('Selecciona una sucursal y un cliente.');
            }
            sede_exigir($sucursalId);
            // El vendedor es obligatorio: toda venta tiene un responsable (comisiones y analítica por vendedor en el Data Hub)
            if (!$vendedorId) {
                throw new RuntimeException('Selecciona el vendedor responsable de la cotización.');
            }
            if ($vigenteHasta < $fecha) {
                throw new RuntimeException('La vigencia no puede ser anterior a la fecha de emisión.');
            }
            if (!in_array($moneda, ['PEN', 'USD'], true)) {
                throw new RuntimeException('La moneda seleccionada no es válida.');
            }

            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT id FROM sucursales WHERE id = ? AND estado = 1');
            $st->execute([$sucursalId]);
            if (!$st->fetchColumn()) throw new RuntimeException('La sucursal seleccionada no está disponible.');
            $st = $pdo->prepare('SELECT id FROM clientes WHERE id = ?');
            $st->execute([$clienteId]);
            if (!$st->fetchColumn()) throw new RuntimeException('El cliente seleccionado no existe.');
            if ($vendedorId) {
                $st = $pdo->prepare('SELECT id FROM vendedores WHERE id = ? AND estado = 1');
                $st->execute([$vendedorId]);
                if (!$st->fetchColumn()) throw new RuntimeException('El vendedor seleccionado no está disponible.');
            }

            $lineas = cotizacion_cargar_lineas_post($pdo, $_POST);
            [$subtotal, $descuento, $impuesto, $total] = cotizacion_totales($lineas);

            if ($id === 0) {
                $numero = cotizacion_numero_siguiente($pdo, $sucursalId);
                $st = $pdo->prepare('INSERT INTO cotizaciones
                    (sucursal_id, cliente_id, vendedor_id, numero, fecha, vigente_hasta, moneda, estado, subtotal, descuento, impuesto, total, observaciones, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, "borrador", ?, ?, ?, ?, ?, ?)');
                $st->execute([$sucursalId, $clienteId, $vendedorId, $numero, $fecha, $vigenteHasta, $moneda, $subtotal, $descuento, $impuesto, $total, $observaciones ?: null, $usuarioId]);
                $id = (int)$pdo->lastInsertId();
                cotizacion_registrar_auditoria($pdo, $usuarioId, $id, 'creada', null, 'borrador');
                $mensaje = "Cotización {$numero} creada como borrador.";
            } else {
                $st = $pdo->prepare('SELECT numero, estado FROM cotizaciones WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $actual = $st->fetch();
                if (!$actual) throw new RuntimeException('La cotización solicitada no existe.');
                if ($actual['estado'] !== 'borrador') throw new RuntimeException('Solo se pueden editar cotizaciones en borrador.');
                $st = $pdo->prepare('UPDATE cotizaciones SET sucursal_id=?, cliente_id=?, vendedor_id=?, fecha=?, vigente_hasta=?, moneda=?, subtotal=?, descuento=?, impuesto=?, total=?, observaciones=? WHERE id=?');
                $st->execute([$sucursalId, $clienteId, $vendedorId, $fecha, $vigenteHasta, $moneda, $subtotal, $descuento, $impuesto, $total, $observaciones ?: null, $id]);
                $pdo->prepare('DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?')->execute([$id]);
                cotizacion_registrar_auditoria($pdo, $usuarioId, $id, 'actualizada', 'borrador', 'borrador');
                $mensaje = "Cotización {$actual['numero']} actualizada.";
            }

            $detalle = $pdo->prepare('INSERT INTO cotizacion_detalles
                (cotizacion_id, linea, producto_id, unidad_id, codigo_producto, descripcion, cantidad, precio_unitario, descuento, subtotal, tasa_impuesto, impuesto, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($lineas as $indice => $linea) {
                $detalle->execute([$id, $indice + 1, $linea['producto_id'], $linea['unidad_id'], $linea['codigo_producto'], $linea['descripcion'], $linea['cantidad'], $linea['precio_unitario'], $linea['descuento'], $linea['subtotal'], $linea['tasa_impuesto'], $linea['impuesto'], $linea['total']]);
            }
            $pdo->commit();
            cotizacion_flash('ok', $mensaje);
            header('Location: ' . cotizacion_url(['accion' => 'ver', 'id' => $id]));
            exit;
        }

        if ($accion === 'cambiar_estado') {
            if (!puede('editar')) throw new RuntimeException('No tienes permiso para cambiar el estado.');
            $id = (int)($_POST['id'] ?? 0);
            $destino = $_POST['estado_destino'] ?? '';
            $motivo = trim($_POST['motivo_anulacion'] ?? '');
            if (!in_array($destino, COTIZACION_ESTADOS, true)) throw new RuntimeException('El estado indicado no es válido.');

            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM cotizaciones WHERE id = ? FOR UPDATE');
            $st->execute([$id]);
            $cotizacion = $st->fetch();
            if (!$cotizacion) throw new RuntimeException('La cotización solicitada no existe.');
            if (!cotizacion_estado_permitido($cotizacion['estado'], $destino)) throw new RuntimeException('La transición de estado solicitada no está permitida.');
            if ($destino === 'aceptada' && $cotizacion['vigente_hasta'] < date('Y-m-d')) throw new RuntimeException('No se puede aceptar una cotización vencida.');
            if ($destino === 'anulada' && $motivo === '') throw new RuntimeException('Indica el motivo de anulación.');

            if ($destino === 'anulada') {
                $pdo->prepare('UPDATE cotizaciones SET estado=?, anulado_por=?, anulado_en=NOW(), motivo_anulacion=? WHERE id=?')
                    ->execute([$destino, $usuarioId, $motivo, $id]);
            } else {
                $pdo->prepare('UPDATE cotizaciones SET estado=? WHERE id=?')->execute([$destino, $id]);
            }
            cotizacion_registrar_auditoria($pdo, $usuarioId, $id, 'estado_actualizado', $cotizacion['estado'], $destino, $destino === 'anulada' ? $motivo : null);
            $pdo->commit();
            cotizacion_flash('ok', 'Estado actualizado a ' . cotizacion_estado_etiqueta($destino) . '.');
            header('Location: ' . cotizacion_url(['accion' => 'ver', 'id' => $id]));
            exit;
        }
        throw new RuntimeException('La acción solicitada no es válida.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        cotizacion_flash('err', $e->getMessage());
        $id = (int)($_POST['id'] ?? 0);
        header('Location: ' . cotizacion_url($id ? ['accion' => 'editar', 'id' => $id] : ['accion' => 'nueva']));
        exit;
    }
}

$flash = $_SESSION['cotizaciones_flash'] ?? null;
unset($_SESSION['cotizaciones_flash']);
$accionVista = $_GET['accion'] ?? '';
$cotizacionId = (int)($_GET['id'] ?? 0);
$titulo = 'Cotizaciones';
require __DIR__ . '/../../core/layout_header.php';

if ($flash): ?>
  <div id="toast-container" class="toast-container"></div>
  <script>document.addEventListener('DOMContentLoaded', () => mostrarToast(<?= json_encode($flash[1]) ?>, <?= json_encode($flash[0]) ?>));</script>
<?php endif; ?>

<style>
.comercial-actions{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap}.comercial-card{background:#fff;border:1px solid var(--border-subtle,#e2e8f0);border-radius:16px;box-shadow:0 4px 18px rgba(15,43,64,.06);overflow:hidden}.comercial-card-head{padding:18px 22px;border-bottom:1px solid var(--border-subtle,#e2e8f0);display:flex;justify-content:space-between;gap:12px;align-items:center}.comercial-card-head h2{margin:0;color:var(--bayer-navy,#10384f);font-size:17px}.comercial-card-body{padding:22px}.estado-badge{font-size:12px;font-weight:800;border-radius:999px;padding:6px 10px;text-transform:capitalize;display:inline-block}.estado-borrador{background:#e2e8f0;color:#475569}.estado-emitida{background:#dbeafe;color:#1d4ed8}.estado-aceptada{background:#dcfce7;color:#15803d}.estado-rechazada,.estado-anulada{background:#fee2e2;color:#b91c1c}.estado-vencida{background:#fef3c7;color:#a16207}.cotizacion-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.cotizacion-form-grid .form-group{display:flex;flex-direction:column;gap:6px}.cotizacion-form-grid .full{grid-column:1/-1}.cotizacion-form-grid label{font-weight:700;font-size:12px;color:var(--bayer-navy,#10384f)}.cotizacion-form-grid input,.cotizacion-form-grid select,.cotizacion-form-grid textarea{width:100%;box-sizing:border-box}.detalle-scroll{overflow-x:auto}.detalle-tabla{width:100%;border-collapse:collapse;min-width:900px}.detalle-tabla th{font-size:11px;text-align:left;text-transform:uppercase;color:#64748b;background:#f8fafc;padding:11px 9px;border-bottom:1px solid #e2e8f0}.detalle-tabla td{padding:8px;border-bottom:1px solid #eef2f7;vertical-align:middle}.detalle-tabla input,.detalle-tabla select{min-width:0;width:100%;box-sizing:border-box;padding:8px}.detalle-tabla .num{text-align:right}.detalle-tabla .linea-total{font-weight:800;color:#10384f;white-space:nowrap}.totales-cotizacion{margin-left:auto;max-width:340px;padding-top:16px}.totales-cotizacion div{display:flex;justify-content:space-between;padding:6px 0;color:#475569}.totales-cotizacion .total-final{border-top:2px solid #10384f;color:#10384f;font-size:18px;font-weight:800;margin-top:5px;padding-top:11px}.documento-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.documento-meta div{padding:12px;border-radius:10px;background:#f8fafc}.documento-meta span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:4px}.documento-meta strong{font-size:13px;color:#10384f}.estado-actions{display:flex;gap:8px;flex-wrap:wrap}.inline-form{display:inline}.btn-warning{background:#f59e0b;color:#fff;border:0}.btn-danger{background:#dc2626;color:#fff;border:0}.btn-muted{background:#e2e8f0;color:#334155;border:0}.empty-commercial{padding:44px 20px;text-align:center;color:#64748b}.link-row{color:#10384f;font-weight:700;text-decoration:none}.link-row:hover{color:#2e8b3d}.small-note{font-size:12px;color:#64748b;margin:8px 0 0}@media(max-width:900px){.cotizacion-form-grid,.documento-meta{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.cotizacion-form-grid,.documento-meta{grid-template-columns:1fr}.comercial-actions{align-items:stretch}.comercial-actions .btn{justify-content:center}}
</style>

<?php
if (in_array($accionVista, ['nueva', 'editar'], true)) {
    $edicion = $accionVista === 'editar';
    $form = ['id' => '', 'sucursal_id' => (string)(sede_activa() ?? ''), 'cliente_id' => '', 'vendedor_id' => '', 'fecha' => date('Y-m-d'), 'vigente_hasta' => date('Y-m-d', strtotime('+' . (int)parametro('vigencia_cotizacion_dias', '15') . ' days')), 'moneda' => 'PEN', 'observaciones' => '', 'numero' => 'Se asignará al guardar', 'estado' => 'borrador'];
    $lineasForm = [];
    if ($edicion) {
        $st = $pdo->prepare('SELECT * FROM cotizaciones WHERE id = ?');
        $st->execute([$cotizacionId]);
        $form = $st->fetch();
        if (!$form) { echo '<div class="alerta err">La cotización solicitada no existe.</div>'; require __DIR__ . '/../../core/layout_footer.php'; exit; }
        if ($form['estado'] !== 'borrador' || !puede('editar')) { echo '<div class="alerta err">Solo un usuario con permiso de edición puede modificar cotizaciones en borrador.</div>'; require __DIR__ . '/../../core/layout_footer.php'; exit; }
        $lineasForm = cotizacion_cargar_lineas($pdo, $cotizacionId);
    }
    // Solo las sedes del usuario; si hay sede activa, solo esa (G4)
    $sucursales = array_values(array_filter(sedes_permitidas(), fn($s) => sede_activa() === null || (int)$s['id'] === sede_activa()));
    $clientes = $pdo->query('SELECT id, codigo, razon_social, nro_doc FROM clientes ORDER BY razon_social')->fetchAll();
    $vendedores = $pdo->query('SELECT id, codigo, nombres, apellidos FROM vendedores WHERE estado=1 ORDER BY nombres, apellidos')->fetchAll();
    // Precio de lista del maestro (G5) para precargar la línea
    $productos = $pdo->query('SELECT p.id, p.codigo, p.nombre, p.unidad_base_id, u.abreviatura, p.precio_venta FROM productos p INNER JOIN unidades_medida u ON u.id=p.unidad_base_id WHERE p.estado=1 ORDER BY p.nombre')->fetchAll();
    $catalogoProductos = [];
    foreach ($productos as $producto) $catalogoProductos[(int)$producto['id']] = $producto;
    // Precios vigentes hoy (G5): por sede (clave = id de sede) y generales (clave 0)
    $preciosVigentes = [];
    foreach ($pdo->query('SELECT producto_id, COALESCE(sucursal_id, 0) sede, precio FROM lista_precios WHERE vigente_desde <= CURDATE() AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())') as $fila) {
        $preciosVigentes[(int)$fila['sede']][(int)$fila['producto_id']] = (float)$fila['precio'];
    }
    // Stock disponible por sede (G7): existencia − reservado en los almacenes activos de cada sucursal
    $stockSede = [];
    foreach ($pdo->query('SELECT a.sucursal_id, s.producto_id, SUM(s.cantidad - s.reservado) disponible FROM g7_saldos s JOIN almacenes a ON a.id=s.almacen_id WHERE a.estado=1 GROUP BY a.sucursal_id, s.producto_id') as $fila) {
        $stockSede[(int)$fila['sucursal_id']][(int)$fila['producto_id']] = (float)$fila['disponible'];
    }
    ?>
    <div class="comercial-actions">
      <div><a class="btn btn-sec" href="<?= h(cotizacion_url()) ?>">← Volver al listado</a></div>
      <span class="estado-badge estado-borrador">Borrador</span>
    </div>
    <form method="post" id="form-cotizacion" class="comercial-card" novalidate>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= h($form['id']) ?>">
      <div class="comercial-card-head"><div><h2><?= $edicion ? 'Editar cotización ' . h($form['numero']) : 'Nueva cotización' ?></h2><p class="small-note">Los importes se recalculan al guardar para proteger la integridad del documento.</p></div><span class="small-note">N.º: <?= h($form['numero']) ?></span></div>
      <div class="comercial-card-body">
        <div class="cotizacion-form-grid">
          <div class="form-group"><label for="sucursal_id">Sucursal *</label><select id="sucursal_id" name="sucursal_id" class="custom-select" required><option value="">Seleccionar</option><?php foreach ($sucursales as $fila): ?><option value="<?= h($fila['id']) ?>" <?= (string)$form['sucursal_id']===(string)$fila['id']?'selected':'' ?>><?= h($fila['codigo'].' · '.$fila['nombre']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label for="cliente_id">Cliente *</label><select id="cliente_id" name="cliente_id" class="custom-select" required><option value="">Seleccionar</option><?php foreach ($clientes as $fila): ?><option value="<?= h($fila['id']) ?>" <?= (string)$form['cliente_id']===(string)$fila['id']?'selected':'' ?>><?= h($fila['razon_social'].' · '.$fila['nro_doc']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label for="vendedor_id">Vendedor *</label><select id="vendedor_id" name="vendedor_id" class="custom-select" required><option value="">Seleccionar</option><?php foreach ($vendedores as $fila): ?><option value="<?= h($fila['id']) ?>" <?= (string)$form['vendedor_id']===(string)$fila['id']?'selected':'' ?>><?= h($fila['codigo'].' · '.$fila['nombres'].' '.$fila['apellidos']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label for="moneda">Moneda *</label><select id="moneda" name="moneda" class="custom-select"><option value="PEN" <?= $form['moneda']==='PEN'?'selected':'' ?>>PEN — Soles</option><option value="USD" <?= $form['moneda']==='USD'?'selected':'' ?>>USD — Dólares</option></select></div>
          <div class="form-group"><label for="fecha">Fecha *</label><input id="fecha" name="fecha" type="date" class="custom-input" required value="<?= h($form['fecha']) ?>"></div>
          <div class="form-group"><label for="vigente_hasta">Válida hasta *</label><input id="vigente_hasta" name="vigente_hasta" type="date" class="custom-input" required value="<?= h($form['vigente_hasta']) ?>"></div>
          <div class="form-group full"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" class="custom-textarea" rows="2" maxlength="4000" placeholder="Condiciones comerciales, entrega u otra referencia."><?= h($form['observaciones']) ?></textarea></div>
        </div>
        <div class="comercial-card-head" style="padding:22px 0 12px;margin-top:16px"><div><h2>Detalle de productos</h2><p class="small-note">Precio y descuento se registran por línea. El impuesto se ingresa como porcentaje.</p></div><button type="button" class="btn btn-sec" id="agregar-linea">+ Agregar línea</button></div>
        <div class="detalle-scroll"><table class="detalle-tabla"><thead><tr><th style="width:34%">Producto *</th><th>Cant. *</th><th>P. unitario *</th><th>Desc.</th><th>Imp. %</th><th class="num">Total</th><th></th></tr></thead><tbody id="detalle-cotizacion"></tbody></table></div><p class="small-note" style="margin-top:6px">El precio se completa con el precio vigente de Listas de precios (de la sede o general) o, si no hay, con el precio de lista del producto; puedes modificarlo. Bajo cada producto se muestra el stock disponible en la sede elegida; el stock se reserva al confirmar la venta.</p>
        <div class="totales-cotizacion"><div><span>Subtotal</span><strong id="total-subtotal">0.00</strong></div><div><span>Descuento</span><strong id="total-descuento">0.00</strong></div><div><span>Impuesto</span><strong id="total-impuesto">0.00</strong></div><div class="total-final"><span>Total</span><span id="total-documento">0.00</span></div></div>
        <div class="comercial-actions" style="margin-top:22px"><span class="small-note">* Campos obligatorios</span><div style="display:flex;gap:10px"><a class="btn btn-sec" href="<?= h(cotizacion_url()) ?>">Cancelar</a><button class="btn btn-save" type="submit"><span><?= $edicion ? 'Guardar cambios' : 'Guardar borrador' ?></span></button></div></div>
      </div>
    </form>
    <script>
    const catalogoProductos = <?= json_encode($catalogoProductos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const preciosVigentes = <?= json_encode($preciosVigentes, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const IGV_POR_DEFECTO = <?= json_encode(igv_porcentaje()) ?>; // parámetro central (Administración › Parámetros)
    const stockSede = <?= json_encode($stockSede, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const lineasIniciales = <?= json_encode($lineasForm, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const detalle = document.getElementById('detalle-cotizacion');
    const moneda = document.getElementById('moneda');
    const dinero = n => (Number(n || 0)).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
    const escaparHtml = valor => String(valor).replace(/[&<>"']/g, caracter => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[caracter]));
    const selectorProducto = seleccionado => '<option value="">Seleccionar producto</option>' + Object.values(catalogoProductos).map(p => `<option value="${p.id}" ${String(p.id)===String(seleccionado)?'selected':''}>${escaparHtml(p.codigo)} · ${escaparHtml(p.nombre)} (${escaparHtml(p.abreviatura)})</option>`).join('');
    function agregarLinea(linea = {}) { const tr=document.createElement('tr'); tr.innerHTML=`<td><select name="producto_id[]" class="producto custom-select" required>${selectorProducto(linea.producto_id||'')}</select><div class="small-note stock-linea"></div></td><td><input class="cantidad custom-input" name="cantidad[]" type="number" step="0.0001" min="0.0001" required value="${linea.cantidad||1}"></td><td><input class="precio custom-input" name="precio_unitario[]" type="number" step="0.0001" min="0" required value="${linea.precio_unitario||''}"></td><td><input class="descuento custom-input" name="descuento_linea[]" type="number" step="0.01" min="0" value="${linea.descuento||0}"></td><td><input class="tasa custom-input" name="tasa_impuesto[]" type="number" step="0.0001" min="0" max="100" value="${linea.tasa_impuesto ?? IGV_POR_DEFECTO}"></td><td class="num linea-total">0.00</td><td><button type="button" class="btn btn-muted quitar-linea" title="Quitar línea">×</button></td>`; detalle.appendChild(tr); calcular(); }
    const sucursal = document.getElementById('sucursal_id');
    // Stock disponible de la sede (G7) bajo cada producto; aviso si la cantidad lo supera
    function mostrarStock(){ detalle.querySelectorAll('tr').forEach(tr=>{ const p=tr.querySelector('.producto').value, info=tr.querySelector('.stock-linea'); if(!p||!sucursal.value){info.textContent='';return;} const disp=Number((stockSede[sucursal.value]||{})[p]||0), c=Number(tr.querySelector('.cantidad').value)||0; info.textContent='Disponible en la sede: '+disp.toLocaleString('es-PE')+(c>disp?' · insuficiente para '+c.toLocaleString('es-PE'):''); info.style.color=c>disp?'#b91c1c':''; }); }
    // Al elegir un producto se precarga su precio de lista (G5) si la línea no tiene precio
    detalle.addEventListener('change',e=>{ if(e.target.classList.contains('producto')){ const tr=e.target.closest('tr'), pr=tr.querySelector('.precio'), prod=catalogoProductos[e.target.value]; const vig=(preciosVigentes[sucursal.value]||{})[e.target.value]??(preciosVigentes[0]||{})[e.target.value]; const base=vig??(prod?prod.precio_venta:null); if(base!==null&&base!==undefined&&(pr.value===''||Number(pr.value)===0))pr.value=Number(base).toFixed(2); } mostrarStock(); });
    detalle.addEventListener('input',mostrarStock); sucursal.addEventListener('change',mostrarStock);
    function calcular(){let subtotal=0,descuento=0,impuesto=0,total=0; detalle.querySelectorAll('tr').forEach(tr=>{const c=Number(tr.querySelector('.cantidad').value)||0,p=Number(tr.querySelector('.precio').value)||0,d=Number(tr.querySelector('.descuento').value)||0,t=Math.min(100,Math.max(0,Number(tr.querySelector('.tasa').value)||0)),sub=c*p,imp=Math.max(0,sub-d)*t/100,tot=Math.max(0,sub-d)+imp; tr.querySelector('.linea-total').textContent=dinero(tot);subtotal+=sub;descuento+=d;impuesto+=imp;total+=tot;}); const sim=moneda.value==='USD'?'USD ':'S/ ';document.getElementById('total-subtotal').textContent=sim+dinero(subtotal);document.getElementById('total-descuento').textContent=sim+dinero(descuento);document.getElementById('total-impuesto').textContent=sim+dinero(impuesto);document.getElementById('total-documento').textContent=sim+dinero(total);}
    document.getElementById('agregar-linea').addEventListener('click',()=>agregarLinea()); detalle.addEventListener('input',calcular); detalle.addEventListener('change',calcular); detalle.addEventListener('click',e=>{if(e.target.closest('.quitar-linea')){e.target.closest('tr').remove();calcular();}}); moneda.addEventListener('change',calcular); (lineasIniciales.length?lineasIniciales:[{}]).forEach(agregarLinea); mostrarStock();
    </script>
<?php
} elseif ($accionVista === 'ver' && $cotizacionId > 0) {
    $st = $pdo->prepare('SELECT c.*, s.nombre AS sucursal_nombre, s.codigo AS sucursal_codigo, cl.razon_social AS cliente_nombre, cl.nro_doc AS cliente_documento, CONCAT(COALESCE(v.nombres,""), " ", COALESCE(v.apellidos,"")) AS vendedor_nombre, u.nombre AS creado_por_nombre
        FROM cotizaciones c INNER JOIN sucursales s ON s.id=c.sucursal_id INNER JOIN clientes cl ON cl.id=c.cliente_id LEFT JOIN vendedores v ON v.id=c.vendedor_id INNER JOIN usuarios u ON u.id=c.creado_por WHERE c.id=?');
    $st->execute([$cotizacionId]);
    $cotizacion = $st->fetch();
    if (!$cotizacion) { echo '<div class="alerta err">La cotización solicitada no existe.</div>'; }
    else { $lineas = cotizacion_cargar_lineas($pdo, $cotizacionId); ?>
      <div class="comercial-actions"><a class="btn btn-sec" href="<?= h(cotizacion_url()) ?>">← Volver al listado</a><div class="estado-actions"><?php if ($cotizacion['estado']==='borrador' && puede('editar')): ?><a class="btn btn-sec" href="<?= h(cotizacion_url(['accion'=>'editar','id'=>$cotizacionId])) ?>">Editar borrador</a><?php endif; ?><span class="estado-badge <?= h(cotizacion_estado_clase($cotizacion['estado'])) ?>"><?= h(cotizacion_estado_etiqueta($cotizacion['estado'])) ?></span></div></div>
      <div class="comercial-card"><div class="comercial-card-head"><div><h2><?= h($cotizacion['numero']) ?></h2><p class="small-note">Registrada por <?= h($cotizacion['creado_por_nombre']) ?> · <?= h($cotizacion['created_at'] ?? $cotizacion['creado_en'] ?? '') ?></p></div><strong style="font-size:20px;color:#10384f"><?= h($cotizacion['moneda']) ?> <?= number_format((float)$cotizacion['total'],2) ?></strong></div><div class="comercial-card-body">
        <div class="documento-meta"><div><span>Cliente</span><strong><?= h($cotizacion['cliente_nombre']) ?></strong><small><?= h($cotizacion['cliente_documento']) ?></small></div><div><span>Sucursal</span><strong><?= h($cotizacion['sucursal_codigo'].' · '.$cotizacion['sucursal_nombre']) ?></strong></div><div><span>Fecha</span><strong><?= h($cotizacion['fecha']) ?></strong></div><div><span>Vigencia</span><strong><?= h($cotizacion['vigente_hasta']) ?></strong></div></div>
        <?php if ($cotizacion['vendedor_nombre']): ?><p class="small-note"><strong>Vendedor:</strong> <?= h(trim($cotizacion['vendedor_nombre'])) ?></p><?php endif; ?>
        <div class="detalle-scroll" style="margin-top:18px"><table class="detalle-tabla"><thead><tr><th>#</th><th>Producto</th><th>Unidad</th><th class="num">Cantidad</th><th class="num">P. unitario</th><th class="num">Desc.</th><th class="num">Impuesto</th><th class="num">Total</th></tr></thead><tbody><?php foreach($lineas as $linea): ?><tr><td><?= h($linea['linea']) ?></td><td><strong><?= h($linea['codigo_producto']) ?></strong><br><small><?= h($linea['descripcion']) ?></small></td><td><?= h($linea['unidad_abreviatura']) ?></td><td class="num"><?= number_format((float)$linea['cantidad'],4) ?></td><td class="num"><?= number_format((float)$linea['precio_unitario'],2) ?></td><td class="num"><?= number_format((float)$linea['descuento'],2) ?></td><td class="num"><?= number_format((float)$linea['impuesto'],2) ?></td><td class="num"><strong><?= number_format((float)$linea['total'],2) ?></strong></td></tr><?php endforeach; ?></tbody></table></div>
        <div class="totales-cotizacion"><div><span>Subtotal</span><strong><?= h($cotizacion['moneda']) ?> <?= number_format((float)$cotizacion['subtotal'],2) ?></strong></div><div><span>Descuento</span><strong><?= h($cotizacion['moneda']) ?> <?= number_format((float)$cotizacion['descuento'],2) ?></strong></div><div><span>Impuesto</span><strong><?= h($cotizacion['moneda']) ?> <?= number_format((float)$cotizacion['impuesto'],2) ?></strong></div><div class="total-final"><span>Total</span><span><?= h($cotizacion['moneda']) ?> <?= number_format((float)$cotizacion['total'],2) ?></span></div></div>
        <?php if ($cotizacion['observaciones']): ?><p style="margin-top:22px"><strong>Observaciones:</strong><br><?= nl2br(h($cotizacion['observaciones'])) ?></p><?php endif; ?>
        <?php if ($cotizacion['estado']==='anulada'): ?><p class="alerta err" style="margin-top:20px"><strong>Anulada:</strong> <?= h($cotizacion['motivo_anulacion']) ?></p><?php endif; ?>
        <?php if (puede('editar') && in_array($cotizacion['estado'], ['borrador','emitida'], true)): ?><div class="comercial-card-head" style="padding:20px 0 0;margin-top:16px"><div><h2>Acciones de estado</h2><p class="small-note">Los documentos aceptados, rechazados, vencidos o anulados quedan bloqueados para edición.</p></div><div class="estado-actions"><?php foreach (($cotizacion['estado']==='borrador'?['emitida']:['aceptada','rechazada','vencida']) as $destino): ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= h($cotizacionId) ?>"><input type="hidden" name="estado_destino" value="<?= h($destino) ?>"><button class="btn <?= $destino==='aceptada'?'btn-save':'btn-sec' ?>" type="submit"><?= h(cotizacion_estado_etiqueta($destino)) ?></button></form><?php endforeach; ?><button type="button" class="btn btn-danger" onclick="document.getElementById('form-anular').hidden=!document.getElementById('form-anular').hidden">Anular</button></div></div><form method="post" id="form-anular" hidden style="margin-top:14px"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= h($cotizacionId) ?>"><input type="hidden" name="estado_destino" value="anulada"><label class="small-note" for="motivo_anulacion">Motivo de anulación *</label><div style="display:flex;gap:10px;margin-top:6px"><input class="custom-input" style="flex:1" id="motivo_anulacion" name="motivo_anulacion" required maxlength="255"><button class="btn btn-danger" type="submit">Confirmar anulación</button></div></form><?php endif; ?>
      </div></div>
    <?php }
} else {
    $estado = $_GET['estado'] ?? 'todos';
    if (!in_array($estado, array_merge(['todos'], COTIZACION_ESTADOS), true)) $estado='todos';
    $q = trim($_GET['q'] ?? '');
    $where=[]; $params=[];
    if ($estado!=='todos') {$where[]='c.estado=?'; $params[]=$estado;}
    if ($q!=='') {$where[]='(c.numero LIKE ? OR cl.razon_social LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%';}
    if (($sedeId = sede_activa()) !== null) {$where[]='c.sucursal_id=?'; $params[]=$sedeId;} // sede activa (G4)
    $sql='SELECT c.id,c.numero,c.fecha,c.vigente_hasta,c.moneda,c.estado,c.total,cl.razon_social cliente,s.nombre sucursal FROM cotizaciones c INNER JOIN clientes cl ON cl.id=c.cliente_id INNER JOIN sucursales s ON s.id=c.sucursal_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY c.id DESC';
    $st=$pdo->prepare($sql);$st->execute($params);$cotizaciones=$st->fetchAll();
    ?>
    <div class="comercial-actions"><form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input class="custom-input" type="search" name="q" value="<?= h($q) ?>" placeholder="Número o cliente"><select class="custom-select" name="estado"><option value="todos">Todos los estados</option><?php foreach(COTIZACION_ESTADOS as $opcion): ?><option value="<?= h($opcion) ?>" <?= $estado===$opcion?'selected':'' ?>><?= h(cotizacion_estado_etiqueta($opcion)) ?></option><?php endforeach; ?></select><button class="btn btn-sec" type="submit">Filtrar</button></form><?php if(puede('crear')): ?><a class="btn btn-primary-action" href="<?= h(cotizacion_url(['accion'=>'nueva'])) ?>">+ Nueva cotización</a><?php endif; ?></div>
    <div class="comercial-card"><div class="comercial-card-head"><div><h2>Registro de cotizaciones</h2><p class="small-note"><?= count($cotizaciones) ?> resultado(s)</p></div></div><div class="detalle-scroll"><table class="detalle-tabla" style="min-width:760px"><thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Sucursal</th><th>Vigencia</th><th>Estado</th><th class="num">Total</th></tr></thead><tbody><?php if(!$cotizaciones): ?><tr><td colspan="7" class="empty-commercial">No hay cotizaciones para los filtros seleccionados.</td></tr><?php else: foreach($cotizaciones as $fila): ?><tr><td><a class="link-row" href="<?= h(cotizacion_url(['accion'=>'ver','id'=>$fila['id']])) ?>"><?= h($fila['numero']) ?></a></td><td><?= h($fila['fecha']) ?></td><td><?= h($fila['cliente']) ?></td><td><?= h($fila['sucursal']) ?></td><td><?= h($fila['vigente_hasta']) ?></td><td><span class="estado-badge <?= h(cotizacion_estado_clase($fila['estado'])) ?>"><?= h(cotizacion_estado_etiqueta($fila['estado'])) ?></span></td><td class="num"><strong><?= h($fila['moneda']) ?> <?= number_format((float)$fila['total'],2) ?></strong></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<?php }
require __DIR__ . '/../../core/layout_footer.php';

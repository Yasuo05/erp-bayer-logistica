<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/auth.php';
requerir_login();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once __DIR__ . '/stock_lib.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/../comercial/tesoreria_lib.php';

function json_response(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jnum($v, string $label = 'Cantidad', bool $allowZero = false): float {
    return (float)g7_decimal(g7_milesimas($v,$label,$allowZero));
}

function jtext($v, string $label = 'Texto', int $limit = 200, bool $required = true): string {
    $s = trim((string)($v ?? ''));
    if (mb_strlen($s) > $limit || ($required && $s === '')) {
        throw new ApiError("$label: valor requerido o demasiado largo");
    }
    return $s;
}

function jid($v, string $label = 'Registro'): int {
    $i = filter_var($v, FILTER_VALIDATE_INT);
    if ($i === false || $i < 1) throw new ApiError("$label inválido");
    return (int)$i;
}


function g7_stock_rows(PDO $pdo, ?int $warehouse = null, string $q = '', string $state = 'todos'): array {
    $wh = "WHERE p.estado = 1";
    $params = [];
    if ($q !== '') {
        $wh .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($warehouse !== null) {
        g7_exigir_almacen($pdo,$warehouse);
        $wh .= " AND s.almacen_id = ?";
        $params[] = $warehouse;
    } elseif (($permitidos = g7_almacenes_permitidos($pdo)) !== null) {
        // Sede activa (G4): solo el stock de los almacenes de la sede
        $wh .= $permitidos ? ' AND s.almacen_id IN (' . implode(',', $permitidos) . ')' : ' AND 1 = 0';
    }

    $sql = "SELECT p.id, p.codigo, p.nombre,
                   COALESCE(cat.nombre, 'General') AS categoria,
                   COALESCE(um.abreviatura, um.codigo, 'UND') AS unidad,
                   COALESCE(m.nombre, '') AS marca,
                   'Pucchún' AS origen,
                   COALESCE(SUM(s.cantidad), 0) AS existencia,
                   COALESCE(SUM(s.reservado), 0) AS reservado,
                   COALESCE(SUM(s.minimo), 0) AS minimo,
                   COALESCE(SUM(s.cantidad - s.reservado), 0) AS disponible,
                   COALESCE(SUM(s.cantidad * s.costo_promedio), 0) AS valor,
                   COUNT(CASE WHEN s.cantidad > 0 THEN 1 END) AS sedes,
                   GROUP_CONCAT(CASE WHEN s.cantidad > 0 THEN CONCAT(a.nombre, ': ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM FORMAT(s.cantidad, 3)))) ELSE NULL END SEPARATOR ' · ') AS ubicaciones,
                   COALESCE(MAX(s.costo_promedio), 0) AS costo_unitario
            FROM productos p
            LEFT JOIN categorias_producto cat ON cat.id = p.categoria_id
            LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
            LEFT JOIN marcas m ON m.id = p.marca_id
            LEFT JOIN g7_saldos s ON p.id = s.producto_id
            LEFT JOIN almacenes a ON a.id = s.almacen_id
            $wh
            GROUP BY p.id, p.codigo, p.nombre, cat.nombre, um.abreviatura, um.codigo, m.nombre
            ORDER BY p.nombre";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    // Unidades en tránsito (traslados aún no recibidos) hacia los almacenes visibles
    $transito = [];
    $tw = "WHERE estado = 'EN_TRANSITO'";
    if ($warehouse !== null) {
        $tw .= ' AND destino_id = ' . (int)$warehouse;
    } elseif (($perm = g7_almacenes_permitidos($pdo)) !== null) {
        $tw .= $perm ? ' AND destino_id IN (' . implode(',', array_map('intval', $perm)) . ')' : ' AND 1 = 0';
    }
    foreach ($pdo->query("SELECT producto_id, SUM(cantidad) c FROM g7_transferencias $tw GROUP BY producto_id") as $t) {
        $transito[(int)$t['producto_id']] = (float)$t['c'];
    }

    $out = [];
    foreach ($rows as $r) {
        $r['en_transito'] = $transito[(int)$r['id']] ?? 0.0;
        $r['existencia'] = (float)$r['existencia'];
        $r['reservado'] = (float)$r['reservado'];
        $r['minimo'] = (float)$r['minimo'];
        $r['disponible'] = (float)$r['disponible'];
        $r['valor'] = (float)$r['valor'];
        $r['sedes'] = (int)$r['sedes'];
        $r['costo_unitario'] = (float)$r['costo_unitario'];
        $avail = $r['disponible'];
        $mn = $r['minimo'];
        $r['estado'] = ($mn > 0 && $avail <= $mn) ? 'CRITICO' : (($mn > 0 && $avail <= $mn * 1.3) ? 'BAJO' : 'OPTIMO');
        $r['ubicaciones'] = $r['ubicaciones'] ?: 'Sin existencias';
        if ($r['en_transito'] > 0) {
            $r['ubicaciones'] .= ' · En tránsito: ' . rtrim(rtrim(number_format($r['en_transito'], 3, '.', ''), '0'), '.');
        }

        if ($state === 'todos' || $r['estado'] === strtoupper($state)) {
            $out[] = $r;
        }
    }
    return $out;
}

function g7_catalog(PDO $pdo): array {
    $prods = $pdo->query("SELECT p.*, COALESCE(um.abreviatura, um.codigo, 'UND') AS unidad, COALESCE(cat.nombre, 'General') AS categoria
                          FROM productos p
                          LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                          LEFT JOIN categorias_producto cat ON cat.id = p.categoria_id
                          WHERE p.estado = 1 ORDER BY p.nombre")->fetchAll();
    $alms = $pdo->query("SELECT * FROM almacenes WHERE estado = 1 ORDER BY id")->fetchAll();
    $destinos=$pdo->query('SELECT a.id,a.codigo,a.nombre,s.nombre sucursal FROM almacenes a JOIN sucursales s ON s.id=a.sucursal_id WHERE a.estado=1 AND s.estado=1 ORDER BY s.nombre,a.nombre')->fetchAll();
    $provs = $pdo->query("SELECT * FROM proveedores WHERE estado = 1 ORDER BY nombre")->fetchAll();
    // Clientes del maestro (G5) para los despachos
    $clis = $pdo->query("SELECT id, codigo, nro_doc, razon_social FROM clientes WHERE estado = 'ACTIVO' ORDER BY razon_social")->fetchAll();
    return ['productos' => $prods, 'almacenes' => $alms, 'almacenes_destino'=>$destinos, 'proveedores' => $provs, 'clientes' => $clis];
}

function g7_dashboard(PDO $pdo): array {
    $rows = g7_stock_rows($pdo);
    $groups = ['CRITICO' => 0, 'BAJO' => 0, 'OPTIMO' => 0];
    $valor = 0.0;
    $reservas = 0;
    $reservadoUnidades = 0.0;
    $alertas = [];

    foreach ($rows as $r) {
        $groups[$r['estado']] = ($groups[$r['estado']] ?? 0) + 1;
        $valor += $r['valor'];
        if ($r['reservado'] > 0) {
            $reservas++;
            $reservadoUnidades += $r['reservado'];
        }
        if ($r['estado'] !== 'OPTIMO') {
            $alertas[] = $r;
        }
    }

    $permitidos=g7_almacenes_permitidos($pdo);
    $scope=fn(string $col)=>$permitidos===null?'':($permitidos?' AND '.$col.' IN ('.implode(',',$permitidos).')':' AND 1=0');
    $transferScope=$permitidos===null?'':($permitidos?' AND (origen_id IN ('.implode(',',$permitidos).') OR destino_id IN ('.implode(',',$permitidos).'))':' AND 1=0');
    $enTransito = (float)$pdo->query("SELECT COALESCE(SUM(cantidad), 0) FROM g7_transferencias WHERE estado = 'EN_TRANSITO'".$transferScope)->fetchColumn();
    $ocPendientes = (int)$pdo->query("SELECT COUNT(*) FROM g7_ordenes_compra WHERE estado IN ('PENDIENTE', 'PARCIAL')".$scope('almacen_id'))->fetchColumn();
    $despPendientes = (int)$pdo->query("SELECT COUNT(*) FROM g7_despachos d LEFT JOIN g7_envios e ON e.despacho_id=d.id WHERE d.estado IN ('PREPARACION','EN_RUTA') AND (e.estado_logistica IS NULL OR e.estado_logistica NOT IN ('CERRADO','CERRADO_CON_INCIDENCIA','CANCELADO'))".$scope('d.almacen_id'))->fetchColumn();
    $cxpPendiente = (float)$pdo->query("SELECT COALESCE(SUM(cp.importe - cp.pagado), 0) FROM g7_cuentas_pagar cp JOIN g7_recepciones r ON r.id=cp.recepcion_id WHERE 1=1".$scope('r.almacen_id'))->fetchColumn();

    $actividad = $pdo->query("SELECT k.fecha, k.tipo, k.documento, p.nombre AS producto, a.nombre AS almacen, k.entrada, k.salida, k.saldo
                              FROM g7_kardex k
                              JOIN productos p ON p.id = k.producto_id
                              JOIN almacenes a ON a.id = k.almacen_id
                              WHERE 1=1 ".$scope('k.almacen_id')."
                              ORDER BY k.fecha DESC, k.id DESC LIMIT 8")->fetchAll();

    return [
        'articulos' => count($rows),
        'valor_inventario' => round($valor, 2),
        'reservas' => $reservas,
        'reservado_unidades' => round($reservadoUnidades, 3),
        'criticos' => $groups['CRITICO'],
        'bajos' => $groups['BAJO'],
        'optimos' => $groups['OPTIMO'],
        'en_transito' => $enTransito,
        'oc_pendientes' => $ocPendientes,
        'despachos_pendientes' => $despPendientes,
        'cxp_pendiente' => $cxpPendiente,
        'alertas' => $alertas,
        'actividad' => $actividad
    ];
}

function g7_movements(PDO $pdo, array $p): array {
    $w = "WHERE 1=1";
    $par = [];
    if (($permitidos=g7_almacenes_permitidos($pdo))!==null) {
        $w.=$permitidos?' AND k.almacen_id IN ('.implode(',',$permitidos).')':' AND 1=0';
    }
    if (!empty($p['producto'])) {
        $w .= " AND k.producto_id = ?";
        $par[] = jid($p['producto'], 'Producto');
    }
    if (!empty($p['almacen'])) {
        $w .= " AND k.almacen_id = ?";
        $par[] = jid($p['almacen'], 'Almacén');
    }
    if (!empty($p['tipo']) && $p['tipo'] !== 'todos') {
        $w .= " AND k.tipo = ?";
        $par[] = $p['tipo'];
    }
    if (!empty($p['q'])) {
        $w .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR k.documento LIKE ?)";
        $q = "%" . $p['q'] . "%";
        $par[] = $q; $par[] = $q; $par[] = $q;
    }

    $sql = "SELECT k.*, p.nombre AS producto, p.codigo AS codigo_producto, COALESCE(um.abreviatura, 'UND') AS unidad,
                   a.nombre AS almacen, ROUND(k.saldo * k.costo_unitario, 2) AS valor_saldo
            FROM g7_kardex k
            JOIN productos p ON p.id = k.producto_id
            LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
            JOIN almacenes a ON a.id = k.almacen_id
            $w
            ORDER BY k.fecha DESC, k.id DESC LIMIT 500";
    $st = $pdo->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function g7_list_orders(PDO $pdo): array {
    $orders = $pdo->query("SELECT o.*, s.nombre AS proveedor, a.nombre AS almacen,
                                  (SELECT COALESCE(ROUND(SUM(d.cantidad * d.costo_unitario), 2), 0) FROM g7_orden_detalles d WHERE d.orden_id = o.id) AS total,
                                  (SELECT COUNT(*) FROM g7_orden_detalles d WHERE d.orden_id = o.id) AS lineas
                           FROM g7_ordenes_compra o
                           JOIN proveedores s ON o.proveedor_id = s.id
                           JOIN almacenes a ON a.id = o.almacen_id
                           ORDER BY o.id DESC")->fetchAll();
    $detSt = $pdo->prepare("SELECT d.*, p.nombre AS producto, COALESCE(um.abreviatura, 'UND') AS unidad
                            FROM g7_orden_detalles d
                            JOIN productos p ON p.id = d.producto_id
                            LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                            WHERE d.orden_id = ?");
    foreach ($orders as &$o) {
        $detSt->execute([$o['id']]);
        $o['detalles'] = $detSt->fetchAll();
    }
    return $orders;
}

function g7_list_receipts(PDO $pdo): array {
    return $pdo->query("SELECT r.*, p.nombre AS producto, COALESCE(um.abreviatura, 'UND') AS unidad, a.nombre AS almacen, s.nombre AS proveedor
                        FROM g7_recepciones r
                        JOIN productos p ON p.id = r.producto_id
                        LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                        JOIN almacenes a ON a.id = r.almacen_id
                        JOIN proveedores s ON s.id = r.proveedor_id
                        ORDER BY r.id DESC")->fetchAll();
}

function g7_list_transfers(PDO $pdo): array {
    return $pdo->query("SELECT t.*, p.nombre AS producto, COALESCE(um.abreviatura, 'UND') AS unidad, ori.nombre AS origen, dst.nombre AS destino
                        FROM g7_transferencias t
                        JOIN productos p ON t.producto_id = p.id
                        LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                        JOIN almacenes ori ON t.origen_id = ori.id
                        JOIN almacenes dst ON t.destino_id = dst.id
                        ORDER BY t.id DESC")->fetchAll();
}

function g7_list_counts(PDO $pdo): array {
    $res = $pdo->query("SELECT ct.*, a.nombre AS almacen FROM g7_conteos ct JOIN almacenes a ON a.id = ct.almacen_id ORDER BY ct.id DESC")->fetchAll();
    $detSt = $pdo->prepare("SELECT d.*, p.nombre AS producto, COALESCE(um.abreviatura, 'UND') AS unidad,
                                   ROUND(d.cantidad_contada - d.cantidad_sistema, 3) AS diferencia,
                                   ROUND((d.cantidad_contada - d.cantidad_sistema) * COALESCE(s.costo_promedio, 0), 2) AS valor_diferencia
                            FROM g7_conteo_detalles d
                            JOIN productos p ON p.id = d.producto_id
                            LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                            LEFT JOIN g7_saldos s ON s.producto_id = d.producto_id AND s.almacen_id = ?
                            WHERE d.conteo_id = ?");
    foreach ($res as &$r) {
        $detSt->execute([$r['almacen_id'], $r['id']]);
        $r['detalles'] = $detSt->fetchAll();
    }
    return $res;
}

function g7_list_dispatches(PDO $pdo): array {
    return $pdo->query("SELECT d.*, p.nombre AS producto, COALESCE(um.abreviatura, 'UND') AS unidad, a.nombre AS almacen
                        FROM g7_despachos d
                        JOIN productos p ON p.id = d.producto_id
                        LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                        JOIN almacenes a ON a.id = d.almacen_id
                        ORDER BY d.id DESC")->fetchAll();
}

function g7_list_payables(PDO $pdo): array {
    return $pdo->query("SELECT x.*, s.nombre AS proveedor, r.codigo AS recepcion,
                               ROUND(x.importe - x.pagado, 2) AS pendiente,
                               CASE WHEN x.pagado >= x.importe THEN 'PAGADO'
                                    WHEN x.pagado > 0 THEN 'PARCIAL'
                                    WHEN x.vencimiento < CURDATE() THEN 'VENCIDO'
                                    ELSE 'PENDIENTE' END AS estado
                        FROM g7_cuentas_pagar x
                        JOIN proveedores s ON s.id = x.proveedor_id
                        JOIN g7_recepciones r ON r.id = x.recepcion_id
                        ORDER BY x.id DESC")->fetchAll();
}

function g7_create_receipt(PDO $pdo, int $product, int $warehouse, int $supplier, float $qty, float $cost, ?int $orderId = null, string $notes = ''): array {
    g7_exists($pdo, 'productos', $product);
    g7_exists($pdo, 'almacenes', $warehouse);
    g7_exists($pdo, 'proveedores', $supplier);

    $st = $pdo->prepare("INSERT INTO g7_recepciones (codigo, orden_id, proveedor_id, almacen_id, producto_id, cantidad, costo_unitario, observacion) VALUES ('TEMP', ?, ?, ?, ?, ?, ?, ?)");
    $st->execute([$orderId, $supplier, $warehouse, $product, $qty, $cost, $notes]);
    $id = (int)$pdo->lastInsertId();
    $ref = code_gen('RC', $id);
    $pdo->prepare("UPDATE g7_recepciones SET codigo = ? WHERE id = ?")->execute([$ref, $id]);

    $name = (string)$pdo->query("SELECT nombre FROM proveedores WHERE id = $supplier")->fetchColumn();
    g7_mutate_stock($pdo, $product, $warehouse, $qty, 'COMPRA', $ref, $name, $cost, $notes);

    // Vencimiento según los días de pago del proveedor (G5); 30 días si no están definidos
    $dp = $pdo->prepare('SELECT dias_pago FROM proveedores WHERE id = ?');
    $dp->execute([$supplier]);
    $dias = $dp->fetchColumn();
    $due = date('Y-m-d', strtotime('+' . (is_numeric($dias) ? (int)$dias : 30) . ' days'));
    $cp = $pdo->prepare("INSERT INTO g7_cuentas_pagar (codigo, recepcion_id, proveedor_id, importe, vencimiento) VALUES (?, ?, ?, ?, ?)");
    $cp->execute([code_gen('CP', $id), $id, $supplier, round($qty * $cost, 2), $due]);

    g7_audit($pdo, 'RECEPCION', ['referencia' => $ref, 'producto_id' => $product, 'cantidad' => $qty, 'orden_id' => $orderId]);
    return ['id' => $id, 'codigo' => $ref, 'cantidad' => $qty];
}

// Excel: generador común del ERP (core/excel.php), con el mismo formato en todos los módulos
require_once __DIR__ . '/../../core/excel.php';

try {
    // Parse route and query string
    $parts = explode('?', $route, 2);
    $path = $parts[0];
    $queryParams = $_GET;
    if (isset($parts[1])) {
        parse_str($parts[1], $extraParams);
        $queryParams = array_merge($queryParams, $extraParams);
    }

    if ($method === 'GET') {
        if (!g7_permiso_persistido($pdo,'ver')) throw new ApiError('Tu rol no tiene permiso de consulta registrado por G4.',403);
        if ($path==='/api/envios') json_response(200,['ok'=>true,'data'=>g7_envios_listar($pdo)]);
        if (preg_match('#^/api/envios/(\d+)$#',$path,$m)) json_response(200,['ok'=>true,'data'=>g7_envio_expediente($pdo,(int)$m[1])]);
        if (preg_match('#^/guias/(\d+)$#', $path, $m)) {
            $id = (int)$m[1];
            $st = $pdo->prepare("SELECT d.*, p.nombre AS producto, p.codigo AS codigo_producto, COALESCE(um.abreviatura, 'UND') AS unidad, a.nombre AS almacen
                                 FROM g7_despachos d
                                 JOIN productos p ON p.id = d.producto_id
                                 LEFT JOIN unidades_medida um ON um.id = p.unidad_base_id
                                 JOIN almacenes a ON a.id = d.almacen_id
                                 WHERE d.id = ?");
            $st->execute([$id]);
            $x = $st->fetch();
            if (!$x) throw new ApiError('Despacho no encontrado', 404);
            g7_exigir_almacen($pdo,(int)$x['almacen_id']);
            $e = fn($k) => htmlspecialchars((string)($x[$k] ?? '—'), ENT_QUOTES, 'UTF-8');
            header('Content-Type: text/html; charset=utf-8');
            echo "<!doctype html><html lang='es'><head><meta charset='utf-8'><title>Guía interna {$e('codigo')}</title>
            <style>*{box-sizing:border-box}body{font:15px Arial;margin:40px auto;max-width:840px;color:#21364f}header{display:flex;justify-content:space-between;border-bottom:3px solid #2e8b3d;padding:10px 0 25px}h1{color:#0b2535;margin:0;font-size:24px}.small{color:#77889b;font-size:12px}.warning{padding:10px;background:#fff5da;border:1px solid #e2c98b;color:#694d19;margin:20px 0}.cols{display:grid;grid-template-columns:1fr 1fr;gap:15px}.part{padding:15px;border:1px solid #d8e3ef;border-radius:8px}.part b{display:block;font-size:12px;color:#627c9b;margin-bottom:4px}table{width:100%;border-collapse:collapse;margin-top:20px}td,th{border:1px solid #d6e2ef;padding:12px;text-align:left}th{background:#e9f2fc;color:#0b2535}.sign{display:flex;justify-content:space-around;margin-top:100px}.sign span{border-top:1px solid #456781;padding:10px 40px}@media print{.print{display:none}body{margin:10px auto}}</style></head>
            <body><header><div><strong style='font-size:18px;color:#0b2535'>BAYER · PUCCHÚN ERP</strong></div><div><h1>Guía interna de despacho</h1><strong>{$e('codigo')}</strong><p class='small'>{$e('fecha')} · {$e('estado')}</p></div></header>
            <div class='warning'>DOCUMENTO INTERNO OPERATIVO. No reemplaza a la guía de remisión electrónica SUNAT.</div>
            <div class='cols'><div class='part'><b>ALMACÉN ORIGEN</b>{$e('almacen')}<br><br><b>DOCUMENTO COMERCIAL</b>{$e('documento_venta')}</div>
            <div class='part'><b>CLIENTE / DESTINATARIO</b>{$e('cliente')}<br><br><b>DESTINO DE ENTREGA</b>{$e('destino')}</div></div>
            <table><thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>U.M.</th></tr></thead><tbody>
            <tr><td>{$e('codigo_producto')}</td><td>{$e('producto')}</td><td>" . ((float)$x['cantidad']) . "</td><td>{$e('unidad')}</td></tr></tbody></table>
            <p><b>Transporte / conductor:</b> {$e('transporte')}</p><p><b>Fecha de salida:</b> {$e('fecha_salida')}</p><p><b>Fecha de entrega:</b> {$e('fecha_entrega')}</p>
            <div class='sign'><span>Despachado por</span><span>Recibido por</span></div>
            <p class='print'><strong>Impresión:</strong> presiona Ctrl+P en el navegador y selecciona tu impresora o Guardar como PDF.</p></body></html>";
            exit;
        }

        if ($path === '/api/exportar') {
            $kind = $queryParams['tipo'] ?? '';
            if ($kind === 'stock') {
                $titulo = 'Stock por sedes';
                $headers = ['Código' => 'texto', 'Producto' => 'texto', 'Unidad' => 'texto', 'Existencia' => 'decimal', 'Reservado' => 'decimal', 'Disponible' => 'decimal', 'En tránsito' => 'decimal', 'Mínimo' => 'decimal', 'Estado' => 'texto', 'Ubicaciones' => 'texto', 'Valor S/' => 'decimal'];
                $rows = array_map(fn($r) => [$r['codigo'],$r['nombre'],$r['unidad'],$r['existencia'],$r['reservado'],$r['disponible'],$r['en_transito'] ?? 0,$r['minimo'],$r['estado'],$r['ubicaciones'],$r['valor']], g7_stock_rows($pdo));
            } elseif ($kind === 'kardex') {
                $titulo = 'Kardex';
                $headers = ['Fecha' => 'fechahora', 'Código producto' => 'texto', 'Producto' => 'texto', 'Almacén' => 'texto', 'Concepto' => 'texto', 'Documento' => 'texto', 'Tercero' => 'texto', 'Entrada' => 'decimal', 'Salida' => 'decimal', 'Saldo' => 'decimal', 'Costo unitario S/' => 'decimal'];
                $rows = array_map(fn($r) => [$r['fecha'],$r['codigo_producto'],$r['producto'],$r['almacen'],$r['tipo'],$r['documento'],$r['tercero'],$r['entrada'],$r['salida'],$r['saldo'],$r['costo_unitario']], g7_movements($pdo, []));
            } elseif ($kind === 'ordenes') {
                $titulo = 'Órdenes de compra';
                $headers = ['Orden' => 'texto', 'Fecha' => 'fecha', 'Proveedor' => 'texto', 'Almacén' => 'texto', 'Estado' => 'texto', 'Total S/' => 'decimal'];
                $rows = array_map(fn($r) => [$r['codigo'],$r['fecha'],$r['proveedor'],$r['almacen'],$r['estado'],$r['total']], g7_filtrar_por_sede($pdo,g7_list_orders($pdo)));
            } else {
                throw new ApiError('Tipo de exportación inválido');
            }
            excel_descargar('PUCCHUN_' . strtoupper($kind) . '_' . excel_ahora('Ymd_Hi') . '.xlsx', excel_generar($titulo, $headers, $rows));
        }

        $data = match ($path) {
            '/api/salud' => ['ok' => true, 'aplicacion' => 'BAYER PUCCHÚN ERP · LOGÍSTICA PHP 8.4', 'motor' => 'MySQL / MariaDB'],
            '/api/catalogos' => g7_catalog($pdo),
            '/api/resumen' => g7_dashboard($pdo),
            '/api/stock' => g7_stock_rows($pdo, !empty($queryParams['almacen']) ? jid($queryParams['almacen']) : null, $queryParams['q'] ?? '', $queryParams['estado'] ?? 'todos'),
            '/api/movimientos' => g7_movements($pdo, $queryParams),
            '/api/ordenes' => g7_list_orders($pdo),
            '/api/recepciones' => g7_list_receipts($pdo),
            '/api/transferencias' => g7_list_transfers($pdo),
            '/api/conteos' => g7_list_counts($pdo),
            '/api/ajustes' => $pdo->query("SELECT m.*, p.nombre AS producto, a.nombre AS almacen FROM g7_ajustes m JOIN productos p ON p.id = m.producto_id JOIN almacenes a ON a.id = m.almacen_id ORDER BY m.id DESC")->fetchAll(),
            '/api/despachos' => g7_list_dispatches($pdo),
            '/api/cuentas-pagar' => g7_list_payables($pdo),
            '/api/sedes' => $pdo->query("SELECT a.id, a.nombre, a.codigo, COUNT(DISTINCT CASE WHEN s.cantidad > 0 THEN s.producto_id END) AS productos, ROUND(COALESCE(SUM(s.cantidad), 0), 2) AS unidades, ROUND(COALESCE(SUM(s.cantidad * s.costo_promedio), 0), 2) AS valor FROM almacenes a LEFT JOIN g7_saldos s ON a.id = s.almacen_id GROUP BY a.id, a.nombre, a.codigo ORDER BY a.id")->fetchAll(),
            '/api/auditoria' => g7_auditoria_visible($pdo),
            default => throw new ApiError('Recurso no disponible', 404),
        };
        // Sede activa (G4): las listas solo muestran los almacenes de la sede
        if ($path === '/api/catalogos') {
            $data['almacenes'] = g7_filtrar_por_sede($pdo, $data['almacenes'], 'id');
        } elseif ($path === '/api/sedes') {
            $data = g7_filtrar_por_sede($pdo, $data, 'id');
        } elseif ($path === '/api/cuentas-pagar' && ($permitidos = g7_almacenes_permitidos($pdo)) !== null) {
            $almacenDe = $pdo->query('SELECT cp.id, r.almacen_id FROM g7_cuentas_pagar cp JOIN g7_recepciones r ON r.id = cp.recepcion_id')->fetchAll(PDO::FETCH_KEY_PAIR);
            $data = array_values(array_filter($data, fn($c) => in_array((int)($almacenDe[$c['id']] ?? 0), $permitidos, true)));
        } elseif (in_array($path, ['/api/ordenes', '/api/recepciones', '/api/transferencias', '/api/conteos', '/api/ajustes', '/api/despachos', '/api/movimientos'], true) && is_array($data)) {
            $data = g7_filtrar_por_sede($pdo, $data);
        }
        json_response(200, ['ok' => true, 'data' => $data]);
    }

    if ($method === 'POST') {
        // Permisos del ERP (G4): registrar algo nuevo exige «crear»; cambiar el estado de un documento exige «editar»
        $requiere = str_starts_with($path,'/api/envios/') || preg_match('#/(recibir|salir|entregar|cancelar|regularizar|pagar)$#', $path) || $path === '/api/minimos' ? 'editar' : 'crear';
        if (!g7_permiso_persistido($pdo,$requiere)) {
            throw new ApiError('Tu rol (' . (usuario_actual()['rol'] ?? '—') . ") no tiene permiso para esta operación ($requiere).", 403);
        }
        $raw = file_get_contents('php://input');
        $p = json_decode($raw ?: '{}', true);
        if (!is_array($p)) throw new ApiError('JSON inválido');
        $csrfEsperado=$_SESSION['csrf']??'';
        $csrfRecibido=$_SERVER['HTTP_X_CSRF_TOKEN']??($p['csrf']??'');
        if (!is_string($csrfRecibido) || $csrfEsperado==='' || !hash_equals($csrfEsperado,$csrfRecibido)) throw new ApiError('Sesión expirada. Actualiza la página para continuar.',419);
        if (preg_match('#^/api/envios/(\d+)/([a-z_]+)$#',$path,$m)) json_response(200,['ok'=>true,'data'=>g7_envio_operar($pdo,(int)$m[1],$m[2],$p)]);
        if (preg_match('#^/api/despachos/(\d+)/(salir|cancelar)$#',$path,$m)) json_response(200,['ok'=>true,'data'=>g7_envio_operar($pdo,(int)$m[1],$m[2],$p)]);
        if ($path==='/api/despachos' || preg_match('#^/api/despachos/\d+/entregar$#',$path)) throw new ApiError('Usa el expediente de Envíos: origen comercial confirmado, intentos y balance físico verificable.',409);
        // Sede activa (G4): el almacén afectado debe pertenecer a la sede activa del usuario
        $almacenes = [];
        foreach (['almacen_id', 'origen_id'] as $k) if (!empty($p[$k])) $almacenes[] = (int)$p[$k];
        $porDocumento = [
            '#^/api/ordenes/(\d+)/recibir$#' => 'SELECT almacen_id FROM g7_ordenes_compra WHERE id = ?',
            '#^/api/transferencias/(\d+)/recibir$#' => 'SELECT destino_id FROM g7_transferencias WHERE id = ?',
            '#^/api/conteos/(\d+)/regularizar$#' => 'SELECT almacen_id FROM g7_conteos WHERE id = ?',
            '#^/api/despachos/(\d+)/[a-z]+$#' => 'SELECT almacen_id FROM g7_despachos WHERE id = ?',
            '#^/api/cuentas-pagar/(\d+)/pagar$#' => 'SELECT r.almacen_id FROM g7_cuentas_pagar cp JOIN g7_recepciones r ON r.id = cp.recepcion_id WHERE cp.id = ?',
        ];
        foreach ($porDocumento as $patron => $sqlAlmacen) {
            if (preg_match($patron, $path, $mm)) { $st = $pdo->prepare($sqlAlmacen); $st->execute([(int)$mm[1]]); if (($a = $st->fetchColumn()) !== false) $almacenes[] = (int)$a; }
        }
        foreach (array_unique($almacenes) as $a) g7_exigir_almacen($pdo, $a);

        $pdo->beginTransaction();
        $res = null;

        if ($path === '/api/ordenes') {
            $sid = jid($p['proveedor_id'] ?? 0, 'Proveedor');
            $wid = jid($p['almacen_id'] ?? 0, 'Almacén');
            g7_exists($pdo, 'proveedores', $sid);
            g7_exists($pdo, 'almacenes', $wid);
            $items = $p['items'] ?? [];
            if (!is_array($items) || count($items) < 1 || count($items) > 100) {
                throw new ApiError('Agregue de 1 a 100 productos a la orden');
            }
            $st = $pdo->prepare("INSERT INTO g7_ordenes_compra (codigo, proveedor_id, almacen_id, observacion, fecha_entrega) VALUES ('TEMP', ?, ?, ?, ?)");
            $st->execute([$sid, $wid, jtext($p['observacion'] ?? '', 'Observación', 300, false), !empty($p['fecha_entrega']) ? $p['fecha_entrega'] : null]);
            $id = (int)$pdo->lastInsertId();
            $ref = code_gen('OC', $id);
            $pdo->prepare("UPDATE g7_ordenes_compra SET codigo = ? WHERE id = ?")->execute([$ref, $id]);

            $detSt = $pdo->prepare("INSERT INTO g7_orden_detalles (orden_id, producto_id, cantidad, costo_unitario) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $prod = jid($item['producto_id'] ?? 0, 'Producto');
                g7_exists($pdo, 'productos', $prod);
                $qty = jnum($item['cantidad'] ?? 0);
                $cost = jnum($item['costo_unitario'] ?? 0, 'Costo', true);
                $detSt->execute([$id, $prod, $qty, $cost]);
            }
            g7_audit($pdo, 'ORDEN_COMPRA', ['codigo' => $ref, 'lineas' => count($items)]);
            $res = ['id' => $id, 'codigo' => $ref, 'mensaje' => 'Orden de compra registrada'];
        } elseif (preg_match('#^/api/ordenes/(\d+)/recibir$#', $path, $m)) {
            $id = (int)$m[1];
            $st = $pdo->prepare("SELECT * FROM g7_ordenes_compra WHERE id = ? FOR UPDATE");
            $st->execute([$id]);
            $o = $st->fetch();
            if (!$o) throw new ApiError('Orden de compra no encontrada', 404);
            if (in_array($o['estado'], ['RECIBIDA', 'CANCELADA'], true)) throw new ApiError('Esta orden ya está cerrada', 409);

            $lines = $pdo->prepare("SELECT * FROM g7_orden_detalles WHERE orden_id = ?");
            $lines->execute([$id]);
            $created = [];
            foreach ($lines->fetchAll() as $line) {
                $remaining = round((float)$line['cantidad'] - (float)$line['recibido'], 3);
                if ($remaining <= 0) continue;
                $r = g7_create_receipt($pdo, (int)$line['producto_id'], (int)$o['almacen_id'], (int)$o['proveedor_id'], $remaining, (float)$line['costo_unitario'], $id, "Recepción de {$o['codigo']}");
                $pdo->prepare("UPDATE g7_orden_detalles SET recibido = recibido + ? WHERE id = ?")->execute([$remaining, $line['id']]);
                $created[] = $r;
            }
            if (!$created) throw new ApiError('No quedan cantidades pendientes', 409);
            $pdo->prepare("UPDATE g7_ordenes_compra SET estado = 'RECIBIDA' WHERE id = ?")->execute([$id]);
            $res = ['mensaje' => 'Orden recibida y stock actualizado', 'recepciones' => $created];
        } elseif ($path === '/api/recepciones') {
            $res = g7_create_receipt(
                $pdo,
                jid($p['producto_id'] ?? 0, 'Producto'),
                jid($p['almacen_id'] ?? 0, 'Almacén'),
                jid($p['proveedor_id'] ?? 0, 'Proveedor'),
                jnum($p['cantidad'] ?? 0),
                jnum($p['costo_unitario'] ?? 0, 'Costo unitario', true),
                null,
                jtext($p['observacion'] ?? '', 'Observación', 300, false)
            );
        } elseif ($path === '/api/movimientos') {
            $prod = jid($p['producto_id'] ?? 0, 'Producto');
            $wh = jid($p['almacen_id'] ?? 0, 'Almacén');
            $typ = jtext($p['tipo'] ?? '', 'Tipo', 40);
            if (!in_array($typ, ['ENTRADA', 'SALIDA', 'DEVOLUCION'], true)) {
                throw new ApiError('Seleccione entrada, salida o devolución');
            }
            $qty = jnum($p['cantidad'] ?? 0);
            $delta = $typ !== 'SALIDA' ? $qty : -$qty;
            $doc = jtext($p['documento'] ?? '', 'Documento', 50, false);
            $b = g7_balance($pdo, $prod, $wh);
            if ($delta < 0 && ((float)$b['cantidad'] - (float)$b['reservado']) < $qty) {
                throw new ApiError('Disponible insuficiente', 409);
            }
            $after = g7_mutate_stock($pdo, $prod, $wh, $delta, $typ, $doc, jtext($p['tercero'] ?? '', 'Tercero', 100, false), null, jtext($p['observacion'] ?? '', 'Observación', 300, false));
            g7_audit($pdo, 'MOVIMIENTO_MANUAL', ['tipo' => $typ, 'producto_id' => $prod, 'cantidad' => $qty]);
            $res = ['mensaje' => 'Movimiento registrado en Kardex', 'saldo' => $after];
        } elseif ($path === '/api/transferencias') {
            $prod = jid($p['producto_id'] ?? 0, 'Producto');
            $ori = jid($p['origen_id'] ?? 0, 'Almacén origen');
            $dst = jid($p['destino_id'] ?? 0, 'Almacén destino');
            $qty = jnum($p['cantidad'] ?? 0);
            if ($ori === $dst) throw new ApiError('El origen y destino deben ser diferentes');
            g7_exists($pdo, 'almacenes', $ori);
            g7_exists($pdo, 'almacenes', $dst);
            $b = g7_balance($pdo, $prod, $ori);
            if (((float)$b['cantidad'] - (float)$b['reservado']) < $qty) {
                throw new ApiError('No hay stock disponible para trasladar', 409);
            }
            $st = $pdo->prepare("INSERT INTO g7_transferencias (codigo, producto_id, origen_id, destino_id, cantidad, observacion, costo_unitario) VALUES ('TEMP', ?, ?, ?, ?, ?, ?)");
            $st->execute([$prod, $ori, $dst, $qty, jtext($p['observacion'] ?? '', 'Observación', 300, false),$b['costo_promedio']]);
            $id = (int)$pdo->lastInsertId();
            $ref = code_gen('TR', $id);
            $pdo->prepare("UPDATE g7_transferencias SET codigo = ? WHERE id = ?")->execute([$ref, $id]);
            g7_mutate_stock($pdo, $prod, $ori, -$qty, 'TRASLADO_SALIDA', $ref, 'Traslado a otra sede');
            g7_audit($pdo, 'TRASLADO_SALIDA', ['codigo' => $ref, 'cantidad' => $qty]);
            $res = ['codigo' => $ref, 'mensaje' => 'Traslado en tránsito; pendiente de recepción en destino'];
        } elseif (preg_match('#^/api/transferencias/(\d+)/recibir$#', $path, $m)) {
            $id = (int)$m[1];
            $st = $pdo->prepare("SELECT * FROM g7_transferencias WHERE id = ? FOR UPDATE");
            $st->execute([$id]);
            $r = $st->fetch();
            if (!$r) throw new ApiError('Traslado no encontrado', 404);
            if ($r['estado'] !== 'EN_TRANSITO') throw new ApiError('El traslado ya fue recibido', 409);
            if ($r['costo_unitario']===null) throw new ApiError('El traslado histórico no tiene valoración de origen verificable. Revisa su salida en Kardex.',409);
            g7_mutate_stock($pdo, (int)$r['producto_id'], (int)$r['destino_id'], (float)$r['cantidad'], 'TRASLADO_ENTRADA', $r['codigo'], 'Recepción entre sedes',(float)$r['costo_unitario']);
            $pdo->prepare("UPDATE g7_transferencias SET estado = 'RECIBIDA', fecha_recepcion = NOW() WHERE id = ?")->execute([$id]);
            g7_audit($pdo, 'TRASLADO_RECEPCION', ['codigo' => $r['codigo']]);
            $res = ['mensaje' => 'Recepción confirmada, stock de destino actualizado'];
        } elseif ($path === '/api/ajustes') {
            $prod = jid($p['producto_id'] ?? 0, 'Producto');
            $wh = jid($p['almacen_id'] ?? 0, 'Almacén');
            $typ = jtext($p['tipo'] ?? '', 'Tipo', 30);
            if (!in_array($typ, ['MERMA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO'], true)) throw new ApiError('Tipo de ajuste inválido');
            $qty = jnum($p['cantidad'] ?? 0);
            $reason = jtext($p['motivo'] ?? '', 'Motivo', 100);
            $delta = $typ === 'AJUSTE_POSITIVO' ? $qty : -$qty;
            $st = $pdo->prepare("INSERT INTO g7_ajustes (codigo, producto_id, almacen_id, cantidad, tipo, motivo, observacion) VALUES ('TEMP', ?, ?, ?, ?, ?, ?)");
            $st->execute([$prod, $wh, $qty, $typ, $reason, jtext($p['observacion'] ?? '', 'Observación', 300, false)]);
            $id = (int)$pdo->lastInsertId();
            $ref = code_gen($typ === 'MERMA' ? 'MR' : 'AJ', $id);
            $pdo->prepare("UPDATE g7_ajustes SET codigo = ? WHERE id = ?")->execute([$ref, $id]);
            g7_mutate_stock($pdo, $prod, $wh, $delta, $typ, $ref, $reason);
            g7_audit($pdo, 'AJUSTE', ['codigo' => $ref, 'tipo' => $typ, 'cantidad' => $qty]);
            $res = ['codigo' => $ref, 'mensaje' => 'Ajuste registrado en el Kardex'];
        } elseif ($path === '/api/conteos') {
            $wh = jid($p['almacen_id'] ?? 0, 'Almacén');
            g7_exists($pdo, 'almacenes', $wh);
            $items = $p['items'] ?? [];
            if (!is_array($items) || !$items) throw new ApiError('Indique los artículos y cantidades contadas');
            $st = $pdo->prepare("INSERT INTO g7_conteos (codigo, almacen_id, responsable, observacion) VALUES ('TEMP', ?, ?, ?)");
            $st->execute([$wh, jtext($p['responsable'] ?? 'Operador de almacén', 'Responsable', 100), jtext($p['observacion'] ?? '', 'Observación', 300, false)]);
            $id = (int)$pdo->lastInsertId();
            $ref = code_gen('CF', $id);
            $pdo->prepare("UPDATE g7_conteos SET codigo = ? WHERE id = ?")->execute([$ref, $id]);
            $seen = [];
            $det = $pdo->prepare("INSERT INTO g7_conteo_detalles (conteo_id, producto_id, cantidad_sistema, cantidad_contada) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $prod = jid($item['producto_id'] ?? 0, 'Producto');
                if (isset($seen[$prod])) throw new ApiError('No duplique artículos en el conteo');
                $seen[$prod] = true;
                $current = (float)g7_balance($pdo, $prod, $wh)['cantidad'];
                $counted = jnum($item['cantidad_contada'] ?? 0, 'Cantidad contada', true);
                $det->execute([$id, $prod, $current, $counted]);
            }
            g7_audit($pdo, 'CONTEO_ABIERTO', ['codigo' => $ref, 'articulos' => count($seen)]);
            $res = ['codigo' => $ref, 'mensaje' => 'Conteo guardado, pendiente de regularizar'];
        } elseif (preg_match('#^/api/conteos/(\d+)/regularizar$#', $path, $m)) {
            $id = (int)$m[1];
            $st = $pdo->prepare("SELECT * FROM g7_conteos WHERE id = ? FOR UPDATE");
            $st->execute([$id]);
            $o = $st->fetch();
            if (!$o) throw new ApiError('Conteo no encontrado', 404);
            if ($o['estado'] !== 'PENDIENTE') throw new ApiError('El conteo ya está regularizado', 409);
            $linesSt = $pdo->prepare("SELECT * FROM g7_conteo_detalles WHERE conteo_id = ?");
            $linesSt->execute([$id]);
            $lines = $linesSt->fetchAll();
            foreach ($lines as $line) {
                $actual = g7_balance($pdo, (int)$line['producto_id'], (int)$o['almacen_id']);
                if (abs((float)$actual['cantidad'] - (float)$line['cantidad_sistema']) > 0.00001) {
                    throw new ApiError('El stock cambió desde el conteo. Realice un nuevo conteo antes de regularizar', 409);
                }
                if ((float)$line['cantidad_contada'] < (float)$actual['reservado']) {
                    throw new ApiError('Un conteo no puede regularizarse por debajo de unidades reservadas', 409);
                }
            }
            foreach ($lines as $line) {
                $delta = round((float)$line['cantidad_contada'] - (float)$line['cantidad_sistema'], 3);
                if (abs($delta) > 1e-8) {
                    g7_mutate_stock($pdo, (int)$line['producto_id'], (int)$o['almacen_id'], $delta, 'CONTEO_AJUSTE', $o['codigo'], 'Inventario físico', null, 'Regularización de conteo');
                }
            }
            $pdo->prepare("UPDATE g7_conteos SET estado = 'REGULARIZADO', fecha_aplicacion = NOW() WHERE id = ?")->execute([$id]);
            g7_audit($pdo, 'CONTEO_REGULARIZADO', ['codigo' => $o['codigo']]);
            $res = ['mensaje' => 'Diferencias regularizadas y registradas en Kardex'];
        } elseif (preg_match('#^/api/cuentas-pagar/(\d+)/pagar$#', $path, $m)) {
            $id = (int)$m[1];
            $st = $pdo->prepare("SELECT * FROM g7_cuentas_pagar WHERE id = ? FOR UPDATE");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) throw new ApiError('Cuenta por pagar no encontrada', 404);
            $amount = jnum($p['importe'] ?? 0, 'Importe');
            if ((float)$row['pagado'] + $amount > (float)$row['importe'] + 0.00001) {
                throw new ApiError('El pago supera el importe pendiente', 409);
            }
            $medio = (string)($p['medio_pago'] ?? 'transferencia');
            if (!in_array($medio, ['efectivo', 'transferencia', 'deposito'], true)) throw new ApiError('Medio de pago no válido');
            $pdo->prepare("UPDATE g7_cuentas_pagar SET pagado = ROUND(pagado + ?, 2) WHERE id = ?")->execute([$amount, $id]);
            $mensaje = 'Pago registrado correctamente.';
            if ($medio === 'efectivo') {
                // Pago en efectivo → Caja (G8): egreso en la caja abierta de la sucursal del almacén que recibió la compra
                $st = $pdo->prepare("SELECT a.sucursal_id, pr.nombre proveedor FROM g7_recepciones r JOIN almacenes a ON a.id = r.almacen_id
                    JOIN proveedores pr ON pr.id = r.proveedor_id WHERE r.id = ?");
                $st->execute([$row['recepcion_id']]);
                $origen = $st->fetch();
                $st = $pdo->prepare("SELECT cs.id, cs.fondo_inicial
                        + COALESCE((SELECT SUM(CASE WHEN m.tipo = 'ingreso' THEN m.importe ELSE -m.importe END) FROM caja_movimientos m WHERE m.sesion_id = cs.id), 0) AS saldo
                    FROM caja_sesiones cs JOIN cajas c ON c.id = cs.caja_id
                    WHERE c.sucursal_id = ? AND cs.estado = 'abierta' ORDER BY cs.id LIMIT 1 FOR UPDATE");
                $st->execute([$origen['sucursal_id'] ?? 0]);
                $sesion = $st->fetch();
                if (!$sesion) throw new ApiError('Para pagar en efectivo abre primero una caja en la sucursal del almacén (Comercial › Caja)', 409);
                if ($amount > (float)$sesion['saldo'] + 0.00001) {
                    throw new ApiError('La caja abierta no tiene efectivo suficiente: disponible S/ ' . number_format((float)$sesion['saldo'], 2), 409);
                }
                $pdo->prepare("INSERT INTO caja_movimientos (sesion_id, tipo, fecha, importe, concepto, g7_cuenta_pagar_id, creado_por) VALUES (?, 'egreso', NOW(), ?, ?, ?, ?)")
                    ->execute([$sesion['id'], $amount, mb_substr("Pago {$row['codigo']} a {$origen['proveedor']}", 0, 255), $id, usuario_actual()['id']]);
                $mensaje = 'Pago registrado y descontado de la caja abierta.';
            }
            if ($medio !== 'efectivo') {
                // Transferencia o depósito → Tesorería (G8): egreso en la cuenta bancaria de la sede del almacén que recibió la compra
                $st = $pdo->prepare("SELECT a.sucursal_id, pr.nombre proveedor FROM g7_recepciones r JOIN almacenes a ON a.id = r.almacen_id JOIN proveedores pr ON pr.id = r.proveedor_id WHERE r.id = ?");
                $st->execute([$row['recepcion_id']]);
                $origen = $st->fetch();
                try { $cuenta = tes_cuenta_sede($pdo, (int)$origen['sucursal_id']); } catch (RuntimeException $e) { throw new ApiError($e->getMessage(), 409); }
                $saldo = tes_saldo($pdo, (int)$cuenta['id']);
                if ($amount > $saldo + 0.00001) throw new ApiError('Fondos insuficientes en ' . $cuenta['nombre'] . ': saldo S/ ' . number_format($saldo, 2), 409);
                $st = $pdo->prepare('SELECT COUNT(*) + 1 FROM tesoreria_movimientos WHERE g7_cuenta_pagar_id = ?');
                $st->execute([$id]);
                tes_movimiento($pdo, $cuenta, 'egreso', 'pago_proveedor', $amount, 'PAGO-' . $row['codigo'] . '-' . $st->fetchColumn(),
                    "Pago {$row['codigo']} a {$origen['proveedor']} ($medio)", ['g7_cuenta_pagar_id' => $id]);
                $mensaje = 'Pago registrado y descontado de la cuenta ' . $cuenta['nombre'] . '.';
            }
            g7_audit($pdo, 'PAGO_PROVEEDOR', ['cuenta' => $row['codigo'], 'importe' => $amount, 'medio' => $medio]);
            $res = ['mensaje' => $mensaje];
        } elseif ($path === '/api/minimos') {
            $prod = jid($p['producto_id'] ?? 0, 'Producto');
            $wh = jid($p['almacen_id'] ?? 0, 'Almacén');
            $val = jnum($p['minimo'] ?? 0, 'Stock mínimo', true);
            $b = g7_balance($pdo, $prod, $wh);
            $pdo->prepare("UPDATE g7_saldos SET minimo = ? WHERE id = ?")->execute([$val, $b['id']]);
            g7_audit($pdo, 'MINIMO_ACTUALIZADO', ['producto_id' => $prod, 'almacen_id' => $wh, 'minimo' => $val]);
            $res = ['mensaje' => 'Umbral de stock actualizado'];
        } else {
            throw new ApiError('Ruta de operación no disponible', 404);
        }

        $pdo->commit();
        json_response(200, ['ok' => true, 'data' => $res]);
    }

    throw new ApiError('Método HTTP no permitido', 405);
} catch (ApiError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response($e->statusCode, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // El detalle técnico (SQL, rutas) va al log del servidor; al usuario solo un código de referencia
    $ref = 'LOG-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2));
    error_log("[$ref] Logística API $method $route: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_response(500, ['ok' => false, 'error' => "Error interno. Código de referencia: $ref"]);
}

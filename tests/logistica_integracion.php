<?php
declare(strict_types=1);

/**
 * Integración de Logística contra las funciones y tablas reales del ERP.
 * Uso: C:\xampp\php\php.exe tests/logistica_integracion.php --qa
 * No migra, no importa SQL y nunca confirma la transacción de pruebas.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!in_array('--qa', $argv, true)) {
    echo json_encode([
        'ok' => false,
        'uso' => 'php tests/logistica_integracion.php --qa',
        'condicion' => 'Sólo escribe si .env apunta a localhost:43327 y @@datadir coincide exactamente con ERP-main/.runtime/mariadb.',
        'limpieza' => 'Transacción exterior y rollback, también cuando una comprobación falla.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

final class QaAssertionFailure extends RuntimeException {}

$qaRoot = dirname(__DIR__);
$qaResults = [];
$qaFatal = null;
$qaPdo = null;
$qaBaseline = null;
$qaStarted = false;
$qaRollback = false;
$qaGuardPassed = false;
$qaOriginalSession = null;
$qaCounters = ['fixture' => 0, 'command' => 0, 'scenario' => 0];
$qaRun = 'QL' . date('ymdHis') . bin2hex(random_bytes(2));

function qa_check(string $name, bool $condition, $expected = null, $actual = null): void {
    global $qaResults;
    $row = ['prueba' => $name, 'ok' => $condition];
    if (!$condition) {
        $row['esperado'] = $expected;
        $row['observado'] = $actual;
    }
    $qaResults[] = $row;
    if (!$condition) throw new QaAssertionFailure($name);
}

function qa_same(string $name, $expected, $actual): void {
    qa_check($name, $expected === $actual, $expected, $actual);
}

function qa_row(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

function qa_value(PDO $pdo, string $sql, array $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function qa_stock(PDO $pdo, array $fixture): array {
    return qa_row($pdo, 'SELECT cantidad,reservado,costo_promedio FROM g7_saldos WHERE producto_id=? AND almacen_id=?', [$fixture['product'], $fixture['warehouse']]);
}

function qa_milli($value): int {
    $text = (string)$value;
    if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $text, $m)) throw new RuntimeException('Decimal inesperado en una observación de QA.');
    $fraction = $m[3] ?? '';
    if (strlen($fraction) > 3 && trim(substr($fraction, 3), '0') !== '') throw new RuntimeException('La observación contiene una fracción menor que una milésima.');
    $n = ((int)$m[2] * 1000) + (int)str_pad(substr($fraction, 0, 3), 3, '0');
    return ($m[1] ?? '') === '-' ? -$n : $n;
}

function qa_snapshot(PDO $pdo): array {
    $tables = ['ventas','venta_detalles','g7_despachos','g7_envios','g7_envio_eventos','g7_envio_intentos','g7_envio_incidencias','g7_envio_resoluciones','g7_envio_operaciones','g7_kardex','g7_auditoria','guias_cabecera','guias_detalle','rol_permiso','documentos_cabecera','documentos_detalle','stock_cabecera','stock_detalle'];
    $out = [];
    foreach ($tables as $table) $out[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $out['saldos'] = $pdo->query('SELECT * FROM g7_saldos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $out['usuarios_estado'] = $pdo->query('SELECT id,estado FROM usuarios ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $out['usuario_rol'] = $pdo->query('SELECT * FROM usuario_rol ORDER BY usuario_id,rol_id')->fetchAll(PDO::FETCH_ASSOC);
    // El sincronizador puede actualizar filas existentes sin cambiar sus recuentos.
    foreach (['documentos_cabecera','documentos_detalle','stock_cabecera','stock_detalle','guias_cabecera','guias_detalle'] as $table) {
        $out[$table.'_contenido'] = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    return $out;
}

function qa_fingerprint(PDO $pdo, int $id): string {
    $parts = [qa_row($pdo, 'SELECT * FROM g7_despachos WHERE id=?', [$id]), qa_row($pdo, 'SELECT * FROM g7_envios WHERE despacho_id=?', [$id])];
    $d = $parts[0];
    $parts[] = qa_row($pdo, 'SELECT * FROM g7_saldos WHERE producto_id=? AND almacen_id=?', [$d['producto_id'], $d['almacen_id']]);
    foreach (['g7_envio_eventos','g7_envio_intentos','g7_envio_incidencias','g7_envio_resoluciones','g7_envio_operaciones'] as $table) {
        $st = $pdo->prepare("SELECT * FROM `$table` WHERE despacho_id=? ORDER BY id");
        $st->execute([$id]);
        $parts[] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $parts[] = qa_value($pdo, 'SELECT COUNT(*) FROM g7_kardex WHERE documento=?', [$d['codigo']]);
    return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
}

function qa_reject(PDO $pdo, int $id, string $name, callable $operation, int $status = 409): void {
    $before = qa_fingerprint($pdo, $id);
    $caught = null;
    try { $operation(); } catch (ApiError $e) { $caught = $e; }
    qa_same($name . ': código de rechazo', $status, $caught?->statusCode);
    qa_same($name . ': no cambia expediente, saldo ni registros', $before, qa_fingerprint($pdo, $id));
}

function qa_command(PDO $pdo, int $id, string $action, array $data = []): array {
    global $qaCounters, $qaRun;
    $payload = $data + [
        'version' => (int)qa_value($pdo, 'SELECT version FROM g7_envios WHERE despacho_id=?', [$id]),
        'idempotency_key' => $qaRun . '-cmd-' . (++$qaCounters['command']),
    ];
    $response = g7_envio_operar($pdo, $id, $action, $payload);
    qa_check($action . ': conserva la transacción del llamador', $pdo->inTransaction());
    return ['payload' => $payload, 'response' => $response];
}

function qa_fixture(PDO $pdo, array $refs, string $quantity = '100.000', ?int $unit = null, bool $reserve = true): array {
    global $qaCounters, $qaRun;
    $number = $qaRun . '-' . (++$qaCounters['fixture']);
    $st = $pdo->prepare("INSERT INTO ventas(sucursal_id,cliente_id,vendedor_id,numero,fecha,moneda,estado,subtotal,total,observaciones,creado_por,condicion_pago) VALUES(?,?,?,?,CURDATE(),'PEN','confirmada',?,?,?,?,'contado')");
    $st->execute([$refs['branch'],$refs['client'],$refs['seller'],$number,$quantity,$quantity,'Fixture transaccional QA Logística',$refs['user']]);
    $sale = (int)$pdo->lastInsertId();
    $st = $pdo->prepare('INSERT INTO venta_detalles(venta_id,linea,producto_id,unidad_id,codigo_producto,descripcion,cantidad,precio_unitario,subtotal,total) VALUES(?,1,?,?,?,?,?,1,?,?)');
    $st->execute([$sale,$refs['product'],$unit ?? $refs['unit'],$refs['product_code'],'Producto existente: fixture QA',$quantity,$quantity,$quantity]);
    if ($reserve) g7_reservar_venta($pdo, $sale);
    $d = qa_row($pdo, 'SELECT * FROM g7_despachos WHERE venta_id=?', [$sale]);
    return $refs + ['sale'=>$sale,'id'=>(int)($d['id']??0),'code'=>$d['codigo']??'','quantity'=>$quantity,'number'=>$number];
}

function qa_guide(PDO $pdo, array $f, ?int $client = null): int {
    global $qaCounters, $qaRun;
    $number = $qaRun . 'G' . (++$qaCounters['fixture']);
    $st = $pdo->prepare("INSERT INTO guias_cabecera(numero,fecha,cliente_id,vendedor_id,sucursal_id,estado_registro,created_by,version,validated_by,validated_at,origen,origen_ref) VALUES(?,CURDATE(),?,?,?,'VALIDADO',?,1,?,NOW(),'QA_LOGISTICA',?)");
    $st->execute([$number,$client ?? $f['client'],$f['seller'],$f['branch'],$f['user'],$f['user'],$number]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES(?,?,?,?)')->execute([$id,$f['product'],$f['unit'],$f['quantity']]);
    return $id;
}

function qa_ready(PDO $pdo, array $f): array {
    qa_command($pdo,$f['id'],'configurar',['destino'=>'Fundo de prueba QA','transporte'=>'Vehículo y conductor QA']);
    qa_command($pdo,$f['id'],'preparar');
    $guide = qa_guide($pdo,$f);
    qa_command($pdo,$f['id'],'guia',['guia_id'=>$guide]);
    return ['guide'=>$guide,'departure'=>qa_command($pdo,$f['id'],'salir')];
}

function qa_incident(PDO $pdo, int $id, string $type = 'INFORMATIVA'): int {
    qa_command($pdo,$id,'incidencia',['tipo'=>$type,'motivo'=>'Caso operativo QA','evidencia'=>'Acta de prueba QA']);
    return (int)qa_value($pdo,'SELECT MAX(id) FROM g7_envio_incidencias WHERE despacho_id=?',[$id]);
}

function qa_scenario(PDO $pdo, string $name, callable $test): void {
    global $qaCounters, $qaResults;
    $savepoint = 'qa_case_' . (++$qaCounters['scenario']);
    $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $test();
    } catch (QaAssertionFailure $e) {
        // La aserción ya conserva su resultado y su diagnóstico.
    } catch (Throwable $e) {
        $qaResults[] = ['prueba'=>$name,'ok'=>false,'error'=>get_class($e).': '.$e->getMessage()];
    } finally {
        if (!$pdo->inTransaction()) throw new RuntimeException('El código probado confirmó o eliminó la transacción exterior.');
        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
    }
}

try {
    $qaEnvPath = $qaRoot . '/.env';
    if (!is_file($qaEnvPath)) throw new RuntimeException('Falta .env local; no se abrió la base ni se ejecutaron pruebas.');
    $qaEnv = parse_ini_file($qaEnvPath);
    if (!is_array($qaEnv) || ($qaEnv['DB_HOST'] ?? '') !== '127.0.0.1' || (string)($qaEnv['DB_PORT'] ?? '') !== '43327') {
        throw new RuntimeException('Guardia: .env no apunta al host y puerto QA autorizados. No se ejecutaron escrituras.');
    }
    unset($qaEnv);
    require_once $qaRoot . '/core/auth.php';
    require_once $qaRoot . '/core/permisos.php';
    require_once $qaRoot . '/modulos/logistica/stock_lib.php';
    require_once $qaRoot . '/modulos/logistica/workflow.php';
    // Carga directa: sin bootstrap Data Hub que redefina los helpers del ERP.
    require_once $qaRoot . '/modulos/datahub/app/Services/ErpSyncService.php';
    $qaPdo = $pdo;
    $normalizePath = static function (string $path): string {
        $resolved = realpath($path);
        return strtolower(rtrim(str_replace('\\','/',$resolved !== false ? $resolved : $path),'/'));
    };
    $expectedData = $normalizePath($qaRoot . '/.runtime/mariadb');
    $actualData = $normalizePath((string)$pdo->query('SELECT @@datadir')->fetchColumn());
    if ($actualData !== $expectedData || !is_dir($qaRoot . '/.runtime/mariadb')) {
        throw new RuntimeException('Guardia: @@datadir no coincide exactamente con ERP-main/.runtime/mariadb. No se ejecutaron escrituras.');
    }
    $qaGuardPassed = true;
    qa_check('Guardia de host, puerto y directorio QA exacto', true);
    $qaTables = ['ventas','venta_detalles','g7_despachos','g7_saldos','g7_kardex','g7_auditoria','guias_cabecera','guias_detalle','g7_envios','g7_envio_eventos','g7_envio_intentos','g7_envio_incidencias','g7_envio_resoluciones','g7_envio_operaciones','rol_permiso','usuarios','usuario_rol','documentos_cabecera','documentos_detalle','stock_cabecera','stock_detalle'];
    $st = $pdo->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . implode(',',array_fill(0,count($qaTables),'?')) . ')');
    $st->execute($qaTables);
    $engines = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($qaTables as $table) {
        if (strtoupper((string)($engines[$table]??'')) !== 'INNODB') throw new RuntimeException('Falta una tabla transaccional requerida o la migración de Logística: '.$table);
    }
    qa_check('Todas las tablas afectadas existen y usan InnoDB', true);
    $qaOriginalSession = $_SESSION;
    $admin = qa_row($pdo,"SELECT u.id,u.nombre,LOWER(r.nombre) rol FROM usuarios u JOIN usuario_rol ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id WHERE LOWER(r.nombre)='administrador' AND u.estado=1 ORDER BY u.id LIMIT 1");
    $ref = qa_row($pdo,'SELECT a.id warehouse,a.sucursal_id branch,p.id product,p.codigo product_code,p.unidad_base_id unit FROM almacenes a JOIN sucursales s ON s.id=a.sucursal_id CROSS JOIN productos p WHERE a.estado=1 AND s.estado=1 AND p.estado=1 AND p.unidad_base_id IS NOT NULL AND a.id=(SELECT MIN(a2.id) FROM almacenes a2 WHERE a2.sucursal_id=a.sucursal_id AND a2.estado=1) ORDER BY a.id,p.id LIMIT 1');
    $client = (int)qa_value($pdo,'SELECT id FROM clientes ORDER BY id LIMIT 1');
    $seller = (int)qa_value($pdo,'SELECT id FROM vendedores WHERE estado=1 ORDER BY id LIMIT 1');
    if (!$admin || !$ref || !$client || !$seller) throw new RuntimeException('Faltan usuario administrador o maestros de ejemplo para preparar fixtures.');
    $_SESSION['usuario'] = ['id'=>(int)$admin['id'],'nombre'=>$admin['nombre'],'rol'=>$admin['rol']];
    $_SESSION['sede_activa'] = (int)$ref['branch'];
    $refs = array_map(static fn($x)=>is_numeric($x)?(int)$x:$x,$ref) + ['user'=>(int)$admin['id'],'client'=>$client,'seller'=>$seller];
    if (!g7_permiso_persistido($pdo,'editar')) throw new RuntimeException('El administrador de QA no tiene el permiso editar persistido.');
    $qaBaseline = qa_snapshot($pdo);
    $pdo->beginTransaction();
    $qaStarted = true;
    g7_mutate_stock($pdo,$refs['product'],$refs['warehouse'],10000.0,'QA_FIXTURE',$qaRun,'',1.0,'Existencias temporales para pruebas; rollback final');

    qa_scenario($pdo,'Cantidades exactas',function () use ($pdo): void {
        qa_same('Una milésima conserva su valor entero exacto',1,g7_milesimas('0.001'));
        qa_same('Tres decimales conservan 1.125 sin redondeo',1125,g7_milesimas('1.125'));
        qa_same('Normalización de DECIMAL(18,4) de la base sin perder información',100000,g7_milesimas_bd('100.0000'));
        foreach (['0.0001','1.0009','1e3','-1','NaN','1,25'] as $invalid) {
            $denied=false;try { g7_milesimas($invalid); } catch (ApiError $e) { $denied=true; }
            qa_check('Cantidad inválida rechazada: '.$invalid,$denied);
        }
        $denied=false;try { g7_milesimas_bd('1.0001'); } catch (ApiError $e) { $denied=true; }
        qa_check('Una cuarta cifra no nula de la base no se redondea',$denied);
    });

    qa_scenario($pdo,'Venta y reserva',function () use ($pdo,$refs): void {
        $before=qa_stock($pdo,$refs);$k=(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_kardex');
        $f=qa_fixture($pdo,$refs);
        qa_check('Comercial crea un despacho real y metadatos logísticos',$f['id']>0 && (int)qa_value($pdo,'SELECT COUNT(*) FROM g7_envios WHERE despacho_id=?',[$f['id']])===1);
        $after=qa_stock($pdo,$f);
        qa_same('Reservar no cambia existencia física',qa_milli($before['cantidad']),qa_milli($after['cantidad']));
        qa_same('Reserva comercial agrega exactamente 100',qa_milli($before['reservado'])+100000,qa_milli($after['reservado']));
        qa_same('Reservar no genera una salida en Kardex',$k,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_kardex'));
        $fingerprint=qa_fingerprint($pdo,$f['id']);$rejected=false;
        try { g7_reservar_venta($pdo,$f['sale']); } catch (ApiError|PDOException $e) { $rejected=true; }
        qa_check('Confirmar de nuevo no crea otro despacho/reserva',$rejected);
        qa_same('Duplicado comercial conserva saldo e historial',$fingerprint,qa_fingerprint($pdo,$f['id']));
        qa_same('Una línea comercial conserva un solo despacho',1,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_despachos WHERE venta_id=?',[$f['sale']]));
    });

    qa_scenario($pdo,'Confirmación comercial multilínea con stock insuficiente',function () use ($pdo,$refs): void {
        $other=qa_row($pdo,'SELECT id product,codigo product_code,unidad_base_id unit FROM productos WHERE id<>? AND estado=1 AND unidad_base_id IS NOT NULL ORDER BY id LIMIT 1',[$refs['product']]);
        if (!$other) throw new RuntimeException('QA multilínea necesita dos productos activos con unidad base.');
        $second=array_replace($refs,['product'=>(int)$other['product'],'product_code'=>$other['product_code'],'unit'=>(int)$other['unit']]);
        $otherStock=g7_balance($pdo,$second['product'],$second['warehouse']);
        $available=qa_milli($otherStock['cantidad'])-qa_milli($otherStock['reservado']);
        // La segunda línea dispone exactamente de 5; la venta necesita 6.
        $delta=(5000-$available)/1000;
        if ($delta!==0.0) g7_mutate_stock($pdo,$second['product'],$second['warehouse'],$delta,'QA_FIXTURE','QA_MULTILINEA','',null,'Ajuste temporal de disponibilidad; rollback final');
        qa_same('Segundo producto dispone de 5 antes de confirmar',5000,qa_milli(qa_stock($pdo,$second)['cantidad'])-qa_milli(qa_stock($pdo,$second)['reservado']));
        $f=qa_fixture($pdo,$refs,'10.000',null,false);
        $pdo->prepare("UPDATE ventas SET estado='borrador',subtotal=16,total=16 WHERE id=?")->execute([$f['sale']]);
        $pdo->prepare('INSERT INTO venta_detalles(venta_id,linea,producto_id,unidad_id,codigo_producto,descripcion,cantidad,precio_unitario,subtotal,total) VALUES(?,2,?,?,?,?,6,1,6,6)')
            ->execute([$f['sale'],$second['product'],$second['unit'],$second['product_code'],'Segundo producto insuficiente QA']);
        $before=qa_snapshot($pdo);$firstStock=qa_stock($pdo,$f);$secondStock=qa_stock($pdo,$second);$caught=null;
        $pdo->exec('SAVEPOINT qa_commercial_confirmation');
        try {
            // La confirmación de Comercial cambia el estado y reserva dentro de la misma transacción.
            $pdo->prepare("UPDATE ventas SET estado='confirmada' WHERE id=?")->execute([$f['sale']]);
            g7_reservar_venta($pdo,$f['sale']);
        } catch (ApiError $e) {
            $caught=$e;
        } finally {
            $pdo->exec('ROLLBACK TO SAVEPOINT qa_commercial_confirmation');
            $pdo->exec('RELEASE SAVEPOINT qa_commercial_confirmation');
        }
        qa_same('La segunda línea insuficiente rechaza la confirmación completa',409,$caught?->statusCode);
        qa_check('El rechazo identifica stock insuficiente',$caught!==null && str_contains($caught->getMessage(),'Stock insuficiente'));
        qa_check('El rollback comercial mantiene la transacción exterior',$pdo->inTransaction());
        qa_same('La venta vuelve a borrador al fallar su segunda línea','borrador',qa_value($pdo,'SELECT estado FROM ventas WHERE id=?',[$f['sale']]));
        qa_same('No queda un despacho parcial de la primera línea',0,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_despachos WHERE venta_id=?',[$f['sale']]));
        qa_same('Primera línea conserva stock y reserva anteriores',$firstStock,qa_stock($pdo,$f));
        qa_same('Segunda línea conserva stock y reserva anteriores',$secondStock,qa_stock($pdo,$second));
        qa_same('Fallo multilínea revierte metadatos, Kardex, auditoría y demás filas',$before,qa_snapshot($pdo));
    });

    qa_scenario($pdo,'Dos despachos de una venta comparten guía y mantienen balances propios',function () use ($pdo,$refs): void {
        $before=qa_stock($pdo,$refs);$base=qa_fixture($pdo,$refs,'60.000',null,false);
        $pdo->prepare('UPDATE ventas SET subtotal=100,total=100 WHERE id=?')->execute([$base['sale']]);
        $pdo->prepare('INSERT INTO venta_detalles(venta_id,linea,producto_id,unidad_id,codigo_producto,descripcion,cantidad,precio_unitario,subtotal,total) VALUES(?,2,?,?,?,?,40,1,40,40)')
            ->execute([$base['sale'],$refs['product'],$refs['unit'],$refs['product_code'],'Segunda línea del mismo producto QA']);
        $codes=g7_reservar_venta($pdo,$base['sale']);
        $st=$pdo->prepare('SELECT id,codigo,cantidad FROM g7_despachos WHERE venta_id=? ORDER BY id');$st->execute([$base['sale']]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
        qa_same('Una venta con dos líneas genera exactamente dos despachos',2,count($rows));
        qa_same('La reserva devuelve dos referencias diferentes',2,count(array_unique($codes)));
        qa_same('Las líneas de 60 y 40 reservan 100 en conjunto',qa_milli($before['reservado'])+100000,qa_milli(qa_stock($pdo,$refs)['reservado']));
        qa_same('Reservar las dos líneas no duplica la existencia',$before['cantidad'],qa_stock($pdo,$refs)['cantidad']);
        $a=array_replace($base,['id'=>(int)$rows[0]['id'],'code'=>$rows[0]['codigo'],'quantity'=>'60.000']);
        $b=array_replace($base,['id'=>(int)$rows[1]['id'],'code'=>$rows[1]['codigo'],'quantity'=>'40.000']);
        foreach ([$a,$b] as $f) {
            qa_command($pdo,$f['id'],'configurar',['destino'=>'Destino común de venta multilínea QA','transporte'=>'Conductor de entrega QA']);
            qa_command($pdo,$f['id'],'preparar');
        }
        $guideCount=(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera');
        $guide=qa_guide($pdo,array_replace($base,['quantity'=>'100.000']));
        $guideBefore=qa_row($pdo,'SELECT * FROM guias_cabecera WHERE id=?',[$guide]);
        $guideLineBefore=qa_row($pdo,'SELECT * FROM guias_detalle WHERE guia_id=?',[$guide]);
        qa_command($pdo,$a['id'],'guia',['guia_id'=>$guide]);
        qa_command($pdo,$b['id'],'guia',['guia_id'=>$guide]);
        qa_same('Los dos despachos referencian la misma guía compatible',2,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_envios WHERE despacho_id IN (?,?) AND guia_id=?',[$a['id'],$b['id'],$guide]));
        qa_same('Compartir una guía no crea una segunda cabecera',$guideCount+1,(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera'));
        qa_command($pdo,$a['id'],'salir');
        qa_same('Primera salida descuenta sólo 60',qa_milli($before['cantidad'])-60000,qa_milli(qa_stock($pdo,$refs)['cantidad']));
        qa_same('Tras salir 60 se conservan las 40 reservadas de la segunda línea',qa_milli($before['reservado'])+40000,qa_milli(qa_stock($pdo,$refs)['reservado']));
        qa_same('Primera salida no adelanta la segunda','PREPARACION',qa_value($pdo,'SELECT estado FROM g7_despachos WHERE id=?',[$b['id']]));
        qa_command($pdo,$b['id'],'salir');
        $stockAfterDepartures=qa_stock($pdo,$refs);
        qa_same('Ambas salidas descuentan exactamente 100',qa_milli($before['cantidad'])-100000,qa_milli($stockAfterDepartures['cantidad']));
        qa_same('Ambas salidas consumen sólo las reservas de esas líneas',$before['reservado'],$stockAfterDepartures['reservado']);
        qa_same('Se conserva una salida en Kardex por despacho',2,(int)qa_value($pdo,"SELECT COUNT(*) FROM g7_kardex WHERE documento IN (?,?) AND tipo='VENTA_DESPACHO'",[$a['code'],$b['code']]));
        qa_same('Kardex de ambas referencias suma 100, sin doble descuento',100000,qa_milli(qa_value($pdo,"SELECT SUM(salida) FROM g7_kardex WHERE documento IN (?,?) AND tipo='VENTA_DESPACHO'",[$a['code'],$b['code']])));
        qa_command($pdo,$a['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'60','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Recepción primera línea']);
        $firstBeforeSecond=qa_row($pdo,'SELECT * FROM g7_envios WHERE despacho_id=?',[$a['id']]);
        qa_command($pdo,$b['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'15','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Recepción de 15 de la segunda línea']);
        qa_same('Primera línea conserva su balance entregado de 60',['enviado'=>'60.000','aceptado'=>'60.000','retornado'=>'0.000','perdida'=>'0.000','pendiente'=>'0.000'],g7_envio_expediente($pdo,$a['id'])['balance']);
        qa_same('Segunda línea tiene balance independiente de 15 recibidas y 25 pendientes',['enviado'=>'40.000','aceptado'=>'15.000','retornado'=>'0.000','perdida'=>'0.000','pendiente'=>'25.000'],g7_envio_expediente($pdo,$b['id'])['balance']);
        qa_same('Aceptar la segunda línea no modifica metadatos de la primera',$firstBeforeSecond,qa_row($pdo,'SELECT * FROM g7_envios WHERE despacho_id=?',[$a['id']]));
        qa_reject($pdo,$b['id'],'Segunda línea no puede consumir las cantidades de la primera',fn()=>qa_command($pdo,$b['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'26','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Exceso QA']));
        qa_command($pdo,$a['id'],'cerrar');
        qa_same('Cerrar primera línea mantiene abierta la segunda','EN_RUTA',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$b['id']]));
        qa_command($pdo,$b['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'25','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Recepción del saldo de la segunda línea']);
        qa_command($pdo,$b['id'],'cerrar');
        qa_same('Las entregas y cierres no vuelven a tocar stock',$stockAfterDepartures,qa_stock($pdo,$refs));
        qa_same('Primera línea conserva un intento propio',1,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_envio_intentos WHERE despacho_id=?',[$a['id']]));
        qa_same('Segunda línea conserva sus dos intentos propios',2,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_envio_intentos WHERE despacho_id=?',[$b['id']]));
        qa_same('Guía compartida conserva número, versión y cliente',$guideBefore,qa_row($pdo,'SELECT * FROM guias_cabecera WHERE id=?',[$guide]));
        qa_same('Guía compartida conserva su detalle de 100',$guideLineBefore,qa_row($pdo,'SELECT * FROM guias_detalle WHERE guia_id=?',[$guide]));
        qa_same('Al terminar sigue existiendo sólo una guía compartida',$guideCount+1,(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera'));
    });

    qa_scenario($pdo,'Preparación, guía y versiones',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);
        qa_reject($pdo,$f['id'],'Preparar sin transporte',fn()=>qa_command($pdo,$f['id'],'preparar'));
        qa_command($pdo,$f['id'],'configurar',['destino'=>'Fundo QA','transporte'=>'Conductor QA']);
        qa_command($pdo,$f['id'],'preparar');
        qa_same('Se alcanza preparado con origen DECIMAL(18,4) válido','PREPARADO',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$f['id']]));
        qa_reject($pdo,$f['id'],'Salir sin guía',fn()=>qa_command($pdo,$f['id'],'salir'));
        $otherClient=(int)qa_value($pdo,'SELECT id FROM clientes WHERE id<>? ORDER BY id LIMIT 1',[$refs['client']]);
        if (!$otherClient) throw new RuntimeException('QA de guía ajena necesita dos clientes existentes.');
        $wrongGuide=qa_guide($pdo,$f,$otherClient);
        qa_reject($pdo,$f['id'],'Guía de otro cliente',fn()=>qa_command($pdo,$f['id'],'guia',['guia_id'=>$wrongGuide]));
        $guide=qa_guide($pdo,$f);qa_command($pdo,$f['id'],'guia',['guia_id'=>$guide]);
        $version=(int)qa_value($pdo,'SELECT version FROM g7_envios WHERE despacho_id=?',[$f['id']]);
        qa_reject($pdo,$f['id'],'Versión obsoleta',fn()=>qa_command($pdo,$f['id'],'configurar',['version'=>$version-1,'destino'=>'Cambio obsoleto','transporte'=>'QA']));
        $pdo->prepare('UPDATE guias_cabecera SET version=version+1 WHERE id=?')->execute([$guide]);
        qa_reject($pdo,$f['id'],'Guía modificada después de asociar',fn()=>qa_command($pdo,$f['id'],'salir'));
        qa_command($pdo,$f['id'],'guia',['guia_id'=>$guide]);
        qa_command($pdo,$f['id'],'salir');
        qa_same('Revisión y reasociación de versión permiten salir','EN_RUTA',qa_value($pdo,'SELECT estado FROM g7_despachos WHERE id=?',[$f['id']]));
    });

    qa_scenario($pdo,'Salida, aceptación y cierre normales',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);$stock=qa_stock($pdo,$f);qa_ready($pdo,$f);$salida=qa_stock($pdo,$f);
        qa_same('Salida descuenta existencia física una vez',qa_milli($stock['cantidad'])-100000,qa_milli($salida['cantidad']));
        qa_same('Salida consume sólo su reserva',qa_milli($stock['reservado'])-100000,qa_milli($salida['reservado']));
        qa_same('Una salida en Kardex',1,(int)qa_value($pdo,"SELECT COUNT(*) FROM g7_kardex WHERE documento=? AND tipo='VENTA_DESPACHO'",[$f['code']]));
        qa_same('Kardex registra las 100 salidas',100000,qa_milli(qa_value($pdo,"SELECT SUM(salida) FROM g7_kardex WHERE documento=? AND tipo='VENTA_DESPACHO'",[$f['code']])));
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'100.000','cantidad_rechazada'=>'0','receptor'=>'Receptor QA','evidencia'=>'Acta QA']);
        $exp=g7_envio_expediente($pdo,$f['id']);
        qa_same('Aceptación completa deja pendiente cero','0.000',$exp['balance']['pendiente']);
        qa_same('Aceptación completa marca entrega física','ENTREGADO',$exp['envio']['estado']);
        qa_same('Entrega no descuenta stock otra vez',$salida,qa_stock($pdo,$f));
        qa_command($pdo,$f['id'],'cerrar');
        qa_same('Cierre normal persistido','CERRADO',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$f['id']]));
        qa_check('Historia conserva preparar, guía, salida, intento y cierre',count(g7_envio_expediente($pdo,$f['id'])['historial'])>=6);
    });

    qa_scenario($pdo,'Emisión de guía operativa real y repetición',function () use ($pdo,$refs,$qaRun): void {
        $f=qa_fixture($pdo,$refs);
        qa_command($pdo,$f['id'],'configurar',['destino'=>'Destino QA','transporte'=>'Conductor QA']);
        qa_command($pdo,$f['id'],'preparar');
        $count=(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera');
        $emit=qa_command($pdo,$f['id'],'emitir_guia',['numero'=>$qaRun.'GE','fecha'=>date('Y-m-d')]);
        $exp=g7_envio_expediente($pdo,$f['id']);$guideId=(int)$exp['envio']['guia_id'];
        qa_check('Emisión crea y asocia una guía real',$guideId>0);
        qa_same('Emisión no crea documentos adicionales',$count+1,(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera'));
        qa_same('Guía operativa sale validada','VALIDADO',qa_value($pdo,'SELECT estado_registro FROM guias_cabecera WHERE id=?',[$guideId]));
        $line=qa_row($pdo,'SELECT producto_id,unidad_id,cantidad FROM guias_detalle WHERE guia_id=?',[$guideId]);
        qa_same('Guía deriva el producto del despacho',$f['product'],(int)$line['producto_id']);
        qa_same('Guía deriva su cantidad autorizada',100000,qa_milli($line['cantidad']));
        qa_command($pdo,$f['id'],'salir');
        $before=qa_fingerprint($pdo,$f['id']);$replay=g7_envio_operar($pdo,$f['id'],'emitir_guia',$emit['payload']);
        qa_same('Emisión repetida tras salir devuelve respuesta original',true,$replay['replayed']);
        qa_same('Replay de emisión no crea guía huérfana',$count+1,(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera'));
        qa_same('Replay de emisión conserva expediente',$before,qa_fingerprint($pdo,$f['id']));
        $other=qa_fixture($pdo,$refs);
        qa_command($pdo,$other['id'],'configurar',['destino'=>'Otro destino QA','transporte'=>'Otro conductor QA']);
        qa_command($pdo,$other['id'],'preparar');
        qa_reject($pdo,$other['id'],'Número de guía duplicado',fn()=>qa_command($pdo,$other['id'],'emitir_guia',['numero'=>$qaRun.'GE','fecha'=>date('Y-m-d')]));
        qa_same('Número duplicado no deja otro documento',$count+1,(int)qa_value($pdo,'SELECT COUNT(*) FROM guias_cabecera'));
        $pdo->exec('SAVEPOINT qa_create_permission');
        try {
            $pdo->exec("DELETE rp FROM rol_permiso rp JOIN roles r ON r.id=rp.rol_id JOIN permisos p ON p.id=rp.permiso_id WHERE LOWER(r.nombre)='administrador' AND p.nombre='crear'");
            qa_reject($pdo,$f['id'],'Replay de emitir sin permiso crear',fn()=>g7_envio_operar($pdo,$f['id'],'emitir_guia',$emit['payload']),403);
        } finally { $pdo->exec('ROLLBACK TO SAVEPOINT qa_create_permission');$pdo->exec('RELEASE SAVEPOINT qa_create_permission'); }
    });

    qa_scenario($pdo,'Data Hub preserva la guía operativa antes y después de salir',function () use ($pdo,$refs,$qaRun): void {
        $f=qa_fixture($pdo,$refs);
        qa_command($pdo,$f['id'],'configurar',['destino'=>'Destino de integración G7-G9','transporte'=>'Conductor QA G7-G9']);
        qa_command($pdo,$f['id'],'preparar');
        qa_command($pdo,$f['id'],'emitir_guia',['numero'=>$qaRun.'G9','fecha'=>date('Y-m-d')]);
        $guideId=(int)qa_value($pdo,'SELECT guia_id FROM g7_envios WHERE despacho_id=?',[$f['id']]);
        qa_same('Expediente nuevo no está marcado como legado',0,(int)qa_value($pdo,'SELECT legado FROM g7_envios WHERE despacho_id=?',[$f['id']]));
        $snapshotGuide=static function () use ($pdo,$guideId): array {
            $st=$pdo->prepare('SELECT * FROM guias_detalle WHERE guia_id=? ORDER BY id');$st->execute([$guideId]);
            return ['cabecera'=>qa_row($pdo,'SELECT * FROM guias_cabecera WHERE id=?',[$guideId]),'detalle'=>$st->fetchAll(PDO::FETCH_ASSOC)];
        };
        $countOrigin=static fn(): int => (int)qa_value($pdo,"SELECT COUNT(*) FROM guias_cabecera WHERE origen IN ('ERP_G7','LOGISTICA_G7') AND origen_ref=?",[(string)$f['id']]);
        $before=$snapshotGuide();$fingerprint=qa_fingerprint($pdo,$f['id']);
        qa_same('Existe una sola guía de origen para el despacho antes de sincronizar',1,$countOrigin());
        $stats=(new \App\Services\ErpSyncService($pdo))->run();
        qa_check('Sincronización G9 en preparación respeta la transacción exterior',$pdo->inTransaction());
        qa_check('Sincronizador público ejecuta sus tres bloques reales',isset($stats['documentos'],$stats['guias'],$stats['stock']));
        qa_same('G9 en preparación conserva número, versión, estado y detalle de la guía',$before,$snapshotGuide());
        qa_same('G9 no crea una segunda guía para el envío preparado',1,$countOrigin());
        qa_same('G9 en preparación no modifica el expediente o inventario físico',$fingerprint,qa_fingerprint($pdo,$f['id']));
        qa_command($pdo,$f['id'],'salir');
        qa_same('La guía sigue vigente para autorizar la salida tras sincronizar','EN_RUTA',qa_value($pdo,'SELECT estado FROM g7_despachos WHERE id=?',[$f['id']]));
        $beforeDepartureSync=$snapshotGuide();$fingerprint=qa_fingerprint($pdo,$f['id']);
        (new \App\Services\ErpSyncService($pdo))->run();
        qa_check('Sincronización G9 después de salir conserva la transacción exterior',$pdo->inTransaction());
        qa_same('G9 después de salir conserva íntegra la guía operativa',$beforeDepartureSync,$snapshotGuide());
        qa_same('La misma guía emitida permanece idéntica antes y después del recorrido',$before,$snapshotGuide());
        qa_same('G9 no duplica guía después de la salida',1,$countOrigin());
        qa_same('G9 después de salir no altera balance, Kardex ni historial',$fingerprint,qa_fingerprint($pdo,$f['id']));
        // qa_scenario revierte también todos los cambios de Documentos/Stock de G9.
    });

    qa_scenario($pdo,'Aceptación parcial sin rechazo y visita fallida',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);qa_ready($pdo,$f);$stock=qa_stock($pdo,$f);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'60.000','cantidad_rechazada'=>'0','receptor'=>'Receptor QA','evidencia'=>'Recepción de 60']);
        $exp=g7_envio_expediente($pdo,$f['id']);
        qa_same('Aceptar 60 sin rechazo deja 40 pendientes','40.000',$exp['balance']['pendiente']);
        qa_same('Aceptación de 60 no se etiqueta como rechazo','ACEPTADA',$exp['intentos'][0]['resultado']);
        qa_same('Despacho permanece abierto con saldo','EN_RUTA',$exp['envio']['estado']);
        qa_reject($pdo,$f['id'],'Cierre prematuro',fn()=>qa_command($pdo,$f['id'],'cerrar'));
        qa_command($pdo,$f['id'],'intento',['resultado'=>'FALLIDA','cantidad_aceptada'=>'0','cantidad_rechazada'=>'0','receptor'=>'','motivo'=>'Receptor ausente','evidencia'=>'Visita QA sin recepción']);
        qa_same('FALLIDA sin receptor ni cantidades conserva 40 pendientes','40.000',g7_envio_expediente($pdo,$f['id'])['balance']['pendiente']);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'40.000','cantidad_rechazada'=>'0','receptor'=>'Receptor QA','evidencia'=>'Segundo recibo QA']);
        $exp=g7_envio_expediente($pdo,$f['id']);
        qa_same('Reintento concilia exactamente las 100','100.000',$exp['balance']['aceptado']);
        qa_same('Tres intentos independientes quedan auditados',3,count($exp['intentos']));
        qa_same('Los intentos no vuelven a tocar stock',$stock,qa_stock($pdo,$f));
        qa_command($pdo,$f['id'],'cerrar');
    });

    qa_scenario($pdo,'Rechazo, retorno y pérdida',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);qa_ready($pdo,$f);$salida=qa_stock($pdo,$f);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'PARCIAL','cantidad_aceptada'=>'80','cantidad_rechazada'=>'20','receptor'=>'Receptor QA','motivo'=>'Rechazo de 20','evidencia'=>'Acta QA']);
        $exp=g7_envio_expediente($pdo,$f['id']);
        qa_same('Rechazo de 20 sigue siendo pendiente','20.000',$exp['balance']['pendiente']);
        qa_same('Rechazo no ingresa existencias',$salida,qa_stock($pdo,$f));
        qa_check('Rechazo genera caso cuantitativo sin doble stock',count($exp['incidencias'])>=1);
        qa_reject($pdo,$f['id'],'Cerrar con rechazo pendiente',fn()=>qa_command($pdo,$f['id'],'cerrar'));
        $incident=(int)$exp['incidencias'][0]['id'];
        $return=qa_command($pdo,$f['id'],'resolver',['incidencia_id'=>$incident,'tipo'=>'RETORNO','cantidad'=>'12','documento'=>'ACTA-QA-RETORNO','evidencia'=>'Recepción física QA']);
        qa_same('Retorno de 12 deja 8 en custodia','8.000',g7_envio_expediente($pdo,$f['id'])['balance']['pendiente']);
        qa_same('Retorno real agrega sólo 12 al almacén',qa_milli($salida['cantidad'])+12000,qa_milli(qa_stock($pdo,$f)['cantidad']));
        qa_same('Retorno no crea una reserva nueva',$salida['reservado'],qa_stock($pdo,$f)['reservado']);
        qa_same('Kardex contiene entrada de retorno por 12',12000,qa_milli(qa_value($pdo,"SELECT SUM(entrada) FROM g7_kardex WHERE documento=? AND tipo='RETORNO_ENVIO'",[$f['code']])));
        qa_same('Contrato actual: resolver 12 deja la incidencia cerrada aunque queden 8','RESUELTA',qa_value($pdo,'SELECT estado FROM g7_envio_incidencias WHERE id=?',[$incident]));
        qa_reject($pdo,$f['id'],'Límite actual: una segunda resolución parcial requiere otra incidencia',fn()=>qa_command($pdo,$f['id'],'resolver',['incidencia_id'=>$incident,'tipo'=>'PERDIDA','cantidad'=>'8','documento'=>'ACTA-QA-PERDIDA-MISMO-CASO','evidencia'=>'Intento de segunda resolución del mismo caso']));
        qa_same('Segunda resolución del mismo caso rechazada conserva las 8 pendientes','8.000',g7_envio_expediente($pdo,$f['id'])['balance']['pendiente']);
        $incident2=qa_incident($pdo,$f['id']);$antesPerdida=qa_stock($pdo,$f);
        qa_command($pdo,$f['id'],'resolver',['incidencia_id'=>$incident2,'tipo'=>'PERDIDA','cantidad'=>'8','documento'=>'ACTA-QA-PERDIDA','evidencia'=>'Autorización documentada QA']);
        qa_same('Pérdida no repite egreso ni ingresa stock',$antesPerdida,qa_stock($pdo,$f));
        $exp=g7_envio_expediente($pdo,$f['id']);
        qa_same('Balance excepcional: 100 = 80 + 12 + 8 + 0',['enviado'=>'100.000','aceptado'=>'80.000','retornado'=>'12.000','perdida'=>'8.000','pendiente'=>'0.000'],$exp['balance']);
        $beforeReplay=qa_fingerprint($pdo,$f['id']);
        $replay=g7_envio_operar($pdo,$f['id'],'resolver',$return['payload']);
        qa_same('Retorno repetido devuelve resultado original',true,$replay['replayed']);
        qa_same('Retorno repetido no duplica entrada ni resolución',$beforeReplay,qa_fingerprint($pdo,$f['id']));
        qa_command($pdo,$f['id'],'cerrar');
        qa_same('Retorno/pérdida conserva un cierre excepcional','CERRADO_CON_INCIDENCIA',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$f['id']]));
    });

    qa_scenario($pdo,'Reintento de un rechazo',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);qa_ready($pdo,$f);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'PARCIAL','cantidad_aceptada'=>'80','cantidad_rechazada'=>'20','receptor'=>'QA','motivo'=>'Rechazo parcial','evidencia'=>'Acta QA']);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'12','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Reintento real QA']);
        $balance=g7_envio_expediente($pdo,$f['id'])['balance'];
        qa_same('Reintento de 12 produce 92 aceptadas','92.000',$balance['aceptado']);
        qa_same('Reintento deja sólo 8 pendientes','8.000',$balance['pendiente']);
        qa_same('Reintento no se cuenta como retorno','0.000',$balance['retornado']);
    });

    qa_scenario($pdo,'Incidencia bloqueante',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);qa_ready($pdo,$f);$incident=qa_incident($pdo,$f['id'],'BLOQUEANTE');
        qa_reject($pdo,$f['id'],'Intento con bloqueo abierto',fn()=>qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'100','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Acta QA']));
        qa_command($pdo,$f['id'],'resolver',['incidencia_id'=>$incident,'tipo'=>'CIERRE','cantidad'=>'0','evidencia'=>'Documento corregido QA']);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'100','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Acta QA']);
        qa_command($pdo,$f['id'],'cerrar');
        qa_same('Resolver bloqueo permite cerrar después del saldo cero','CERRADO',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$f['id']]));
    });

    qa_scenario($pdo,'Incidencia informativa con mercadería aceptada',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);qa_ready($pdo,$f);$incident=qa_incident($pdo,$f['id'],'INFORMATIVA');
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'100','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Se acepta la mercadería y se registra reclamo informativo']);
        qa_same('Reclamo informativo no duplica mercancía pendiente','0.000',g7_envio_expediente($pdo,$f['id'])['balance']['pendiente']);
        qa_command($pdo,$f['id'],'cerrar');
        qa_same('Una incidencia informativa abierta permite cierre físico completo','CERRADO',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$f['id']]));
        qa_same('Cierre conserva el reclamo informativo para trazabilidad','ABIERTA',qa_value($pdo,'SELECT estado FROM g7_envio_incidencias WHERE id=?',[$incident]));
    });

    qa_scenario($pdo,'Idempotencia antes de consultar estado mutable',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);$ready=qa_ready($pdo,$f);$first=$ready['departure'];
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'100','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Recepción QA']);
        $before=qa_fingerprint($pdo,$f['id']);$replay=g7_envio_operar($pdo,$f['id'],'salir',$first['payload']);
        qa_same('Replay tras avanzar devuelve versión original',$first['response']['version'],$replay['version']);
        qa_same('Replay se identifica expresamente',true,$replay['replayed']);
        qa_same('Replay de salida no agrega movimiento ni historial',$before,qa_fingerprint($pdo,$f['id']));
        $changed=$first['payload'];$changed['observacion']='Contenido diferente';
        qa_reject($pdo,$f['id'],'Misma clave y contenido distinto',fn()=>g7_envio_operar($pdo,$f['id'],'salir',$changed));
        $pdo->exec('SAVEPOINT qa_permission');
        try {
            $pdo->exec("DELETE rp FROM rol_permiso rp JOIN roles r ON r.id=rp.rol_id JOIN permisos p ON p.id=rp.permiso_id WHERE LOWER(r.nombre)='administrador' AND p.nombre='editar'");
            qa_reject($pdo,$f['id'],'Replay sin permiso vigente',fn()=>g7_envio_operar($pdo,$f['id'],'salir',$first['payload']),403);
        } finally { $pdo->exec('ROLLBACK TO SAVEPOINT qa_permission');$pdo->exec('RELEASE SAVEPOINT qa_permission'); }
        qa_check('Se restaura permiso de QA dentro de la transacción',g7_permiso_persistido($pdo,'editar'));
        $pdo->exec('SAVEPOINT qa_user_role');
        try {
            $pdo->prepare("DELETE ur FROM usuario_rol ur JOIN roles r ON r.id=ur.rol_id WHERE ur.usuario_id=? AND LOWER(r.nombre)='administrador'")->execute([$refs['user']]);
            qa_reject($pdo,$f['id'],'Replay con rol revocado al usuario',fn()=>g7_envio_operar($pdo,$f['id'],'salir',$first['payload']),403);
        } finally { $pdo->exec('ROLLBACK TO SAVEPOINT qa_user_role');$pdo->exec('RELEASE SAVEPOINT qa_user_role'); }
        qa_check('Asignación de rol restaurada después de QA',g7_permiso_persistido($pdo,'editar'));
        $pdo->exec('SAVEPOINT qa_user_active');
        try {
            $pdo->prepare('UPDATE usuarios SET estado=0 WHERE id=?')->execute([$refs['user']]);
            qa_reject($pdo,$f['id'],'Replay con usuario desactivado',fn()=>g7_envio_operar($pdo,$f['id'],'salir',$first['payload']),403);
        } finally { $pdo->exec('ROLLBACK TO SAVEPOINT qa_user_active');$pdo->exec('RELEASE SAVEPOINT qa_user_active'); }
        qa_check('Usuario permanece activo al restaurar QA',g7_permiso_persistido($pdo,'editar'));
    });

    qa_scenario($pdo,'Cancelación y reserva',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);$before=qa_stock($pdo,$f);$k=(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_kardex');
        $c=qa_command($pdo,$f['id'],'cancelar',['motivo'=>'Cancelación previa QA']);$after=qa_stock($pdo,$f);
        qa_same('Cancelar conserva existencia física',$before['cantidad'],$after['cantidad']);
        qa_same('Cancelar libera exactamente su reserva',qa_milli($before['reservado'])-100000,qa_milli($after['reservado']));
        qa_same('Cancelar antes de salir no crea entrada/salida',$k,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_kardex'));
        qa_same('Cancelación conserva el estado del proceso','CANCELADO',qa_value($pdo,'SELECT estado_logistica FROM g7_envios WHERE despacho_id=?',[$f['id']]));
        $fingerprint=qa_fingerprint($pdo,$f['id']);$replay=g7_envio_operar($pdo,$f['id'],'cancelar',$c['payload']);
        qa_same('Repetir cancelar no libera dos veces',$fingerprint,qa_fingerprint($pdo,$f['id']));
        qa_same('Repetir cancelar es replay',true,$replay['replayed']);
        $f2=qa_fixture($pdo,$refs);qa_ready($pdo,$f2);
        qa_reject($pdo,$f2['id'],'Cancelación después de salir',fn()=>qa_command($pdo,$f2['id'],'cancelar',['motivo'=>'Cancelación indebida']));
    });

    qa_scenario($pdo,'Sede activa y permiso del comando',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs);$branch=(int)qa_value($pdo,'SELECT id FROM sucursales WHERE id<>? AND estado=1 ORDER BY id LIMIT 1',[$refs['branch']]);
        if (!$branch) throw new RuntimeException('QA multisede necesita dos sucursales activas.');
        $original=$_SESSION['sede_activa'];$_SESSION['sede_activa']=$branch;
        try {
            qa_reject($pdo,$f['id'],'Comando de otra sede activa',fn()=>qa_command($pdo,$f['id'],'configurar',['destino'=>'Fuera de sede','transporte'=>'QA']),403);
            $denied=null;try { g7_envio_expediente($pdo,$f['id']); } catch (ApiError $e) { $denied=$e; }
            qa_same('Lectura directa del expediente de otra sede se rechaza',403,$denied?->statusCode);
        } finally { $_SESSION['sede_activa']=$original; }
        $role=$_SESSION['usuario']['rol'];$_SESSION['usuario']['rol']='rol_qa_sin_permiso';
        try { qa_reject($pdo,$f['id'],'Comando sin permiso persistido',fn()=>qa_command($pdo,$f['id'],'cancelar',['motivo'=>'No autorizado']),403); }
        finally { $_SESSION['usuario']['rol']=$role; }
    });

    qa_scenario($pdo,'Cantidades fraccionarias en el circuito real',function () use ($pdo,$refs): void {
        $f=qa_fixture($pdo,$refs,'1.125');$before=qa_stock($pdo,$f);qa_ready($pdo,$f);
        qa_same('Salida real de 1.125 conserva tres decimales',qa_milli($before['cantidad'])-1125,qa_milli(qa_stock($pdo,$f)['cantidad']));
        qa_reject($pdo,$f['id'],'No se acepta una cuarta cifra decimal del formulario',fn()=>qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'1.1251','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'QA']),400);
        qa_reject($pdo,$f['id'],'No se acepta más que el saldo',fn()=>qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'1.126','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'QA']));
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'0.001','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Recepción de una milésima']);
        qa_same('Una milésima aceptada deja exactamente 1.124','1.124',g7_envio_expediente($pdo,$f['id'])['balance']['pendiente']);
        qa_command($pdo,$f['id'],'intento',['resultado'=>'ACEPTADA','cantidad_aceptada'=>'1.124','cantidad_rechazada'=>'0','receptor'=>'QA','evidencia'=>'Saldo recibido QA']);
        qa_same('La suma física fraccionaria es exacta','1.125',g7_envio_expediente($pdo,$f['id'])['balance']['aceptado']);
        qa_command($pdo,$f['id'],'cerrar');
    });

    qa_scenario($pdo,'Venta con precisión o unidad incompatibles',function () use ($pdo,$refs): void {
        $before=qa_stock($pdo,$refs);$f=qa_fixture($pdo,$refs,'1.0001',null,false);$denied=false;
        try { g7_reservar_venta($pdo,$f['sale']); } catch (ApiError $e) { $denied=true; }
        qa_check('Comercial no redondea una cantidad de cuatro decimales no nulos',$denied);
        qa_same('Cantidad incompatible no crea despacho',0,(int)qa_value($pdo,'SELECT COUNT(*) FROM g7_despachos WHERE venta_id=?',[$f['sale']]));
        qa_same('Cantidad incompatible no altera reserva',$before,qa_stock($pdo,$refs));
        $otherUnit=(int)qa_value($pdo,'SELECT id FROM unidades_medida WHERE id<>? ORDER BY id LIMIT 1',[$refs['unit']]);
        if (!$otherUnit) throw new RuntimeException('QA de unidad incompatible necesita dos unidades existentes.');
        $f2=qa_fixture($pdo,$refs,'1.000',$otherUnit,false);$denied=false;
        try { g7_reservar_venta($pdo,$f2['sale']); } catch (ApiError $e) { $denied=true; }
        qa_check('Unidad comercial incompatible se rechaza antes de reservar',$denied);
        qa_same('Unidad incompatible conserva existencia y reserva',$before,qa_stock($pdo,$refs));
    });
} catch (Throwable $e) {
    $qaFatal = get_class($e) . ': ' . $e->getMessage();
} finally {
    if ($qaPdo instanceof PDO && $qaPdo->inTransaction()) {
        $qaPdo->rollBack();
        $qaRollback = true;
    }
    if ($qaOriginalSession !== null) $_SESSION = $qaOriginalSession;
    if ($qaBaseline !== null && $qaPdo instanceof PDO && $qaRollback) {
        try { qa_same('Rollback final restaura filas y saldos anteriores',$qaBaseline,qa_snapshot($qaPdo)); }
        catch (Throwable $e) { if (!($e instanceof QaAssertionFailure)) $qaFatal = get_class($e).': '.$e->getMessage(); }
    }
}

$qaPassed = count(array_filter($qaResults,static fn($r)=>$r['ok']));
$qaFailed = count($qaResults)-$qaPassed;
$qaOutput = [
    'ok' => $qaFatal===null && $qaFailed===0 && $qaStarted && $qaRollback,
    'suite' => 'Logística: funciones reales, inventario y persistencia en QA aislada',
    'pasadas' => $qaPassed,
    'fallos' => $qaFailed,
    'escenarios' => $qaCounters['scenario'],
    'rollback_final' => $qaRollback,
    'resultados' => $qaResults,
    'limites' => ['No verifica HTTP, CSRF ni representación visual.','Los fixtures y cambios de permisos se revierten; los contadores AUTO_INCREMENT pueden conservar saltos propios de InnoDB.'],
];
if ($qaFatal!==null) $qaOutput['error_fatal']=$qaFatal;
$qaJsonFlags = JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE;
if ($qaGuardPassed) {
    $qaOutput['evidencia'] = '.runtime/resultado-integracion.json';
    $saved = file_put_contents($qaRoot . '/.runtime/resultado-integracion.json',json_encode($qaOutput,$qaJsonFlags).PHP_EOL);
    if ($saved === false) {
        $qaOutput['ok'] = false;
        $qaOutput['error_evidencia'] = 'No se pudo guardar el JSON de evidencia.';
    }
}
$qaSummary = $qaOutput;
if (!in_array('--json',$argv,true)) {
    unset($qaSummary['resultados']);
    $qaSummary['fallos_detalle'] = array_values(array_filter($qaResults,static fn($r)=>!$r['ok']));
}
echo json_encode($qaSummary,$qaJsonFlags).PHP_EOL;
exit($qaOutput['ok']?0:1);

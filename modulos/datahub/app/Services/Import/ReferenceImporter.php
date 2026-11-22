<?php
namespace App\Services\Import;

/**
 * Importador idempotente de los insumos de referencia hacia bayer_pucchun.
 *
 * Datasets reconocidos (por las tablas presentes en el archivo):
 *  - sedes_almacenes : dim_sede, dim_almacen                     → sucursales, almacenes
 *  - comercial       : Sedes, Vendedores, Clientes (+Servicios/Ventas TI, fuera de alcance) → geo, vendedores, clientes
 *  - bayer           : Productos, Precios, Inventario, Ventas    → productos, stock, documentos y guías
 *
 * Estrategia incremental (UPSERT):
 *  - Maestros: clave natural (código/RUC); se actualizan sólo si cambian.
 *  - Transaccionales: clave de origen (origen + origen_ref) + hash SHA1 de la fila de origen.
 *    Igual hash → sin cambios. Distinto → actualiza, salvo que el registro ya esté PUBLICADO
 *    (inmutable para Bayer) a menos que se use $force.
 *  - Homologaciones Bayer: INSERT IGNORE para no pisar ajustes manuales.
 */
class ReferenceImporter
{
    public const ORIGEN_VENTAS = 'BAYER_VTA';
    public const ORIGEN_STOCK = 'BAYER_INV';
    public const MODES = ['ROLLING', 'BORRADOR', 'VALIDADO', 'PUBLICADO'];

    private array $cfg;
    private array $stats = [];
    private array $warnings = [];
    private array $geoCache = [];
    private array $idCache = [];
    private ?int $partnerId = null;
    private int $actorId;
    private array $published = ['documentos' => [], 'guias' => [], 'stock' => []];

    public function __construct(private \PDO $pdo, ?array $cfg = null, ?int $userId = null)
    {
        $this->cfg = $cfg ?? \config('import');
        $this->actorId = $userId ?: (int)$pdo->query("SELECT u.id FROM usuarios u JOIN usuario_rol ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id WHERE r.nombre='ADMIN' ORDER BY u.id LIMIT 1")->fetchColumn();
    }

    /** Analiza un archivo y detecta su dataset sin escribir en BD. */
    public function inspect(string $path): array
    {
        $tables = (new SqlInsertParser())->parseFile($path);
        return ['dataset' => $this->detect($tables), 'tables' => array_map('count', $tables)];
    }

    /** Ordena archivos según dependencias (sedes → comercial → bayer). */
    public function orderFiles(array $paths): array
    {
        $prio = ['sedes_almacenes' => 1, 'comercial' => 2, 'bayer' => 3, 'desconocido' => 9];
        $withPrio = [];
        foreach ($paths as $p) {
            try { $ds = $this->inspect($p)['dataset']; } catch (\Throwable) { $ds = 'desconocido'; }
            $withPrio[] = [$prio[$ds] ?? 9, $p];
        }
        usort($withPrio, fn($a, $b) => $a[0] <=> $b[0] ?: strcmp($a[1], $b[1]));
        return array_column($withPrio, 1);
    }

    public function importFile(string $path, string $mode = 'BORRADOR', ?string $displayName = null, bool $force = false): array
    {
        $mode = strtoupper($mode);
        if (!in_array($mode, self::MODES, true)) throw new \InvalidArgumentException("Modo de estado inválido: $mode");
        $this->stats = []; $this->warnings = []; $this->published = ['documentos' => [], 'guias' => [], 'stock' => []];
        $name = $displayName ?: basename($path);
        $hash = hash_file('sha256', $path);
        $tables = (new SqlInsertParser())->parseFile($path);
        $dataset = $this->detect($tables);
        $rowsRead = array_sum(array_map('count', $tables));

        $st = $this->pdo->prepare('INSERT INTO importaciones(archivo,hash_sha256,dataset,modo_estado,estado,filas_leidas,usuario_id) VALUES(?,?,?,?,?,?,?)');
        $st->execute([$name, $hash, $dataset, $mode, 'EN_PROCESO', $rowsRead, $this->actorId ?: null]);
        $importId = (int)$this->pdo->lastInsertId();
        $prev = $this->pdo->prepare("SELECT COUNT(*) FROM importaciones WHERE hash_sha256=? AND estado='COMPLETADO' AND id<>?");
        $prev->execute([$hash, $importId]);
        if ($prev->fetchColumn()) $this->warn('Este archivo ya fue importado antes; se re-aplicará de forma idempotente.');

        $t0 = microtime(true);
        $this->pdo->beginTransaction();
        try {
            match ($dataset) {
                'sedes_almacenes' => $this->importSedesAlmacenes($tables),
                'comercial' => $this->importComercial($tables),
                'bayer' => $this->importBayer($tables, $mode, $force),
                default => throw new \RuntimeException('No se reconoce la estructura del archivo (tablas: '.implode(', ', array_keys($tables)).').'),
            };
            $this->registerPublications();
            $this->pdo->commit();
            $status = 'COMPLETADO';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->warn('ERROR: '.$e->getMessage());
            $status = 'FALLIDO';
        }
        $tot = $this->totals();
        $summary = ['dataset' => $dataset, 'tablas' => array_map('count', $tables), 'entidades' => $this->stats, 'advertencias' => array_slice($this->warnings, 0, 100), 'segundos' => round(microtime(true) - $t0, 2)];
        $st = $this->pdo->prepare('UPDATE importaciones SET estado=?,insertados=?,actualizados=?,sin_cambios=?,omitidos=?,resumen=?,finished_at=NOW() WHERE id=?');
        $st->execute([$status, $tot['insertados'], $tot['actualizados'], $tot['sin_cambios'], $tot['omitidos'], json_encode($summary, JSON_UNESCAPED_UNICODE), $importId]);
        return ['id' => $importId, 'archivo' => $name, 'estado' => $status] + $tot + $summary;
    }

    private function detect(array $tables): string
    {
        $has = fn(string ...$t) => !array_diff($t, array_keys($tables));
        if ($has('dim_sede') || $has('dim_almacen')) return 'sedes_almacenes';
        if ($has('productos') || $has('inventario') || ($has('ventas') && isset($tables['ventas'][0]['producto_id']))) return 'bayer';
        if ($has('clientes') || $has('vendedores')) return 'comercial';
        return 'desconocido';
    }

    // =====================================================================
    // Dataset: sedes y almacenes
    // =====================================================================
    private function importSedesAlmacenes(array $t): void
    {
        $empresa = $this->dealer();
        $sedeById = [];
        foreach ($t['dim_sede'] ?? [] as $r) {
            $ciudad = trim((string)$r['ciudad']);
            $loc = $this->cfg['ciudades'][$ciudad] ?? ['departamento' => $ciudad, 'provincia' => $ciudad, 'distrito' => $ciudad];
            $geo = $this->geo($loc['departamento'], $loc['provincia'], $loc['distrito']);
            $id = $this->upsert('sucursales', 'codigo', [
                'codigo' => $r['codigo'], 'empresa_id' => $empresa, 'nombre' => $r['nombre'], 'ciudad' => $ciudad,
                'direccion' => '', 'distrito_id' => $geo[2], 'estado' => (int)($r['activo'] ?? 1),
            ], 'sucursales');
            $sedeById[$r['id']] = $id;
            $this->homologate('homologacion_sucursales', 'sucursal_id', $id, ['branch_id' => $r['codigo'], 'branch_name' => $r['nombre']]);
        }
        foreach ($t['dim_almacen'] ?? [] as $r) {
            $suc = $sedeById[$r['sede_id']] ?? null;
            if (!$suc) { $this->skip('almacenes', "Almacén {$r['codigo']}: sede {$r['sede_id']} inexistente"); continue; }
            $id = $this->upsert('almacenes', 'codigo', [
                'codigo' => $r['codigo'], 'sucursal_id' => $suc, 'nombre' => $r['nombre'], 'tipo' => strtoupper((string)$r['tipo']), 'estado' => (int)($r['activo'] ?? 1),
            ], 'almacenes');
            $this->homologate('homologacion_almacenes', 'almacen_id', $id, ['warehouse_id' => $r['codigo'], 'warehouse_name' => $r['nombre']]);
        }
    }

    // =====================================================================
    // Dataset: comercial (clientes / vendedores)
    // =====================================================================
    private function importComercial(array $t): void
    {
        $sedes = [];
        foreach ($t['sedes'] ?? [] as $s) {
            $sedes[$s['sede_id']] = $s;
            $this->geo($s['departamento'], $s['ciudad']);
        }
        $vend = [];
        foreach ($t['vendedores'] ?? [] as $r) {
            $id = $this->upsert('vendedores', 'codigo', [
                'codigo' => $r['vendedor_id'], 'nombres' => $r['nombres'], 'apellidos' => $r['apellidos'],
                'email' => $r['correo'] ?? '', 'telefono' => $r['telefono'] ?? null, 'cargo' => $r['cargo'] ?? null,
                'estado_laboral' => $r['estado'] ?? null,
                'estado' => in_array(strtoupper((string)($r['estado'] ?? 'ACTIVO')), ['ACTIVO', 'LICENCIA', 'VACACIONES'], true) ? 1 : 0,
            ], 'vendedores');
            $vend[$r['vendedor_id']] = $id;
            $this->homologate('homologacion_vendedores', 'vendedor_id', $id, ['sales_id' => $r['vendedor_id'], 'sales_name' => trim($r['nombres'].' '.$r['apellidos'])]);
        }
        foreach ($t['clientes'] ?? [] as $r) {
            $ruc = preg_replace('/\D/', '', (string)$r['ruc']);
            if ($ruc === '' || !in_array(strlen($ruc), [8, 11], true)) { $this->skip('clientes', "Cliente {$r['cliente_id']}: documento inválido '{$r['ruc']}'"); continue; }
            $sede = $sedes[$r['sede_id']] ?? null;
            $geo = [null, null, null];
            if ($sede) {
                $dist = trim((string)($r['distrito'] ?? '')) ?: $sede['ciudad'];
                $ov = $this->cfg['distritos_override'][$dist] ?? null;
                $geo = $this->geo($ov['departamento'] ?? $sede['departamento'], $ov['provincia'] ?? $sede['ciudad'], $dist);
            } else {
                $this->warn("Cliente {$r['cliente_id']}: sede {$r['sede_id']} sin ubicación");
            }
            $vendId = $vend[$r['vendedor_id']] ?? $this->lookup('vendedores', 'codigo', $r['vendedor_id']);
            $id = $this->upsert('clientes', 'nro_doc', [
                'nro_doc' => $ruc, 'codigo' => $r['cliente_id'], 'tipo_doc' => strlen($ruc) === 11 ? '06' : '01',
                'razon_social' => $r['razon_social'], 'departamento_id' => $geo[0], 'provincia_id' => $geo[1], 'distrito_id' => $geo[2],
                'tipo_cliente' => $r['tipo_cliente'] ?? null, 'sector' => $r['sector'] ?? null, 'contacto' => $r['contacto'] ?? null,
                'correo' => $r['correo'] ?? null, 'telefono' => $r['telefono'] ?? null, 'vendedor_id' => $vendId, 'estado' => $r['estado'] ?? 'ACTIVO',
            ], 'clientes');
            $this->homologate('homologacion_clientes', 'cliente_id', $id, ['customer_id' => $ruc, 'customer_name' => $r['razon_social']]);
        }
        if (!empty($t['servicios']) || !empty($t['ventas'])) {
            $this->warn(sprintf('Fuera de alcance Bayer: %d servicios TI y %d ventas de servicios (Matt Innova) no se cargan como documentos Bayer.', count($t['servicios'] ?? []), count($t['ventas'] ?? [])));
            $this->stats['servicios_ti_excluidos'] = ['insertados' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => count($t['servicios'] ?? []) + count($t['ventas'] ?? [])];
        }
    }

    // =====================================================================
    // Dataset: Bayer (productos, precios, inventario, ventas)
    // =====================================================================
    private function importBayer(array $t, string $mode, bool $force): void
    {
        $empresa = $this->dealer();
        // --- Productos + precios
        $precios = [];
        foreach ($t['precios'] ?? [] as $p) $precios[$p['producto_id']] = $p;
        $prod = [];
        foreach ($t['productos'] ?? [] as $r) {
            $unit = $this->unit((string)($r['unidad_medida'] ?? 'UND'));
            $cat = $this->catalog('categorias_producto', (string)$r['categoria'], ['descripcion' => null]);
            $marca = $this->catalog('marcas', (string)$r['fabricante'], ['fabricante' => $r['fabricante']]);
            $pr = $precios[$r['producto_id']] ?? [];
            $id = $this->upsert('productos', 'codigo', [
                'codigo' => $r['producto_id'], 'nombre' => $r['nombre_producto'], 'presentacion' => $r['presentacion'] ?? null,
                'es_bayer' => (int)$r['es_bayer'], 'categoria_id' => $cat, 'marca_id' => $marca, 'unidad_base_id' => $unit,
                'costo_unitario' => $pr['costo_unitario'] ?? null, 'precio_venta' => $pr['precio_venta'] ?? null,
                'moneda' => $pr['moneda'] ?? 'PEN', 'observacion' => $r['observacion'] ?? null, 'estado' => 1,
            ], 'productos', true);
            $prod[$r['producto_id']] = ['id' => $id, 'unit' => $unit];
            if ((int)$r['es_bayer'] === 1) {
                $this->homologate('homologacion_productos_bayer', 'producto_id', $id, ['material_id' => $r['producto_id'], 'material_name' => $r['nombre_producto'], 'unidad_bayer_id' => $unit]);
            }
        }
        foreach ($t['precios'] ?? [] as $p) {
            if (!isset($prod[$p['producto_id']]) && !$this->lookup('productos', 'codigo', $p['producto_id'])) $this->skip('precios', "Precio de {$p['producto_id']} sin producto");
            elseif (!isset($prod[$p['producto_id']])) {
                $this->pdo->prepare('UPDATE productos SET costo_unitario=?,precio_venta=?,moneda=?,updated_at=NOW() WHERE codigo=?')->execute([$p['costo_unitario'], $p['precio_venta'], $p['moneda'] ?? 'PEN', $p['producto_id']]);
                $this->count('precios', 'actualizados');
            }
        }
        if (!empty($t['inventario'])) $this->importInventario($t['inventario'], $empresa, $mode, $force);
        if (!empty($t['ventas'])) $this->importVentas($t['ventas'], $mode, $force);
    }

    private function importInventario(array $rows, int $empresa, string $mode, bool $force): void
    {
        $groups = [];
        foreach ($rows as $r) $groups[$r['almacen_id'].'|'.$r['fecha_corte']][] = $r;
        $initial = $mode === 'ROLLING' ? 'PUBLICADO' : $mode; // el último corte de stock es el que se reporta
        $selHead = $this->pdo->prepare('SELECT id,origen_hash,estado_registro FROM stock_cabecera WHERE origen=? AND origen_ref=?');
        $insHead = $this->pdo->prepare('INSERT INTO stock_cabecera(fecha_stock,almacen_id,estado_registro,created_by,created_at,published_at,origen,origen_ref,origen_hash) VALUES(?,?,?,?,NOW(),?,?,?,?)');
        $updHead = $this->pdo->prepare('UPDATE stock_cabecera SET fecha_stock=?,almacen_id=?,origen_hash=?,updated_at=NOW() WHERE id=?');
        $delDet = $this->pdo->prepare('DELETE FROM stock_detalle WHERE stock_id=?');
        $insDet = $this->pdo->prepare('INSERT INTO stock_detalle(stock_id,lote_id,producto_id,unidad_id,cantidad,stock_minimo) VALUES(?,NULL,?,?,?,?)');
        foreach ($groups as $key => $items) {
            [$almCode, $fecha] = explode('|', $key);
            $sedeName = (string)$items[0]['sede'];
            $suc = $this->sucursalBayer($sedeName, $empresa);
            $alm = $this->upsert('almacenes', 'codigo', ['codigo' => $almCode, 'sucursal_id' => $suc, 'nombre' => 'Almacén '.$sedeName, 'tipo' => 'GENERAL', 'ciudad' => $sedeName, 'estado' => 1], 'almacenes', false, ['nombre', 'tipo']);
            $this->homologate('homologacion_almacenes', 'almacen_id', $alm, ['warehouse_id' => $almCode, 'warehouse_name' => 'Almacén '.$sedeName]);
            $hash = sha1(json_encode($items));
            $selHead->execute([self::ORIGEN_STOCK, $key]);
            $ex = $selHead->fetch();
            if ($ex && $ex['origen_hash'] === $hash) { $this->count('stock_cortes', 'sin_cambios'); continue; }
            if ($ex && $ex['estado_registro'] === 'PUBLICADO' && !$force) { $this->skip('stock_cortes', "Corte $key ya publicado con cambios: use --force para reemplazar"); continue; }
            if ($ex) {
                $id = (int)$ex['id'];
                $updHead->execute([$fecha, $alm, $hash, $id]);
                $delDet->execute([$id]);
                $this->count('stock_cortes', 'actualizados');
            } else {
                $insHead->execute([$fecha, $alm, $initial, $this->actorId ?: null, $initial === 'PUBLICADO' ? date('Y-m-d H:i:s') : null, self::ORIGEN_STOCK, $key, $hash]);
                $id = (int)$this->pdo->lastInsertId();
                if ($initial === 'PUBLICADO') $this->published['stock'][] = $id;
                $this->count('stock_cortes', 'insertados');
            }
            foreach ($items as $r) {
                $p = $this->productRef($r['producto_id']);
                if (!$p) { $this->skip('stock_detalle', "Stock $key: producto {$r['producto_id']} inexistente"); continue; }
                $insDet->execute([$id, $p['id'], $p['unit'], $r['stock_unidades'], $r['stock_minimo'] ?? null]);
                $this->count('stock_detalle', 'insertados');
            }
        }
    }

    private function importVentas(array $rows, string $mode, bool $force): void
    {
        $anulados = array_map('strtoupper', $this->cfg['estados_anulados']);
        // Fecha de referencia para el modo ROLLING: meses cerrados → PUBLICADO, mes previo → VALIDADO, mes en curso → BORRADOR.
        $max = '0000-00-00';
        foreach ($rows as $r) if ($r['fecha'] > $max) $max = $r['fecha'];
        $curMonth = substr($max, 0, 7).'-01';
        $prevMonth = date('Y-m-01', strtotime($curMonth.' -1 month'));

        // Precarga de maestros para evitar N consultas.
        $clients = [];
        foreach ($this->pdo->query('SELECT id,codigo,nro_doc,departamento_id,provincia_id,distrito_id FROM clientes') as $c) $clients[$c['codigo']] = $c;
        $sellers = $this->pdo->query('SELECT codigo,id FROM vendedores')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $types = $this->pdo->query('SELECT codigo,id FROM tipos_documento')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $docs = [];
        $st = $this->pdo->prepare('SELECT origen_ref,id,origen_hash,estado_registro FROM documentos_cabecera WHERE origen=?');
        $st->execute([self::ORIGEN_VENTAS]);
        foreach ($st as $d) $docs[$d['origen_ref']] = $d;
        $guides = [];
        $st = $this->pdo->prepare('SELECT origen_ref,id,estado_registro FROM guias_cabecera WHERE origen=?');
        $st->execute([self::ORIGEN_VENTAS]);
        foreach ($st as $g) $guides[$g['origen_ref']] = $g;

        $insDoc = $this->pdo->prepare('INSERT INTO documentos_cabecera(tipo_documento_id,numero,fecha,cliente_id,vendedor_id,sucursal_id,estado_registro,created_by,created_at,published_at,origen,origen_ref,origen_hash,estado_comercial,monto_total,moneda) VALUES(?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?)');
        $updDoc = $this->pdo->prepare('UPDATE documentos_cabecera SET tipo_documento_id=?,numero=?,fecha=?,cliente_id=?,vendedor_id=?,sucursal_id=?,origen_hash=?,estado_comercial=?,monto_total=?,estado_registro=IF(?=1,\'BORRADOR\',estado_registro),updated_at=NOW() WHERE id=?');
        $delDet = $this->pdo->prepare('DELETE FROM documentos_detalle WHERE documento_id=?');
        $insDet = $this->pdo->prepare('INSERT INTO documentos_detalle(documento_id,producto_id,unidad_id,cantidad,valor_unitario) VALUES(?,?,?,?,?)');
        $insGuia = $this->pdo->prepare('INSERT INTO guias_cabecera(numero,fecha,cliente_id,vendedor_id,sucursal_id,departamento_id,provincia_id,distrito_id,estado_registro,created_by,created_at,published_at,origen,origen_ref,origen_hash,documento_id) VALUES(?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?)');
        $updGuia = $this->pdo->prepare('UPDATE guias_cabecera SET numero=?,fecha=?,cliente_id=?,vendedor_id=?,sucursal_id=?,departamento_id=?,provincia_id=?,distrito_id=?,origen_hash=?,documento_id=?,updated_at=NOW() WHERE id=?');
        $delGuiaDet = $this->pdo->prepare('DELETE FROM guias_detalle WHERE guia_id=?');
        $insGuiaDet = $this->pdo->prepare('INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES(?,?,?,?)');
        $delGuia = $this->pdo->prepare('DELETE FROM guias_cabecera WHERE id=?');
        $empresa = $this->dealer();
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $r) {
            $ref = (string)$r['venta_id'];
            $hash = sha1(json_encode($r));
            $ex = $docs[$ref] ?? null;
            if ($ex && $ex['origen_hash'] === $hash) { $this->count('documentos', 'sin_cambios'); continue; }
            if ($ex && $ex['estado_registro'] === 'PUBLICADO' && !$force) { $this->skip('documentos', "Venta $ref publicada con cambios en origen: use --force"); continue; }

            // Resolución de referencias (integridad referencial)
            $cliCode = preg_match($this->cfg['cliente_map']['pattern'], (string)$r['cliente_id'], $m) ? sprintf($this->cfg['cliente_map']['format'], (int)$m[1]) : (string)$r['cliente_id'];
            $cli = $clients[$cliCode] ?? $clients[(string)$r['cliente_id']] ?? null;
            if (!$cli) { $this->skip('documentos', "Venta $ref: cliente {$r['cliente_id']} ($cliCode) no existe en el maestro"); continue; }
            $vend = $sellers[$r['vendedor_id']] ?? null;
            if (!$vend) { $this->skip('documentos', "Venta $ref: vendedor {$r['vendedor_id']} no existe"); continue; }
            $p = $this->productRef($r['producto_id']);
            if (!$p) { $this->skip('documentos', "Venta $ref: producto {$r['producto_id']} no existe"); continue; }
            $sedeCfg = $this->cfg['sedes_bayer'][$r['sede']] ?? null;
            $suc = $this->sucursalBayer((string)$r['sede'], $empresa);
            $isFactura = strlen((string)$cli['nro_doc']) === 11;
            $tipo = $types[$isFactura ? '01' : '03'];
            $num = (int)preg_replace('/\D/', '', $ref);
            $serie = $isFactura ? ($sedeCfg['serie_factura'] ?? 'F999') : ($sedeCfg['serie_boleta'] ?? 'B999');
            $numero = sprintf('%s-%08d', $serie, $num);
            $estadoCom = strtoupper((string)$r['estado']);
            $anulado = in_array($estadoCom, $anulados, true);
            $status = $this->initialStatus($mode, (string)$r['fecha'], $anulado, $prevMonth, $curMonth);

            if ($ex) {
                $docId = (int)$ex['id'];
                $updDoc->execute([$tipo, $numero, $r['fecha'], $cli['id'], $vend, $suc, $hash, $estadoCom, $r['monto_total'], $anulado ? 1 : 0, $docId]);
                $delDet->execute([$docId]);
                $this->count('documentos', 'actualizados');
                $docStatus = $anulado ? 'BORRADOR' : $ex['estado_registro'];
            } else {
                $insDoc->execute([$tipo, $numero, $r['fecha'], $cli['id'], $vend, $suc, $status, $this->actorId ?: null, $status === 'PUBLICADO' ? $now : null, self::ORIGEN_VENTAS, $ref, $hash, $estadoCom, $r['monto_total'], 'PEN']);
                $docId = (int)$this->pdo->lastInsertId();
                if ($status === 'PUBLICADO') $this->published['documentos'][] = $docId;
                $this->count('documentos', 'insertados');
                $docStatus = $status;
            }
            $insDet->execute([$docId, $p['id'], $p['unit'], $r['cantidad'], $r['precio_unitario']]);

            // Guía de remisión derivada (sólo ventas no anuladas): despacho al domicilio del cliente.
            $g = $guides[$ref] ?? null;
            if ($anulado) {
                if ($g && $g['estado_registro'] !== 'PUBLICADO') { $delGuia->execute([$g['id']]); $this->count('guias', 'actualizados'); }
                continue;
            }
            $gNumero = sprintf('%s-%08d', $sedeCfg['serie_guia'] ?? 'T999', $num);
            $geo = [$cli['departamento_id'], $cli['provincia_id'], $cli['distrito_id']];
            if ($g) {
                if ($g['estado_registro'] === 'PUBLICADO' && !$force) { $this->skip('guias', "Guía $gNumero publicada: no se modifica"); continue; }
                $updGuia->execute([$gNumero, $r['fecha'], $cli['id'], $vend, $suc, $geo[0], $geo[1], $geo[2], $hash, $docId, $g['id']]);
                $delGuiaDet->execute([$g['id']]);
                $gid = (int)$g['id'];
                $this->count('guias', 'actualizados');
            } else {
                $insGuia->execute([$gNumero, $r['fecha'], $cli['id'], $vend, $suc, $geo[0], $geo[1], $geo[2], $docStatus, $this->actorId ?: null, $docStatus === 'PUBLICADO' ? $now : null, self::ORIGEN_VENTAS, $ref, $hash, $docId]);
                $gid = (int)$this->pdo->lastInsertId();
                if ($docStatus === 'PUBLICADO') $this->published['guias'][] = $gid;
                $this->count('guias', 'insertados');
            }
            $insGuiaDet->execute([$gid, $p['id'], $p['unit'], $r['cantidad']]);
        }
    }

    private function initialStatus(string $mode, string $fecha, bool $anulado, string $prevMonth, string $curMonth): string
    {
        if ($anulado) return 'BORRADOR';
        if ($mode !== 'ROLLING') return $mode;
        if ($fecha < $prevMonth) return 'PUBLICADO';
        if ($fecha < $curMonth) return 'VALIDADO';
        return 'BORRADOR';
    }

    /** Registra en publicaciones/detalle_publicacion lo que la carga dejó PUBLICADO. */
    private function registerPublications(): void
    {
        if (!$this->actorId) return;
        $map = ['documentos' => 'documents', 'guias' => 'guides', 'stock' => 'stock'];
        foreach ($this->published as $mod => $ids) {
            if (!$ids) continue;
            $this->pdo->prepare("INSERT INTO publicaciones(modulo,usuario_id,estado) VALUES(?,?,'IMPORTACION')")->execute([$mod, $this->actorId]);
            $pub = (int)$this->pdo->lastInsertId();
            foreach (array_chunk($ids, 500) as $chunk) {
                $vals = implode(',', array_fill(0, count($chunk), '(?,?,?)'));
                $params = [];
                foreach ($chunk as $id) array_push($params, $pub, $id, $map[$mod]);
                $this->pdo->prepare("INSERT INTO detalle_publicacion(publicacion_id,registro_id,dataset) VALUES $vals")->execute($params);
            }
        }
    }

    // =====================================================================
    // Helpers de maestros
    // =====================================================================
    private function dealer(): int
    {
        if (isset($this->idCache['dealer'])) return $this->idCache['dealer'];
        $d = $this->cfg['dealer'];
        return $this->idCache['dealer'] = $this->upsert('empresas', 'ruc', ['ruc' => $d['ruc'], 'razon_social' => $d['name'], 'nombre_comercial' => $d['name'], 'estado' => 1], 'empresas');
    }

    private function sucursalBayer(string $sede, int $empresa): int
    {
        $key = 'suc:'.$sede;
        if (isset($this->idCache[$key])) return $this->idCache[$key];
        $c = $this->cfg['sedes_bayer'][$sede] ?? null;
        $code = $c['codigo'] ?? 'SED-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $sede)), 0, 3));
        if (!$c) $this->warn("Sede '$sede' sin configuración en config/import.php; se creó como $code");
        $geo = $c ? $this->geo($c['departamento'], $c['provincia'], $c['distrito']) : [null, null, null];
        // Si la sucursal ya existe (p.ej. creada por 01_sedes_almacenes) sólo se completan datos vacíos.
        $id = $this->lookup('sucursales', 'codigo', $code);
        if (!$id) {
            $id = $this->upsert('sucursales', 'codigo', ['codigo' => $code, 'empresa_id' => $empresa, 'nombre' => $c['nombre'] ?? 'Sede '.$sede, 'ciudad' => $sede, 'direccion' => '', 'distrito_id' => $geo[2], 'estado' => 1], 'sucursales');
            $this->homologate('homologacion_sucursales', 'sucursal_id', $id, ['branch_id' => $code, 'branch_name' => $c['nombre'] ?? 'Sede '.$sede]);
        } elseif ($geo[2]) {
            $this->pdo->prepare('UPDATE sucursales SET distrito_id=COALESCE(distrito_id,?),ciudad=COALESCE(ciudad,?) WHERE id=?')->execute([$geo[2], $sede, $id]);
        }
        return $this->idCache[$key] = $id;
    }

    private function productRef(string $code): ?array
    {
        $key = 'prod:'.$code;
        if (array_key_exists($key, $this->idCache)) return $this->idCache[$key];
        $st = $this->pdo->prepare('SELECT id,unidad_base_id unit FROM productos WHERE codigo=?');
        $st->execute([$code]);
        $r = $st->fetch();
        if ($r && !$r['unit']) $r['unit'] = $this->unit('UND');
        return $this->idCache[$key] = $r ? ['id' => (int)$r['id'], 'unit' => (int)$r['unit']] : null;
    }

    private function unit(string $raw): int
    {
        $k = strtoupper(trim($raw)) ?: 'UND';
        $u = $this->cfg['unidades'][$k] ?? ['codigo' => $k, 'nombre' => $k];
        $key = 'unit:'.$u['codigo'];
        if (isset($this->idCache[$key])) return $this->idCache[$key];
        $id = $this->upsert('unidades_medida', 'codigo', ['codigo' => $u['codigo'], 'nombre' => $u['nombre'], 'abreviatura' => $u['codigo'], 'factor_base' => 1], 'unidades', false, ['nombre', 'abreviatura']);
        $this->homologate('homologacion_unidades', 'unidad_id', $id, ['external_unit_code' => $u['codigo'], 'external_unit_name' => $u['nombre']]);
        return $this->idCache[$key] = $id;
    }

    private function catalog(string $table, string $name, array $extra): ?int
    {
        $name = trim($name);
        if ($name === '') return null;
        $key = "$table:$name";
        if (isset($this->idCache[$key])) return $this->idCache[$key];
        return $this->idCache[$key] = $this->upsert($table, 'nombre', ['nombre' => $name] + $extra, $table, false, []);
    }

    /** @return array{0:?int,1:?int,2:?int} [departamento, provincia, distrito] */
    private function geo(?string $dep, ?string $prov = null, ?string $dist = null): array
    {
        $dep = trim((string)$dep); $prov = trim((string)$prov); $dist = trim((string)$dist);
        $key = "$dep|$prov|$dist";
        if (isset($this->geoCache[$key])) return $this->geoCache[$key];
        $out = [null, null, null];
        if ($dep !== '') {
            $this->pdo->prepare('INSERT IGNORE INTO departamentos(nombre) VALUES(?)')->execute([$dep]);
            $out[0] = $this->lookup('departamentos', 'nombre', $dep);
            if ($prov !== '') {
                $this->pdo->prepare('INSERT IGNORE INTO provincias(departamento_id,nombre) VALUES(?,?)')->execute([$out[0], $prov]);
                $st = $this->pdo->prepare('SELECT id FROM provincias WHERE departamento_id=? AND nombre=?');
                $st->execute([$out[0], $prov]);
                $out[1] = (int)$st->fetchColumn();
                if ($dist !== '') {
                    $this->pdo->prepare('INSERT IGNORE INTO distritos(provincia_id,nombre) VALUES(?,?)')->execute([$out[1], $dist]);
                    $st = $this->pdo->prepare('SELECT id FROM distritos WHERE provincia_id=? AND nombre=?');
                    $st->execute([$out[1], $dist]);
                    $out[2] = (int)$st->fetchColumn();
                }
            }
        }
        return $this->geoCache[$key] = $out;
    }

    private function lookup(string $table, string $col, mixed $val): ?int
    {
        $st = $this->pdo->prepare("SELECT id FROM `$table` WHERE `$col`=? LIMIT 1");
        $st->execute([$val]);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }

    /**
     * UPSERT por clave natural. Sólo actualiza columnas que cambiaron.
     * @param array|null $updatable columnas actualizables (null = todas)
     */
    private function upsert(string $table, string $key, array $data, string $entity, bool $touch = false, ?array $updatable = null): int
    {
        $cols = array_keys($data);
        $st = $this->pdo->prepare('SELECT id,`'.implode('`,`', $cols).'` FROM `'.$table.'` WHERE `'.$key.'`=? LIMIT 1');
        $st->execute([$data[$key]]);
        $row = $st->fetch();
        if (!$row) {
            $ins = $this->pdo->prepare('INSERT INTO `'.$table.'`(`'.implode('`,`', $cols).'`) VALUES('.implode(',', array_fill(0, count($cols), '?')).')');
            $ins->execute(array_values($data));
            $this->count($entity, 'insertados');
            return (int)$this->pdo->lastInsertId();
        }
        $diff = [];
        foreach ($data as $c => $v) {
            if ($c === $key || ($updatable !== null && !in_array($c, $updatable, true))) continue;
            $cur = $row[$c];
            $same = ($cur === null && $v === null) || ($cur !== null && $v !== null && (is_numeric($cur) && is_numeric($v) ? (float)$cur == (float)$v : (string)$cur === (string)$v));
            if (!$same) $diff[$c] = $v;
        }
        if (!$diff) { $this->count($entity, 'sin_cambios'); return (int)$row['id']; }
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($diff))).($touch ? ',updated_at=NOW()' : '');
        $this->pdo->prepare("UPDATE `$table` SET $set WHERE id=?")->execute([...array_values($diff), $row['id']]);
        $this->count($entity, 'actualizados');
        return (int)$row['id'];
    }

    /** Alta de homologación Bayer sin sobrescribir ajustes manuales existentes. */
    private function homologate(string $table, string $fk, int $id, array $data): void
    {
        $this->partnerId ??= (int)$this->pdo->query("SELECT id FROM partners WHERE codigo='BAYER'")->fetchColumn();
        if (!$this->partnerId) return;
        $cols = array_merge(['partner_id', $fk], array_keys($data));
        $sql = 'INSERT IGNORE INTO `'.$table.'`(`'.implode('`,`', $cols).'`) VALUES('.implode(',', array_fill(0, count($cols), '?')).')';
        $st = $this->pdo->prepare($sql);
        $st->execute(array_merge([$this->partnerId, $id], array_values($data)));
        if ($st->rowCount()) $this->count('homologaciones', 'insertados');
    }

    private function count(string $entity, string $what, int $n = 1): void
    {
        $this->stats[$entity] ??= ['insertados' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => 0];
        $this->stats[$entity][$what] += $n;
    }

    private function skip(string $entity, string $msg): void { $this->count($entity, 'omitidos'); $this->warn($msg); }
    private function warn(string $msg): void { $this->warnings[] = $msg; }

    private function totals(): array
    {
        $t = ['insertados' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'omitidos' => 0];
        foreach ($this->stats as $s) foreach ($t as $k => $_) $t[$k] += $s[$k];
        return $t;
    }
}

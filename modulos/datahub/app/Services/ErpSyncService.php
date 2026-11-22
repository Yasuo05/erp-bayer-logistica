<?php
namespace App\Services;

/**
 * Sincroniza el Data Hub (Grupo 9) con la operación real del ERP.
 *  - Documentos: comprobantes de Facturación (G1) emitidos desde ventas del ERP (G8), con su detalle.
 *  - Guías: despachos de Logística (G7) originados por ventas del ERP.
 *  - Stock: foto del día de los saldos de Logística por almacén.
 * Es idempotente: usa las claves de origen (origen + origen_ref) y la huella origen_hash para insertar,
 * actualizar solo lo que cambió o no tocar nada. No modifica las tablas de los otros grupos.
 *
 * Estado en el Data Hub (lo que Bayer puede ver es lo PUBLICADO):
 *  - Comprobante aceptado u observado por SUNAT → PUBLICADO; anulado → ANULADO; en proceso → BORRADOR.
 *  - Despacho en ruta o entregado → PUBLICADO; en preparación → BORRADOR; cancelado → ANULADO.
 *  - Stock del día → PUBLICADO.
 */
class ErpSyncService
{
    private const ORIGEN_DOC = 'ERP_G1';
    private const ORIGEN_GUIA = 'ERP_G7';
    private const ORIGEN_STOCK = 'ERP_G7_STOCK';

    private \PDO $pdo;
    private array $stats = ['documentos' => 0, 'guias' => 0, 'stock' => 0, 'sin_cambios' => 0, 'omitidos' => 0];

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function run(): array
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $this->documentos();
            $this->guias();
            $this->stock();
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return $this->stats;
    }

    private function documentos(): void
    {
        $tipos = $this->pdo->query("SELECT codigo, id FROM tipos_documento WHERE codigo IN ('01','03','07','08')")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $codigoTipo = ['factura' => '01', 'boleta' => '03', 'nota_credito' => '07', 'nota_debito' => '08'];
        // Facturas y boletas de ventas del ERP, y notas de crédito/débito sobre ellas (la nota toma la venta de su comprobante de referencia)
        $rows = $this->pdo->query("SELECT f.id, f.tipo, f.serie, f.numero, f.fecha_emision, f.subtotal, f.total, f.estado,
                v.id venta_id, v.cliente_id, v.vendedor_id, v.sucursal_id, v.moneda
            FROM facturacion_comprobantes f
            LEFT JOIN facturacion_comprobantes ref ON ref.id = f.comprobante_referenciado_id
            JOIN ventas v ON v.id = COALESCE(f.venta_erp_id, ref.venta_erp_id)
            WHERE f.tipo IN ('factura','boleta','nota_credito','nota_debito')")->fetchAll();
        // Valor unitario NETO de descuento: cantidad × valor_unitario = base imponible de la línea (igual que el comprobante)
        $detalle = $this->pdo->prepare("SELECT producto_id, unidad_id, cantidad,
                ROUND((ROUND(cantidad * precio_unitario, 2) - descuento) / cantidad, 6) AS precio_unitario,
                ROUND(ROUND(cantidad * precio_unitario, 2) - descuento, 2) AS base
            FROM venta_detalles WHERE venta_id = ? ORDER BY linea");
        foreach ($rows as $f) {
            $tipoId = $tipos[$codigoTipo[$f['tipo']]] ?? null;
            if (!$tipoId || !$f['vendedor_id']) { $this->stats['omitidos']++; continue; } // el Data Hub exige tipo y vendedor
            $estado = in_array($f['estado'], ['aceptado', 'observado'], true) ? 'PUBLICADO'
                : (in_array($f['estado'], ['anulado', 'anulado_local'], true) ? 'ANULADO' : 'BORRADOR');
            $numero = $f['serie'] . '-' . str_pad((string)$f['numero'], 8, '0', STR_PAD_LEFT);
            $detalle->execute([$f['venta_id']]);
            $lineas = $detalle->fetchAll();
            $esNota = in_array($f['tipo'], ['nota_credito', 'nota_debito'], true);
            $signo = $f['tipo'] === 'nota_credito' ? -1 : 1;
            // Nota: ajusta dinero sin mover unidades; su base se reparte entre los productos de la venta según su peso
            $filas = [];
            if ($esNota) {
                $totalBase = array_sum(array_column($lineas, 'base')) ?: 1;
                $restante = round((float)$f['subtotal'], 2);
                foreach ($lineas as $i => $l) {
                    $parte = $i === count($lineas) - 1 ? $restante : round((float)$f['subtotal'] * (float)$l['base'] / $totalBase, 2);
                    $restante = round($restante - $parte, 2);
                    $filas[] = [$l['producto_id'], $l['unidad_id'], 0, 0, $signo * $parte];
                }
            } else {
                foreach ($lineas as $l) $filas[] = [$l['producto_id'], $l['unidad_id'], $l['cantidad'], $l['precio_unitario'], $l['base']];
            }
            $hash = sha1(json_encode(['v3', $numero, $f['fecha_emision'], $f['total'], $estado, $f['cliente_id'], $f['vendedor_id'], $filas]));
            $id = $this->upsert('documentos_cabecera', self::ORIGEN_DOC, (string)$f['id'], $hash, [
                'tipo_documento_id' => $tipoId, 'numero' => $numero, 'fecha' => substr($f['fecha_emision'], 0, 10),
                'cliente_id' => $f['cliente_id'], 'vendedor_id' => $f['vendedor_id'], 'sucursal_id' => $f['sucursal_id'],
                'estado_registro' => $estado, 'estado_comercial' => $estado === 'ANULADO' ? 'ANULADA' : 'APROBADA',
                'monto_total' => ($esNota ? $signo : 1) * (float)$f['total'], 'moneda' => $f['moneda'] ?: 'PEN',
                'published_at' => $estado === 'PUBLICADO' ? date('Y-m-d H:i:s') : null,
            ], 'documentos');
            if ($id === null) continue;
            $this->pdo->prepare('DELETE FROM documentos_detalle WHERE documento_id = ?')->execute([$id]);
            $ins = $this->pdo->prepare('INSERT INTO documentos_detalle (documento_id, producto_id, unidad_id, cantidad, valor_unitario, importe) VALUES (?,?,?,?,?,?)');
            foreach ($filas as $fila) $ins->execute([$id, ...$fila]);
        }
    }

    private function guias(): void
    {
        // Logística ya gestiona las guías validadas: el portal no debe cambiar su número,
        // versión ni detalle al consultar. Se conserva el adaptador para registros heredados.
        $hasWorkflow = (bool)$this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='g7_envios'")->fetchColumn();
        $legacyOnly = $hasWorkflow ? ' WHERE NOT EXISTS (SELECT 1 FROM g7_envios le WHERE le.despacho_id=d.id AND le.legado=0)' : '';
        $rows = $this->pdo->query("SELECT d.id, d.codigo, d.producto_id, d.cantidad, d.estado, d.fecha, d.fecha_salida,
                v.id venta_id, v.cliente_id, v.vendedor_id, v.sucursal_id, p.unidad_base_id unidad_id, s.distrito_id
            FROM g7_despachos d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id
            JOIN sucursales s ON s.id = v.sucursal_id" . $legacyOnly)->fetchAll();
        $doc = $this->pdo->prepare("SELECT dc.id FROM documentos_cabecera dc JOIN facturacion_comprobantes f ON dc.origen = '" . self::ORIGEN_DOC . "' AND dc.origen_ref = f.id WHERE f.venta_erp_id = ? LIMIT 1");
        foreach ($rows as $g) {
            if (!$g['vendedor_id']) { $this->stats['omitidos']++; continue; }
            $estado = in_array($g['estado'], ['EN_RUTA', 'ENTREGADO'], true) ? 'PUBLICADO' : ($g['estado'] === 'CANCELADO' ? 'ANULADO' : 'BORRADOR');
            $doc->execute([$g['venta_id']]);
            $documentoId = $doc->fetchColumn() ?: null;
            $hash = sha1(json_encode([$g['codigo'], $g['estado'], $g['cantidad'], $g['fecha_salida'], $documentoId]));
            $id = $this->upsert('guias_cabecera', self::ORIGEN_GUIA, (string)$g['id'], $hash, [
                'numero' => $g['codigo'], 'fecha' => substr($g['fecha_salida'] ?: $g['fecha'], 0, 10),
                'cliente_id' => $g['cliente_id'], 'vendedor_id' => $g['vendedor_id'], 'sucursal_id' => $g['sucursal_id'],
                'distrito_id' => $g['distrito_id'], 'estado_registro' => $estado, 'documento_id' => $documentoId,
                'published_at' => $estado === 'PUBLICADO' ? date('Y-m-d H:i:s') : null,
            ], 'guias');
            if ($id === null) continue;
            $this->pdo->prepare('DELETE FROM guias_detalle WHERE guia_id = ?')->execute([$id]);
            $this->pdo->prepare('INSERT INTO guias_detalle (guia_id, producto_id, unidad_id, cantidad) VALUES (?,?,?,?)')
                ->execute([$id, $g['producto_id'], $g['unidad_id'], $g['cantidad']]);
        }
    }

    private function stock(): void
    {
        $hoy = date('Y-m-d');
        $saldos = $this->pdo->query("SELECT s.almacen_id, s.producto_id, p.unidad_base_id unidad_id, s.cantidad, s.minimo
            FROM g7_saldos s JOIN productos p ON p.id = s.producto_id ORDER BY s.almacen_id, s.producto_id")->fetchAll();
        $porAlmacen = [];
        foreach ($saldos as $r) $porAlmacen[$r['almacen_id']][] = $r;
        foreach ($porAlmacen as $almacen => $lineas) {
            $ref = $almacen . '@' . $hoy;
            $hash = sha1(json_encode($lineas));
            $clave = 'ERP_G7:' . $ref;
            $id = $this->upsert('stock_cabecera', self::ORIGEN_STOCK, $ref, $hash, [
                'fecha_stock' => $hoy, 'almacen_id' => $almacen, 'estado_registro' => 'PUBLICADO',
                'idempotency_key' => $clave, 'request_hash' => hash('sha256', $clave . $hash),
                'published_at' => date('Y-m-d H:i:s'),
            ], 'stock');
            if ($id === null) continue;
            $this->pdo->prepare('DELETE FROM stock_detalle WHERE stock_id = ?')->execute([$id]);
            $ins = $this->pdo->prepare('INSERT INTO stock_detalle (stock_id, producto_id, unidad_id, cantidad, fecha_stock, almacen_id, stock_minimo) VALUES (?,?,?,?,?,?,?)');
            foreach ($lineas as $l) $ins->execute([$id, $l['producto_id'], $l['unidad_id'], $l['cantidad'], $hoy, $almacen, $l['minimo']]);
        }
    }

    /**
     * Inserta o actualiza la cabecera por su clave de origen. Devuelve el id si hubo cambios
     * (para reescribir el detalle) o null si el registro ya estaba al día.
     */
    private function upsert(string $tabla, string $origen, string $ref, string $hash, array $datos, string $contador): ?int
    {
        $st = $this->pdo->prepare("SELECT id, origen_hash FROM `$tabla` WHERE origen = ? AND origen_ref = ? FOR UPDATE");
        $st->execute([$origen, $ref]);
        $actual = $st->fetch();
        if ($actual && $actual['origen_hash'] === $hash) { $this->stats['sin_cambios']++; return null; }
        $datos += ['origen_hash' => $hash, 'updated_at' => date('Y-m-d H:i:s')];
        if ($actual) {
            if (array_key_exists('published_at', $datos) && $datos['published_at'] !== null) unset($datos['published_at']); // conserva la primera publicación
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($datos)));
            $this->pdo->prepare("UPDATE `$tabla` SET $set, version = version + 1 WHERE id = ?")->execute([...array_values($datos), $actual['id']]);
            $this->stats[$contador]++;
            return (int)$actual['id'];
        }
        $datos += ['origen' => $origen, 'origen_ref' => $ref];
        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($datos)));
        $this->pdo->prepare("INSERT INTO `$tabla` ($cols) VALUES (" . implode(', ', array_fill(0, count($datos), '?')) . ')')->execute(array_values($datos));
        $this->stats[$contador]++;
        return (int)$this->pdo->lastInsertId();
    }
}

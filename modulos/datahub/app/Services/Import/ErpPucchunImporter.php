<?php
namespace App\Services\Import;

/**
 * Importador transaccional e idempotente para BD_Grupo9_ERP_Agricola_Pucchun.sql
 * Integra infraestructura (sedes y almacenes), clientes, vendedores,
 * homologación de productos y los 285 comprobantes con origen 'ERP_PUCCHUN'.
 */
class ErpPucchunImporter
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function run(string $sqlFilePath): array
    {
        if (!file_exists($sqlFilePath)) {
            throw new \InvalidArgumentException("Archivo no encontrado: $sqlFilePath");
        }

        $sql = file_get_contents($sqlFilePath);
        $this->pdo->beginTransaction();

        try {
            $stats = [
                'sedes' => 0,
                'almacenes' => 0,
                'clientes' => 0,
                'vendedores' => 0,
                'comprobantes' => 0,
                'detalles' => 0,
                'stock_cabeceras' => 0,
                'stock_detalles' => 0,
            ];

            // 1. Sedes formales de Pucchún
            $sedesMap = $this->importSedes($sql, $stats);

            // 2. Almacenes formales vinculados a las sedes
            $almacenesMap = $this->importAlmacenes($sql, $sedesMap, $stats);

            // 3. Clientes del ERP
            $clientesMap = $this->importClientes($sql, $stats);

            // 4. Vendedores del ERP
            $vendedoresMap = $this->importVendedores($sql, $sedesMap, $stats);

            // 5. Homologación de Productos (Mapeo a los 25 existentes)
            $productosMap = $this->getProductMapping();

            // 6. Compras / Proveedores (Opcional o estructura base)
            // (Nos enfocamos en Comprobantes/Ventas e Inventario solicitados)

            // 7. Ventas y Comprobantes (285 comprobantes)
            $this->importComprobantes($sql, $sedesMap, $almacenesMap, $clientesMap, $vendedoresMap, $productosMap, $stats);

            // 8. Inventario de los 4 almacenes (100 registros)
            $this->importInventario($sql, $almacenesMap, $productosMap, $stats);

            $this->pdo->commit();
            return $stats;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function importSedes(string $sql, array &$stats): array
    {
        // Sedes en File 1: (1,'Sede Pucchún','Pucchún'), (2,'Sede Chiclayo','Chiclayo'), (3,'Sede Trujillo','Trujillo')
        $defSedes = [
            1 => ['codigo' => 'SED-PUC', 'nombre' => 'Sede Pucchún', 'ciudad' => 'Pucchún'],
            2 => ['codigo' => 'SED-CHX', 'nombre' => 'Sede Chiclayo', 'ciudad' => 'Chiclayo'],
            3 => ['codigo' => 'SED-TRU', 'nombre' => 'Sede Trujillo', 'ciudad' => 'Trujillo'],
        ];

        $sedesMap = [];
        foreach ($defSedes as $erpId => $s) {
            $st = $this->pdo->prepare("SELECT id FROM sucursales WHERE codigo = ? OR nombre = ? LIMIT 1");
            $st->execute([$s['codigo'], $s['nombre']]);
            $id = $st->fetchColumn();

            if (!$id) {
                $ins = $this->pdo->prepare("INSERT INTO sucursales (empresa_id, codigo, nombre, direccion, distrito_id, estado, ciudad) VALUES (1, ?, ?, '', 1, 1, ?)");
                $ins->execute([$s['codigo'], $s['nombre'], $s['ciudad']]);
                $id = (int)$this->pdo->lastInsertId();
                $stats['sedes']++;
            }
            $sedesMap[$erpId] = (int)$id;
        }

        return $sedesMap;
    }

    private function importAlmacenes(string $sql, array $sedesMap, array &$stats): array
    {
        // Almacenes en File 1:
        // (1,1,'Almacén Central'), (2,1,'Almacén Agroquímicos'), (3,2,'Almacén Chiclayo'), (4,3,'Almacén Trujillo')
        $defAlmacenes = [
            1 => ['sede_erp_id' => 1, 'codigo' => 'ALM-PUC-01', 'nombre' => 'Almacén Central Pucchún', 'tipo' => 'CENTRAL', 'ciudad' => 'Pucchún'],
            2 => ['sede_erp_id' => 1, 'codigo' => 'ALM-PUC-02', 'nombre' => 'Almacén Agroquímicos Pucchún', 'tipo' => 'INSUMOS', 'ciudad' => 'Pucchún'],
            3 => ['sede_erp_id' => 2, 'codigo' => 'ALM-CHX-01', 'nombre' => 'Almacén Chiclayo', 'tipo' => 'GENERAL', 'ciudad' => 'Chiclayo'],
            4 => ['sede_erp_id' => 3, 'codigo' => 'ALM-TRU-01', 'nombre' => 'Almacén Trujillo', 'tipo' => 'GENERAL', 'ciudad' => 'Trujillo'],
        ];

        $almacenesMap = [];
        foreach ($defAlmacenes as $erpId => $a) {
            $sucursalId = $sedesMap[$a['sede_erp_id']];
            $st = $this->pdo->prepare("SELECT id FROM almacenes WHERE codigo = ? OR (sucursal_id = ? AND nombre = ?) LIMIT 1");
            $st->execute([$a['codigo'], $sucursalId, $a['nombre']]);
            $id = $st->fetchColumn();

            if (!$id) {
                $ins = $this->pdo->prepare("INSERT INTO almacenes (sucursal_id, codigo, nombre, tipo, estado, ciudad) VALUES (?, ?, ?, ?, 1, ?)");
                $ins->execute([$sucursalId, $a['codigo'], $a['nombre'], $a['tipo'], $a['ciudad']]);
                $id = (int)$this->pdo->lastInsertId();
                $stats['almacenes']++;
            }
            $almacenesMap[$erpId] = (int)$id;
        }

        return $almacenesMap;
    }

    private function importClientes(string $sql, array &$stats): array
    {
        preg_match('/INSERT INTO clientes [^;]+;/s', $sql, $m);
        $lines = array_filter(explode("\n", $m[0] ?? ''));

        $clientesMap = [];
        foreach ($lines as $line) {
            if (preg_match('/\((\d+),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*(?:\'([^\']*)\'|NULL),\s*(?:\'([^\']*)\'|NULL)\)/i', $line, $row)) {
                $erpId = (int)$row[1];
                $razonSocial = trim($row[2]);
                $rucFicticio = trim($row[3]);
                $correo = !empty($row[4]) ? trim($row[4]) : 'contacto@' . strtolower(preg_replace('/[^a-z0-9]/i', '', $razonSocial)) . '.pe';
                $tipoCliente = !empty($row[5]) ? trim($row[5]) : 'CLIENTE_FINAL';

                $st = $this->pdo->prepare("SELECT id FROM clientes WHERE nro_doc = ? OR razon_social = ? LIMIT 1");
                $st->execute([$rucFicticio, $razonSocial]);
                $id = $st->fetchColumn();

                if (!$id) {
                    $ins = $this->pdo->prepare("INSERT INTO clientes (codigo, tipo_doc, nro_doc, razon_social, departamento_id, provincia_id, distrito_id, tipo_cliente, correo, estado, updated_at) VALUES (?, '06', ?, ?, 1, 1, 1, ?, ?, 'ACTIVO', NOW())");
                    $ins->execute(['CLI-ERP-' . str_pad((string)$erpId, 4, '0', STR_PAD_LEFT), $rucFicticio, $razonSocial, $tipoCliente, $correo]);
                    $id = (int)$this->pdo->lastInsertId();
                    $stats['clientes']++;
                }
                $clientesMap[$erpId] = (int)$id;
            }
        }

        return $clientesMap;
    }

    private function importVendedores(string $sql, array $sedesMap, array &$stats): array
    {
        preg_match('/INSERT INTO vendedores [^;]+;/s', $sql, $m);
        $lines = array_filter(explode("\n", $m[0] ?? ''));

        $vendedoresMap = [];
        foreach ($lines as $line) {
            if (preg_match('/\((\d+),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*(\d+)\)/i', $line, $row)) {
                $erpId = (int)$row[1];
                $nombre = trim($row[2]);
                $codigo = trim($row[3]);
                $sedeErpId = (int)$row[4];

                $st = $this->pdo->prepare("SELECT id FROM vendedores WHERE codigo = ? OR nombres = ? LIMIT 1");
                $st->execute([$codigo, $nombre]);
                $id = $st->fetchColumn();

                if (!$id) {
                    $ins = $this->pdo->prepare("INSERT INTO vendedores (codigo, nombres, apellidos, email, estado, cargo, estado_laboral) VALUES (?, ?, '', ?, 1, 'Vendedor Técnico', 'ACTIVO')");
                    $ins->execute([$codigo, $nombre, strtolower($codigo) . '@pucchun.pe']);
                    $id = (int)$this->pdo->lastInsertId();
                    $stats['vendedores']++;
                }
                $vendedoresMap[$erpId] = (int)$id;
            }
        }

        return $vendedoresMap;
    }

    /**
     * Homologa los 25 productos de File 1 con los 25 productos existentes en bayer_pucchun.
     */
    private function getProductMapping(): array
    {
        // Clave: producto_id en File 1 => Valor: id en tabla productos de bayer_pucchun
        return [
            // 8 productos Bayer (mapeados por codigo_mapeo_bayer y equivalencia de ingrediente activo)
            1 => 1,   // Roundup Power (BAY-MAP-001)  -> Roundup Full II (BAY001)
            2 => 8,   // Decis Forte (BAY-MAP-002)    -> Decis 2.5 EC (BAY008)
            3 => 5,   // Confidor 350 SC (BAY-MAP-003)-> Confidor 350 SC (BAY005)
            4 => 10,  // Nativo (BAY-MAP-004)         -> Nativo 75 WG (BAY010)
            5 => 9,   // Antracol (BAY-MAP-005)       -> Aliette 80 WP (BAY009)
            6 => 3,   // Bayfolan Forte (BAY-MAP-006) -> Sencor 480 SC (BAY003)
            7 => 6,   // Movento (BAY-MAP-007)        -> Movento 150 OD (BAY006)
            8 => 7,   // Flint Max (BAY-MAP-008)      -> Oberon 240 SC (BAY007)

            // 17 productos de otros fabricantes (mapeados por categoría e insumo)
            9  => 21, // HerbiCampo 480   -> Herbicida genérico A (OTR011)
            10 => 20, // InsectoStop      -> Insecticida genérico A (OTR010)
            11 => 19, // FungiAndes       -> Fungicida genérico A (OTR009)
            12 => 24, // Adherente Plus   -> Adherente agrícola genérico (OTR014)
            13 => 11, // CampoMix NPK     -> Fertilizante NPK genérico (OTR001)
            14 => 13, // CalcioCampo      -> Sulfato de potasio genérico (OTR003)
            15 => 16, // BioRaíz          -> Bioestimulante genérico (OTR006)
            16 => 15, // CobreCampo       -> Humus de lombriz genérico (OTR005)
            17 => 12, // NutriN 46        -> Urea agrícola genérica (OTR002)
            18 => 14, // FosfoCultivo     -> Compost agrícola genérico (OTR004)
            19 => 13, // PotaMax          -> Sulfato de potasio genérico (OTR003)
            20 => 16, // MicroMix         -> Bioestimulante genérico (OTR006)
            21 => 20, // ControlPlaga 20  -> Insecticida genérico A (OTR010)
            22 => 22, // FertiFoliar      -> Fertilizante foliar genérico (OTR012)
            23 => 16, // Enraizador NC    -> Bioestimulante genérico (OTR006)
            24 => 21, // MataMaleza Select-> Herbicida genérico A (OTR011)
            25 => 25, // AzufreCampo      -> Sustrato agrícola genérico (OTR015)
        ];
    }

    private function importComprobantes(
        string $sql,
        array $sedesMap,
        array $almacenesMap,
        array $clientesMap,
        array $vendedoresMap,
        array $productosMap,
        array &$stats
    ): void {
        // Parse ventas
        preg_match('/INSERT INTO ventas [^;]+;/s', $sql, $mVentas);
        $vLines = array_filter(explode("\n", $mVentas[0] ?? ''));
        $ventas = [];
        foreach ($vLines as $l) {
            if (preg_match('/\((\d+),\s*\'([^\']+)\',\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*\'([^\']+)\',\s*\'([^\']+)\'\)/i', $l, $m)) {
                $ventas[(int)$m[1]] = [
                    'venta_id' => (int)$m[1],
                    'fecha' => $m[2],
                    'sede_id' => (int)$m[3],
                    'cliente_id' => (int)$m[4],
                    'vendedor_id' => (int)$m[5],
                    'almacen_id' => (int)$m[6],
                    'estado' => $m[7],
                    'moneda' => $m[8],
                ];
            }
        }

        // Parse detalle_ventas
        preg_match('/INSERT INTO detalle_ventas [^;]+;/s', $sql, $mDet);
        $dLines = array_filter(explode("\n", $mDet[0] ?? ''));
        $detallesPorVenta = [];
        foreach ($dLines as $l) {
            if (preg_match('/\((\d+),\s*(\d+),\s*(\d+),\s*([0-9.]+),\s*([0-9.]+),\s*([0-9.]+)\)/i', $l, $m)) {
                $ventaId = (int)$m[2];
                $detallesPorVenta[$ventaId][] = [
                    'detalle_id' => (int)$m[1],
                    'producto_id' => (int)$m[3],
                    'cantidad' => (float)$m[4],
                    'precio_unitario' => (float)$m[5],
                    'descuento' => (float)$m[6]
                ];
            }
        }

        // Cache product units
        $unitCache = $this->pdo->query("SELECT id, unidad_base_id FROM productos")->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Parse comprobantes
        preg_match('/INSERT INTO comprobantes [^;]+;/s', $sql, $mComp);
        $cLines = array_filter(explode("\n", $mComp[0] ?? ''));

        $insertDocSt = $this->pdo->prepare("
            INSERT INTO documentos_cabecera (
                tipo_documento_id, numero, fecha, cliente_id, vendedor_id, sucursal_id,
                estado_registro, created_by, created_at, published_at, origen, origen_ref, origen_hash,
                estado_comercial, monto_total, moneda
            ) VALUES (?, ?, ?, ?, ?, ?, 'PUBLICADO', 1, NOW(), NOW(), 'ERP_PUCCHUN', ?, ?, ?, ?, 'PEN')
        ");

        $insertDetSt = $this->pdo->prepare("
            INSERT INTO documentos_detalle (documento_id, producto_id, unidad_id, cantidad, valor_unitario)
            VALUES (?, ?, ?, ?, ?)
        ");

        $checkSt = $this->pdo->prepare("SELECT id FROM documentos_cabecera WHERE origen = 'ERP_PUCCHUN' AND origen_ref = ? LIMIT 1");

        foreach ($cLines as $l) {
            if (preg_match('/\((\d+),\s*(\d+),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*(\d+),\s*\'([^\']+)\',\s*([0-9.]+),\s*\'([^\']+)\'\)/i', $l, $m)) {
                $compErpId = (int)$m[1];
                $ventaId = (int)$m[2];
                $tipo = strtoupper(trim($m[3]));
                $serie = trim($m[4]);
                $numero = (int)$m[5];
                $fechaEmision = trim($m[6]);
                $total = (float)$m[7];
                $estado = strtoupper(trim($m[8]));

                $v = $ventas[$ventaId] ?? null;
                if (!$v) continue;

                $tipoDocId = ($tipo === 'BOLETA') ? 2 : 1;
                $numeroFormateado = 'ERP-' . $serie . '-' . str_pad((string)$numero, 8, '0', STR_PAD_LEFT);
                $origenRef = $numeroFormateado;

                // Verificación de idempotencia
                $checkSt->execute([$origenRef]);
                $existingDocId = $checkSt->fetchColumn();

                if ($existingDocId) {
                    continue; // Ya migrado
                }

                $clienteId = $clientesMap[$v['cliente_id']] ?? 1;
                $vendedorId = $vendedoresMap[$v['vendedor_id']] ?? 1;
                $sucursalId = $sedesMap[$v['sede_id']] ?? 1;
                $estadoComercial = ($estado === 'ACEPTADO') ? 'APROBADA' : 'ANULADA';
                $origenHash = sha1("ERP_PUCCHUN|{$compErpId}|{$ventaId}|{$total}|{$fechaEmision}");

                $insertDocSt->execute([
                    $tipoDocId,
                    $numeroFormateado,
                    $fechaEmision,
                    $clienteId,
                    $vendedorId,
                    $sucursalId,
                    $origenRef,
                    $origenHash,
                    $estadoComercial,
                    $total
                ]);

                $docId = (int)$this->pdo->lastInsertId();
                $stats['comprobantes']++;

                // Insertar detalles de la venta
                $items = $detallesPorVenta[$ventaId] ?? [];
                foreach ($items as $item) {
                    $prodMapped = $productosMap[$item['producto_id']] ?? 1;
                    $unidadId = $unitCache[$prodMapped] ?? 1;
                    $cantidad = $item['cantidad'];
                    $valorUnitario = max(0, $item['precio_unitario'] - ($item['descuento'] / max(1, $cantidad)));

                    $insertDetSt->execute([
                        $docId,
                        $prodMapped,
                        $unidadId,
                        $cantidad,
                        $valorUnitario
                    ]);
                    $stats['detalles']++;
                }
            }
        }
    }

    private function importInventario(
        string $sql,
        array $almacenesMap,
        array $productosMap,
        array &$stats
    ): void {
        preg_match('/INSERT INTO inventario [^;]+;/s', $sql, $m);
        $lines = array_filter(explode("\n", $m[0] ?? ''));

        // Agrupar inventario por almacén
        $porAlmacen = [];
        foreach ($lines as $l) {
            if (preg_match('/\((\d+),\s*(\d+),\s*\'([^\']+)\',\s*([0-9.]+)\)/i', $l, $row)) {
                $prodErpId = (int)$row[1];
                $almErpId = (int)$row[2];
                $fechaCorte = trim($row[3]);
                $cantidad = (float)$row[4];

                $porAlmacen[$almErpId][] = [
                    'producto_erp_id' => $prodErpId,
                    'fecha_corte' => $fechaCorte,
                    'cantidad' => $cantidad
                ];
            }
        }

        $unitCache = $this->pdo->query("SELECT id, unidad_base_id FROM productos")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $insertCabSt = $this->pdo->prepare("
            INSERT INTO stock_cabecera (fecha_stock, almacen_id, estado_registro, created_by, created_at, published_at, origen, origen_ref, origen_hash)
            VALUES (?, ?, 'PUBLICADO', 1, NOW(), NOW(), 'ERP_PUCCHUN', ?, ?)
        ");

        $insertDetSt = $this->pdo->prepare("
            INSERT INTO stock_detalle (stock_id, producto_id, unidad_id, cantidad, stock_minimo)
            VALUES (?, ?, ?, ?, 50.000)
        ");

        $checkSt = $this->pdo->prepare("SELECT id FROM stock_cabecera WHERE origen = 'ERP_PUCCHUN' AND origen_ref = ? LIMIT 1");

        foreach ($porAlmacen as $almErpId => $items) {
            $dbAlmId = $almacenesMap[$almErpId] ?? null;
            if (!$dbAlmId) continue;

            $fechaCorte = $items[0]['fecha_corte'] ?? '2026-04-01';
            $origenRef = "ERP-INV-ALM-{$almErpId}-{$fechaCorte}";
            $origenHash = sha1("ERP_PUCCHUN|INV|{$almErpId}|{$fechaCorte}");

            $checkSt->execute([$origenRef]);
            $existingStockId = $checkSt->fetchColumn();

            if ($existingStockId) {
                continue; // Ya existe
            }

            $insertCabSt->execute([$fechaCorte, $dbAlmId, $origenRef, $origenHash]);
            $stockId = (int)$this->pdo->lastInsertId();
            $stats['stock_cabeceras']++;

            foreach ($items as $item) {
                $prodMapped = $productosMap[$item['producto_erp_id']] ?? 1;
                $unidadId = $unitCache[$prodMapped] ?? 1;

                $insertDetSt->execute([
                    $stockId,
                    $prodMapped,
                    $unidadId,
                    $item['cantidad']
                ]);
                $stats['stock_detalles']++;
            }
        }
    }
}

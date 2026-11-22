<?php
namespace App\Services;

class DashboardService
{
    /**
     * Calcula los KPIs consolidados del sistema.
     *
     * @param bool $publishedOnly Si es true, filtra únicamente registros publicados (para Portal Bayer).
     * @return array<string, mixed>
     */
    /** Condición SQL (alias sc = stock_cabecera): la foto de stock más reciente de cada almacén, opcionalmente solo publicada. */
    public static function fotoVigente(bool $publishedOnly = false, ?int $sede = null): string
    {
        $pub = $publishedOnly ? " AND x.estado_registro = 'PUBLICADO'" : '';
        return "sc.id = (SELECT x.id FROM stock_cabecera x WHERE x.almacen_id = sc.almacen_id$pub ORDER BY x.fecha_stock DESC, x.id DESC LIMIT 1)"
            . self::condAlmacen($sede, 'sc.almacen_id');
    }

    /**
     * Condición SQL para limitar a la sede activa en tablas con sucursal_id (documentos, cobranzas).
     * Si $sede es null («Todas las sedes») no agrega nada. El id se castea a entero.
     */
    private static function condSucursal(?int $sede, string $alias = ''): string
    {
        if ($sede === null) return '';
        return ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'sucursal_id = ' . (int)$sede;
    }

    /** Igual que condSucursal pero para tablas con almacen_id (stock): el almacén debe pertenecer a la sede. */
    private static function condAlmacen(?int $sede, string $columna = 'almacen_id'): string
    {
        if ($sede === null) return '';
        return " AND $columna IN (SELECT id FROM almacenes WHERE sucursal_id = " . (int)$sede . ')';
    }

    public function kpis(bool $publishedOnly = false, ?int $sede = null): array
    {
        $pdo = \db();
        $out = [];

        // Filtros para documentos y guías
        // Sede activa: filtra documentos y stock; guías, clientes y productos se mantienen globales
        $sd = self::condSucursal($sede);
        $pubDoc = $publishedOnly ? " AND estado_registro='PUBLICADO'" : '';
        $docFilter = "WHERE 1=1$pubDoc$sd";
        $guiaFilter = $publishedOnly ? "WHERE estado_registro='PUBLICADO'" : "";
        $docApprovedFilter = "WHERE estado_comercial='APROBADA'$pubDoc$sd";

        // 1. Contadores base de entidades
        $out['documentos'] = (int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera $docFilter")->fetchColumn();
        $out['guias'] = (int)$pdo->query("SELECT COUNT(*) FROM guias_cabecera $guiaFilter")->fetchColumn();
        $out['clientes'] = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
        $out['productos'] = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();

        // 2. Corrección conceptual de Stock (volumen físico, SKUs, almacenes físicos y alertas)
        // Cada día se guarda una foto del stock por almacén: se cuenta solo la más reciente de cada almacén (antes se sumaban todas)
        $stockDetJoin = "JOIN stock_cabecera sc ON sc.id = sd.stock_id WHERE " . self::fotoVigente($publishedOnly, $sede);
        $stockCabFilter = "WHERE 1=1" . ($publishedOnly ? " AND estado_registro='PUBLICADO'" : '') . self::condAlmacen($sede);

        $out['stock_unidades'] = (float)$pdo->query("SELECT COALESCE(SUM(sd.cantidad), 0) FROM stock_detalle sd $stockDetJoin")->fetchColumn();
        $out['stock_skus'] = (int)$pdo->query("SELECT COUNT(DISTINCT sd.producto_id) FROM stock_detalle sd $stockDetJoin AND sd.cantidad > 0")->fetchColumn();
        $out['almacenes_activos'] = (int)$pdo->query("SELECT COUNT(DISTINCT almacen_id) FROM stock_cabecera $stockCabFilter")->fetchColumn();
        $out['alertas_stock_minimo'] = (int)$pdo->query("SELECT COUNT(*) FROM stock_detalle sd $stockDetJoin AND sd.cantidad <= sd.stock_minimo")->fetchColumn();

        // Compatibilidad: 'stock' expone las unidades físicas reales para evitar el valor '3'
        $out['stock'] = $out['stock_unidades'];
        $out['stock_cabeceras'] = (int)$pdo->query("SELECT COUNT(*) FROM stock_cabecera $stockCabFilter")->fetchColumn();

        // 3. Métricas comerciales y monetarias
        $out['facturacion_total_pen'] = (float)$pdo->query("SELECT COALESCE(SUM(monto_total), 0) FROM documentos_cabecera $docApprovedFilter")->fetchColumn();
        // Ticket promedio = facturación neta (incluye notas) / número de facturas y boletas
        $ventasDocs = (int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera dc JOIN tipos_documento td ON td.id = dc.tipo_documento_id WHERE dc.estado_comercial='APROBADA'"
            . ($publishedOnly ? " AND dc.estado_registro='PUBLICADO'" : '')
            . self::condSucursal($sede, 'dc')
            . " AND td.codigo IN ('01','03')")->fetchColumn();
        $out['ticket_promedio'] = $ventasDocs ? round($out['facturacion_total_pen'] / $ventasDocs, 2) : 0.0;

        // 4. Desglose de estados de publicación y flujo
        $out['documentos_publicados'] = (int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='PUBLICADO'$sd")->fetchColumn();
        $out['documentos_validados'] = (int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='VALIDADO'$sd")->fetchColumn();
        $out['documentos_borrador'] = (int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='BORRADOR'$sd")->fetchColumn();
        $out['documentos_pendientes'] = $out['documentos_validados'] + $out['documentos_borrador'];
        $out['documentos_anulados'] = (int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera WHERE estado_comercial='ANULADA'$sd")->fetchColumn();

        $out['guias_publicadas'] = (int)$pdo->query("SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='PUBLICADO'")->fetchColumn();
        $out['guias_validadas'] = (int)$pdo->query("SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='VALIDADO'")->fetchColumn();
        $out['guias_borrador'] = (int)$pdo->query("SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='BORRADOR'")->fetchColumn();

        if ($publishedOnly) {
            // Portal Bayer: no se revela el trabajo interno pendiente de Pucchún
            foreach (['documentos_validados', 'documentos_borrador', 'documentos_pendientes', 'guias_validadas', 'guias_borrador'] as $k) $out[$k] = 0;
        }

        $out['publicados'] = $out['documentos_publicados'] + $out['guias_publicadas'] + (int)$pdo->query("SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro='PUBLICADO'" . self::condAlmacen($sede))->fetchColumn();

        return $out;
    }

    /**
     * Retorna la serie de ventas agrupada por mes ('YYYY-MM'), con unidades físicas y monto en Soles (PEN).
     *
     * @param bool $publishedOnly Si es true, solo considera ventas publicadas.
     * @param int $limit Número máximo de meses.
     * @return array<int, array{periodo: string, cantidad: float, monto_pen: float}>
     */
    public function salesByMonth(bool $publishedOnly = false, int $limit = 12, ?int $sede = null): array
    {
        $statusCondition = ($publishedOnly ? "AND dc.estado_registro='PUBLICADO'" : "") . self::condSucursal($sede, 'dc');
        $sql = "SELECT 
                    DATE_FORMAT(dc.fecha, '%Y-%m') AS periodo,
                    COUNT(DISTINCT dc.id) AS documentos,
                    ROUND(SUM(dd.cantidad), 2) AS cantidad,
                    ROUND(SUM(COALESCE(dd.importe, dd.cantidad * dd.valor_unitario)), 2) AS monto_pen
                FROM documentos_cabecera dc
                JOIN documentos_detalle dd ON dd.documento_id = dc.id
                WHERE dc.estado_comercial = 'APROBADA' $statusCondition
                GROUP BY periodo
                ORDER BY periodo DESC
                LIMIT :lim";

        $st = \db()->prepare($sql);
        $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll();
        return array_map(fn($r) => [
            'periodo' => (string)$r['periodo'],
            'documentos' => (int)$r['documentos'],
            'cantidad' => (float)$r['cantidad'],
            'monto_pen' => (float)$r['monto_pen'],
        ], $rows);
    }

    /**
     * Retorna los productos con mayor volumen de venta, incluyendo indicador de fabricante y facturación, con soporte para desglose mensual y filtro por periodo.
     *
     * @param int $limit Cantidad de productos a retornar.
     * @param bool $publishedOnly Si es true, solo considera ventas publicadas.
     * @param string|null $periodo Periodo en formato 'YYYY-MM' o null para todos.
     * @param bool $byMonth Si es true, desglosa por periodo mensual.
     * @return array<int, array{id: int, codigo: string, nombre: string, es_bayer: int, cantidad: float, monto_pen: float, periodo?: string}>
     */
    public function topProducts(int $limit = 50, bool $publishedOnly = false, ?string $periodo = null, bool $byMonth = true, ?int $sede = null): array
    {
        $where = "WHERE dc.estado_comercial = 'APROBADA'" . self::condSucursal($sede, 'dc');
        $params = [];
        if ($publishedOnly) {
            $where .= " AND dc.estado_registro='PUBLICADO'";
        }
        if ($periodo !== null && $periodo !== '' && $periodo !== 'all') {
            $where .= " AND DATE_FORMAT(dc.fecha, '%Y-%m') = :periodo";
            $params[':periodo'] = $periodo;
        }

        $periodField = $byMonth ? "DATE_FORMAT(dc.fecha, '%Y-%m') AS periodo," : "";
        $groupBy = $byMonth ? "periodo, p.id, p.codigo, p.nombre, p.es_bayer" : "p.id, p.codigo, p.nombre, p.es_bayer";
        $orderBy = $byMonth ? "periodo DESC, cantidad DESC" : "cantidad DESC";
        $limitClause = (!$byMonth || $periodo !== null) ? "LIMIT :lim" : "";

        $sql = "SELECT 
                    $periodField
                    p.id,
                    p.codigo,
                    p.nombre,
                    p.es_bayer,
                    ROUND(SUM(dd.cantidad), 2) AS cantidad,
                    ROUND(SUM(COALESCE(dd.importe, dd.cantidad * dd.valor_unitario)), 2) AS monto_pen
                FROM documentos_cabecera dc
                JOIN documentos_detalle dd ON dd.documento_id = dc.id
                JOIN productos p ON p.id = dd.producto_id
                $where
                GROUP BY $groupBy
                ORDER BY $orderBy
                $limitClause";

        $st = \db()->prepare($sql);
        if ($limitClause !== '') {
            $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        }
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();

        $rows = $st->fetchAll();
        return array_map(function($r) use ($byMonth) {
            $item = [
                'id' => (int)$r['id'],
                'codigo' => (string)$r['codigo'],
                'nombre' => (string)$r['nombre'],
                'es_bayer' => (int)$r['es_bayer'],
                'cantidad' => (float)$r['cantidad'],
                'monto_pen' => (float)$r['monto_pen'],
            ];
            if ($byMonth && isset($r['periodo'])) {
                $item['periodo'] = (string)$r['periodo'];
            }
            return $item;
        }, $rows);
    }

    /**
     * Retorna los comprobantes emitidos más recientes para alimentar componentes de actividad del Dashboard.
     *
     * @param int $limit Cantidad de comprobantes.
     * @return array<int, array{id: int, numero: string, fecha: string, cliente: string, cliente_doc: string, monto_total: float, estado_comercial: string, estado_registro: string, items: int}>
     */
    public function recentDocuments(int $limit = 8, bool $publishedOnly = false, ?int $sede = null): array
    {
        $whereDocs = 'WHERE 1=1' . ($publishedOnly ? " AND dc.estado_registro = 'PUBLICADO'" : '') . self::condSucursal($sede, 'dc');
        $sql = "SELECT 
                    dc.id,
                    dc.numero,
                    DATE_FORMAT(dc.fecha, '%Y-%m-%d') AS fecha,
                    c.razon_social AS cliente,
                    c.nro_doc AS cliente_doc,
                    dc.monto_total,
                    dc.estado_comercial,
                    dc.estado_registro,
                    COUNT(dd.id) AS items
                FROM documentos_cabecera dc
                JOIN clientes c ON c.id = dc.cliente_id
                LEFT JOIN documentos_detalle dd ON dd.documento_id = dc.id
                $whereDocs
                GROUP BY dc.id, dc.numero, dc.fecha, c.razon_social, c.nro_doc, dc.monto_total, dc.estado_comercial, dc.estado_registro
                ORDER BY dc.fecha DESC, dc.id DESC
                LIMIT :lim";

        $st = \db()->prepare($sql);
        $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll();
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'numero' => (string)$r['numero'],
            'fecha' => (string)$r['fecha'],
            'cliente' => (string)$r['cliente'],
            'cliente_doc' => (string)$r['cliente_doc'],
            'monto_total' => (float)$r['monto_total'],
            'estado_comercial' => (string)$r['estado_comercial'],
            'estado_registro' => (string)$r['estado_registro'],
            'items' => (int)$r['items'],
        ], $rows);
    }

    /**
     * Retorna el inventario consolidado con semáforo de reposición (CRITICO, ALERTA, OPTIMO).
     *
     * @param int $limit
     * @return array<int, array>
     */
    public function inventoryAlerts(int $limit = 25, bool $publishedOnly = false, ?int $sede = null): array
    {
        $sql = "SELECT 
                    p.id AS producto_id,
                    p.codigo,
                    p.nombre AS producto_nombre,
                    p.es_bayer,
                    a.id AS almacen_id,
                    a.nombre AS almacen_nombre,
                    COALESCE(s.nombre, 'Sin Sede') AS sede_nombre,
                    ROUND(sd.cantidad, 2) AS stock_actual,
                    ROUND(sd.stock_minimo, 2) AS stock_minimo,
                    ROUND(sd.cantidad - sd.stock_minimo, 2) AS diferencia,
                    CASE 
                        WHEN sd.cantidad <= (sd.stock_minimo * 0.5) THEN 'CRITICO' 
                        WHEN sd.cantidad <= sd.stock_minimo THEN 'ALERTA' 
                        ELSE 'OPTIMO' 
                    END AS semaforo
                FROM stock_detalle sd
                JOIN stock_cabecera sc ON sc.id = sd.stock_id
                JOIN almacenes a ON a.id = sc.almacen_id
                LEFT JOIN sucursales s ON s.id = a.sucursal_id
                JOIN productos p ON p.id = sd.producto_id
                WHERE " . self::fotoVigente($publishedOnly, $sede) . "
                ORDER BY diferencia ASC, sd.cantidad ASC
                LIMIT :lim";

        $st = \db()->prepare($sql);
        $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $st->execute();

        return array_map(fn($r) => [
            'producto_id' => (int)$r['producto_id'],
            'codigo' => (string)$r['codigo'],
            'producto_nombre' => (string)$r['producto_nombre'],
            'es_bayer' => (int)$r['es_bayer'],
            'almacen_id' => (int)$r['almacen_id'],
            'almacen_nombre' => (string)$r['almacen_nombre'],
            'sede_nombre' => (string)$r['sede_nombre'],
            'stock_actual' => (float)$r['stock_actual'],
            'stock_minimo' => (float)$r['stock_minimo'],
            'diferencia' => (float)$r['diferencia'],
            'semaforo' => (string)$r['semaforo'],
        ], $st->fetchAll());
    }

    /**
     * Retorna el desempeño comercial y de despachos desglosado por sede/sucursal, opcionalmente desglosado por mes o filtrado por periodo.
     *
     * @param string|null $periodo Periodo en formato 'YYYY-MM' o null para todos.
     * @param bool $byMonth Si es true, desglosa por periodo mensual (añade campo 'periodo').
     * @return array<int, array>
     */
    public function salesByBranch(?string $periodo = null, bool $byMonth = true, bool $publishedOnly = false, ?int $sede = null): array
    {
        $where = "WHERE dc.estado_comercial = 'APROBADA'" . ($publishedOnly ? " AND dc.estado_registro = 'PUBLICADO'" : '') . self::condSucursal($sede, 'dc');
        $params = [];
        if ($periodo !== null && $periodo !== '' && $periodo !== 'all') {
            $where .= " AND DATE_FORMAT(dc.fecha, '%Y-%m') = :periodo";
            $params[':periodo'] = $periodo;
        }

        $periodField = $byMonth ? "DATE_FORMAT(dc.fecha, '%Y-%m') AS periodo," : "";
        $groupBy = $byMonth ? "periodo, s.id, s.nombre, s.ciudad" : "s.id, s.nombre, s.ciudad";
        $orderBy = $byMonth ? "periodo DESC, total_facturado_pen DESC" : "total_facturado_pen DESC";

        $sql = "SELECT 
                    $periodField
                    s.id AS sucursal_id,
                    s.nombre AS sede_nombre,
                    s.ciudad,
                    COUNT(DISTINCT dc.id) AS total_documentos,
                    ROUND(COALESCE(SUM(COALESCE(dd.importe, dd.cantidad * dd.valor_unitario)), 0), 2) AS total_facturado_pen,
                    ROUND(COALESCE(SUM(dd.cantidad), 0), 2) AS total_unidades_vendidas
                FROM sucursales s
                JOIN documentos_cabecera dc ON dc.sucursal_id = s.id
                JOIN documentos_detalle dd ON dd.documento_id = dc.id
                $where
                GROUP BY $groupBy
                ORDER BY $orderBy";

        $st = \db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        return array_map(function($r) use ($byMonth) {
            $item = [
                'sucursal_id' => (int)$r['sucursal_id'],
                'sede_nombre' => (string)$r['sede_nombre'],
                'ciudad' => (string)$r['ciudad'],
                'total_documentos' => (int)$r['total_documentos'],
                'total_facturado_pen' => (float)$r['total_facturado_pen'],
                'total_unidades_vendidas' => (float)$r['total_unidades_vendidas'],
            ];
            if ($byMonth && isset($r['periodo'])) {
                $item['periodo'] = (string)$r['periodo'];
            }
            return $item;
        }, $rows);
    }

    /**
     * Retorna la comparativa de catálogo entre portafolio oficial Bayer y terceros/genéricos, opcionalmente desglosada por mes o filtrada por periodo.
     *
     * @param string|null $periodo Periodo en formato 'YYYY-MM' o null para todos.
     * @param bool $byMonth Si es true, desglosa por periodo mensual (añade campo 'periodo').
     * @return array<int, array>
     */
    public function catalogSplit(?string $periodo = null, bool $byMonth = true, bool $publishedOnly = false, ?int $sede = null): array
    {
        $where = "WHERE dc.estado_comercial = 'APROBADA'" . ($publishedOnly ? " AND dc.estado_registro = 'PUBLICADO'" : '') . self::condSucursal($sede, 'dc');
        $params = [];
        if ($periodo !== null && $periodo !== '' && $periodo !== 'all') {
            $where .= " AND DATE_FORMAT(dc.fecha, '%Y-%m') = :periodo";
            $params[':periodo'] = $periodo;
        }

        $periodField = $byMonth ? "DATE_FORMAT(dc.fecha, '%Y-%m') AS periodo," : "";
        $groupBy = $byMonth ? "periodo, p.es_bayer" : "p.es_bayer";
        $orderBy = $byMonth ? "periodo DESC, p.es_bayer DESC" : "p.es_bayer DESC";

        $sql = "SELECT 
                    $periodField
                    p.es_bayer,
                    CASE WHEN p.es_bayer = 1 THEN 'Bayer Oficial' ELSE 'Genéricos / Terceros' END AS fabricante,
                    COUNT(DISTINCT p.id) AS total_productos,
                    ROUND(COALESCE(SUM(dd.cantidad), 0), 2) AS unidades_vendidas,
                    ROUND(COALESCE(SUM(COALESCE(dd.importe, dd.cantidad * dd.valor_unitario)), 2), 2) AS monto_total_pen
                FROM productos p
                JOIN documentos_detalle dd ON dd.producto_id = p.id
                JOIN documentos_cabecera dc ON dc.id = dd.documento_id
                $where
                GROUP BY $groupBy
                ORDER BY $orderBy";

        $st = \db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        return array_map(function($r) use ($byMonth) {
            $item = [
                'es_bayer' => (int)$r['es_bayer'],
                'fabricante' => (string)$r['fabricante'],
                'total_productos' => (int)$r['total_productos'],
                'unidades_vendidas' => (float)$r['unidades_vendidas'],
                'monto_total_pen' => (float)$r['monto_total_pen'],
            ];
            if ($byMonth && isset($r['periodo'])) {
                $item['periodo'] = (string)$r['periodo'];
            }
            return $item;
        }, $rows);
    }

    /**
     * Operación interna del ERP (Inf. 19 · analítica corporativa): ventas, cobranzas, cuentas por cobrar, caja,
     * bancos, compras y cuentas por pagar, en total y por sede. Lectura directa de los módulos G7/G8 (solo lectura).
     * Es información interna: el controlador no la entrega al rol BAYER.
     */
    public function operacionErp(?int $sede = null): array
    {
        $pdo = \db();
        $q = fn(string $sql) => $pdo->query($sql)->fetchAll();
        $sedes = $q("SELECT s.id, s.nombre,
                (SELECT COALESCE(SUM(v.total),0) FROM ventas v WHERE v.sucursal_id=s.id AND v.estado='confirmada') ventas,
                (SELECT COUNT(*) FROM ventas v WHERE v.sucursal_id=s.id AND v.estado='confirmada') n_ventas,
                (SELECT COALESCE(SUM(c.importe),0) FROM cobranzas c WHERE c.sucursal_id=s.id AND c.estado='confirmada') cobrado,
                (SELECT COALESCE(SUM(cx.importe_original),0) FROM cuentas_por_cobrar cx JOIN ventas v ON v.id=cx.venta_id WHERE v.sucursal_id=s.id AND cx.estado='activa')
                  - (SELECT COALESCE(SUM(a.importe),0) FROM cobranza_aplicaciones a JOIN cobranzas c ON c.id=a.cobranza_id JOIN cuenta_cuotas cc ON cc.id=a.cuota_id
                       JOIN cuentas_por_cobrar cx ON cx.id=cc.cuenta_id JOIN ventas v ON v.id=cx.venta_id WHERE v.sucursal_id=s.id AND cx.estado='activa' AND c.estado='confirmada')
                  - (SELECT COALESCE(SUM(CASE nota.tipo WHEN 'nota_credito' THEN nota.total WHEN 'nota_debito' THEN -nota.total ELSE 0 END),0)
                       FROM facturacion_comprobantes nota JOIN facturacion_comprobantes origen ON origen.id=nota.comprobante_referenciado_id
                       JOIN ventas v ON v.id=origen.venta_erp_id JOIN cuentas_por_cobrar cx ON cx.venta_id=v.id AND cx.estado='activa'
                      WHERE v.sucursal_id=s.id AND nota.estado IN ('aceptado','observado')) cxc_saldo,
                (SELECT COALESCE(SUM(cs.fondo_inicial + COALESCE((SELECT SUM(CASE m.tipo WHEN 'ingreso' THEN m.importe ELSE -m.importe END) FROM caja_movimientos m WHERE m.sesion_id=cs.id),0)),0)
                   FROM caja_sesiones cs JOIN cajas ca ON ca.id=cs.caja_id WHERE ca.sucursal_id=s.id AND cs.estado='abierta') caja,
                (SELECT COALESCE(SUM(CASE m.tipo WHEN 'ingreso' THEN m.importe ELSE -m.importe END),0) FROM tesoreria_movimientos m JOIN tesoreria_cuentas t ON t.id=m.cuenta_id WHERE t.sucursal_id=s.id AND m.estado='confirmado') bancos,
                (SELECT COALESCE(SUM(r.cantidad*r.costo_unitario),0) FROM g7_recepciones r JOIN almacenes a ON a.id=r.almacen_id WHERE a.sucursal_id=s.id) compras,
                (SELECT COALESCE(SUM(cp.importe-cp.pagado),0) FROM g7_cuentas_pagar cp JOIN g7_recepciones r ON r.id=cp.recepcion_id JOIN almacenes a ON a.id=r.almacen_id WHERE a.sucursal_id=s.id) cxp_saldo
            FROM sucursales s WHERE s.estado=1" . ($sede !== null ? ' AND s.id = ' . (int)$sede : '') . " ORDER BY s.nombre");
        $medios = $q("SELECT medio_pago, COUNT(*) n, SUM(importe) total FROM cobranzas WHERE estado='confirmada'" . self::condSucursal($sede) . " GROUP BY medio_pago ORDER BY total DESC");
        $total = [];
        foreach (['ventas','n_ventas','cobrado','cxc_saldo','caja','bancos','compras','cxp_saldo'] as $k) $total[$k] = array_sum(array_map(fn($r) => (float)$r[$k], $sedes));
        return ['sedes' => $sedes, 'medios' => $medios, 'total' => $total];
    }
}
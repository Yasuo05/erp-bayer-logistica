<?php
namespace App\Services;

/**
 * Reglas de negocio Bayer aplicadas antes de publicar.
 * Bloqueantes (impiden publicar) vs. advertencias (se publican, pero se informan).
 */
class BayerRulesService
{
    public const MODULES = [
        'documentos' => ['table' => 'documentos_cabecera', 'date' => 'fecha', 'dataset' => 'documents', 'label' => 'Documentos'],
        'guias' => ['table' => 'guias_cabecera', 'date' => 'fecha', 'dataset' => 'guides', 'label' => 'Guías de remisión'],
        'stock' => ['table' => 'stock_cabecera', 'date' => 'fecha_stock', 'dataset' => 'stock', 'label' => 'Stock'],
    ];

    public const RULES = [
        'dealer_ruc' => 'RUC del distribuidor inválido (11 dígitos, prefijo 10/15/17/20)',
        'anulado' => 'Comprobante anulado: no se reporta a Bayer',
        'sin_bayer' => 'No contiene productos Bayer',
        'sin_homologar' => 'Producto Bayer sin código de material homologado',
        'cantidad' => 'Cantidad inválida (debe ser > 0)',
        'cliente_doc' => 'Documento del cliente inválido (DNI 8 / RUC 11 dígitos)',
        'geo' => 'Ubicación incompleta (departamento/provincia/distrito)',
        'fecha_futura' => 'Fecha posterior a hoy',
        'almacen' => 'Almacén sin homologación Bayer',
        'sin_lineas' => 'Registro sin líneas de detalle',
    ];
    public const WARNINGS = [
        'vendedor_dni' => 'Vendedor sin DNI (8 dígitos) homologado',
    ];

    public static function module(string $m): array
    {
        if (!isset(self::MODULES[$m])) throw new \InvalidArgumentException("Módulo inválido: $m");
        return self::MODULES[$m];
    }

    public function partnerId(): int
    {
        static $id = null;
        return $id ??= (int)\db()->query("SELECT id FROM partners WHERE codigo='BAYER'")->fetchColumn();
    }

    /** IDs de un módulo en un estado y rango de fechas. */
    public function candidates(string $module, string $status, ?string $from = null, ?string $to = null): array
    {
        $m = self::module($module);
        $sql = "SELECT id FROM {$m['table']} WHERE estado_registro=?";
        $p = [$status];
        if ($from) { $sql .= " AND {$m['date']}>=?"; $p[] = $from; }
        if ($to) { $sql .= " AND {$m['date']}<=?"; $p[] = $to; }
        $st = \db()->prepare($sql.' ORDER BY id');
        $st->execute($p);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Evalúa reglas para los IDs dados.
     * @return array<int, array{ref:string, ok:bool, errors:string[], warnings:string[]}>
     */
    public function evaluate(string $module, array $ids): array
    {
        self::module($module);
        $out = [];
        $anulados = array_map('strtoupper', \config('import.estados_anulados', ['ANULADA']));
        $today = date('Y-m-d');
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 1000) as $chunk) {
            if (!$chunk) continue;
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = \db()->prepare($this->sql($module, $in));
            $st->execute(array_merge([$this->partnerId(), $this->partnerId()], $chunk));
            foreach ($st as $r) {
                $err = [];
                $warn = [];
                if (!preg_match('/^(10|15|17|20)\d{9}$/', (string)$r['dealer_ruc'])) $err[] = 'dealer_ruc';
                if ((int)$r['lines'] === 0) $err[] = 'sin_lineas';
                elseif ((int)$r['bayer_lines'] === 0) $err[] = 'sin_bayer';
                if ((int)$r['sin_hom'] > 0) $err[] = 'sin_homologar';
                if ((int)$r['qty_bad'] > 0) $err[] = 'cantidad';
                if ($r['fecha'] > $today) $err[] = 'fecha_futura';
                if ($module !== 'stock') {
                    if (in_array(strtoupper((string)$r['estado_comercial']), $anulados, true)) $err[] = 'anulado';
                    if (!preg_match('/^(\d{8}|\d{11})$/', (string)$r['nro_doc'])) $err[] = 'cliente_doc';
                    if (!$r['dep'] || !$r['prov'] || !$r['dist']) $err[] = 'geo';
                    if (!preg_match('/^\d{8}$/', (string)$r['sales_id'])) $warn[] = 'vendedor_dni';
                } elseif (trim((string)$r['warehouse_id']) === '') {
                    $err[] = 'almacen';
                }
                $out[(int)$r['id']] = ['ref' => (string)$r['ref'], 'ok' => !$err, 'errors' => $err, 'warnings' => $warn];
            }
        }
        return $out;
    }

    /** Resumen agregado de validación (para el tablero de publicación). */
    public function summary(string $module, string $status, ?string $from, ?string $to): array
    {
        $ids = $this->candidates($module, $status, $from, $to);
        $ev = $this->evaluate($module, $ids);
        $s = ['total' => count($ev), 'ok' => 0, 'errors' => [], 'warnings' => [], 'examples' => [], 'ok_ids' => []];
        foreach ($ev as $id => $r) {
            if ($r['ok']) { $s['ok']++; $s['ok_ids'][] = $id; }
            foreach ($r['errors'] as $e) {
                $s['errors'][$e] = ($s['errors'][$e] ?? 0) + 1;
                if (count($s['examples'][$e] ?? []) < 5) $s['examples'][$e][] = $r['ref'];
            }
            foreach ($r['warnings'] as $w) $s['warnings'][$w] = ($s['warnings'][$w] ?? 0) + 1;
        }
        arsort($s['errors']);
        return $s;
    }

    private function sql(string $module, string $in): string
    {
        return match ($module) {
            'documentos' => "SELECT dc.id, dc.numero ref, dc.fecha, dc.estado_comercial, e.ruc dealer_ruc, c.nro_doc,
                    c.departamento_id dep, c.provincia_id prov, c.distrito_id dist, hv.sales_id,
                    COUNT(dd.id) lines, COALESCE(SUM(pr.es_bayer=1),0) bayer_lines,
                    COALESCE(SUM(pr.es_bayer=1 AND COALESCE(h.material_id,'')=''),0) sin_hom, COALESCE(SUM(dd.cantidad<=0),0) qty_bad
                FROM documentos_cabecera dc
                JOIN sucursales s ON s.id=dc.sucursal_id JOIN empresas e ON e.id=s.empresa_id
                JOIN clientes c ON c.id=dc.cliente_id
                LEFT JOIN homologacion_vendedores hv ON hv.vendedor_id=dc.vendedor_id AND hv.partner_id=?
                LEFT JOIN documentos_detalle dd ON dd.documento_id=dc.id
                LEFT JOIN productos pr ON pr.id=dd.producto_id
                LEFT JOIN homologacion_productos_bayer h ON h.producto_id=pr.id AND h.partner_id=?
                WHERE dc.id IN ($in) GROUP BY dc.id",
            'guias' => "SELECT gc.id, gc.numero ref, gc.fecha, COALESCE(dc.estado_comercial,'APROBADA') estado_comercial, e.ruc dealer_ruc, c.nro_doc,
                    gc.departamento_id dep, gc.provincia_id prov, gc.distrito_id dist, hv.sales_id,
                    COUNT(gd.id) lines, COALESCE(SUM(pr.es_bayer=1),0) bayer_lines,
                    COALESCE(SUM(pr.es_bayer=1 AND COALESCE(h.material_id,'')=''),0) sin_hom, COALESCE(SUM(gd.cantidad<=0),0) qty_bad
                FROM guias_cabecera gc
                JOIN sucursales s ON s.id=gc.sucursal_id JOIN empresas e ON e.id=s.empresa_id
                JOIN clientes c ON c.id=gc.cliente_id
                LEFT JOIN documentos_cabecera dc ON dc.id=gc.documento_id
                LEFT JOIN homologacion_vendedores hv ON hv.vendedor_id=gc.vendedor_id AND hv.partner_id=?
                LEFT JOIN guias_detalle gd ON gd.guia_id=gc.id
                LEFT JOIN productos pr ON pr.id=gd.producto_id
                LEFT JOIN homologacion_productos_bayer h ON h.producto_id=pr.id AND h.partner_id=?
                WHERE gc.id IN ($in) GROUP BY gc.id",
            'stock' => "SELECT sc.id, CONCAT(a.codigo,' · ',sc.fecha_stock) ref, sc.fecha_stock fecha, e.ruc dealer_ruc, ha.warehouse_id,
                    COUNT(sd.id) lines, COALESCE(SUM(pr.es_bayer=1),0) bayer_lines,
                    COALESCE(SUM(pr.es_bayer=1 AND COALESCE(h.material_id,'')=''),0) sin_hom, COALESCE(SUM(sd.cantidad<0),0) qty_bad
                FROM stock_cabecera sc
                JOIN almacenes a ON a.id=sc.almacen_id JOIN sucursales s ON s.id=a.sucursal_id JOIN empresas e ON e.id=s.empresa_id
                LEFT JOIN homologacion_almacenes ha ON ha.almacen_id=a.id AND ha.partner_id=?
                LEFT JOIN stock_detalle sd ON sd.stock_id=sc.id
                LEFT JOIN productos pr ON pr.id=sd.producto_id
                LEFT JOIN homologacion_productos_bayer h ON h.producto_id=pr.id AND h.partner_id=?
                WHERE sc.id IN ($in) GROUP BY sc.id",
        };
    }
}

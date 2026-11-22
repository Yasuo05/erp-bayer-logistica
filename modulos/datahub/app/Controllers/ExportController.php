<?php
namespace App\Controllers; use App\Services\{BayerDataService,SimplePdfExporter};
class ExportController {
    /** Encabezados en español y tipo de cada columna para el Excel. La API para Bayer conserva los nombres técnicos en inglés. */
    private const COLUMNAS = [
        'dealerId' => ['RUC del distribuidor', 'texto'], 'dealerName' => ['Distribuidor', 'texto'],
        'documentTypeId' => ['Cód. tipo de documento', 'texto'], 'documentType' => ['Tipo de documento', 'texto'],
        'documentNumber' => ['N° de documento', 'texto'], 'documentDate' => ['Fecha', 'fecha'],
        'salesId' => ['Cód. vendedor', 'texto'], 'salesName' => ['Vendedor', 'texto'],
        'branchId' => ['Cód. sede', 'texto'], 'branchName' => ['Sede', 'texto'],
        'customerId' => ['Doc. del cliente', 'texto'], 'customerName' => ['Cliente', 'texto'],
        'materialId' => ['Cód. producto', 'texto'], 'materialName' => ['Producto', 'texto'],
        'measureUnit' => ['Unidad', 'texto'], 'quantity' => ['Cantidad', 'decimal'],
        'province' => ['Provincia', 'texto'], 'department' => ['Departamento', 'texto'], 'district' => ['Distrito', 'texto'],
        'stockDate' => ['Fecha del stock', 'fecha'], 'warehouseId' => ['Cód. almacén', 'texto'], 'warehouseName' => ['Almacén', 'texto'],
        'batch' => ['Lote', 'texto'], 'expirationDate' => ['Vencimiento', 'fecha'],
    ];
    private const TITULOS = ['documents' => 'Ventas publicadas', 'sales' => 'Ventas publicadas', 'guides' => 'Guías publicadas', 'shipments' => 'Guías publicadas', 'stock' => 'Stock publicado', 'inventory' => 'Stock publicado'];

    public function export():void{\require_role('ADMIN','GERENCIA','BAYER');$type=(string)\input('type','documents');$format=strtolower((string)\input('format','csv'));$rows=(new BayerDataService())->dataset($type);\audit('exportaciones',$format,null);$base='bayer_'.$type.'_'.date('Ymd_His');if($format==='json'){header('Content-Type: application/json; charset=utf-8');header("Content-Disposition: attachment; filename=$base.json");echo json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);exit;}if($format==='txt'){header('Content-Type: text/plain; charset=utf-8');header("Content-Disposition: attachment; filename=$base.txt");if($rows){echo implode("\t",array_keys($rows[0]))."\n";foreach($rows as $r)echo implode("\t",array_values($r))."\n";}exit;}if($format==='xlsx'){$this->xlsx($type,$rows,$base.'.xlsx');}if($format==='pdf'){(new SimplePdfExporter())->output($rows,'Bayer - '.ucfirst($type),$base.'.pdf');}$this->csv($rows,$base.'.csv');}

    /** Excel con el formato común del ERP (core/excel.php): encabezados en español, filtro, fila fija, fechas y cantidades como tales. */
    private function xlsx(string $type, array $rows, string $archivo): never {
        require_once dirname(\base_path(), 2) . '/core/excel.php';
        $claves = $rows ? array_keys($rows[0]) : array_keys(self::COLUMNAS);
        $columnas = [];
        foreach ($claves as $k) { [$nombre, $tipo] = self::COLUMNAS[$k] ?? [$k, 'texto']; $columnas[$nombre] = $tipo; }
        $filas = array_map(fn($r) => array_values($r), $rows);
        \excel_descargar($archivo, \excel_generar(self::TITULOS[$type] ?? 'Datos', $columnas, $filas));
    }

    private function csv(array $rows,string $f):never{header('Content-Type: text/csv; charset=utf-8');header("Content-Disposition: attachment; filename=$f");$o=fopen('php://output','w');fwrite($o,"\xEF\xBB\xBF");if($rows){fputcsv($o,array_keys($rows[0]),';');foreach($rows as $r)fputcsv($o,array_values($r),';');}fclose($o);exit;}
}

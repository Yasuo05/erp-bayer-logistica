<?php
// Exportación a Excel, paginación y confirmaciones, por HTTP.
declare(strict_types=1);

use App\Core\Database;
use Tests\Prueba;

return function (Prueba $t, string $base): void {
    $pedir = static function (string $ruta) use ($base): array {
        $cuerpo = (string) @file_get_contents($base . $ruta, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20]]));
        $cabeceras = $http_response_header ?? [];
        preg_match('#HTTP/\S+ (\d+)#', $cabeceras[0] ?? '', $m);
        return [(int) ($m[1] ?? 0), $cuerpo, implode("\n", $cabeceras)];
    };

    $t->grupo('Exportar a Excel');
    [$codigo, $cuerpo, $cabeceras] = $pedir('/consultas/exportar?tipo=factura');
    $t->verdadero($codigo === 200 && str_starts_with($cuerpo, 'PK'), 'descarga un archivo .xlsx');
    $t->verdadero(str_contains($cabeceras, 'spreadsheetml.sheet') && (bool) preg_match('/filename="comprobantes_\d{8}_\d{4}\.xlsx"/', $cabeceras), 'con el tipo de Excel y nombre comprobantes_fecha_hora.xlsx');
    [, $html] = $pedir('/consultas/descargas?estado=aceptado');
    $t->verdadero(str_contains($html, '/consultas/exportar?estado=aceptado'), 'el botón exporta con los filtros de la búsqueda');
    [, $html] = $pedir('/comprobantes/boletas');
    $t->verdadero(str_contains($html, '/consultas/exportar?tipo=boleta'), 'en Boletas exporta solo boletas');

    $t->grupo('Paginación');
    foreach (['/consultas/trazabilidad?por_pagina=5', '/envio/estado?por_pagina=5', '/envio/rechazos?por_pagina=5'] as $ruta) {
        [$codigo, $html] = $pedir($ruta);
        $t->verdadero($codigo === 200 && (bool) preg_match('/Mostrando 1–\d+ de \d+/', $html), "{$ruta} muestra «Mostrando 1–N de total»");
    }

    $t->grupo('Confirmación de acciones irreversibles');
    [, $html] = $pedir('/');
    $t->verdadero(str_contains($html, 'dataset.confirmar') && str_contains($html, 'window.confirm'), 'el layout pide confirmación a los formularios marcados');
    
    $conAceptados = (int) Database::conexion()->query("SELECT COUNT(*) FROM facturacion_comprobantes WHERE tipo IN ('factura', 'boleta') AND estado IN ('aceptado', 'observado')")->fetchColumn();
    if ($conAceptados > 0) {
        [, $html] = $pedir('/comprobantes/notas-credito');
        $t->verdadero(str_contains($html, 'data-confirmar="¿Emitir la nota?'), 'emitir una nota pide confirmación');
    } else {
        $t->omitir('no hay facturas ni boletas aceptadas para revisar la confirmación de la nota');
    }

    $firmado = Database::conexion()->query("SELECT id FROM facturacion_comprobantes WHERE estado = 'firmado' ORDER BY id LIMIT 1")->fetchColumn();
    if ($firmado) {
        [, $html] = $pedir('/envio/estado?id=' . $firmado);
        $t->verdadero(str_contains($html, 'data-confirmar="¿Enviar el comprobante?'), 'enviar un comprobante pide confirmación');
    } else {
        $t->omitir('no hay un comprobante firmado para revisar la confirmación de envío');
    }
    [, $html] = $pedir('/configuracion/series');
    $t->verdadero(str_contains($html, 'data-confirmar="¿Desactivar la serie'), 'desactivar una serie pide confirmación');

    $t->grupo('Registro de errores');
    [, $html] = $pedir('/configuracion/parametros');
    $t->verdadero(str_contains($html, 'Registro de errores') && str_contains($html, 'storage/logs/errores-'), 'Parámetros muestra el estado del registro de errores');
};

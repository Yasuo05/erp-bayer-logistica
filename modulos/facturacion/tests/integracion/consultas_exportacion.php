<?php
// Validación del RUC al registrar ventas, exportación a Excel y paginación de Trazabilidad y Envíos.
declare(strict_types=1);

use App\Repositories\ConsultaRepository;
use App\Repositories\FacturacionRepository;
use App\Services\ConsultaComprobantesService;
use App\Services\EnvioService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $t->grupo('Validación del RUC al registrar ventas');
    $producto = (int) $e->valor('SELECT id FROM demo_productos WHERE activo = 1 ORDER BY id LIMIT 1');
    $venta = fn (string $ruc) => (new FacturacionRepository())->crearVentaManual([
        'cliente' => Escenario::MARCA . ' RUC', 'tipo_documento' => 'RUC', 'numero_documento' => $ruc,
        'producto_id' => [$producto], 'cantidad' => ['1'],
    ]);
    $t->falla(fn () => $venta('20123456789'), 'no es válido', 'un RUC con dígito verificador incorrecto se rechaza');
    $t->falla(fn () => $venta('30123456785'), 'no es válido', 'un RUC con prefijo inexistente se rechaza');

    $t->grupo('Exportación a Excel');
    $id = $e->comprobante('RUC');
    $numero = $e->valor("SELECT CONCAT(serie, '-', LPAD(numero, 8, '0')) FROM facturacion_comprobantes WHERE id = ?", [$id]);
    $archivo = (new ConsultaComprobantesService())->exportar(['numero' => $numero]);
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, $archivo['contenido']);
    $zip = new ZipArchive();
    $hoja = $zip->open($ruta) === true ? (string) $zip->getFromName('xl/worksheets/sheet1.xml') : '';
    $zip->close();
    @unlink($ruta);
    $t->verdadero(str_ends_with($archivo['nombre'], '.xlsx') && $archivo['filas'] === 1, 'exporta solo lo filtrado: 1 comprobante (' . $archivo['nombre'] . ')');
    $t->verdadero(str_contains($hoja, $numero) && str_contains($hoja, Escenario::MARCA), 'el Excel contiene su número y su cliente');
    $t->verdadero(str_contains($hoja, '>Borrador<') && str_contains($hoja, '>Sin enviar<') && str_contains($hoja, '>No generado<'), 'y su estado, respuesta y situación del XML');

    $t->grupo('Paginación de Trazabilidad y Envíos');
    $repo = new ConsultaRepository();
    $filtros = ['id' => 0, 'numero' => '', 'etapas' => [], 'desde' => '', 'hasta' => '', 'incidencias' => false];
    $total = $repo->contarEventos($filtros);
    $todos = array_map(static fn (array $ev): string => $ev['fuente'] . $ev['id'], $repo->eventos($filtros, max(1, $total)));
    $porPaginas = [];
    for ($offset = 0; $offset < $total; $offset += 7) {
        $porPaginas = array_merge($porPaginas, array_map(static fn (array $ev): string => $ev['fuente'] . $ev['id'], $repo->eventos($filtros, 7, $offset)));
    }
    $t->verdadero($total === count($todos) && $porPaginas === $todos, "trazabilidad: recorrer de 7 en 7 da los mismos {$total} eventos, sin repetir ni saltar");
    $envio = new EnvioService();
    $totalBandeja = $envio->contarBandeja('');
    $t->igual(count($envio->bandeja('')), $totalBandeja, "envíos: el conteo coincide con la bandeja ({$totalBandeja})");
    $t->igual(min(3, $totalBandeja), count($envio->bandeja('', '', 3, 0)), 'envíos: una página de 3 trae 3 registros');
};

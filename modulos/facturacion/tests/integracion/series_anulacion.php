<?php
// Series reservadas para las pruebas y reglas para anular ante SUNAT un comprobante aceptado.
declare(strict_types=1);

use App\Core\Database;
use App\Repositories\FacturacionRepository;
use App\Repositories\SeriesRepository;
use App\Services\EnvioService;
use App\Services\EstadoComprobanteService;
use App\Services\FacturacionService;
use App\Services\SeriesService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $t->grupo('Series reservadas para las pruebas');
    $series = new SeriesRepository();
    // Lo que tocaría a una emisión real, consultado sin activar las series de prueba
    SeriesRepository::usarSeriesDePrueba(false);
    $realAntes = $series->previsualizar('factura');
    SeriesRepository::usarSeriesDePrueba(true);
    $t->verdadero($realAntes === null || !SeriesRepository::esSeriePrueba('factura', $realAntes['serie']), 'fuera de las pruebas no se elige una serie de prueba');

    $id = $e->comprobante('RUC');
    $serie = (string) $e->valor('SELECT serie FROM facturacion_comprobantes WHERE id = ?', [$id]);
    $t->verdadero(SeriesRepository::esSeriePrueba('factura', $serie), "las pruebas emiten en su serie reservada ({$serie})");

    SeriesRepository::usarSeriesDePrueba(false);
    $realDespues = $series->previsualizar('factura');
    SeriesRepository::usarSeriesDePrueba(true);
    $t->igual($realAntes, $realDespues, 'la serie real no gasta números por las pruebas');
    $t->falla(fn () => (new SeriesService())->crear(['tipo' => 'factura', 'serie' => 'fz99', 'siguiente' => 1]), 'reservada', 'no se puede crear una serie real con un nombre reservado');

    $t->grupo('Anulación ante SUNAT');
    $estados = new EstadoComprobanteService();
    $t->falla(fn () => $estados->verificarAnulacion($id, 'Venta cancelada'), 'aceptado u observado', 'un borrador no se anula ante SUNAT');

    $factura = $e->comprobanteFirmado('RUC');
    (new EnvioService())->enviar($factura, 'aceptado');
    $estados->verificarAnulacion($factura, 'Venta cancelada');
    $t->verdadero(true, 'una factura aceptada hoy, con motivo y sin notas, se puede anular');
    $t->falla(fn () => $estados->verificarAnulacion($factura, ''), 'motivo', 'pide el motivo de la anulación');

    $nota = (new FacturacionService())->emitirNota($factura, 'nota_credito', '09', 'Descuento', 1);
    $t->falla(fn () => $estados->verificarAnulacion($factura, 'Venta cancelada'), 'notas', 'con una nota vigente no se puede anular');
    $t->falla(fn () => (new FacturacionRepository())->cambiarEstado($factura, 'anulado', 'Prueba'), 'notas', 'tampoco pasa a «Anulado» mientras la nota tenga efecto');
    (new FacturacionRepository())->anularBorradorDemo($nota, 'Nota de prueba');
    $estados->verificarAnulacion($factura, 'Venta cancelada');
    $t->verdadero(true, 'anulada la nota, la factura vuelve a poder anularse');

    $plazo = (int) config('sunat.plazo_anulacion_dias');
    Database::conexion()->prepare('UPDATE facturacion_comprobantes SET fecha_emision = NOW() - INTERVAL ' . ($plazo + 1) . ' DAY WHERE id = ?')->execute([$factura]);
    $t->falla(fn () => $estados->verificarAnulacion($factura, 'Venta cancelada'), 'plazo', "pasados {$plazo} días ya no se puede pedir la baja");
};

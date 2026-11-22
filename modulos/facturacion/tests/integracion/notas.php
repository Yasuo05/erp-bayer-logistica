<?php
// Notas de crédito y débito: referencias aceptadas, saldo, motivos del catálogo, tasa y serie del original.
declare(strict_types=1);

use App\Repositories\FacturacionRepository;
use App\Services\EnvioService;
use App\Services\FacturacionService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $t->grupo('Notas de crédito y débito');
    $servicio = new FacturacionService();
    $repo = new FacturacionRepository();

    $borrador = $e->comprobante('RUC');
    $t->falla(fn () => $servicio->emitirNota($borrador, 'nota_credito', '09', 'Descuento', 1), 'aceptados', 'no se emiten notas sobre un comprobante no aceptado');

    $factura = $e->comprobanteFirmado('RUC');
    (new EnvioService())->enviar($factura, 'aceptado');
    $original = $repo->comprobante($factura);
    $saldo = (float) $original['subtotal'];

    $nota = $repo->comprobante($servicio->emitirNota($factura, 'nota_credito', '09', 'Descuento acordado', 1.00));
    $t->verdadero(str_starts_with($nota['serie'], 'F') && $nota['tipo'] === 'nota_credito', 'nota de crédito de una factura: serie con F (' . $nota['serie'] . ')');
    $t->igual('09', $nota['codigo_motivo'], 'guarda el motivo 09 del catálogo');
    $t->igual((float) $original['datos_emision']['tasa_igv'], (float) $nota['datos_emision']['tasa_igv'], 'usa la tasa de IGV del comprobante original');
    $t->igual((int) $factura, (int) $nota['comprobante_referenciado_id'], 'queda vinculada a su factura');
    $t->falla(fn () => $servicio->emitirNota($factura, 'nota_credito', '09', 'Excede', $saldo), 'saldo', 'el crédito acumulado no puede superar la base imponible');
    $t->verdadero($servicio->emitirNota($factura, 'nota_credito', '09', 'Resto', round($saldo - 1, 2)) > 0, 'sí se acepta hasta completar el saldo exacto');

    $debito = $repo->comprobante($servicio->emitirNota($factura, 'nota_debito', '01', 'Intereses por mora', 5));
    $t->verdadero(str_starts_with($debito['serie'], 'F') && $debito['codigo_motivo'] === '01', 'nota de débito con motivo 01 (intereses por mora)');
    $t->falla(fn () => $servicio->emitirNota($factura, 'nota_debito', '09', 'Motivo de crédito', 5), 'motivo', 'un débito no acepta motivos del catálogo de crédito');
    $t->falla(fn () => $servicio->emitirNota($factura, 'nota_debito', '01', '   ', 5), 'describe', 'la descripción es obligatoria');
    $t->falla(fn () => $servicio->emitirNota($factura, 'nota_debito', '01', 'Cero', 0), 'mayor a cero', 'el monto debe ser mayor a cero');

    $boleta = $e->comprobanteFirmado('DNI');
    (new EnvioService())->enviar($boleta, 'aceptado');
    $notaBoleta = $repo->comprobante($servicio->emitirNota($boleta, 'nota_credito', '09', 'Devolución parcial', 1));
    $t->verdadero(str_starts_with($notaBoleta['serie'], 'B'), 'nota de crédito de una boleta: serie con B (' . $notaBoleta['serie'] . ')');
};

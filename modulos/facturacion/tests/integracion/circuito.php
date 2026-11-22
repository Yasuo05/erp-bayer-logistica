<?php
// Circuito completo de una factura: venta → emisión → XML → firma → envío → respuesta → CDR → PDF → trazabilidad.
declare(strict_types=1);

use App\Repositories\ConsultaRepository;
use App\Repositories\FacturacionRepository;
use App\Services\EnvioService;
use App\Services\EstadoComprobanteService;
use App\Services\FacturacionService;
use App\Services\PdfComprobanteService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $t->grupo('Circuito completo de una factura');
    $id = $e->comprobanteFirmado('RUC');
    $comprobante = (new FacturacionRepository())->comprobante($id);
    $t->igual('firmado', $e->estado($id), 'emitida, con XML generado, firmado y verificado');
    $t->verdadero(str_starts_with($comprobante['serie'], 'F'), 'serie de factura (empieza con F): ' . $comprobante['serie']);
    $t->igual(FacturacionService::tasaIgv(), (float) $comprobante['datos_emision']['tasa_igv'], 'guarda la tasa de IGV de la emisión');
    $t->verdadero(str_starts_with((string) $comprobante['cliente'], Escenario::MARCA), 'guarda los datos del cliente de la emisión');
    $t->verdadero((bool) $e->valor('SELECT hash IS NOT NULL FROM facturacion_xml WHERE comprobante_id = ?', [$id]), 'el XML tiene su hash de firma');

    (new EnvioService())->enviar($id, 'aceptado');
    $t->igual('aceptado', $e->estado($id), 'envío simulado con respuesta «aceptado»');
    $t->igual('simulado', (new ConsultaRepository())->origenRespuesta($id), 'la respuesta queda marcada como simulada');

    $envio = (int) $e->valor('SELECT id FROM facturacion_envios WHERE comprobante_id = ? ORDER BY intento DESC LIMIT 1', [$id]);
    $cdr = (new EnvioService())->cdr($envio);
    $t->verdadero($cdr !== null && hash_equals((string) $cdr['cdr_sha256'], hash('sha256', (string) $cdr['cdr_contenido'])), 'CDR guardado con su huella SHA-256 correcta');

    $pdf = (new PdfComprobanteService())->generar($id);
    $t->verdadero(str_starts_with($pdf['contenido'], '%PDF') && str_ends_with($pdf['nombre'], '.pdf'), 'PDF generado: ' . $pdf['nombre']);
    $marca = new ReflectionMethod(PdfComprobanteService::class, 'marcaAgua');
    $t->igual('SIN VALIDEZ TRIBUTARIA', $marca->invoke(new PdfComprobanteService(), 'aceptado', 'simulado'), 'aceptado por el simulador: el PDF conserva la advertencia');

    $etapas = array_column((new ConsultaRepository())->eventos(['id' => $id, 'numero' => '', 'etapas' => [], 'desde' => '', 'hasta' => '', 'incidencias' => false]), 'etapa');
    $t->igual(['borrador_local', 'generado_xml', 'firmado', 'solicitud', 'enviado', 'respuesta', 'cdr', 'aceptado'], $etapas, 'trazabilidad en orden, del borrador al CDR');

    $t->falla(fn () => (new EstadoComprobanteService())->exigirCondiciones($id, 'anulado_local'), 'intentos de envío', 'ya enviado: no se puede anular localmente');
    $t->falla(fn () => (new EnvioService())->enviar($id, 'aceptado'), 'solo se envían', 'un comprobante aceptado no se vuelve a enviar');
};

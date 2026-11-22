<?php

/*
 * Mapa de URLs del módulo: ruta => [Controlador, método].
 * Toda opción del menú (config/menu.php) debe tener aquí su ruta. Una sección nueva sin funcionalidad
 * puede apuntar a PendienteController (página "pendiente de implementar") hasta tener su controlador.
 */

use App\Controllers\InicioController;
use App\Controllers\ParametrosController;
use App\Controllers\ComprobantesController;
use App\Controllers\ConsultaController;
use App\Controllers\DocumentoElectronicoController;
use App\Controllers\EmisionController;
use App\Controllers\SeriesController;
use App\Controllers\EnvioController;

return [
    '/'                            => [InicioController::class, 'index'],

    // Emisión
    '/emision/ventas-por-facturar' => [EmisionController::class, 'ventas'],
    '/emision/emitir'              => [EmisionController::class, 'emitir'],
    '/emision/ventas-demo/crear'   => [EmisionController::class, 'nuevaVenta'],

    // Comprobantes
    '/comprobantes/facturas'       => [ComprobantesController::class, 'facturas'],
    '/comprobantes/boletas'        => [ComprobantesController::class, 'boletas'],
    '/comprobantes/notas-credito'  => [ComprobantesController::class, 'notasCredito'],
    '/comprobantes/notas-debito'   => [ComprobantesController::class, 'notasDebito'],
    '/comprobantes/ver'            => [ComprobantesController::class, 'ver'],
    '/comprobantes/nota/emitir'     => [ComprobantesController::class, 'crearNota'],
    '/comprobantes/anular-demo'    => [ComprobantesController::class, 'anularDemo'],
    '/comprobantes/xml/generar'    => [DocumentoElectronicoController::class, 'generar'],
    '/comprobantes/xml/firmar'     => [DocumentoElectronicoController::class, 'firmar'],
    '/comprobantes/xml/descargar'  => [DocumentoElectronicoController::class, 'descargar'],
    '/comprobantes/xml/corregir'   => [DocumentoElectronicoController::class, 'corregir'],
    '/comprobantes/anulacion/generar'   => [DocumentoElectronicoController::class, 'generarAnulacion'],
    '/comprobantes/anulacion/descargar' => [DocumentoElectronicoController::class, 'descargarAnulacion'],
    '/comprobantes/anulacion/enviar'    => [DocumentoElectronicoController::class, 'enviarAnulacion'],
    '/comprobantes/anulacion/consultar' => [DocumentoElectronicoController::class, 'consultarAnulacion'],

    // Envío electrónico
    '/envio/estado'                => [EnvioController::class, 'estado'],
    '/envio/enviar'                => [EnvioController::class, 'enviar'],
    '/envio/consultar'             => [EnvioController::class, 'consultarTicket'],
    '/envio/observacion/revisar'   => [EnvioController::class, 'revisarObservacion'],
    '/envio/recuperar'             => [EnvioController::class, 'recuperar'],
    '/envio/cdr'                   => [EnvioController::class, 'descargarCdr'],
    '/envio/rechazos'              => [EnvioController::class, 'incidencias'],

    // Consultas
    '/consultas/descargas'         => [ConsultaController::class, 'descargas'],
    '/consultas/pdf'               => [ConsultaController::class, 'pdf'],
    '/consultas/exportar'          => [ConsultaController::class, 'exportar'],
    '/consultas/trazabilidad'      => [ConsultaController::class, 'trazabilidad'],
    '/consultas/cdr-anulacion'     => [DocumentoElectronicoController::class, 'descargarCdrAnulacion'],

    // Configuración
    '/configuracion/series'            => [SeriesController::class, 'index'],
    '/configuracion/series/crear'      => [SeriesController::class, 'crear'],
    '/configuracion/series/actualizar' => [SeriesController::class, 'actualizar'],
    '/configuracion/series/estado'     => [SeriesController::class, 'cambiarEstado'],
    '/configuracion/parametros'    => [ParametrosController::class, 'index'],
];

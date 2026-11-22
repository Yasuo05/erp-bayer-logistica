<?php
// Incidencias del envío: rechazo con corrección, falla técnica con reintento, observación, ticket e intento interrumpido.
declare(strict_types=1);

use App\Services\DocumentoElectronicoService;
use App\Services\EnvioService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $envio = new EnvioService();
    $intentos = fn (int $id): int => (int) $e->valor('SELECT COUNT(*) FROM facturacion_envios WHERE comprobante_id = ?', [$id]);

    $t->grupo('Rechazo: se corrige regenerando el XML');
    $id = $e->comprobanteFirmado('DNI');
    $envio->enviar($id, 'rechazado');
    $t->igual('rechazado', $e->estado($id), 'respuesta «rechazado»');
    $t->falla(fn () => $envio->enviar($id, 'aceptado'), 'solo se envían', 'no se reenvía el mismo XML rechazado');
    (new DocumentoElectronicoService())->corregirYRegenerar($id, 'Se corrigió el nombre del cliente');
    $t->igual('generado_xml', $e->estado($id), 'corregido: XML regenerado con el mismo número');
    $t->igual(1, (int) $e->valor('SELECT COUNT(*) FROM facturacion_xml_historial WHERE comprobante_id = ?', [$id]), 'el XML rechazado queda en el historial');
    (new DocumentoElectronicoService())->firmar($id);
    $envio->enviar($id, 'aceptado');
    $t->verdadero($e->estado($id) === 'aceptado' && $intentos($id) === 2, 'firmado y reenviado: aceptado en el intento 2');
    $t->verdadero((bool) $e->valor("SELECT COUNT(*) FROM facturacion_eventos WHERE comprobante_id = ? AND detalle LIKE 'Intento 2%'", [$id]), 'los eventos muestran el número real del intento');

    $t->grupo('Falla técnica: se reintenta con el mismo XML');
    $id = $e->comprobanteFirmado('RUC');
    $envio->enviar($id, 'error_transitorio');
    $t->igual('pendiente_reintento', $e->estado($id), 'error temporal → pendiente de reintento');
    $t->igual('Reintentar el envío', EnvioService::accionSugerida($e->estado($id))[0], 'acción sugerida: reintentar');
    $t->falla(fn () => $envio->enviar($id, 'aceptado', ''), 'motivo', 'el reintento exige un motivo');
    $envio->enviar($id, 'aceptado', 'Se restableció la conexión');
    $t->verdadero($e->estado($id) === 'aceptado' && $intentos($id) === 2, 'reintentado: aceptado en el intento 2');

    $t->grupo('Observación: válido, no se reenvía');
    $id = $e->comprobanteFirmado('RUC');
    $envio->enviar($id, 'observado');
    $enAtencion = fn (): bool => in_array($id, array_map('intval', array_column($envio->bandeja('incidencias'), 'comprobante_id')), true);
    $t->verdadero($e->estado($id) === 'observado' && $enAtencion(), 'observado figura en «Requieren atención»');
    $envio->registrarRevisionObservacion($id, 'Se actualizará la dirección del cliente');
    $t->verdadero(!$enAtencion(), 'revisada: deja de figurar como pendiente');
    $t->falla(fn () => $envio->registrarRevisionObservacion($id, 'Otra vez'), 'ya fue revisada', 'no se revisa dos veces');

    $t->grupo('Ticket simulado de comprobante permanece pendiente');
    $id = $e->comprobanteFirmado('RUC');
    $envio->enviar($id, 'pendiente');
    $t->igual('enviado', $e->estado($id), 'recepción con ticket → enviado');
    $envio->consultarTicket($id);
    $t->igual('enviado', $e->estado($id), 'un comprobante normal sigue pendiente después de consultar ticket simulado');
    $cdr = $envio->cdr((int)$envio->intentos($id)[0]['id']);
    $t->igual(null, $cdr, 'la consulta pendiente no genera CDR para comprobantes normales');

    $t->grupo('Intento interrumpido: se recupera sin duplicar');
    $id = $e->comprobanteFirmado('RUC');
    $e->interrumpirEnvio($id);
    $t->verdadero($envio->intentoInterrumpido($id) !== null, 'se detecta el intento sin respuesta');
    $t->falla(fn () => $envio->enviar($id, 'aceptado'), 'recuperar intento', 'no deja crear otro intento encima');
    $envio->recuperarIntento($id, 'aceptado');
    $t->verdadero($e->estado($id) === 'aceptado' && $intentos($id) === 1, 'recuperado: aceptado en el mismo intento 1');
};

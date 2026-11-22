<?php

declare(strict_types=1);

use App\Repositories\FacturacionRepository;
use App\Services\DocumentoAnulacionService;
use App\Services\EnvioAnulacionService;
use App\Services\EnvioService;
use App\Services\FacturacionService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $tablas = (int)$e->valor("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facturacion_bajas'");
    $columnas = (int)$e->valor("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facturacion_bajas' AND COLUMN_NAME IN ('envio_estado','ticket','cdr_contenido')");
    if ($tablas === 0 || $columnas !== 3) {
        $t->omitir('La prueba de envío de anulación requiere la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).');
        return;
    }
    if (!class_exists(ZipArchive::class)) {
        $t->omitir('La prueba del CDR de anulación requiere activa la extensión PHP zip.');
        return;
    }

    $crearAceptado = static function () use ($e): int {
        $id = $e->comprobanteFirmado('RUC');
        (new EnvioService())->enviar($id, 'aceptado');
        return $id;
    };
    $documentos = new DocumentoAnulacionService();
    $envios = new EnvioAnulacionService();

    $t->grupo('Envío de anulación: ticket, respuesta y cambio de estado');
    $id = $crearAceptado();
    $baja = $documentos->generar($id, 'Error en los datos del cliente');
    $solicitud = $envios->enviar($baja['id']);
    $t->igual('pendiente', $solicitud['tipo'], 'el envío simulado entrega un ticket pendiente');
    $t->verdadero((bool)$solicitud['ticket'], 'el envío devuelve un ticket consultable');
    $respuesta = $envios->consultar($baja['id']);
    $t->igual('aceptado', $respuesta['tipo'], 'la consulta de la baja devuelve aceptación simulada');
    $t->igual('anulado', $e->estado($id), 'el comprobante queda Anulado tras la aceptación de la baja');
    $guardada = $e->valor('SELECT envio_estado FROM facturacion_bajas WHERE id=?', [$baja['id']]);
    $t->igual('aceptado', $guardada, 'la baja conserva su respuesta final');
    $t->verdadero((int)$e->valor('SELECT COUNT(*) FROM facturacion_eventos WHERE comprobante_id=? AND estado=? AND detalle LIKE ?', [$id, 'anulado', '%'.$baja['identificador'].'%']) === 1, 'cambiarEstado deja el evento de anulación en la trazabilidad');
    $cdr = $e->valor('SELECT cdr_contenido FROM facturacion_bajas WHERE id=?', [$baja['id']]);
    $t->verdadero(is_string($cdr) && str_starts_with($cdr, 'PK'), 'la respuesta de anulación guarda el CDR ZIP simulado');

    $t->grupo('Aceptación de anulación: las notas vigentes bloquean el cambio');
    $idConNota = $crearAceptado();
    $bajaConNota = $documentos->generar($idConNota, 'Anulación de prueba');
    (new FacturacionService())->emitirNota($idConNota, 'nota_credito', '09', 'Descuento', 1);
    $envios->enviar($bajaConNota['id']);
    $t->falla(fn () => $envios->consultar($bajaConNota['id']), 'notas', 'la respuesta no anula un comprobante con notas vigentes');
    $t->igual('aceptado', $e->valor('SELECT envio_estado FROM facturacion_bajas WHERE id=?', [$bajaConNota['id']]), 'la respuesta se conserva aunque la transición sea rechazada');
    $t->igual('aceptado', $e->estado($idConNota), 'el comprobante permanece aceptado mientras su nota siga vigente');

    $t->grupo('Falla al persistir respuesta: no queda envío atascado');
    $idFalla = $crearAceptado();
    $bajaFalla = $documentos->generar($idFalla, 'Falla de transporte simulada');
    $gateway = new class implements \App\Services\GatewayEnvio {
        public function origen(): string { return 'simulado'; }
        public function enviar(string $nombre, string $xml, string $escenario, string $idempotencia): array
        { return ['tipo'=>'aceptado','codigo'=>'0','mensaje'=>'Respuesta de prueba mal formada.','ticket'=>null,'cdr'=>new stdClass()]; }
        public function consultar(string $ticket, string $nombre): array
        { return ['tipo'=>'pendiente','codigo'=>'','mensaje'=>'Pendiente.','ticket'=>$ticket,'cdr'=>null]; }
    };
    $t->falla(fn () => (new EnvioAnulacionService(gateway: $gateway))->enviar($bajaFalla['id']), 'guardar la respuesta', 'un error de persistencia se informa para recuperación');
    $estadoEnvio = $e->valor('SELECT envio_estado FROM facturacion_bajas WHERE id=?', [$bajaFalla['id']]);
    $t->igual('error_transitorio', $estadoEnvio, 'la falla queda registrada como recuperable, no como enviando');
    $t->verdadero($estadoEnvio !== 'enviando', 'no queda una baja atascada en enviando');
};

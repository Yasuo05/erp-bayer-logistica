<?php
// XML de anulación: comunicación de baja (factura) y resumen diario (boleta) de comprobantes aceptados.
declare(strict_types=1);

use App\Services\DocumentoAnulacionService;
use App\Services\DocumentoElectronicoService;
use App\Services\EnvioService;
use App\Services\FirmaXmlService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $anulacion = new DocumentoAnulacionService();
    $firma = new FirmaXmlService();
    $aceptado = static function (string $tipoDocumento) use ($e): int {
        $id = $e->comprobanteFirmado($tipoDocumento);
        (new EnvioService())->enviar($id, 'aceptado');
        return $id;
    };

    $t->grupo('XML de anulación: comunicación de baja (factura)');
    $factura = $aceptado('RUC');
    $baja = $anulacion->generar($factura, 'Error en el RUC del cliente');
    $hoy = date('Ymd');
    $t->verdadero((bool) preg_match("/^RA-{$hoy}-\\d+$/", $baja['identificador']), "identificador RA del día ({$baja['identificador']})");
    $t->igual('comunicacion_baja', $baja['tipo'], 'una factura se anula con comunicación de baja');
    $envio = $anulacion->xmlParaEnvio($baja['id']);
    $t->igual($envio['nombre'], $e->valor('SELECT ruc FROM demo_empresa WHERE id = 1') . "-{$baja['identificador']}.xml", 'el archivo se nombra RUC-RA-AAAAMMDD-N.xml');
    $t->verdadero(str_contains($envio['xml'], '<VoidedDocuments') && str_contains($envio['xml'], 'Error en el RUC del cliente'), 'el XML es una comunicación de baja con el motivo');
    $t->verdadero($firma->verificar($envio['xml']), 'el XML de la baja está firmado y la firma es válida');
    $t->igual([$factura], $envio['comprobantes'], 'la entrega al envío indica qué comprobante anula');
    $t->igual('aceptado', $e->estado($factura), 'el comprobante sigue «Aceptado» hasta que SUNAT acepte la baja');
    $t->verdadero((int) $e->valor("SELECT COUNT(*) FROM facturacion_eventos WHERE comprobante_id = ? AND detalle LIKE ?", [$factura, "%{$baja['identificador']}%"]) === 1, 'la trazabilidad registra la baja generada');
    $t->falla(fn () => $anulacion->generar($factura, 'Otro motivo'), $baja['identificador'], 'no se genera una segunda baja del mismo comprobante');

    $t->grupo('XML de anulación: resumen diario (boleta)');
    $boleta = $aceptado('DNI');
    $resumen = $anulacion->generar($boleta, 'Venta cancelada');
    $t->verdadero((bool) preg_match("/^RC-{$hoy}-\\d+$/", $resumen['identificador']), "identificador RC del día ({$resumen['identificador']})");
    $xml = $anulacion->xmlParaEnvio($resumen['id'])['xml'];
    $t->verdadero(str_contains($xml, '<SummaryDocuments') && str_contains($xml, '<cbc:ConditionCode>3</cbc:ConditionCode>'), 'el XML es un resumen diario con la boleta en estado anulado');
    $t->verdadero($firma->verificar($xml), 'el resumen diario está firmado y la firma es válida');

    $t->grupo('XML de anulación: reglas (las define EstadoComprobanteService)');
    $borrador = $e->comprobante('RUC');
    $t->falla(fn () => $anulacion->generar($borrador, 'Venta cancelada'), 'aceptado u observado', 'un comprobante no aceptado no genera XML de anulación');
    $otra = $aceptado('RUC');
    $t->falla(fn () => $anulacion->generar($otra, ''), 'motivo', 'pide el motivo');
    $t->falla(fn () => $anulacion->generar($otra, str_repeat('x', 101)), '100', 'el motivo admite hasta 100 caracteres');
    $t->igual(0, (int) $e->valor('SELECT COUNT(*) FROM facturacion_baja_items WHERE comprobante_id = ?', [$otra]), 'si no pasa las reglas no se guarda nada');
    $segunda = $anulacion->generar($otra, 'Venta duplicada');
    $t->verdadero((int) substr($segunda['identificador'], strrpos($segunda['identificador'], '-') + 1) > (int) substr($baja['identificador'], strrpos($baja['identificador'], '-') + 1), 'el correlativo del día aumenta en cada comunicación de baja');
    $t->verdadero(DocumentoElectronicoService::rutaCertificado() !== '', 'se firma con el mismo certificado que los comprobantes');
};

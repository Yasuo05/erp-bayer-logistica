<?php

declare(strict_types=1);

namespace App\Services;

/** Respuestas reproducibles para verificar el flujo sin transmitir datos a SUNAT. */
final class GatewayDemo implements GatewayEnvio
{
    public function origen(): string { return 'simulado'; }

    public function enviar(string $nombre, string $xml, string $escenario, string $idempotencia): array
    {
        $casos = [
            'aceptado' => ['aceptado','0','Comprobante aceptado.'],
            'observado' => ['observado','2006','Comprobante recibido con observación: revisar los datos indicados.'],
            'rechazado' => ['rechazado','1033','Comprobante rechazado: corrige el motivo indicado antes de emitir nuevamente.'],
            'pendiente' => ['pendiente','', 'Recepción confirmada; el comprobante está en proceso con el ticket asignado.'],
            'error_transitorio' => ['error_transitorio','HTTP_TIMEOUT','No se recibió respuesta dentro del plazo. Verifica el estado antes de volver a enviar.'],
        ];
        [$tipo,$codigo,$mensaje] = $casos[$escenario] ?? $casos['aceptado'];
        $cdr = in_array($tipo, ['aceptado','observado','rechazado'], true) ? $this->cdrZip($nombre,$tipo,$codigo,$mensaje) : null;

        return ['tipo'=>$tipo,'codigo'=>$codigo,'mensaje'=>$mensaje,'ticket'=>$tipo==='pendiente'?'TICKET-'.bin2hex(random_bytes(4)):null,'cdr'=>$cdr];
    }

    public function consultar(string $ticket, string $nombre): array
    {
        // Solo las bajas (RA) y los resúmenes diarios (RC) reciben respuesta final simulada.
        if (!preg_match('/-R[AC]-\d{8}-\d+\.xml$/i', $nombre)) {
            return ['tipo'=>'pendiente','codigo'=>'','mensaje'=>'Envío simulado: el comprobante continúa pendiente. No se consultó un proveedor externo.','ticket'=>$ticket,'cdr'=>null];
        }
        $mensaje='Respuesta final simulada al consultar el ticket: documento aceptado. No se consultó un proveedor externo.';
        return ['tipo'=>'aceptado','codigo'=>'0','mensaje'=>$mensaje,'ticket'=>$ticket,'cdr'=>$this->cdrZip($nombre,'aceptado','0',$mensaje)];
    }

    private function cdrZip(string $nombre,string $tipo,string $codigo,string $mensaje): string
    {
        if(!class_exists(\ZipArchive::class))throw new \RuntimeException('La extensión PHP zip es necesaria para generar el CDR simulado.');
        $escape=static fn(string $s):string=>htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');
        $xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<ApplicationResponse xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2\"\n"
            ."                    xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\"\n"
            ."                    xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\">\n"
            ."    <cbc:ID>CDR-DEMO</cbc:ID>\n"
            ."    <cbc:Note>".$escape($mensaje)."</cbc:Note>\n"
            ."    <cac:DocumentResponse>\n"
            ."        <cac:Response>\n"
            ."            <cbc:ResponseCode>".$escape($codigo)."</cbc:ResponseCode>\n"
            ."            <cbc:Description>".$escape($tipo.' (simulado, sin validez tributaria)')."</cbc:Description>\n"
            ."        </cac:Response>\n"
            ."        <cac:DocumentReference>\n"
            ."            <cbc:ID>".$escape($nombre)."</cbc:ID>\n"
            ."        </cac:DocumentReference>\n"
            ."    </cac:DocumentResponse>\n"
            ."</ApplicationResponse>\n";
        $directorio=BASE_PATH.'/storage/tmp';if(!is_dir($directorio)&&!@mkdir($directorio,0775,true)&&!is_dir($directorio))throw new \RuntimeException('No se pudo preparar el directorio temporal del CDR.');
        $ruta=tempnam($directorio,'cdr');if($ruta===false)throw new \RuntimeException('No se pudo preparar el CDR.');
        try{$zip=new \ZipArchive();if($zip->open($ruta,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('No se pudo crear el ZIP del CDR.');$zip->addFromString('R-'.$nombre,$xml);$zip->close();$bytes=file_get_contents($ruta);if($bytes===false)throw new \RuntimeException('No se pudo leer el ZIP generado.');return $bytes;}finally{@unlink($ruta);}
    }
}

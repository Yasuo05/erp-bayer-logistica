<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/** Adapter para un middleware externo con contrato JSON acordado por el integrador. */
final class GatewayHttp implements GatewayEnvio
{
    private string $base;
    public function __construct()
    {
        $this->base=rtrim((string)Env::get('ENVIO_GATEWAY_URL',''),'/');
        if($this->base===''||!filter_var($this->base,FILTER_VALIDATE_URL)||!in_array(parse_url($this->base,PHP_URL_SCHEME),['https','http'],true))throw new \RuntimeException('ENVIO_GATEWAY_URL debe ser una URL HTTP(S) válida.');
    }
    public function origen(): string { return 'proveedor'; }
    public function enviar(string $nombre,string $xml,string $escenario,string $idempotencia): array
    { return $this->request('/documents',['filename'=>$nombre,'xml_base64'=>base64_encode($xml)],$idempotencia); }
    public function consultar(string $ticket,string $nombre): array
    { return $this->request('/tickets/'.rawurlencode($ticket),['filename'=>$nombre],hash('sha256',$ticket)); }

    private function request(string $path,array $payload,string $idempotencia): array
    {
        $headers=['Content-Type: application/json','Accept: application/json','Idempotency-Key: '.$idempotencia];$token=(string)Env::get('ENVIO_GATEWAY_TOKEN','');if($token!=='')$headers[]='Authorization: Bearer '.$token;
        $context=stream_context_create(['http'=>['method'=>$path==='/documents'?'POST':'GET','header'=>implode("\r\n",$headers),'content'=>$path==='/documents'?json_encode($payload,JSON_THROW_ON_ERROR):'','timeout'=>(int)Env::get('ENVIO_GATEWAY_TIMEOUT','20'),'ignore_errors'=>true,'protocol_version'=>1.1]]);
        $body=@file_get_contents($this->base.$path,false,$context);$status=0;foreach($http_response_header??[] as $header)if(preg_match('#^HTTP/\S+\s+(\d{3})#',$header,$m))$status=(int)$m[1];
        if($body===false){return ['tipo'=>'error_transitorio','codigo'=>'GATEWAY_NETWORK','mensaje'=>'No hubo respuesta del servicio externo. Se programó revisión y reintento manual.','ticket'=>null,'cdr'=>null];}
        $data=json_decode($body,true);if(!is_array($data))return ['tipo'=>'error_transitorio','codigo'=>'GATEWAY_INVALID_RESPONSE','mensaje'=>'El servicio externo respondió con formato inválido (HTTP '.$status.').','ticket'=>null,'cdr'=>null];
        if($status>=500||$status===429)return ['tipo'=>'error_transitorio','codigo'=>'HTTP_'.$status,'mensaje'=>'Error transitorio del servicio externo (HTTP '.$status.').','ticket'=>null,'cdr'=>null];
        if($status<200||$status>=300)throw new \RuntimeException('El gateway rechazó la solicitud con HTTP '.$status.'.');
        $tipo=$data['tipo']??$data['status']??'';if(!in_array($tipo,['aceptado','observado','rechazado','pendiente','error_transitorio'],true))return ['tipo'=>'error_transitorio','codigo'=>'GATEWAY_UNKNOWN_STATUS','mensaje'=>'El gateway devolvió un estado desconocido.','ticket'=>null,'cdr'=>null];
        $cdr=null;if(isset($data['cdr_base64'])&&is_string($data['cdr_base64'])){$cdr=base64_decode($data['cdr_base64'],true);if($cdr===false)throw new \RuntimeException('El gateway devolvió un CDR con codificación inválida.');}
        return ['tipo'=>$tipo,'codigo'=>mb_substr((string)($data['codigo']??''),0,30),'mensaje'=>mb_substr((string)($data['mensaje']??''),0,4000),'ticket'=>isset($data['ticket'])?mb_substr((string)$data['ticket'],0,100):null,'cdr'=>$cdr];
    }
}

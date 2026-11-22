<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Router;
use App\Core\View;
use App\Services\EnvioService;

final class EnvioController
{
    public function estado(): void { $this->mostrar(''); }
    public function incidencias(): void { $this->mostrar('incidencias'); }
    private function mostrar(string $filtro): void
    {
        $estado=isset($_GET['estado'])&&is_string($_GET['estado'])?trim($_GET['estado']):'';$buscar=isset($_GET['buscar'])&&is_string($_GET['buscar'])?mb_substr(trim($_GET['buscar']),0,100):'';
        $estados=$filtro==='incidencias'?['observado','rechazado','pendiente_reintento']:['proceso','firmado','enviado','aceptado','observado','rechazado','pendiente_reintento'];if(!in_array($estado,$estados,true))$estado='';
        $interrumpido=false;$observacionRevisada=false;
        try{$service=new EnvioService();$grupo=$estado!==''?$estado:$filtro;$paginacion=\App\Core\Paginacion::desdeUrl($service->contarBandeja($grupo,$buscar),$_GET);$rows=$service->bandeja($grupo,$buscar,$paginacion->porPagina,$paginacion->offset());$resumen=$service->resumen();$error=null;$detalle=null;$intentos=[];
            if(isset($_GET['id'])){$id=(int)$_GET['id'];$repo=new \App\Repositories\FacturacionRepository();$detalle=$repo->comprobante($id);if(!$detalle)throw new \RuntimeException('No se encontró el comprobante.');$intentos=$service->intentos($id);$interrumpido=$service->intentoInterrumpido($id)!==null;$observacionRevisada=$service->observacionRevisada($id);}
        }catch(\Throwable $e){$rows=[];$paginacion=new \App\Core\Paginacion(0);$resumen=['listos'=>0,'esperando'=>0,'incidencias'=>0,'aceptados'=>0];$error=mensaje_error($e);$detalle=null;$intentos=[];}
        View::render('envio/bandeja',['titulo'=>$filtro==='incidencias'?'Rechazos y reintentos':'Envío, respuesta y CDR','filas'=>$rows,'paginacion'=>$paginacion,'error'=>$error,'filtro'=>$filtro,'detalle'=>$detalle,'intentos'=>$intentos,'interrumpido'=>$interrumpido,'observacionRevisada'=>$observacionRevisada,'modoDemo'=>\App\Services\GatewayFactory::esDemo(),'resumen'=>$resumen,'buscar'=>$buscar,'estadoFiltro'=>$estado]);
    }
    public function enviar(): void
    {
        $id=(int)($_POST['id']??0);if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.url('/envio/estado'));exit;}
        try{(new EnvioService())->enviar($id,(string)($_POST['escenario']??'aceptado'),(string)($_POST['motivo']??''));$_SESSION['flash']='Intento registrado. Revisa la respuesta, CDR y trazabilidad.';}
        catch(\Throwable $e){$_SESSION['flash_error']=mensaje_error($e);}
        header('Location: '.url('/envio/estado').'?id='.$id);exit;
    }
    public function consultarTicket(): void
    {
        $id=(int)($_POST['id']??0);
        try {
            $respuesta=(new EnvioService())->consultarTicket($id);
            if(\App\Services\GatewayFactory::esDemo())$_SESSION['flash']='Consulta simulada: '.ucfirst($respuesta['tipo']).'. '.$respuesta['mensaje'];
            elseif($respuesta['tipo']==='error_transitorio')$_SESSION['flash_error']='No se pudo consultar el proveedor. El ticket se conserva para volver a intentarlo.';
            else $_SESSION['flash']='Respuesta consultada al proveedor: '.ucfirst(str_replace('_',' ',$respuesta['tipo'])).'.';
        } catch(\Throwable $e) { $_SESSION['flash_error']=mensaje_error($e); }
        header('Location: '.url('/envio/estado').'?id='.$id);exit;
    }
    /** Observado: el comprobante es válido; se registra la revisión en lugar de reenviarlo */
    public function revisarObservacion(): void { $id=(int)($_POST['id']??0);if($_SERVER['REQUEST_METHOD']==='POST'){try{(new EnvioService())->registrarRevisionObservacion($id,(string)($_POST['nota']??''));$_SESSION['flash']='Observación revisada. El comprobante sigue siendo válido y ya no figura como pendiente de atención.';}catch(\Throwable $e){$_SESSION['flash_error']=mensaje_error($e);}}header('Location: '.url('/envio/estado').'?id='.$id);exit; }
    /** Intento interrumpido: se consulta de nuevo con la misma clave, sin crear otro intento */
    public function recuperar(): void
    {
        $id=(int)($_POST['id']??0);
        if($_SERVER['REQUEST_METHOD']==='POST'){
            try{$r=(new EnvioService())->recuperarIntento($id,(string)($_POST['escenario']??'aceptado'));$_SESSION['flash']='Intento recuperado sin duplicar el envío. Respuesta: '.ucfirst(str_replace('_',' ',$r['tipo'])).'.';}
            catch(\Throwable $e){$_SESSION['flash_error']=mensaje_error($e);}
        }
        header('Location: '.url('/envio/estado').'?id='.$id);exit;
    }
    public function descargarCdr(): void
    {
        $cdr=(new EnvioService())->cdr((int)($_GET['envio']??0));if(!$cdr){http_response_code(404);echo 'CDR no disponible';return;}
        if(!hash_equals((string)$cdr['cdr_sha256'],hash('sha256',$cdr['cdr_contenido']))){http_response_code(500);echo 'La huella del CDR no coincide; descarga bloqueada.';return;}
        try { $xml=$this->extraerXmlCdr((string)$cdr['cdr_contenido']); }
        catch (\Throwable $e) { http_response_code(500);echo 'No se pudo leer el XML contenido en el CDR.';return; }
        if(!$this->cdrCorrespondeAlComprobante($xml,$cdr)){http_response_code(409);echo 'El CDR no corresponde a este comprobante; descarga bloqueada.';return;}
        // Ej.: R-20000000001-01-F001-1-intento1-SIMULADO.xml (el nombre indica comprobante, intento y origen)
        $base=$cdr['nombre_archivo']?pathinfo((string)$cdr['nombre_archivo'],PATHINFO_FILENAME):$cdr['serie'].'-'.$cdr['numero'];
        $nombre='R-'.$base.'-intento'.(int)$cdr['intento'].($cdr['origen']==='simulado'?'-SIMULADO':'').'.xml';
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.str_replace('"','',$nombre).'"');
        header('Content-Length: '.strlen($xml));
        echo $xml;
    }

    /** El CDR recibido se conserva como ZIP para trazabilidad; esta descarga entrega su XML interno. */
    private function extraerXmlCdr(string $contenido): string
    {
        if(str_starts_with(ltrim($contenido),'<'))return $this->formatearXmlCdr($contenido);
        if(!class_exists(\ZipArchive::class))throw new \RuntimeException('La extensión ZIP no está disponible.');
        $directorio=BASE_PATH.'/storage/tmp';if(!is_dir($directorio)&&!@mkdir($directorio,0775,true)&&!is_dir($directorio))throw new \RuntimeException('No se pudo preparar el directorio temporal del CDR.');
        $ruta=tempnam($directorio,'cdr-download-');
        if($ruta===false)throw new \RuntimeException('No se pudo preparar la lectura del CDR.');
        try {
            if(file_put_contents($ruta,$contenido)!==strlen($contenido))throw new \RuntimeException('No se pudo leer el CDR.');
            $zip=new \ZipArchive();
            if($zip->open($ruta)!==true)throw new \RuntimeException('El CDR no es un ZIP válido.');
            try {
                for($i=0;$i<$zip->numFiles;$i++) {
                    $nombre=$zip->getNameIndex($i);
                    if(!is_string($nombre)||str_ends_with($nombre,'/')||strtolower(pathinfo($nombre,PATHINFO_EXTENSION))!=='xml')continue;
                    $xml=$zip->getFromIndex($i);
                    if(is_string($xml)&&trim($xml)!=='')return $this->formatearXmlCdr($xml);
                }
            } finally { $zip->close(); }
            throw new \RuntimeException('El CDR no contiene un XML.');
        } finally { @unlink($ruta); }
    }

    /** El CDR debe referirse al documento de este comprobante: por su nombre de archivo o por serie-número */
    private function cdrCorrespondeAlComprobante(string $xml,array $cdr): bool
    {
        $documento=new \DOMDocument();
        if(!@$documento->loadXML($xml,LIBXML_NONET))return false;
        $archivo=(string)$cdr['nombre_archivo'];
        $esperados=array_filter([$archivo,pathinfo($archivo,PATHINFO_FILENAME),$cdr['serie'].'-'.(int)$cdr['numero'],$cdr['serie'].'-'.str_pad((string)$cdr['numero'],8,'0',STR_PAD_LEFT)]);
        foreach($documento->getElementsByTagNameNS('*','DocumentReference') as $referencia)
            foreach($referencia->childNodes as $hijo)
                if($hijo instanceof \DOMElement&&$hijo->localName==='ID'&&in_array(trim($hijo->textContent),$esperados,true))return true;
        return false;
    }

    private function formatearXmlCdr(string $xml): string
    {
        if(!class_exists(\DOMDocument::class))return $xml;
        $documento=new \DOMDocument('1.0','UTF-8');
        $documento->preserveWhiteSpace=false;
        $documento->formatOutput=true;
        return @$documento->loadXML($xml,LIBXML_NONET)?($documento->saveXML()?:$xml):$xml;
    }
}

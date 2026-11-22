<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Router;
use App\Core\View;
use App\Repositories\DocumentoElectronicoRepository;
use App\Repositories\FacturacionRepository;
use App\Services\ConsultaComprobantesService;
use App\Services\FacturacionService;

final class ComprobantesController
{
    /** Facturas o boletas: reutiliza la búsqueda paginada de Consulta y descarga, fijando el tipo */
    private function listado(string $tipo,string $titulo): void
    {
        View::render('comprobantes/lista',(new ConsultaComprobantesService())->listar($_GET,$tipo)+['titulo'=>$titulo,'tipo'=>$tipo,'tipos'=>ConsultaController::TIPOS,'estados'=>config('estados.etiquetas',[]),'flash'=>$_SESSION['flash']??null,'flashError'=>$_SESSION['flash_error']??null]);unset($_SESSION['flash'],$_SESSION['flash_error']);
    }
    public function facturas():void{$this->listado('factura','Facturas');}
    public function boletas():void{$this->listado('boleta','Boletas');}
    public function notasCredito():void{$this->notas('nota_credito','Notas de crédito');}
    public function notasDebito():void{$this->notas('nota_debito','Notas de débito');}
    /** Notas emitidas, más las facturas y boletas que pueden recibir una nota y los motivos del catálogo SUNAT */
    private function notas(string $tipo,string $titulo): void
    {
        $repo=new FacturacionRepository();
        try{$rows=$repo->comprobantes($tipo);$referencias=$repo->referenciasParaNota();$error=null;}catch(\Throwable $e){$rows=[];$referencias=[];$error=mensaje_error($e);}
        View::render('comprobantes/notas',['titulo'=>$titulo,'tipo'=>$tipo,'comprobantes'=>$rows,'referencias'=>$referencias,'motivos'=>config("sunat.motivos_nota.{$tipo}",[]),'error'=>$error,'flash'=>$_SESSION['flash']??null,'flashError'=>$_SESSION['flash_error']??null]);unset($_SESSION['flash'],$_SESSION['flash_error']);
    }
    public function ver():void
    {
        $row=(new FacturacionRepository())->comprobante((int)($_GET['id']??0));if(!$row){Router::noEncontrada();return;}
        try{$docs=new DocumentoElectronicoRepository();$xml=$docs->xml((int)$row['id']);$versionesXml=$docs->historial((int)$row['id']);$xmlError=null;}catch(\Throwable $e){$xml=null;$versionesXml=[];$xmlError=mensaje_error($e);}
        try{$origenRespuesta=(new \App\Repositories\ConsultaRepository())->origenRespuesta((int)$row['id']);}catch(\Throwable){$origenRespuesta=null;}
        try{$baja=(new \App\Services\DocumentoAnulacionService())->deComprobante((int)$row['id']);$bajaError=null;}catch(\Throwable $e){$baja=null;$bajaError=mensaje_error($e);}
        // Cobros registrados en Comercial (G8) contra este comprobante
        try{$q=\App\Core\Database::conexion()->prepare("SELECT numero,fecha,medio_pago,importe,estado FROM cobranzas WHERE comprobante_id=? ORDER BY id");$q->execute([(int)$row['id']]);$cobros=$q->fetchAll();}catch(\Throwable){$cobros=[];}
        View::render('comprobantes/ver',['titulo'=>'Comprobante '.$row['serie'].'-'.$row['numero'],'comprobante'=>$row,'xml'=>$xml,'xmlError'=>$xmlError,'versionesXml'=>$versionesXml,'origenRespuesta'=>$origenRespuesta,'baja'=>$baja,'bajaError'=>$bajaError,'cobros'=>$cobros]);
    }
    public function anularDemo():void
    {
        $id=(int)($_POST['id']??0);
        if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.url('/comprobantes/ver').'?id='.$id);exit;}
        try{(new FacturacionRepository())->anularBorradorDemo($id,(string)($_POST['motivo']??''));$_SESSION['flash']='Comprobante anulado. Se conservó su número y trazabilidad.';}
        catch(\Throwable $e){$_SESSION['flash_error']=mensaje_error($e);}
        header('Location: '.url('/comprobantes/ver').'?id='.$id);exit;
    }
    public function crearNota():void
    {
        if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.url('/'));exit;}
        $tipo=(string)($_POST['tipo']??'');try{$id=(new FacturacionService())->emitirNota((int)($_POST['referencia_id']??0),$tipo,(string)($_POST['codigo_motivo']??''),(string)($_POST['motivo']??''),(float)($_POST['monto']??0));$_SESSION['flash']='Nota emitida correctamente.';header('Location: '.url('/comprobantes/ver').'?id='.$id);}
        catch(\Throwable $e){$_SESSION['flash_error']=mensaje_error($e);$destino=$tipo==='nota_debito'?'/comprobantes/notas-debito':'/comprobantes/notas-credito';header('Location: '.url($destino));}exit;
    }
}

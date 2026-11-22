<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Paginacion;
use App\Core\View;
use App\Repositories\FacturacionRepository;
use App\Services\FacturacionService;

final class EmisionController
{
    public function ventas(): void
    {
        $buscar=is_string($_GET['buscar']??null)?mb_substr(trim($_GET['buscar']),0,100):'';
        try{$repo=new FacturacionRepository();$paginacion=Paginacion::desdeUrl($repo->contarVentasPendientes($buscar),$_GET);$ventas=$repo->ventasPendientes($buscar,$paginacion->porPagina,$paginacion->offset());$error=null;}
        catch(\Throwable $e){$ventas=[];$paginacion=new Paginacion(0);$error=mensaje_error($e);}
        View::render('emision/ventas',['titulo'=>'Ventas por facturar','ventas'=>$ventas,'buscar'=>$buscar,'paginacion'=>$paginacion,'error'=>$error,'flash'=>$_SESSION['flash']??null]);unset($_SESSION['flash']);
    }

    /** La herramienta de prueba «Registrar venta» se retiró: las ventas llegan desde Comercial (Grupo 8). */
    public function nuevaVenta(): void
    {
        $_SESSION['flash']='Las ventas se registran y confirman en Comercial › Ventas del ERP; aquí aparecen listas para facturar.';
        header('Location: '.url('/emision/ventas-por-facturar'));exit;
    }

    public function emitir(): void
    {
        $repo=new FacturacionRepository();
        if($_SERVER['REQUEST_METHOD']!=='POST'){
            $ventas=$repo->ventasPendientes();$ventaId=(int)($_GET['venta_id']??0);$venta=$ventaId?$repo->venta($ventaId):null;
            try{$revision=$venta?(new FacturacionService())->revisarVenta($venta):null;$error=null;}catch(\Throwable $e){$revision=null;$error=mensaje_error($e);}
            View::render('emision/emitir',['titulo'=>'Emitir comprobante','ventas'=>$ventas,'venta'=>$venta,'revision'=>$revision,'error'=>$error,'flashError'=>$_SESSION['flash_error']??null]);unset($_SESSION['flash_error']);return;
        }
        $ventaId=(int)($_POST['venta_id']??0);
        try{
            if(($_POST['confirmar']??'')!=='1')throw new \InvalidArgumentException('Marca la casilla para confirmar que revisaste los importes.');
            $total=is_numeric($_POST['total_revisado']??null)?(float)$_POST['total_revisado']:throw new \InvalidArgumentException('Vuelve a revisar la venta antes de emitir.');
            $tasa=is_numeric($_POST['tasa_revisada']??null)?(float)$_POST['tasa_revisada']:throw new \InvalidArgumentException('Vuelve a revisar la tasa de IGV antes de emitir.');
            $huella=is_string($_POST['huella_revision']??null)?$_POST['huella_revision']:'';
            $id=(new FacturacionService())->emitirVenta($ventaId,(string)($_POST['tipo']??''),$total,$tasa,$huella);$_SESSION['flash']='Comprobante emitido correctamente.';header('Location: '.url('/comprobantes/ver').'?id='.$id);
        }
        catch(\Throwable $e){$_SESSION['flash_error']=mensaje_error($e);header('Location: '.url('/emision/emitir').'?venta_id='.$ventaId);}exit;
    }
}

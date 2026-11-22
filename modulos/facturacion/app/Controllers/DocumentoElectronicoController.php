<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DocumentoAnulacionService;
use App\Services\DocumentoElectronicoService;

/** Acciones de XML y firma desde el detalle del comprobante */
final class DocumentoElectronicoController
{
    public function generar(): void
    {
        $this->accion(function (int $id): string {
            $nombre = (new DocumentoElectronicoService())->generarXml($id);
            return "XML generado: {$nombre}. Ahora puedes firmarlo.";
        });
    }

    public function firmar(): void
    {
        $this->accion(function (int $id): string {
            $hash = (new DocumentoElectronicoService())->firmar($id);
            return "XML firmado digitalmente. Hash: {$hash}";
        });
    }

    public function corregir(): void
    {
        $this->accion(function (int $id): string {
            $nombre = (new DocumentoElectronicoService())->corregirYRegenerar($id, (string) ($_POST['motivo'] ?? ''));
            return "XML corregido y regenerado: {$nombre}. Fírmalo para volver a enviarlo.";
        });
    }

    /** Comunicación de baja (factura) o resumen diario (boleta) de un comprobante aceptado */
    public function generarAnulacion(): void
    {
        $this->accion(function (int $id): string {
            $baja = (new DocumentoAnulacionService())->generar($id, (string) ($_POST['motivo'] ?? ''));
            return DocumentoAnulacionService::NOMBRES[$baja['tipo']] . " {$baja['identificador']} generada y firmada: {$baja['nombre']}. Queda lista para enviarla a SUNAT.";
        });
    }

    public function descargarAnulacion(): void
    {
        try {
            $archivo = (new DocumentoAnulacionService())->descargar((int) ($_GET['baja'] ?? 0));
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = mensaje_error($e);
            header('Location: ' . url('/comprobantes/ver') . '?id=' . (int) ($_GET['id'] ?? 0));
            exit;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        echo $archivo['xml'];
    }

    public function enviarAnulacion(): void
    {
        $id=(int)($_POST['baja']??0); $comp=(int)($_POST['id']??0);
        try { $r=(new \App\Services\EnvioAnulacionService())->enviar($id,(string)($_POST['escenario']??'pendiente')); $_SESSION['flash']='Envío de anulación registrado. '.($r['ticket']?'Ticket: '.$r['ticket'].'. ':'').$r['mensaje']; }
        catch(\Throwable $e) { $_SESSION['flash_error']=mensaje_error($e); }
        header('Location: '.url('/comprobantes/ver').'?id='.$comp); exit;
    }

    public function consultarAnulacion(): void
    {
        $id=(int)($_POST['baja']??0); $comp=(int)($_POST['id']??0);
        try { $r=(new \App\Services\EnvioAnulacionService())->consultar($id); $_SESSION['flash']='Respuesta de anulación: '.ucfirst($r['tipo']).'. '.$r['mensaje']; }
        catch(\Throwable $e) { $_SESSION['flash_error']=mensaje_error($e); }
        header('Location: '.url('/comprobantes/ver').'?id='.$comp); exit;
    }

    public function descargarCdrAnulacion(): void
    {
        $db=\App\Core\Database::conexion(); $q=$db->prepare('SELECT nombre_archivo,cdr_contenido,cdr_sha256,origen_envio FROM facturacion_bajas WHERE id=? AND cdr_contenido IS NOT NULL'); $q->execute([(int)($_GET['baja']??0)]); $cdr=$q->fetch();
        if(!$cdr){http_response_code(404);echo 'CDR no disponible';return;}
        if(!hash_equals((string)$cdr['cdr_sha256'],hash('sha256',$cdr['cdr_contenido']))){http_response_code(500);echo 'La huella del CDR no coincide.';return;}
        header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="CDR-'.$cdr['nombre_archivo'].($cdr['origen_envio']==='simulado'?'-SIMULADO':'').'.zip"'); header('Content-Length: '.strlen($cdr['cdr_contenido'])); echo $cdr['cdr_contenido'];
    }

    public function descargar(): void
    {
        try {
            $archivo = (new DocumentoElectronicoService())->descargar((int) ($_GET['id'] ?? 0));
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = mensaje_error($e);
            header('Location: ' . url('/comprobantes/ver') . '?id=' . (int) ($_GET['id'] ?? 0));
            exit;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        echo $archivo['xml'];
    }

    /** POST con id del comprobante → ejecuta la acción y vuelve al detalle con el resultado */
    private function accion(callable $ejecutar): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $_SESSION['flash'] = $ejecutar($id);
            } catch (\Throwable $e) {
                $_SESSION['flash_error'] = mensaje_error($e);
            }
        }
        header('Location: ' . url('/comprobantes/ver') . '?id=' . $id);
        exit;
    }
}

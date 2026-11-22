<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Paginacion;
use App\Core\Router;
use App\Core\View;
use App\Repositories\ConsultaRepository;
use App\Services\ComprobanteNoEncontrado;
use App\Services\ConsultaComprobantesService;
use App\Services\PdfComprobanteService;

/** Representación: consulta y descarga de comprobantes (PDF, XML y CDR) y su trazabilidad */
final class ConsultaController
{
    public const TIPOS = [
        'factura'      => 'Factura',
        'boleta'       => 'Boleta',
        'nota_credito' => 'Nota de crédito',
        'nota_debito'  => 'Nota de débito',
    ];

    /** Filtro "Etapa" de la trazabilidad: grupo => [etiqueta, etapas que incluye] */
    public const ETAPAS = [
        'emision'   => ['Emisión', ['borrador_local']],
        'xml'       => ['XML y firma', ['generado_xml', 'firmado']],
        'envio'     => ['Envío y reintentos', ['solicitud', 'enviado', 'reintento', 'reintento_habilitado', 'recuperacion', 'pendiente_reintento']],
        'respuesta' => ['Respuesta y CDR', ['consulta_ticket', 'respuesta', 'persistencia', 'cdr', 'cdr_ausente', 'aceptado', 'observado', 'observacion_revisada', 'rechazado']],
        'anulacion' => ['Anulación', ['anulado_local', 'anulado']],
    ];

    public function descargas(): void
    {
        View::render('consultas/descargas', (new ConsultaComprobantesService())->listar($_GET) + [
            'titulo'     => 'Consulta y descarga',
            'tipos'      => self::TIPOS,
            'estados'    => config('estados.etiquetas', []),
            'flashError' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_error']);
    }

    /** Excel con los comprobantes que cumplen los filtros de la búsqueda actual */
    public function exportar(): void
    {
        try {
            $archivo = (new ConsultaComprobantesService())->exportar($_GET);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'No se pudo exportar: ' . mensaje_error($e);
            header('Location: ' . url('/consultas/descargas'));
            exit;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        header('Content-Length: ' . strlen($archivo['contenido']));
        echo $archivo['contenido'];
    }

    /** PDF del comprobante: se abre en el navegador, o se descarga con ?descargar=1 */
    public function pdf(): void
    {
        try {
            $pdf = (new PdfComprobanteService())->generar((int) ($_GET['id'] ?? 0));
        } catch (ComprobanteNoEncontrado) {
            Router::noEncontrada();
            return;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'No se pudo generar el PDF: ' . mensaje_error($e);
            header('Location: ' . url('/consultas/descargas'));
            exit;
        }

        $disposicion = isset($_GET['descargar']) ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header("Content-Disposition: {$disposicion}; filename=\"{$pdf['nombre']}\"");
        header('Content-Length: ' . strlen($pdf['contenido']));
        echo $pdf['contenido'];
    }

    /**
     * Línea de tiempo de los comprobantes: emisión, XML, firma, envíos, respuestas y CDR.
     * Con ?id= muestra el historial completo de un comprobante.
     */
    public function trazabilidad(): void
    {
        $valor = static fn (string $clave): string => is_string($_GET[$clave] ?? null) ? trim($_GET[$clave]) : '';
        $etapa = array_key_exists($valor('etapa'), self::ETAPAS) ? $valor('etapa') : '';

        $filtros = [
            'id'          => ctype_digit($valor('id')) ? (int) $valor('id') : 0,
            'numero'      => mb_substr($valor('numero'), 0, 20),
            'etapa'       => $etapa,
            'etapas'      => $etapa !== '' ? self::ETAPAS[$etapa][1] : [],
            'incidencias' => $valor('incidencias') === '1',
            'desde'       => ConsultaComprobantesService::fecha($valor('desde')),
            'hasta'       => ConsultaComprobantesService::fecha($valor('hasta')),
        ];
        if ($filtros['desde'] !== '' && $filtros['hasta'] !== '' && $filtros['desde'] > $filtros['hasta']) {
            [$filtros['desde'], $filtros['hasta']] = [$filtros['hasta'], $filtros['desde']];
        }

        $comprobante = null;
        try {
            $repositorio = new ConsultaRepository();
            if ($filtros['id'] > 0) {
                $comprobante = $repositorio->resumen($filtros['id']);
                if ($comprobante === null) {
                    Router::noEncontrada();
                    return;
                }
            }
            // El historial de un comprobante se muestra completo; la vista general va por páginas
            $paginacion = $comprobante ? null : Paginacion::desdeUrl($repositorio->contarEventos($filtros), $_GET);
            $eventos = $repositorio->eventos($filtros, $paginacion?->porPagina, $paginacion?->offset() ?? 0);
            $error = null;
        } catch (\Throwable $e) {
            $eventos = [];
            $paginacion = $comprobante ? null : new Paginacion(0);
            $error = mensaje_error($e);
        }

        View::render('consultas/trazabilidad', [
            'titulo'      => 'Trazabilidad',
            'filtros'     => $filtros,
            'comprobante' => $comprobante,
            'eventos'     => $eventos,
            'error'       => $error,
            'tipos'       => self::TIPOS,
            'paginacion'  => $paginacion,
        ]);
    }
}

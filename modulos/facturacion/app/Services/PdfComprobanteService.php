<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ConsultaRepository;
use App\Repositories\DocumentoElectronicoRepository;
use App\Repositories\FacturacionRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Representación impresa (PDF) de un comprobante.
 * Solo lee: comprobante, detalle y datos de emisión (Emisión) y XML/hash (Procesamiento electrónico).
 */
final class PdfComprobanteService
{
    private const TITULOS = [
        'factura'      => 'FACTURA ELECTRÓNICA',
        'boleta'       => 'BOLETA DE VENTA ELECTRÓNICA',
        'nota_credito' => 'NOTA DE CRÉDITO ELECTRÓNICA',
        'nota_debito'  => 'NOTA DE DÉBITO ELECTRÓNICA',
    ];

    /** Estados en los que el comprobante ya tiene validez ante SUNAT (lo confirma el envío y su CDR) */
    private const ESTADOS_VALIDOS = ['aceptado'];

    private const ESTADOS_ANULADOS = ['anulado', 'anulado_local'];

    /**
     * @return array{nombre: string, contenido: string} nombre de archivo y bytes del PDF
     * @throws ComprobanteNoEncontrado si el id no existe
     */
    public function generar(int $id): array
    {
        $comprobante = (new FacturacionRepository())->comprobante($id);
        if ($comprobante === null) {
            throw new ComprobanteNoEncontrado('No se encontró el comprobante.');
        }

        // Emisor, cliente y tasa tal como estaban al emitir: el PDF no cambia aunque después cambien en sus módulos.
        // El cliente ya viene así desde FacturacionRepository::comprobante().
        $datos = $comprobante['datos_emision'] ?? null;
        if (!is_array($datos) || !isset($datos['emisor'], $datos['cliente'], $datos['tasa_igv'])) {
            throw new \RuntimeException('El comprobante no tiene sus datos de emisión: revisa que la base esté completa: importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).');
        }
        $emisor = $datos['emisor'];

        $documentos = new DocumentoElectronicoRepository();
        $origen = (new ConsultaRepository())->origenRespuesta($id);

        $html = $this->html([
            'comprobante'  => $comprobante,
            'emisor'       => $emisor,
            'xml'          => $documentos->xml($id),
            'titulo'       => self::TITULOS[$comprobante['tipo']] ?? strtoupper($comprobante['tipo']),
            'numero'       => $comprobante['serie'] . '-' . str_pad((string) $comprobante['numero'], 8, '0', STR_PAD_LEFT),
            'referencia'   => $comprobante['serie_referencia']
                ? $comprobante['serie_referencia'] . '-' . str_pad((string) $comprobante['numero_referencia'], 8, '0', STR_PAD_LEFT)
                : null,
            'tasaIgv'      => (float) $datos['tasa_igv'] * 100,
            'montoLetras'  => MontoEnLetras::convertir((float) $comprobante['total']),
            'marcaAgua'    => $this->marcaAgua($comprobante['estado'], $origen),
            'simulado'     => $origen === 'simulado',
        ]);

        return [
            'nombre'    => $this->nombreArchivo($emisor['ruc'], $comprobante),
            'contenido' => $this->renderizar($html),
        ];
    }

    /** Mismo formato que el XML: RUC-TIPO-SERIE-NUMERO (ej. 20000000001-03-B001-1.pdf) */
    private function nombreArchivo(string $ruc, array $comprobante): string
    {
        $codigoTipo = config("sunat.tipos_comprobante.{$comprobante['tipo']}", '00');

        return sprintf('%s-%s-%s-%d.pdf', $ruc, $codigoTipo, $comprobante['serie'], $comprobante['numero']);
    }

    /**
     * Texto en diagonal cuando el PDF no debe usarse como comprobante válido.
     * Solo se quita si la aceptación vino del proveedor: un "aceptado" del simulador conserva la advertencia.
     */
    private function marcaAgua(string $estado, ?string $origen): ?string
    {
        if (in_array($estado, self::ESTADOS_ANULADOS, true)) {
            return 'ANULADO';
        }

        return in_array($estado, self::ESTADOS_VALIDOS, true) && $origen === 'proveedor' ? null : 'SIN VALIDEZ TRIBUTARIA';
    }

    private function html(array $datos): string
    {
        extract($datos, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/app/Views/pdf/comprobante.php';

        return (string) ob_get_clean();
    }

    private function renderizar(string $html): string
    {
        $opciones = new Options();
        $opciones->set('defaultFont', 'DejaVu Sans'); // incluye tildes y ñ
        $opciones->set('isRemoteEnabled', false);     // el PDF no descarga nada de internet
        $opciones->set('isPhpEnabled', false);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}

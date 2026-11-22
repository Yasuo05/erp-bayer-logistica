<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Repositories\DocumentoElectronicoRepository;
use App\Repositories\FacturacionRepository;

/**
 * Generación del XML y firma del comprobante: borrador_local → generado_xml → firmado.
 * Corrección: rechazado → generado_xml (se regenera con los datos corregidos y se vuelve a firmar).
 */
final class DocumentoElectronicoService
{
    public function __construct(
        private DocumentoElectronicoRepository $repo = new DocumentoElectronicoRepository(),
        private FacturacionRepository $comprobantes = new FacturacionRepository(),
    ) {
    }

    public function generarXml(int $id): string
    {
        $comprobante = $this->comprobantes->comprobante($id) ?? throw new \RuntimeException('No se encontró el comprobante.');
        if ($comprobante['estado'] !== 'borrador_local') {
            throw new \RuntimeException('Solo se genera el XML de comprobantes en estado «Borrador».');
        }

        [$nombre, $xml] = $this->construirXml($comprobante, self::datosEmision($comprobante));
        $this->repo->guardarGenerado($id, $nombre, $xml, "XML UBL 2.1 generado y validado ({$this->alcanceValidacion()}): {$nombre}.");

        return $nombre;
    }

    /**
     * Corrige un comprobante rechazado: vuelve a tomar los datos del emisor y del cliente
     * (ya corregidos en sus módulos de origen), regenera el XML con el mismo número y lo deja listo para firmar.
     * El XML rechazado se conserva en el historial. Los importes y las líneas no cambian.
     */
    public function corregirYRegenerar(int $id, string $motivo): string
    {
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 500) {
            throw new \InvalidArgumentException('Describe qué se corrigió (máximo 500 caracteres).');
        }
        $comprobante = $this->comprobantes->comprobante($id) ?? throw new \RuntimeException('No se encontró el comprobante.');
        if ($comprobante['estado'] !== 'rechazado') {
            throw new \RuntimeException('Solo se corrige el XML de comprobantes rechazados.');
        }

        // La tasa se mantiene: los importes del comprobante se calcularon con ella
        $datos = $this->comprobantes->datosEmisionActuales((int) $comprobante['cliente_id'], isset($comprobante['venta_erp_id']) ? (int) $comprobante['venta_erp_id'] : null)
            + ['tasa_igv' => self::datosEmision($comprobante)['tasa_igv']];
        [$nombre, $xml] = $this->construirXml($comprobante, $datos);
        $this->repo->guardarCorreccion($id, $nombre, $xml, $datos, $motivo,
            "Corrección tras rechazo: {$motivo}. XML regenerado y validado ({$this->alcanceValidacion()}): {$nombre}. El XML anterior quedó en el historial.");

        return $nombre;
    }

    public function firmar(int $id): string
    {
        $documento = $this->repo->xml($id) ?? throw new \RuntimeException('Primero genera el XML del comprobante.');
        if ($documento['hash'] !== null) {
            throw new \RuntimeException('El XML ya está firmado.');
        }
        $comprobante = $this->comprobantes->comprobante($id) ?? throw new \RuntimeException('No se encontró el comprobante.');

        $firma = new FirmaXmlService();
        $certificado = self::rutaCertificado();
        $firmado = $firma->firmar($documento['xml'], $certificado, (string) Env::get('FIRMA_CLAVE', ''));

        // Verificación automática: si la firma o el contenido no cuadran, no se guarda como firmado
        if (!$firma->verificar($firmado['xml'])) {
            throw new \RuntimeException('La verificación de la firma falló; el XML no se marcó como firmado.');
        }
        $datos = self::datosEmision($comprobante);
        $errores = (new ValidadorXmlService())->validar($firmado['xml'], self::conDatosDeEmision($comprobante, $datos), $datos['emisor']);
        if ($errores) {
            throw new \RuntimeException('El XML firmado no pasó la validación: ' . implode(' ', array_slice($errores, 0, 5)));
        }

        $this->repo->guardarFirmado($id, $firmado['xml'], $firmado['hash'],
            'XML firmado con el certificado «' . basename($certificado) . "» y verificado (firma y contenido correctos). Hash: {$firmado['hash']}");

        return $firmado['hash'];
    }

    /** XML anteriores del comprobante (reemplazados por correcciones) */
    public function versionesAnteriores(int $id): array
    {
        return $this->repo->historial($id);
    }

    /** Genera el XML con los datos de emisión y lo valida; si hay errores no devuelve nada que guardar. */
    private function construirXml(array $comprobante, array $datos): array
    {
        $comprobante = self::conDatosDeEmision($comprobante, $datos);
        $nombre = XmlComprobanteService::nombreArchivo($datos['emisor'], $comprobante);
        $xml = (new XmlComprobanteService())->generar($comprobante, $datos['emisor'], (float) $datos['tasa_igv']);

        $errores = (new ValidadorXmlService())->validar($xml, $comprobante, $datos['emisor']);
        if ($errores) {
            throw new \RuntimeException('El XML no pasó la validación y no se guardó: ' . implode(' ', array_slice($errores, 0, 5)));
        }

        return [$nombre, $xml];
    }

    /** Datos de emisor, cliente y tasa guardados al emitir el comprobante */
    public static function datosEmision(array $comprobante): array
    {
        $datos = $comprobante['datos_emision'] ?? null;
        if (!is_array($datos) || !isset($datos['emisor'], $datos['cliente'], $datos['tasa_igv'])) {
            throw new \RuntimeException('El comprobante no tiene sus datos de emisión: revisa que la base esté completa: importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).');
        }

        return $datos;
    }

    /** El cliente del XML es el de la emisión, no el que figure hoy en el maestro de clientes */
    public static function conDatosDeEmision(array $comprobante, array $datos): array
    {
        $comprobante['cliente'] = $datos['cliente']['nombre'];
        $comprobante['tipo_documento'] = $datos['cliente']['tipo_documento'];
        $comprobante['numero_documento'] = $datos['cliente']['numero_documento'];

        return $comprobante;
    }

    private function alcanceValidacion(): string
    {
        return ValidadorXmlService::hayEsquemas() ? 'estructura, esquema XSD y datos' : 'estructura y datos';
    }

    /** @return array{nombre: string, xml: string} */
    public function descargar(int $id): array
    {
        $documento = $this->repo->xml($id) ?? throw new \RuntimeException('El comprobante todavía no tiene XML.');

        return ['nombre' => $documento['nombre_archivo'], 'xml' => $documento['xml']];
    }

    /**
     * Entrega al envío y CDR: el XML firmado que se debe enviar a SUNAT.
     * Solo responde si el comprobante está en estado «firmado».
     *
     * @return array{nombre: string, xml: string, hash: string}
     */
    public function xmlFirmadoParaEnvio(int $id): array
    {
        $comprobante = $this->comprobantes->comprobante($id) ?? throw new \RuntimeException('No se encontró el comprobante.');
        if ($comprobante['estado'] !== 'firmado') {
            throw new \RuntimeException('El comprobante no está listo para envío: su estado es «' . estado_comprobante($comprobante['estado']) . '».');
        }
        $documento = $this->repo->xml($id) ?? throw new \RuntimeException('El comprobante no tiene XML firmado.');

        return ['nombre' => $documento['nombre_archivo'], 'xml' => $documento['xml'], 'hash' => $documento['hash']];
    }

    /** FIRMA_CERTIFICADO puede ser relativo a la carpeta del proyecto (también lo usa Parámetros de facturación) */
    public static function rutaCertificado(): string
    {
        $ruta = (string) Env::get('FIRMA_CERTIFICADO', 'storage/certificados/certificado-demo.pfx');

        return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $ruta) ? $ruta : BASE_PATH . '/' . $ruta;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Repositories\AnulacionRepository;
use App\Repositories\FacturacionRepository;

/**
 * XML de anulación de un comprobante aceptado: comunicación de baja (facturas) o resumen diario (boletas).
 * Comprueba las reglas de anulación (EstadoComprobanteService::verificarAnulacion), genera el XML,
 * lo valida, lo firma, verifica la firma y lo deja listo para el envío, que responde con un ticket.
 * El comprobante pasa a «Anulado» recién cuando SUNAT acepte la baja (parte del envío).
 */
final class DocumentoAnulacionService
{
    public const NOMBRES = ['comunicacion_baja' => 'Comunicación de baja', 'resumen_diario' => 'Resumen diario'];

    public function __construct(
        private AnulacionRepository $repo = new AnulacionRepository(),
        private FacturacionRepository $comprobantes = new FacturacionRepository(),
    ) {
    }

    /** @return array{id: int, identificador: string, nombre: string, hash: string, tipo: string} */
    public function generar(int $comprobanteId, string $motivo, ?\DateTimeImmutable $hoy = null): array
    {
        $motivo = trim($motivo);
        $comprobante = $this->comprobantes->comprobante($comprobanteId) ?? throw new \RuntimeException('No se encontró el comprobante.');
        (new EstadoComprobanteService())->verificarAnulacion($comprobanteId, $motivo, $hoy);
        if ($existente = $this->repo->bajaVigente($comprobanteId)) {
            throw new \RuntimeException("El comprobante ya está incluido en {$existente['identificador']}.");
        }

        $datos = DocumentoElectronicoService::datosEmision($comprobante);
        $comprobante = DocumentoElectronicoService::conDatosDeEmision($comprobante, $datos);
        $emisor = $datos['emisor'];
        $tipo = XmlAnulacionService::tipoPara($comprobante);
        $prefijo = XmlAnulacionService::FORMATOS[$tipo][0];
        $generacion = ($hoy ?? new \DateTimeImmutable())->setTime(0, 0);
        $referencia = new \DateTimeImmutable(substr((string) $comprobante['fecha_emision'], 0, 10));
        $items = [['comprobante' => $comprobante, 'motivo' => $motivo]];

        $construir = function (string $identificador) use ($tipo, $generacion, $referencia, $emisor, $items): array {
            $validador = new ValidadorXmlService();
            $xml = (new XmlAnulacionService())->generar($tipo, $identificador, $referencia, $generacion, $emisor, $items);
            $errores = $validador->validarAnulacion($xml, $tipo, $identificador, $referencia, $emisor, $items);
            if ($errores) {
                throw new \RuntimeException('El XML de anulación no pasó la validación: ' . implode(' ', array_slice($errores, 0, 5)));
            }

            $firma = new FirmaXmlService();
            $firmado = $firma->firmar($xml, DocumentoElectronicoService::rutaCertificado(), (string) Env::get('FIRMA_CLAVE', ''));
            $errores = $firma->verificar($firmado['xml'])
                ? $validador->validarAnulacion($firmado['xml'], $tipo, $identificador, $referencia, $emisor, $items)
                : ['La verificación de la firma falló.'];
            if ($errores) {
                throw new \RuntimeException('El XML de anulación firmado no se guardó: ' . implode(' ', array_slice($errores, 0, 5)));
            }

            return ['nombre' => "{$emisor['ruc']}-{$identificador}.xml", 'xml' => $firmado['xml'], 'hash' => $firmado['hash']];
        };

        $detalle = self::NOMBRES[$tipo] . ' {identificador} generada, validada y firmada (motivo: ' . $motivo . '). Pendiente de envío a SUNAT.';
        $baja = $this->repo->guardar($tipo, $prefijo, $generacion, $referencia, [$comprobanteId => $motivo], $construir, $detalle);

        return $baja + ['tipo' => $tipo];
    }

    /** Documento de anulación vigente del comprobante (para mostrarlo en su detalle) */
    public function deComprobante(int $comprobanteId): ?array
    {
        return $this->repo->bajaVigente($comprobanteId);
    }

    /** @return array{nombre: string, xml: string} */
    public function descargar(int $bajaId): array
    {
        $baja = $this->repo->baja($bajaId) ?? throw new \RuntimeException('No se encontró el documento de anulación.');

        return ['nombre' => $baja['nombre_archivo'], 'xml' => $baja['xml']];
    }

    /**
     * Entrega al envío: XML firmado de la anulación y los comprobantes que incluye.
     * SUNAT responde con un ticket; si acepta, esos comprobantes pasan a «Anulado».
     *
     * @return array{tipo: string, identificador: string, nombre: string, xml: string, hash: string, comprobantes: int[]}
     */
    public function xmlParaEnvio(int $bajaId): array
    {
        $baja = $this->repo->baja($bajaId) ?? throw new \RuntimeException('No se encontró el documento de anulación.');
        if ($baja['estado'] !== 'firmado') {
            throw new \RuntimeException("El documento {$baja['identificador']} no está listo para envío: su estado es «{$baja['estado']}».");
        }

        return [
            'tipo'          => $baja['tipo'],
            'identificador' => $baja['identificador'],
            'nombre'        => $baja['nombre_archivo'],
            'xml'           => $baja['xml'],
            'hash'          => $baja['hash'],
            'comprobantes'  => array_map('intval', array_column($baja['items'], 'comprobante_id')),
        ];
    }
}

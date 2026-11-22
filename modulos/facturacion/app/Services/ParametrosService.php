<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Registro;

/**
 * Estado de la configuración del módulo, para la pantalla "Parámetros de facturación".
 * Solo lee: los valores se cambian en el archivo .env (nunca desde la pantalla, por seguridad).
 */
final class ParametrosService
{
    /** Días antes del vencimiento en que el certificado se marca "por vencer" */
    public const DIAS_AVISO_CERTIFICADO = 30;

    /**
     * Qué deja cada migración en la base, para saber si ya se importó
     * (el proyecto no tiene una tabla de control de migraciones).
     * migración => [descripción, tabla, columna o null, condición SQL opcional]
     */
    private const MIGRACIONES = [
        '001_demo_facturacion.sql'            => ['Tablas base de comprobantes', 'facturacion_comprobantes', null],
        '002_catalogo_demo_peru.sql'          => ['Catálogo de productos de prueba', 'demo_productos', null, "codigo = 'PROD-003'"],
        '003_series_correlativos_estados.sql' => ['Estado y descripción de series', 'facturacion_series', 'activo'],
        '004_xml_firma.sql'                   => ['Emisor y XML de comprobantes', 'facturacion_xml', null],
        '005_envio_respuesta_cdr.sql'         => ['Envíos, respuestas y CDR', 'facturacion_envios', null],
        '006_origen_respuesta_envio.sql'      => ['Origen de cada respuesta', 'facturacion_envios', 'origen'],
        '007_datos_emision_correccion_xml.sql' => ['Datos de emisión e historial de XML', 'facturacion_comprobantes', 'datos_emision'],
        '008_cambios_correlativos.sql'        => ['Historial de cambios de correlativos', 'facturacion_series_cambios', null],
        '009_motivos_series_notas.sql'        => ['Motivos de notas y series BC01/BD01', 'facturacion_comprobantes', 'codigo_motivo'],
        '010_anulacion_xml.sql'               => ['XML de anulación (bajas y resúmenes diarios)', 'facturacion_bajas', null],
        '011_metadatos_auditoria_eventos.sql' => ['Metadatos de auditoría en eventos', 'facturacion_eventos', 'metadatos'],
        '012_envio_anulaciones.sql'           => ['Envío y respuesta de anulaciones', 'facturacion_bajas', 'ticket'],
    ];

    public function estado(): array
    {
        return [
            'facturacion' => $this->facturacion(),
            'envio'       => $this->envio(),
            'firma'       => $this->firma(),
            'base'        => $this->base(),
            'registro'    => $this->registro(),
            'sesion'      => config('app.sesion_simulada', []),
        ];
    }

    /** Registro de errores del mes actual (storage/logs): cuántos errores y avisos hubo y cuándo fue el último */
    private function registro(): array
    {
        $archivo = Registro::archivo();
        $texto = is_file($archivo) ? (string) file_get_contents($archivo) : '';
        preg_match_all('/^\[([^\]]+)\] (E-\S+|AVISO)/m', $texto, $m);

        return [
            'archivo'  => str_replace(BASE_PATH . '/', '', $archivo),
            'errores'  => count(array_filter($m[2], static fn (string $tipo): bool => $tipo !== 'AVISO')),
            'avisos'   => count(array_filter($m[2], static fn (string $tipo): bool => $tipo === 'AVISO')),
            'ultimo'   => $m[1] ? end($m[1]) : null,
        ];
    }

    private function facturacion(): array
    {
        return [
            'tasa_igv' => FacturacionService::tasaIgv(),
            'moneda'   => (string) config('sunat.moneda', 'PEN'),
            'entorno'  => (string) Env::get('APP_ENV', 'local'),
            'depurar'  => Env::get('APP_DEBUG') === 'true',
            'tipos'    => config('sunat.tipos_comprobante', []),
            'xsd'      => ValidadorXmlService::hayEsquemas(),
            // Sin la extensión zip no funcionan la exportación a Excel ni el CDR simulado y su descarga
            'zip'      => class_exists(\ZipArchive::class),
        ];
    }

    /** Modo de envío. Del proveedor solo se muestra el dominio; el token nunca se muestra */
    private function envio(): array
    {
        $url = trim((string) Env::get('ENVIO_GATEWAY_URL', ''));

        return [
            'simulado' => GatewayFactory::esDemo(),
            'servidor' => $url !== '' ? (string) parse_url($url, PHP_URL_HOST) : null,
            'token'    => trim((string) Env::get('ENVIO_GATEWAY_TOKEN', '')) !== '',
            'timeout'  => (int) Env::get('ENVIO_GATEWAY_TIMEOUT', '20'),
        ];
    }

    /** Certificado de firma: titular, emisor y vigencia. La contraseña se usa para leerlo, nunca se muestra */
    private function firma(): array
    {
        $ruta = DocumentoElectronicoService::rutaCertificado();
        $datos = ['archivo' => (string) Env::get('FIRMA_CERTIFICADO', ''), 'existe' => is_file($ruta), 'error' => null];
        if (!$datos['existe']) {
            return array_merge($datos, ['error' => 'No se encontró el certificado. Para pruebas: php bin/generar_certificado_demo.php']);
        }
        if (!function_exists('openssl_pkcs12_read')) {
            return array_merge($datos, ['error' => 'La extensión openssl de PHP no está activa.']);
        }

        $certificados = [];
        if (!openssl_pkcs12_read((string) file_get_contents($ruta), $certificados, (string) Env::get('FIRMA_CLAVE', ''))) {
            return array_merge($datos, ['error' => 'No se pudo abrir el certificado: revisa FIRMA_CLAVE en el .env.']);
        }
        $x509 = openssl_x509_parse($certificados['cert']);
        if (!is_array($x509)) {
            return array_merge($datos, ['error' => 'El archivo no contiene un certificado válido.']);
        }

        $hasta = (int) $x509['validTo_time_t'];
        $dias = (int) floor(($hasta - time()) / 86400);

        return array_merge($datos, [
            'titular'    => $x509['subject']['CN'] ?? ($x509['subject']['O'] ?? '—'),
            'emisor'     => $x509['issuer']['CN'] ?? ($x509['issuer']['O'] ?? '—'),
            'desde'      => (int) $x509['validFrom_time_t'],
            'hasta'      => $hasta,
            'dias'       => $dias,
            'vigencia'   => $dias < 0 ? 'vencido' : ($dias <= self::DIAS_AVISO_CERTIFICADO ? 'por_vencer' : 'vigente'),
            // Autofirmado = emitido por sí mismo: es de prueba, SUNAT no lo acepta
            'autofirmado' => ($x509['subject'] ?? null) == ($x509['issuer'] ?? null),
        ]);
    }

    private function base(): array
    {
        $conexion = Database::estado();
        $migraciones = [];
        if ($conexion['ok']) {
            $db = Database::conexion();
            foreach (self::MIGRACIONES as $archivo => [$descripcion, $tabla, $columna]) {
                $condicion = self::MIGRACIONES[$archivo][3] ?? null;
                $q = $db->prepare($columna === null
                    ? 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                    : 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $q->execute($columna === null ? [$tabla] : [$tabla, $columna]);
                $aplicada = (int) $q->fetchColumn() > 0;
                if ($aplicada && $condicion !== null) {
                    $aplicada = (int) $db->query("SELECT COUNT(*) FROM {$tabla} WHERE {$condicion}")->fetchColumn() > 0;
                }
                $migraciones[] = ['archivo' => $archivo, 'descripcion' => $descripcion, 'aplicada' => $aplicada];
            }
        }

        return [
            'ok'          => $conexion['ok'],
            'mensaje'     => $conexion['mensaje'],
            'nombre'      => (string) Env::get('DB_DATABASE', 'erp_pucchun'),
            'servidor'    => (string) Env::get('DB_HOST', '127.0.0.1'),
            'migraciones' => $migraciones,
        ];
    }
}

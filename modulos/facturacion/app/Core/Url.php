<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Arma rutas y enlaces sin importar dónde esté instalado el sistema:
 * en XAMPP vive en /erp-facturacion y en el VPS puede estar en la raíz del sitio.
 */
final class Url
{
    /** Carpeta base en la URL: "/erp-facturacion" en XAMPP, "" si public/ es la raíz del sitio */
    public static function base(): string
    {
        $carpeta = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $carpeta = preg_replace('#/public$#', '', $carpeta);

        return rtrim($carpeta, '/');
    }

    /** Ruta pedida dentro del módulo, sin la carpeta base ni parámetros: "/comprobantes/facturas" */
    public static function rutaActual(): string
    {
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::base();

        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base));
        }
        // Por si se entra directo a /erp-facturacion/public/...
        $ruta = preg_replace('#^/public(?=/|$)#', '', $ruta);

        return '/' . trim(rawurldecode($ruta), '/');
    }

    /** Enlace absoluto a una ruta del módulo */
    public static function a(string $ruta = '/'): string
    {
        return self::base() . '/' . ltrim($ruta, '/');
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de errores en storage/logs/errores-AAAA-MM.log (un archivo por mes; los .log no se suben a Git).
 * Cada error lleva un código de referencia que también ve el usuario, para encontrarlo rápido en el archivo.
 */
final class Registro
{
    /** Guarda el error y devuelve su código de referencia (ej. E-20261022-7F3A1C) */
    public static function error(\Throwable $error, ?string $carpeta = null): string
    {
        $referencia = 'E-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $ruta = $_SERVER['REQUEST_URI'] ?? implode(' ', $_SERVER['argv'] ?? []);

        $linea = sprintf("[%s] %s %s %s\n  %s: %s\n  en %s:%d\n",
            date('Y-m-d H:i:s'), $referencia, $metodo, $ruta,
            get_class($error), $error->getMessage(), self::relativa($error->getFile()), $error->getLine());
        // Las primeras llamadas de la traza bastan para ubicar el origen
        foreach (array_slice($error->getTrace(), 0, 5) as $paso) {
            $linea .= '    · ' . (isset($paso['class']) ? $paso['class'] . $paso['type'] : '') . ($paso['function'] ?? '') . '()'
                . (isset($paso['file']) ? ' ' . self::relativa($paso['file']) . ':' . ($paso['line'] ?? 0) : '') . "\n";
        }

        self::escribir($linea, $carpeta);

        return $referencia;
    }

    /** Advertencias de PHP (variables indefinidas, etc.): se registran sin interrumpir la página */
    public static function advertencia(int $nivel, string $mensaje, string $archivo, int $linea): void
    {
        self::escribir(sprintf("[%s] AVISO %s %s\n  PHP %d: %s\n  en %s:%d\n",
            date('Y-m-d H:i:s'), $_SERVER['REQUEST_METHOD'] ?? 'CLI', $_SERVER['REQUEST_URI'] ?? '', $nivel, $mensaje, self::relativa($archivo), $linea));
    }

    public static function carpeta(): string
    {
        return BASE_PATH . '/storage/logs';
    }

    /** Archivo del mes actual */
    public static function archivo(?string $carpeta = null): string
    {
        return ($carpeta ?? self::carpeta()) . '/errores-' . date('Y-m') . '.log';
    }

    private static function escribir(string $texto, ?string $carpeta = null): void
    {
        $carpeta ??= self::carpeta();
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }
        // Si no se puede escribir, el error igual se informa al usuario: el registro nunca debe romper la página
        @file_put_contents(self::archivo($carpeta), $texto, FILE_APPEND | LOCK_EX);
    }

    private static function relativa(string $ruta): string
    {
        return str_replace([BASE_PATH . DIRECTORY_SEPARATOR, BASE_PATH . '/'], '', $ruta);
    }
}

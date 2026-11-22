<?php

declare(strict_types=1);

namespace App\Core;

/** Lee el archivo .env (configuración local: base de datos, modo debug) */
final class Env
{
    public static function cargar(string $archivo): void
    {
        if (!is_file($archivo)) {
            return;
        }

        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
            $_ENV[$clave] = trim($valor, "\"'");
        }
    }

    public static function get(string $clave, ?string $defecto = null): ?string
    {
        return $_ENV[$clave] ?? $defecto;
    }
}

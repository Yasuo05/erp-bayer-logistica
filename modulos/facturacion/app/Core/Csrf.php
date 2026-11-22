<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF: cada formulario POST lleva un código secreto de la sesión.
 * Así otra página web no puede hacer que el navegador del usuario envíe un formulario
 * al módulo (por ejemplo, anular o enviar un comprobante) sin que él lo sepa.
 */
final class Csrf
{
    public const CAMPO = '_csrf';

    /** Código de la sesión actual; se crea la primera vez que se necesita */
    public static function token(): string
    {
        if (empty($_SESSION[self::CAMPO]) || !is_string($_SESSION[self::CAMPO])) {
            $_SESSION[self::CAMPO] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CAMPO];
    }

    public static function valido(mixed $enviado): bool
    {
        return is_string($enviado) && is_string($_SESSION[self::CAMPO] ?? null)
            && hash_equals($_SESSION[self::CAMPO], $enviado);
    }

    /** Campo oculto con el código, para un formulario */
    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::CAMPO . '" value="' . self::token() . '">';
    }

    /**
     * Agrega el campo a todos los formularios POST del HTML (lo usa View al pintar cada página),
     * de modo que ningún formulario quede sin protección, incluidos los que se agreguen después.
     */
    public static function agregarAFormularios(string $html): string
    {
        return (string) preg_replace_callback(
            '/<form\b[^>]*\bmethod\s*=\s*(["\'])post\1[^>]*>/i',
            static fn (array $m): string => $m[0] . self::campo(),
            $html
        );
    }
}

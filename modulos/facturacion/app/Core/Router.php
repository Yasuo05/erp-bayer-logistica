<?php

declare(strict_types=1);

namespace App\Core;

/** Decide qué controlador atiende cada URL (el mapa está en config/rutas.php) */
final class Router
{
    /** @param array<string, array{0: class-string, 1: string}> $rutas ruta => [Controlador, método] */
    public function __construct(private array $rutas)
    {
    }

    public function despachar(string $ruta): void
    {
        if (!isset($this->rutas[$ruta])) {
            self::noEncontrada();
            return;
        }

        [$controlador, $metodo] = $this->rutas[$ruta];
        (new $controlador())->$metodo();
    }

    public static function noEncontrada(): void
    {
        http_response_code(404);
        View::render('errores/404', ['titulo' => 'Página no encontrada']);
    }
}

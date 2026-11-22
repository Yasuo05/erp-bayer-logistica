<?php

declare(strict_types=1);

namespace App\Core;

/** Acceso al menú del módulo (definido en config/menu.php) */
final class Menu
{
    public static function secciones(): array
    {
        return config('menu');
    }

    /** Opción del menú de una ruta, junto con el nombre de su sección */
    public static function buscar(string $ruta): ?array
    {
        foreach (self::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                if ($item['ruta'] === $ruta) {
                    return $item + ['seccion' => $seccion['titulo']];
                }
            }
        }

        return null;
    }
}

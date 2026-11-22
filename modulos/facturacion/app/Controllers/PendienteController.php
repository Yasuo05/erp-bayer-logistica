<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Menu;
use App\Core\Router;
use App\Core\Url;
use App\Core\View;

/** Página de una sección del menú que todavía no tiene funcionalidad */
final class PendienteController
{
    public function mostrar(): void
    {
        $item = Menu::buscar(Url::rutaActual());

        if ($item === null) {
            Router::noEncontrada();
            return;
        }

        View::render('pendiente', ['titulo' => $item['titulo'], 'item' => $item]);
    }
}

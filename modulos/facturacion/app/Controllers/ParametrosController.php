<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\ParametrosService;

/** Parámetros de facturación: estado de la configuración (solo lectura) */
final class ParametrosController
{
    public function index(): void
    {
        View::render('configuracion/parametros', [
            'titulo'     => 'Parámetros de facturación',
            'parametros' => (new ParametrosService())->estado(),
        ]);
    }
}

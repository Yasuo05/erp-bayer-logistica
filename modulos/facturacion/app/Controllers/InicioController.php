<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\EnvioService;

final class InicioController
{
    public function index(): void
    {
        View::render('inicio', [
            'titulo'  => 'Inicio',
            'resumen' => $this->resumen(),
            'entorno' => [
                'php'   => PHP_VERSION,
                'mysql' => Database::estado(),
            ],
        ]);
    }

    /** Conteos para el encabezado; null si la base aún no está lista (el inicio se muestra igual) */
    private function resumen(): ?array
    {
        try {
            $conteos = (new EnvioService())->resumen();
            $conteos['total'] = (int) Database::conexion()->query('SELECT COUNT(*) FROM facturacion_comprobantes')->fetchColumn();

            return $conteos;
        } catch (\Throwable) {
            return null;
        }
    }
}

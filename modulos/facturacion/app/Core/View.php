<?php

declare(strict_types=1);

namespace App\Core;

/** Capa de presentación: pinta una vista de app/Views dentro del layout (menú + contenido) */
final class View
{
    public static function render(string $vista, array $datos = []): void
    {
        $datos += ['titulo' => config('app.nombre')];
        extract($datos, EXTR_SKIP);

        ob_start();
        require BASE_PATH . "/app/Views/{$vista}.php";
        // Todo formulario POST sale con el código CSRF de la sesión (ver Csrf)
        $contenido = Csrf::agregarAFormularios((string) ob_get_clean());

        // Datos que usa el layout en todas las páginas
        $menu       = Menu::secciones();
        $rutaActual = Url::rutaActual();
        // Usuario y rol reales de la sesión del ERP (G4). Aún no hay sede activa por usuario: se indica «Todas las sedes».
        $sesion     = config('app.sesion_simulada');
        $contexto   = $_SESSION['erp_context'] ?? null;
        if (is_array($contexto) && is_array($contexto['usuario'] ?? null)) {
            $sesion = [
                'usuario' => (string) ($contexto['usuario']['nombre'] ?? $sesion['usuario']),
                'rol'     => ucfirst((string) ($contexto['usuario']['rol'] ?? $sesion['rol'])),
                'sede'    => (string) ($contexto['sede']['nombre'] ?? 'Todas las sedes'),
            ];
        }

        require BASE_PATH . '/app/Views/layouts/app.php';
    }
}

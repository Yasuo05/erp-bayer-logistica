<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\SeriesService;

final class SeriesController
{
    public function index(): void
    {
        $servicio = new SeriesService();
        try {
            $series = $servicio->listar();
            $error = null;
        } catch (\Throwable $e) {
            $series = [];
            $error = mensaje_error($e);
        }
        try {
            $cambios = $servicio->cambios();
            $errorCambios = null;
        } catch (\Throwable $e) {
            $cambios = [];
            $errorCambios = mensaje_error($e);
        }

        View::render('configuracion/series', [
            'titulo' => 'Series y correlativos',
            'series' => $series,
            'error' => $error,
            'cambios' => $cambios,
            'errorCambios' => $errorCambios,
            'flash' => $_SESSION['flash'] ?? null,
            'flashError' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash'], $_SESSION['flash_error']);
    }

    public function crear(): void
    {
        $this->soloPost();
        try {
            (new SeriesService())->crear($_POST);
            $_SESSION['flash'] = 'Serie creada correctamente.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = mensaje_error($e);
        }
        $this->volver();
    }

    public function actualizar(): void
    {
        $this->soloPost();
        try {
            (new SeriesService())->actualizar((int) ($_POST['id'] ?? 0), $_POST);
            $_SESSION['flash'] = 'Serie actualizada correctamente.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = mensaje_error($e);
        }
        $this->volver();
    }

    public function cambiarEstado(): void
    {
        $this->soloPost();
        try {
            $activo = (string) ($_POST['activo'] ?? '') === '1';
            (new SeriesService())->cambiarActivo((int) ($_POST['id'] ?? 0), $activo);
            $_SESSION['flash'] = $activo ? 'Serie activada correctamente.' : 'Serie desactivada correctamente.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = mensaje_error($e);
        }
        $this->volver();
    }

    private function soloPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->volver();
        }
    }

    private function volver(): never
    {
        header('Location: ' . url('/configuracion/series'));
        exit;
    }
}


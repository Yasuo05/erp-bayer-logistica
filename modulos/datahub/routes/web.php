<?php
use App\Controllers\{AuthController, DashboardController, DocumentController, GuideController, StockController, ExportController, ApiController};

// Autenticación
$router->get('/', fn() => auth_user() ? redirect('/dashboard') : redirect('/login'));
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Dashboard centralizado
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/bayer', fn() => redirect('/dashboard'));
$router->get('/bayer', fn() => redirect('/dashboard'));

// Módulos operativos en modo Historial y Auditoría (Solo Lectura)
$router->get('/documentos', [DocumentController::class, 'index']);
$router->get('/guias', [GuideController::class, 'index']);
$router->get('/stock', [StockController::class, 'index']);

// Blindaje: bloqueo de formularios y operaciones de escritura (403 Modo Auditoría)
$abortReadonly = function(): void {
    http_response_code(403);
    view('errors.403');
    exit;
};

// Endpoints residuales de creación/edición deshabilitados
$router->get('/documentos/nuevo', $abortReadonly);
$router->post('/documentos/guardar', $abortReadonly);
$router->post('/documentos/estado', $abortReadonly);

$router->get('/guias/nuevo', $abortReadonly);
$router->post('/guias/guardar', $abortReadonly);
$router->post('/guias/estado', $abortReadonly);

$router->get('/stock/nuevo', $abortReadonly);
$router->post('/stock/guardar', $abortReadonly);
$router->post('/stock/estado', $abortReadonly);

// Exportación y API de integración (Solo Lectura)
$router->get('/export', [ExportController::class, 'export']);
$router->get('/api/v1/bayer/sales', [ApiController::class, 'sales']);
$router->get('/api/v1/bayer/shipments', [ApiController::class, 'shipments']);
$router->get('/api/v1/bayer/inventory', [ApiController::class, 'inventory']);

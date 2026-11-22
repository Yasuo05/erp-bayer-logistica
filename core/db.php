<?php
$env = parse_ini_file(__DIR__ . '/../.env');

// Detección automática e inteligente de BASE_URL (evita errores 404 en subcarpetas, puertos o raíz)
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Si estamos dentro de un submódulo (ej. /erp_maestros/modulos/catalogo), recortamos hasta la raíz del proyecto
    if (str_contains($scriptDir, '/modulos/')) {
        $baseUrl = substr($scriptDir, 0, strpos($scriptDir, '/modulos/'));
    } elseif ($scriptDir === '/' || $scriptDir === '.') {
        $baseUrl = '';
    } else {
        $baseUrl = $scriptDir;
    }
    // Si se definió explícitamente en .env y no está vacío, lo usamos; de lo contrario usamos la autodetectada
    $urlConfig = trim($env['APP_URL'] ?? '');
    $finalUrl = ($urlConfig !== '' && $urlConfig !== '/') ? $urlConfig : $baseUrl;
    define('BASE_URL', rtrim($finalUrl, '/'));
}

try {
    $pdo = new PDO(
        
"mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    exit('Error de conexión a la base de datos.');
}

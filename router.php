<?php
declare(strict_types=1);

/**
 * Router unificado para ejecutar todo el ERP con:
 *   php -S localhost:8080 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$docRoot = __DIR__;
// El servidor integrado no interpreta .htaccess. Proteger configuración y archivos de trabajo.
if (preg_match('#(?:^|/)(?:\.[^/]+|sql|scripts|tests|docs|core)(?:/|$)#i', rawurldecode($uri))
    || preg_match('#\.(?:sql|log|md|zip|lock|ini|ps1)$#i', $uri)) {
    http_response_code(404);
    return true;
}
$filePath = $docRoot . str_replace('/', DIRECTORY_SEPARATOR, $uri);

// 1. Servir archivos estáticos existentes directamente (CSS, JS, imágenes, PHP directos)
if ($uri !== '/' && is_file($filePath)) {
    return false;
}

// 2. Despachar módulo de Facturación Electrónica (MVC)
if (str_starts_with($uri, '/modulos/facturacion')) {
    $factPublicDir = $docRoot . DIRECTORY_SEPARATOR . 'modulos' . DIRECTORY_SEPARATOR . 'facturacion' . DIRECTORY_SEPARATOR . 'public';
    $subPath = substr($uri, strlen('/modulos/facturacion'));
    $subPathWithoutPublic = str_starts_with($subPath, '/public') ? substr($subPath, strlen('/public')) : $subPath;
    $staticCandidate = $factPublicDir . str_replace('/', DIRECTORY_SEPARATOR, $subPathWithoutPublic);

    if ($subPathWithoutPublic !== '' && $subPathWithoutPublic !== '/' && is_file($staticCandidate)) {
        $ext = strtolower(pathinfo($staticCandidate, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css; charset=utf-8',
            'js'  => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'json' => 'application/json',
            default => mime_content_type($staticCandidate) ?: 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($staticCandidate);
        return true;
    }

    $_SERVER['SCRIPT_NAME'] = '/modulos/facturacion/public/index.php';
    $_SERVER['PHP_SELF']    = '/modulos/facturacion/public/index.php';
    chdir($factPublicDir);
    require $factPublicDir . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

// 3. Despachar módulo DataHub Bayer (MVC)
if (str_starts_with($uri, '/modulos/datahub')) {
    $dhPublicDir = $docRoot . DIRECTORY_SEPARATOR . 'modulos' . DIRECTORY_SEPARATOR . 'datahub' . DIRECTORY_SEPARATOR . 'public';
    $subPath = substr($uri, strlen('/modulos/datahub'));
    $subPathWithoutPublic = str_starts_with($subPath, '/public') ? substr($subPath, strlen('/public')) : $subPath;
    $staticCandidate = $dhPublicDir . str_replace('/', DIRECTORY_SEPARATOR, $subPathWithoutPublic);

    if ($subPathWithoutPublic !== '' && $subPathWithoutPublic !== '/' && is_file($staticCandidate)) {
        $ext = strtolower(pathinfo($staticCandidate, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css; charset=utf-8',
            'js'  => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'json' => 'application/json',
            default => mime_content_type($staticCandidate) ?: 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($staticCandidate);
        return true;
    }

    $_SERVER['SCRIPT_NAME'] = '/modulos/datahub/public/index.php';
    $_SERVER['PHP_SELF']    = '/modulos/datahub/public/index.php';
    chdir($dhPublicDir);
    require $dhPublicDir . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

// 4. Por defecto, dejar que el servidor integrado sirva el archivo o index.php
return false;

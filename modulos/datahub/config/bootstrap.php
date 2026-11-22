<?php
require_once dirname(__DIR__).'/app/Helpers/functions.php';
load_env(dirname(__DIR__).'/.env');
$app=config('app');
date_default_timezone_set($app['timezone']);
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name($app['session_name']);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
    session_start();
}
ini_set('log_errors','1');
ini_set('error_log', dirname(__DIR__).'/storage/logs/php_errors.log');
if ($app['debug']) { ini_set('display_errors','1'); error_reporting(E_ALL); }
else { ini_set('display_errors','0'); error_reporting(E_ALL); }
spl_autoload_register(function($class){$prefix='App\\';if(str_starts_with($class,$prefix)){ $rel=str_replace('\\','/',substr($class,strlen($prefix))); $f=dirname(__DIR__).'/app/'.$rel.'.php'; if(is_file($f)) require $f; }});

// Manejo centralizado de excepciones no capturadas: registra y muestra una página 500 amigable.
if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (Throwable $e) use ($app) {
        log_event('uncaught_exception', ['e' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine(), 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
        if (!headers_sent()) http_response_code(500);
        $msg = $app['debug'] ? $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine() : 'Ocurrió un error inesperado. El incidente fue registrado.';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); return; }
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Error</title><style>body{font-family:system-ui;background:#f3f6f9;color:#0b2e4f;display:grid;place-items:center;height:100vh;margin:0}.b{background:#fff;padding:2rem 2.5rem;border-radius:16px;box-shadow:0 10px 30px rgba(11,46,79,.1);max-width:560px}h1{color:#006699;margin-top:0}a{color:#006699}</style></head><body><div class="b"><h1>Error 500</h1><p>'.e($msg).'</p><a href="'.e(url('/dashboard')).'">Volver al inicio</a></div></body></html>';
    });
}

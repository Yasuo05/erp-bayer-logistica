<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}require_once __DIR__ . '/db.php';

function usuario_actual() { return $_SESSION['usuario'] ?? null; }

function requerir_login() {
    if (!usuario_actual()) { header('Location: ' . BASE_URL . '/login.php'); exit; }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_validar() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Sesión expirada.'); }
}

require_once __DIR__ . '/sede.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/parametros.php';
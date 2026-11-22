<?php
// Cambia la sede activa del usuario (selector de la cabecera). Solo acepta sedes permitidas.
require_once __DIR__ . '/core/auth.php';
requerir_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . BASE_URL . '/index.php'); exit; }
csrf_validar();
$valor = $_POST['sede'] ?? '';
if ($valor === '' && !sede_restringida()) {
    sede_fijar(null);
} elseif (ctype_digit((string)$valor) && isset(sedes_permitidas()[(int)$valor])) {
    sede_fijar((int)$valor);
}
// Vuelve a la página desde donde se cambió (solo rutas internas del ERP)
$volver = (string)($_POST['volver'] ?? '');
header('Location: ' . (str_starts_with($volver, '/') && !str_starts_with($volver, '//') ? $volver : BASE_URL . '/index.php'));
exit;

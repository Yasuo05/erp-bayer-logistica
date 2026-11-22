<?php
require_once __DIR__ . '/../../core/auth.php';
requerir_login();
// Los enlaces antiguos conservan su fragmento hasta que la página de destino lo resuelve.
header('Location: ' . BASE_URL . '/modulos/logistica/envios.php', true, 302);
exit;
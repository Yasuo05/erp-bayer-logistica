<?php
/**
 * Punto de entrada único: todas las URLs del módulo pasan por aquí (ver .htaccess).
 * Solo la carpeta public/ es visible desde el navegador.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (!is_file(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Faltan las dependencias: ejecuta "composer install" en la carpeta del proyecto.');
}

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Registro;
use App\Core\Router;
use App\Core\Url;
use App\Core\View;

Env::cargar(BASE_PATH . '/.env');
// Cookie de sesión no accesible desde JavaScript y que el navegador no envía en formularios de otros sitios
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
date_default_timezone_set('America/Lima');

// En local se muestran los errores de PHP; en producción nunca (APP_DEBUG=false)
$depurar = Env::get('APP_DEBUG') === 'true';
ini_set('display_errors', $depurar ? '1' : '0');
error_reporting(E_ALL);

// Errores inesperados: se guardan en storage/logs con un código y el usuario ve una página amable con ese código
set_exception_handler(static function (\Throwable $e) use ($depurar): void {
    $referencia = Registro::error($e);
    // Se descarta lo que la página alcanzó a armar antes del error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    try {
        View::render('errores/500', ['titulo' => 'Error inesperado', 'referencia' => $referencia, 'detalle' => $depurar ? $e->getMessage() : null]);
    } catch (\Throwable) {
        echo 'Ocurrió un error inesperado. Código: ' . htmlspecialchars($referencia);
    }
});
// Advertencias de PHP: se registran y la página continúa (las silenciadas con @ se ignoran)
set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if (error_reporting() & $nivel) {
        Registro::advertencia($nivel, $mensaje, $archivo, $linea);
    }
    return false;
});

// Acceso (contrato con el Grupo 4): Facturación funciona dentro de la sesión del ERP y con su matriz de permisos.
// Sin sesión se envía al login del ERP; consultar requiere «ver» y toda operación (POST) requiere «crear» o «editar».
require_once dirname(BASE_PATH, 2) . '/core/permisos.php';
$pdo = \App\Core\Database::conexion(); // la matriz de permisos se lee de la base del ERP (G4)
$usuarioErp = $_SESSION['usuario'] ?? null;
if (!is_array($usuarioErp)) {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseErp = str_contains($script, '/modulos/') ? substr($script, 0, strpos($script, '/modulos/')) : '';
    header('Location: ' . $baseErp . '/login.php');
    exit;
}
if (!puede('ver') || ($_SERVER['REQUEST_METHOD'] === 'POST' && !puede('crear') && !puede('editar'))) {
    http_response_code(403);
    View::render('errores/403', ['titulo' => 'Sin permiso', 'mensaje' => 'Tu rol (' . ($usuarioErp['rol'] ?? '—') . ') solo permite consultar Facturación.']);
    exit;
}
// Contexto de usuario para la auditoría de eventos tributarios (lo define el login del ERP; se completa si falta)
if (!is_array($_SESSION['erp_context'] ?? null)) {
    $_SESSION['erp_context'] = ['usuario' => ['id' => $usuarioErp['id'] ?? null, 'nombre' => $usuarioErp['nombre'] ?? null, 'rol' => $usuarioErp['rol'] ?? null], 'sede' => null];
}

// Sede activa (G4): un usuario con sede activa no puede ver ni operar ventas o comprobantes de otra sede, ni por id directo
$sedeActiva = sede_activa_facturacion();
if ($sedeActiva !== null) {
    $ruta = Url::rutaActual();
    $db = \App\Core\Database::conexion();
    $ajena = false;
    $ventaId = (int) ($_REQUEST['venta_id'] ?? 0);
    if ($ventaId > 0 && str_starts_with($ruta, '/emision')) {
        $q = $db->prepare('SELECT sucursal_id FROM ventas WHERE id = ?');
        $q->execute([$ventaId]);
        $ajena = (int) $q->fetchColumn() !== $sedeActiva;
    }
    foreach (['id', 'referencia_id'] as $campo) {
        $id = (int) ($_REQUEST[$campo] ?? 0);
        if ($id > 0 && preg_match('#^/(comprobantes|envio|consultas)#', $ruta) && !str_starts_with($ruta, '/envio/cdr')) {
            $ajena = $ajena || sede_de_comprobante($db, $id) !== $sedeActiva;
        }
    }
    if ($ajena) {
        http_response_code(403);
        View::render('errores/403', ['titulo' => 'Sin permiso', 'mensaje' => 'El documento pertenece a otra sede. Cambia la sede activa en el ERP para trabajar con él.']);
        exit;
    }
}

// Todo formulario POST debe traer el código CSRF de la sesión; si no, no se procesa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::valido($_POST[Csrf::CAMPO] ?? null)) {
    http_response_code(403);
    View::render('errores/formulario', ['titulo' => 'Formulario no válido']);
    exit;
}

(new Router(require BASE_PATH . '/config/rutas.php'))->despachar(Url::rutaActual());

<?php

declare(strict_types=1);

use App\Core\Url;

/** Escapa texto antes de mostrarlo en HTML (evita inyección de código) */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Enlace a una ruta del módulo: url('/comprobantes/facturas') */
function url(string $ruta = '/'): string
{
    return Url::a($ruta);
}

/** Enlace a un archivo de public/assets: asset('css/app.css') */
function asset(string $archivo): string
{
    return Url::a('assets/' . ltrim($archivo, '/'));
}

/** Etiquetas legibles de estados internos de los comprobantes. */
function estado_comprobante(string $estado): string
{
    $etiquetas = config('estados.etiquetas', []);
    return $etiquetas[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

/** Clase visual según la familia del estado del comprobante. */
function clase_estado_comprobante(string $estado): string
{
    return match ($estado) {
        'aceptado' => 'badge-ok',
        'rechazado', 'anulado', 'anulado_local' => 'badge-error',
        'generado_xml', 'firmado', 'enviado' => 'badge-info',
        'observado', 'pendiente_reintento', 'borrador_local' => 'badge-warn',
        default => 'badge-muted',
    };
}

/** Valor de config/*.php con puntos: config('app.nombre') lee config/app.php['nombre'] */
function config(string $clave, mixed $defecto = null): mixed
{
    static $cargados = [];

    $partes  = explode('.', $clave);
    $archivo = array_shift($partes);
    $cargados[$archivo] ??= require BASE_PATH . "/config/{$archivo}.php";

    $valor = $cargados[$archivo];
    foreach ($partes as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $defecto;
        }
        $valor = $valor[$parte];
    }

    return $valor;
}

/** Ícono SVG en línea por nombre (lista en app/Views/iconos.php) */
function icono(string $nombre, int $tamano = 18): string
{
    static $iconos = null;
    $iconos ??= require BASE_PATH . '/app/Views/iconos.php';

    return sprintf(
        '<svg class="icono" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
        $tamano,
        $iconos[$nombre] ?? $iconos['info']
    );
}

/**
 * Mensaje para mostrar al usuario cuando un controlador atrapa un error (usar en lugar de $e->getMessage()).
 *  - InvalidArgumentException y ComprobanteNoEncontrado: son validaciones, su mensaje ya está escrito para el usuario.
 *    Se muestra tal cual y no se registra.
 *  - Errores de base de datos (PDOException, texto con SQLSTATE) y errores internos de PHP (TypeError, etc.):
 *    se guardan en storage/logs y el usuario solo ve un mensaje general con el código de referencia,
 *    nunca el texto técnico de MySQL (nombres de tablas, consultas).
 *  - Cualquier otro error: se guarda en storage/logs y se muestra su mensaje junto con el código de referencia.
 *
 * @param string|null $carpeta carpeta del registro (solo las pruebas la cambian)
 */
function mensaje_error(\Throwable $e, ?string $carpeta = null): string
{
    if ($e instanceof \InvalidArgumentException || $e instanceof \App\Services\ComprobanteNoEncontrado) {
        return $e->getMessage();
    }

    $referencia = \App\Core\Registro::error($e, $carpeta);
    if ($e instanceof \PDOException || str_contains($e->getMessage(), 'SQLSTATE')) {
        return 'Error en la base de datos. Código de referencia: ' . $referencia;
    }
    if (!($e instanceof \Exception)) { // error interno de PHP (TypeError, etc.)
        return 'Error inesperado. Código de referencia: ' . $referencia;
    }

    return $e->getMessage() . ' (código ' . $referencia . ')';
}
/** Sede activa del ERP (G4) o null si el usuario trabaja con todas las sedes. */
function sede_activa_facturacion(): ?int
{
    $sede = $_SESSION['erp_context']['sede']['id'] ?? null;
    return $sede !== null ? (int) $sede : null;
}

/**
 * Condición SQL « AND …» que limita los comprobantes (alias $f) a la sede activa. La sede de un comprobante es la
 * de su venta; la de una nota, la de la venta de su comprobante de referencia. Comprobantes sin venta (demostración
 * antigua) solo se ven con «Todas las sedes».
 */
function sql_sede_comprobante(string $f = 'f'): string
{
    $sede = sede_activa_facturacion();
    if ($sede === null) return '';
    return " AND (SELECT vs.sucursal_id FROM ventas vs WHERE vs.id = COALESCE($f.venta_erp_id,"
        . " (SELECT rs.venta_erp_id FROM facturacion_comprobantes rs WHERE rs.id = $f.comprobante_referenciado_id))) = $sede";
}

/** Sede (sucursal) de un comprobante, o null si no viene de una venta del ERP. */
function sede_de_comprobante(\PDO $db, int $id): ?int
{
    $q = $db->prepare('SELECT v.sucursal_id FROM facturacion_comprobantes f LEFT JOIN facturacion_comprobantes r ON r.id = f.comprobante_referenciado_id
        JOIN ventas v ON v.id = COALESCE(f.venta_erp_id, r.venta_erp_id) WHERE f.id = ?');
    $q->execute([$id]);
    $s = $q->fetchColumn();
    return $s === false ? null : (int) $s;
}

<?php
/**
 * Parámetros corporativos (G4 · Inf. 4 «Administración y configuración: parámetros e impuestos»).
 * Un solo lugar para valores que comparten los módulos; se editan en Administración › Parámetros.
 */
function parametro(string $clave, ?string $porDefecto = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            try { $cache = $pdo->query('SELECT clave, valor FROM parametros')->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $e) { $cache = []; }
        }
    }
    return $cache[$clave] ?? $porDefecto;
}

/** Tasa de IGV en porcentaje (p. ej. 18.0). */
function igv_porcentaje(): float {
    return (float)parametro('igv_tasa', '18');
}

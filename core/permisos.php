<?php
/**
 * Permisos por rol (G4). La matriz vive en la base (roles × permisos en rol_permiso) y se administra en
 * Administración › Roles y permisos. Esta constante solo se usa como respaldo si las tablas están vacías
 * o la base no está disponible (instalación nueva). Bayer no entra al CRUD: solo consulta lo publicado.
 */
const PERMISOS = [
    'administrador' => ['ver', 'crear', 'editar', 'eliminar'],
    'digitador'     => ['ver', 'crear', 'editar'],
    'supervisor'    => ['ver', 'editar'],
    'gerencia'      => ['ver'],
];

/** Permisos de un rol según la base; null si la matriz no está cargada. Se lee una vez por solicitud. */
function permisos_de_rol(string $rol): ?array {
    static $matriz = false;
    if ($matriz === false) {
        $matriz = null;
        $pdo = $GLOBALS['pdo'] ?? null;
        if ($pdo instanceof PDO) {
            try {
                $filas = $pdo->query('SELECT LOWER(r.nombre) rol, p.nombre permiso FROM rol_permiso rp JOIN roles r ON r.id = rp.rol_id JOIN permisos p ON p.id = rp.permiso_id')->fetchAll(PDO::FETCH_ASSOC);
                if ($filas) {
                    $matriz = [];
                    foreach ($filas as $f) $matriz[$f['rol']][] = $f['permiso'];
                }
            } catch (Throwable $e) {
                $matriz = null;
            }
        }
    }
    if ($matriz === null) return null;
    return $matriz[$rol] ?? [];
}

function puede(string $accion): bool {
    $rol = $_SESSION['usuario']['rol'] ?? '';
    $lista = permisos_de_rol($rol) ?? (PERMISOS[$rol] ?? []);
    return in_array($accion, $lista, true);
}

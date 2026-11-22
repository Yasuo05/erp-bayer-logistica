<?php
/**
 * Sede activa por usuario (Grupo 4 · multisede, Inf. 3 y 14).
 *  - usuario_sucursal define las sedes de cada usuario. Sin filas, el usuario trabaja con todas las sedes.
 *  - La sede activa vive en la sesión y se comparte con los módulos en $_SESSION['erp_context']['sede'].
 *  - null = «Todas las sedes» (solo para usuarios sin restricción de sede).
 */

/** Sedes activas que el usuario puede usar: [id => ['id','codigo','nombre']]. */
function sedes_permitidas(): array {
    global $pdo;
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = usuario_actual();
    if (!$u) return $cache = [];
    $st = $pdo->prepare('SELECT s.id, s.codigo, s.nombre FROM usuario_sucursal us JOIN sucursales s ON s.id = us.sucursal_id WHERE us.usuario_id = ? AND s.estado = 1 ORDER BY s.nombre');
    $st->execute([$u['id']]);
    $filas = $st->fetchAll();
    if (!$filas) {
        $assigned = $pdo->prepare('SELECT COUNT(*) FROM usuario_sucursal WHERE usuario_id = ?');
        $assigned->execute([$u['id']]);
        // Una asignación a una sede inactiva no concede acceso a otras sedes.
        if ((int)$assigned->fetchColumn() === 0) $filas = $pdo->query('SELECT id, codigo, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre')->fetchAll();
    }
    $cache = [];
    foreach ($filas as $f) $cache[(int)$f['id']] = $f;
    return $cache;
}

/** ¿El usuario tiene sedes asignadas (no puede ver «Todas»)? */
function sede_restringida(): bool {
    global $pdo;
    $u = usuario_actual();
    if (!$u) return true;
    $st = $pdo->prepare('SELECT COUNT(*) FROM usuario_sucursal WHERE usuario_id = ?');
    $st->execute([$u['id']]);
    return (int)$st->fetchColumn() > 0;
}

/** Id de la sede activa o null si trabaja con todas. Corrige la sesión si quedó una sede no permitida. */
function sede_activa(): ?int {
    $permitidas = sedes_permitidas();
    $id = $_SESSION['sede_activa'] ?? null;
    if ($id !== null && !isset($permitidas[(int)$id])) $id = null;
    if ($id === null && sede_restringida() && $permitidas) $id = (int)array_key_first($permitidas);
    sede_fijar($id);
    return $id;
}

/** Guarda la sede activa en la sesión y en el contexto que reciben los módulos. */
function sede_fijar(?int $id): void {
    $_SESSION['sede_activa'] = $id;
    $sede = $id !== null ? (sedes_permitidas()[$id] ?? null) : null;
    $u = usuario_actual();
    $_SESSION['erp_context'] = [
        'usuario' => ['id' => $u['id'] ?? null, 'nombre' => $u['nombre'] ?? null, 'rol' => $u['rol'] ?? null],
        'sede'    => $sede ? ['id' => (int)$sede['id'], 'nombre' => $sede['nombre']] : null,
    ];
}

/** Condición SQL para filtrar por la sede activa. Devuelve ['', []] si trabaja con todas. */
function sede_filtro(string $columna): array {
    if (!sedes_permitidas()) return [' AND 1 = 0', []];
    $id = sede_activa();
    return $id === null ? ['', []] : [" AND $columna = ?", [$id]];
}

/** Lanza excepción si la sucursal no está permitida para el usuario o no es la sede activa (si hay una elegida). */
function sede_exigir(int $sucursalId): void {
    if (!isset(sedes_permitidas()[$sucursalId])) throw new RuntimeException('No tienes acceso a la sede seleccionada.');
    $activa = sede_activa();
    if ($activa !== null && $activa !== $sucursalId) {
        throw new RuntimeException('La operación es de otra sede. Cambia la sede activa a «' . (sedes_permitidas()[$sucursalId]['nombre'] ?? '') . '» para registrarla.');
    }
}

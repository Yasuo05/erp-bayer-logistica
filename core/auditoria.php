<?php
/**
 * Bitácora común de la plataforma (Grupo 4 · Inf. 6 «Auditoría y trazabilidad: registra lo que ocurre en toda la plataforma»).
 * Escribe en auditoria_acciones, la misma tabla que usa el Data Hub (G9). Cada módulo conserva su historial
 * propio (comercial_auditoria, g7_auditoria, facturacion_eventos) y la vista Administración › Auditoría los une.
 * Nunca interrumpe la operación: si no puede registrar, lo deja en el log de PHP.
 */
function auditar(string $modulo, string $accion, ?int $entidadId = null, array $detalle = [], string $resultado = 'ok'): void {
    global $pdo;
    $u = usuario_actual();
    if (!$u || !isset($pdo)) return;
    try {
        $detalle += ['sede_id' => $_SESSION['sede_activa'] ?? null, 'usuario' => $u['nombre'] ?? null];
        $pdo->prepare('INSERT INTO auditoria_acciones (usuario_id, modulo, accion, entidad_id, fecha_hora, ip, user_agent, metadata_json, resultado) VALUES (?,?,?,?,NOW(),?,?,?,?)')
            ->execute([
                $u['id'], mb_substr($modulo, 0, 30), mb_substr($accion, 0, 30), $entidadId,
                mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                json_encode($detalle, JSON_UNESCAPED_UNICODE), mb_substr($resultado, 0, 20),
            ]);
    } catch (Throwable $e) {
        error_log('auditoria: ' . $e->getMessage());
    }
}

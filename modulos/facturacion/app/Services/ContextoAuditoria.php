<?php

declare(strict_types=1);

namespace App\Services;

/** Contexto autenticado de usuario y sede que entrega la plataforma ERP. */
final class ContextoAuditoria
{
    /** Estructura esperada en $_SESSION['erp_context']: usuario/sede con id y nombre. */
    public static function metadatosJson(): ?string
    {
        $contexto = $_SESSION['erp_context'] ?? null;
        if (!is_array($contexto)) return null;
        $usuario = is_array($contexto['usuario'] ?? null) ? $contexto['usuario'] : [];
        $sede = is_array($contexto['sede'] ?? null) ? $contexto['sede'] : [];
        $metadatos = [];
        foreach ([
            'usuario_id' => $usuario['id'] ?? null,
            'usuario_nombre' => $usuario['nombre'] ?? null,
            'sede_id' => $sede['id'] ?? null,
            'sede_nombre' => $sede['nombre'] ?? null,
        ] as $clave => $valor) {
            if (is_string($valor) || is_int($valor)) {
                $valor = trim((string) $valor);
                if ($valor !== '') $metadatos[$clave] = mb_substr($valor, 0, 120);
            }
        }
        if (!$metadatos) return null;
        $json = json_encode($metadatos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return is_string($json) ? $json : null;
    }
}

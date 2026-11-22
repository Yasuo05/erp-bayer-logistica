<?php
// Contexto de usuario y sede de la sesión para la trazabilidad: sin base de datos.
declare(strict_types=1);

use App\Services\ContextoAuditoria;
use Tests\Prueba;

return function (Prueba $t): void {
    $sesionAnterior = $_SESSION ?? null;
    if (!is_array($_SESSION ?? null)) $_SESSION = [];

    unset($_SESSION['erp_context']);
    $t->igual(null, ContextoAuditoria::metadatosJson(), 'sin sesión ERP no atribuye usuario demo');

    $_SESSION['erp_context'] = [
        'usuario' => ['id' => 42, 'nombre' => 'Operador de prueba'],
        'sede' => ['id' => 'LIM-01', 'nombre' => 'Sede Lima'],
    ];
    $datos = json_decode((string) ContextoAuditoria::metadatosJson(), true);
    $t->igual([
        'usuario_id' => '42',
        'usuario_nombre' => 'Operador de prueba',
        'sede_id' => 'LIM-01',
        'sede_nombre' => 'Sede Lima',
    ], $datos, 'serializa usuario y sede de la sesión autenticada');

    $_SESSION['erp_context'] = ['usuario' => ['nombre' => 'Operador']];
    $parcial = json_decode((string) ContextoAuditoria::metadatosJson(), true);
    $t->igual(['usuario_nombre' => 'Operador'], $parcial, 'acepta contexto parcial sin inventar campos');

    if (is_array($sesionAnterior)) $_SESSION = $sesionAnterior;
    else unset($_SESSION);
};

<?php
// Estados del comprobante, acciones por incidencia y protección CSRF: reglas puras, sin base de datos.
declare(strict_types=1);

use App\Core\Csrf;
use App\Services\EnvioService;
use App\Services\EstadoComprobanteService;
use Tests\Prueba;

return function (Prueba $t): void {
    $t->grupo('Estados y transiciones');
    $estados = new EstadoComprobanteService();
    $etiquetas = config('estados.etiquetas', []);
    $sinEtiqueta = [];
    foreach (config('estados.transiciones', []) as $desde => $hacia) {
        foreach (array_merge([$desde], $hacia) as $estado) {
            if (!isset($etiquetas[$estado])) $sinEtiqueta[] = $estado;
        }
    }
    $t->verdadero($sinEtiqueta === [], 'todo estado de las transiciones tiene etiqueta', implode(', ', $sinEtiqueta));
    $permitida = static function (string $de, string $a) use ($estados): bool {
        try { $estados->validarTransicion($de, $a); return true; } catch (\Throwable) { return false; }
    };
    $t->verdadero($permitida('borrador_local', 'generado_xml') && $permitida('generado_xml', 'firmado') && $permitida('firmado', 'enviado'), 'flujo normal: borrador → XML → firmado → enviado');
    $t->verdadero($permitida('rechazado', 'generado_xml'), 'un rechazo se corrige regenerando el XML');
    $t->verdadero(!$permitida('rechazado', 'pendiente_reintento'), 'un rechazo no se reenvía con el mismo XML');
    $t->verdadero(!$permitida('observado', 'pendiente_reintento'), 'un observado (válido) no se reenvía');
    $t->verdadero(!$permitida('aceptado', 'anulado_local') && !$permitida('enviado', 'anulado_local'), 'no se anula localmente lo que ya se envió');
    $t->verdadero(!$permitida('borrador_local', 'aceptado'), 'no se salta pasos (borrador → aceptado)');
    $t->verdadero($permitida('aceptado', 'anulado') && $permitida('observado', 'anulado'), 'un aceptado o un observado se anulan ante SUNAT');
    $t->verdadero(!$permitida('enviado', 'anulado') && !$permitida('rechazado', 'anulado') && config('estados.transiciones.anulado') === [], 'no se anula lo que SUNAT no aceptó, y un anulado no cambia más');

    $t->grupo('Anulación ante SUNAT');
    $plazo = (int) config('sunat.plazo_anulacion_dias');
    $emitido = ['estado' => 'aceptado', 'fecha_emision' => '2026-10-20 15:30:00'];
    $dia = static fn (int $dias): \DateTimeImmutable => (new \DateTimeImmutable('2026-10-20 23:00:00'))->modify("+{$dias} days");
    $impedimento = static fn (array $c, int $notas, string $motivo, \DateTimeImmutable $hoy): ?string => EstadoComprobanteService::impedimentoAnulacion($c, $notas, $motivo, $hoy);
    $t->igual(null, $impedimento($emitido, 0, 'Error en el RUC del cliente', $dia(1)), 'aceptado, con motivo, dentro del plazo y sin notas: se puede anular');
    $t->igual(null, $impedimento(['estado' => 'observado'] + $emitido, 0, 'Venta cancelada', $dia(1)), 'un observado también se puede anular');
    $t->igual(null, $impedimento($emitido, 0, 'Venta cancelada', $dia($plazo)), "el último día del plazo ({$plazo} días después de la emisión) todavía se puede");
    $t->verdadero(str_contains((string) $impedimento($emitido, 0, 'Venta cancelada', $dia($plazo + 1)), 'plazo'), 'al día siguiente del plazo ya no se puede');
    $t->verdadero(str_contains((string) $impedimento(['estado' => 'enviado'] + $emitido, 0, 'Venta cancelada', $dia(1)), 'aceptado u observado'), 'uno enviado sin respuesta no se anula');
    $t->verdadero(str_contains((string) $impedimento($emitido, 0, '   ', $dia(1)), 'motivo'), 'el motivo es obligatorio');
    $t->verdadero(str_contains((string) $impedimento($emitido, 0, str_repeat('x', EstadoComprobanteService::MAXIMO_MOTIVO_ANULACION + 1), $dia(1)), 'como máximo'), 'el motivo no pasa de ' . EstadoComprobanteService::MAXIMO_MOTIVO_ANULACION . ' caracteres');
    $t->verdadero(str_contains((string) $impedimento($emitido, 1, 'Venta cancelada', $dia(1)), 'notas'), 'con notas vigentes no se anula');

    $t->grupo('Acción sugerida por incidencia');
    $t->igual('Reintentar el envío', EnvioService::accionSugerida('pendiente_reintento')[0] ?? null, 'falla técnica → reintentar');
    $t->igual('Corregir y regenerar el XML', EnvioService::accionSugerida('rechazado')[0] ?? null, 'rechazo → corregir y regenerar');
    $t->igual('Revisar la observación', EnvioService::accionSugerida('observado')[0] ?? null, 'observación → revisar');
    $t->igual(null, EnvioService::accionSugerida('observado', false, true), 'observación ya revisada → sin acción');
    $t->igual('Recuperar el intento', EnvioService::accionSugerida('firmado', true)[0] ?? null, 'intento interrumpido → recuperar');

    $t->grupo('Protección CSRF');
    $_SESSION = [];
    $token = Csrf::token();
    $t->verdadero((bool) preg_match('/^[0-9a-f]{64}$/', $token), 'el código es aleatorio de 64 caracteres');
    $t->igual($token, Csrf::token(), 'se mantiene durante la sesión');
    $t->verdadero(Csrf::valido($token), 'el código correcto es válido');
    $t->verdadero(!Csrf::valido(str_repeat('a', 64)) && !Csrf::valido(null) && !Csrf::valido(['x']), 'un código falso, vacío o una lista no son válidos');
    $html = Csrf::agregarAFormularios('<form method="post" action="/x"></form><form method="get"></form><FORM METHOD=\'POST\'></FORM>');
    $t->igual(2, substr_count($html, 'name="_csrf"'), 'se agrega solo a los formularios POST (en mayúsculas o minúsculas)');
    $_SESSION = [];
};

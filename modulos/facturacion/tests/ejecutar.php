<?php
/**
 * Pruebas automáticas del módulo de facturación.
 *
 *   php tests/ejecutar.php               todas
 *   php tests/ejecutar.php unitarias     reglas y cálculos (no usan la base de datos)
 *   php tests/ejecutar.php integracion   circuito completo (crea datos de prueba y los borra al terminar)
 *   php tests/ejecutar.php web           pantallas y seguridad por HTTP (requiere Apache encendido)
 *
 * Las de integración solo corren con APP_ENV=local y el envío simulado: nunca contra la base real del ERP.
 * La URL para las pruebas web se toma de PRUEBAS_URL (por defecto http://localhost/erp-facturacion).
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
require __DIR__ . '/Prueba.php';
require __DIR__ . '/Escenario.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\DocumentoElectronicoService;
use App\Services\GatewayFactory;
use Tests\Escenario;
use Tests\Prueba;

Env::cargar(BASE_PATH . '/.env');
date_default_timezone_set('America/Lima');

$elegido = $argv[1] ?? 'todas';
$grupos = ['unitarias', 'integracion', 'web'];
if ($elegido !== 'todas' && !in_array($elegido, $grupos, true)) {
    fwrite(STDERR, "Grupo desconocido «{$elegido}». Usa: unitarias, integracion, web o nada para todas.\n");
    exit(2);
}

$t = new Prueba();
$casos = static fn (string $carpeta): array => glob(__DIR__ . "/{$carpeta}/*.php") ?: [];
$correr = static function (string $archivo, array $argumentos) use ($t): void {
    try {
        (require $archivo)($t, ...$argumentos);
    } catch (\Throwable $e) {
        $t->verdadero(false, basename($archivo) . ' terminó con un error inesperado', $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine());
    }
};

foreach ($grupos as $grupo) {
    if ($elegido !== 'todas' && $elegido !== $grupo) continue;
    echo "\n== " . strtoupper($grupo) . "\n";

    if ($grupo === 'unitarias') {
        foreach ($casos($grupo) as $archivo) $correr($archivo, []);
    }

    if ($grupo === 'integracion') {
        // Salvaguardas: nunca crear comprobantes de prueba en un entorno real
        $motivo = match (true) {
            Env::get('APP_ENV') !== 'local' => 'APP_ENV no es «local»: las pruebas de integración solo se ejecutan en local.',
            !GatewayFactory::esDemo() => 'Hay un proveedor de envío configurado: las pruebas solo usan el envío simulado.',
            !Database::estado()['ok'] => 'No hay conexión con la base de datos.',
            !is_file(DocumentoElectronicoService::rutaCertificado()) => 'Falta el certificado de prueba: php bin/generar_certificado_demo.php',
            default => null,
        };
        if ($motivo !== null) {
            $t->omitir($motivo);
            continue;
        }
        $escenario = new Escenario();
        $escenario->limpiar(); // restos de una ejecución anterior interrumpida
        try {
            // Los comprobantes de prueba se numeran en series reservadas, nunca en las reales
            $escenario->prepararSeries();
            foreach ($casos($grupo) as $archivo) $correr($archivo, [$escenario]);
        } catch (\Throwable $e) {
            $t->verdadero(false, 'preparar las series de prueba', $e->getMessage());
        } finally {
            echo "\n  Datos de prueba eliminados (" . $escenario->limpiar() . " clientes con sus ventas y comprobantes).\n";
        }
    }

    if ($grupo === 'web') {
        $base = rtrim((string) (getenv('PRUEBAS_URL') ?: Env::get('PRUEBAS_URL', 'http://localhost/erp-facturacion')), '/');
        if (@file_get_contents($base . '/', false, stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]])) === false) {
            $t->omitir("No responde {$base}: enciende Apache para las pruebas web.");
            continue;
        }
        foreach ($casos($grupo) as $archivo) $correr($archivo, [$base]);
    }
}

exit($t->resumen());

<?php

declare(strict_types=1);

namespace Tests;

/**
 * Mini marco de pruebas, sin dependencias: comprueba condiciones, muestra cada resultado
 * y al final resume cuántas pasaron, fallaron u omitieron.
 */
final class Prueba
{
    private int $correctas = 0;
    private int $omitidas = 0;
    /** @var string[] */
    private array $fallidas = [];
    private string $grupo = '';

    public function grupo(string $nombre): void
    {
        $this->grupo = $nombre;
        echo "\n  {$nombre}\n";
    }

    public function verdadero(bool $condicion, string $descripcion, string $detalle = ''): void
    {
        if ($condicion) {
            $this->correctas++;
            echo "    ok     {$descripcion}\n";
            return;
        }
        $this->fallidas[] = "{$this->grupo} > {$descripcion}" . ($detalle !== '' ? " ({$detalle})" : '');
        echo "    FALLA  {$descripcion}" . ($detalle !== '' ? " -- {$detalle}" : '') . "\n";
    }

    public function igual(mixed $esperado, mixed $obtenido, string $descripcion): void
    {
        $this->verdadero($esperado === $obtenido, $descripcion,
            'esperado ' . var_export($esperado, true) . ', obtenido ' . var_export($obtenido, true));
    }

    /** La acción debe fallar con un mensaje que contenga $texto (sin distinguir mayúsculas) */
    public function falla(callable $accion, string $texto, string $descripcion): void
    {
        try {
            $accion();
            $this->verdadero(false, $descripcion, 'no se produjo ningún error');
        } catch (\Throwable $e) {
            $this->verdadero(str_contains(mb_strtolower($e->getMessage()), mb_strtolower($texto)), $descripcion, 'mensaje: ' . $e->getMessage());
        }
    }

    public function omitir(string $motivo): void
    {
        $this->omitidas++;
        echo "    --     {$motivo}\n";
    }

    /** Imprime el resumen y devuelve el código de salida (0 si todo pasó) */
    public function resumen(): int
    {
        echo "\n" . str_repeat('-', 60) . "\n";
        echo "  Correctas: {$this->correctas} · Fallidas: " . count($this->fallidas) . " · Omitidas: {$this->omitidas}\n";
        foreach ($this->fallidas as $fallida) {
            echo "  FALLA  {$fallida}\n";
        }
        echo $this->fallidas ? "\n  RESULTADO: HAY FALLAS\n" : "\n  RESULTADO: TODO OK\n";

        return $this->fallidas ? 1 : 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/** Leyenda del importe total en letras (código 1000 del catálogo 52): "SON CIENTO OCHO CON 50/100 SOLES" */
final class MontoEnLetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];
    private const DECENAS = [3 => 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    private const CENTENAS = [1 => 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convertir(float $monto, string $moneda = 'SOLES'): string
    {
        $centimos = (int) round($monto * 100);

        return sprintf('SON %s CON %02d/100 %s', self::numero(intdiv($centimos, 100)), $centimos % 100, $moneda);
    }

    private static function numero(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }

        $millones = intdiv($n, 1_000_000);
        $miles    = intdiv($n % 1_000_000, 1000);
        $resto    = $n % 1000;

        $partes = [];
        if ($millones > 0) {
            $partes[] = $millones === 1 ? 'UN MILLÓN' : self::hastaMil($millones, true) . ' MILLONES';
        }
        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'MIL' : self::hastaMil($miles, true) . ' MIL';
        }
        if ($resto > 0) {
            $partes[] = self::hastaMil($resto, false);
        }

        return implode(' ', $partes);
    }

    /** 1 a 999. Con $apocopar, "UNO" pasa a "UN" delante de MIL/MILLONES (VEINTIÚN MIL). */
    private static function hastaMil(int $n, bool $apocopar): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $centena = intdiv($n, 100);
        $resto   = $n % 100;
        $partes  = $centena > 0 ? [self::CENTENAS[$centena]] : [];

        if ($resto > 0) {
            $texto = $resto < 30
                ? self::UNIDADES[$resto]
                : self::DECENAS[intdiv($resto, 10)] . ($resto % 10 ? ' Y ' . self::UNIDADES[$resto % 10] : '');
            if ($apocopar) {
                $texto = preg_replace(['/VEINTIUNO$/', '/UNO$/'], ['VEINTIÚN', 'UN'], $texto);
            }
            $partes[] = $texto;
        }

        return implode(' ', $partes);
    }
}

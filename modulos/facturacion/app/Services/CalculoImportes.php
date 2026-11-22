<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cálculo de importes de un comprobante: subtotal, IGV y total por línea y en total.
 * Es el mismo para la revisión antes de emitir y para la emisión, así lo revisado es lo que se emite.
 */
final class CalculoImportes
{
    /**
     * @param array $detalle líneas con cantidad y precio_unitario (sin IGV)
     * @param float $tasa    tasa de IGV, ej. 0.18
     * @return array{lineas: array, subtotal: float, impuesto: float, total: float}
     */
    public static function calcular(array $detalle, float $tasa): array
    {
        $subtotal = 0.0;
        $impuesto = 0.0;
        foreach ($detalle as &$linea) {
            // Mismo redondeo que Comercial (G8): valor de venta bruto, menos descuento de la línea = base imponible
            $linea['descuento'] = round(max(0.0, (float) ($linea['descuento'] ?? 0)), 2);
            $bruto = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
            if ($linea['descuento'] > $bruto) {
                throw new \InvalidArgumentException('El descuento de una línea no puede superar su valor de venta.');
            }
            $linea['subtotal'] = round($bruto - $linea['descuento'], 2);
            $linea['impuesto'] = round($linea['subtotal'] * $tasa, 2);
            $linea['total'] = round($linea['subtotal'] + $linea['impuesto'], 2);
            $subtotal += $linea['subtotal'];
            $impuesto += $linea['impuesto'];
        }
        unset($linea);

        return [
            'lineas'   => $detalle,
            'subtotal' => round($subtotal, 2),
            'impuesto' => round($impuesto, 2),
            'total'    => round($subtotal + $impuesto, 2),
        ];
    }
}

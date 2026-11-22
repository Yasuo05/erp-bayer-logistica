<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validación de documentos de identidad del cliente.
 * El RUC tiene 11 dígitos: el último es un dígito verificador (módulo 11) que se calcula con los diez primeros;
 * así se detectan RUC mal escritos antes de emitir un comprobante que SUNAT rechazaría.
 */
final class DocumentoIdentidad
{
    /** Pesos que SUNAT aplica a los diez primeros dígitos del RUC */
    private const PESOS_RUC = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /** Prefijos válidos: 10 persona natural, 15/16/17 otros contribuyentes, 20 persona jurídica */
    private const PREFIJOS_RUC = ['10', '15', '16', '17', '20'];

    public static function dniValido(string $dni): bool
    {
        return (bool) preg_match('/^\d{8}$/', $dni);
    }

    public static function rucValido(string $ruc): bool
    {
        return (bool) preg_match('/^\d{11}$/', $ruc)
            && in_array(substr($ruc, 0, 2), self::PREFIJOS_RUC, true)
            && self::digitoVerificadorRuc(substr($ruc, 0, 10)) === (int) $ruc[10];
    }

    /** Dígito verificador para los diez primeros dígitos de un RUC */
    public static function digitoVerificadorRuc(string $diezDigitos): int
    {
        if (!preg_match('/^\d{10}$/', $diezDigitos)) {
            throw new \InvalidArgumentException('Se necesitan los diez primeros dígitos del RUC.');
        }
        $suma = 0;
        foreach (self::PESOS_RUC as $i => $peso) {
            $suma += (int) $diezDigitos[$i] * $peso;
        }
        $digito = 11 - ($suma % 11);

        return match ($digito) {
            10 => 0,
            11 => 1,
            default => $digito,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Paginación de listados: lee ?pagina= y ?por_pagina= de la URL y arma los enlaces
 * conservando los filtros que ya tenga la búsqueda.
 */
final class Paginacion
{
    /** Tamaños de página que se pueden elegir */
    public const TAMANOS = [5, 10, 20, 50];
    public const POR_DEFECTO = 20;

    public readonly int $total;
    public readonly int $porPagina;
    public readonly int $paginas;
    public readonly int $pagina;

    public function __construct(int $total, int $pagina = 1, int $porPagina = self::POR_DEFECTO)
    {
        $this->total = max(0, $total);
        $this->porPagina = in_array($porPagina, self::TAMANOS, true) ? $porPagina : self::POR_DEFECTO;
        $this->paginas = max(1, (int) ceil($this->total / $this->porPagina));
        // Una página fuera de rango (ej. tras filtrar) se lleva a la última que existe
        $this->pagina = min(max(1, $pagina), $this->paginas);
    }

    /** @param array $consulta normalmente $_GET */
    public static function desdeUrl(int $total, array $consulta): self
    {
        $numero = static fn (string $clave): int => is_string($consulta[$clave] ?? null) && ctype_digit($consulta[$clave]) && strlen($consulta[$clave]) <= 6
            ? (int) $consulta[$clave] : 0;

        return new self($total, $numero('pagina') ?: 1, $numero('por_pagina') ?: self::POR_DEFECTO);
    }

    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    public function desde(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function hasta(): int
    {
        return min($this->total, $this->offset() + $this->porPagina);
    }

    /** Parámetros de la búsqueda actual (solo texto), sin los de paginación */
    public static function filtrosDe(array $consulta): array
    {
        unset($consulta['pagina'], $consulta['por_pagina']);

        return array_filter($consulta, static fn ($valor): bool => is_string($valor) && $valor !== '');
    }

    /** Enlace (?…) a otra página con los mismos filtros y tamaño de página */
    public function enlace(int $pagina, array $consulta): string
    {
        $parametros = self::filtrosDe($consulta);
        if ($this->porPagina !== self::POR_DEFECTO) {
            $parametros['por_pagina'] = $this->porPagina;
        }
        if ($pagina > 1) {
            $parametros['pagina'] = $pagina;
        }

        return $parametros ? '?' . http_build_query($parametros) : '?';
    }

    /** Páginas a mostrar: la primera, la última y las cercanas a la actual; null marca un salto («…») */
    public function numeros(): array
    {
        $numeros = [];
        $anterior = 0;
        for ($i = 1; $i <= $this->paginas; $i++) {
            if ($i === 1 || $i === $this->paginas || abs($i - $this->pagina) <= 1) {
                if ($anterior && $i - $anterior > 1) {
                    $numeros[] = null;
                }
                $numeros[] = $i;
                $anterior = $i;
            }
        }

        return $numeros;
    }
}

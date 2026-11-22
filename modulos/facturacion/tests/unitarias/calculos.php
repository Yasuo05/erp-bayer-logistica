<?php
// Cálculo de importes, monto en letras y paginación: reglas puras, sin base de datos.
declare(strict_types=1);

use App\Core\Paginacion;
use App\Services\CalculoImportes;
use App\Services\MontoEnLetras;
use Tests\Prueba;

return function (Prueba $t): void {
    $t->grupo('Cálculo de importes');
    $c = CalculoImportes::calcular([
        ['cantidad' => 3, 'precio_unitario' => 33.33],
        ['cantidad' => 0.5, 'precio_unitario' => 10.01],
    ], 0.18);
    $t->igual(105.0, $c['subtotal'], 'subtotal = suma de líneas redondeadas (99.99 + 5.01)');
    $t->igual(18.9, $c['impuesto'], 'IGV redondeado por línea (18.00 + 0.90)');
    $t->igual(123.9, $c['total'], 'total = subtotal + IGV');
    $t->igual(5.91, $c['lineas'][1]['total'], 'total de cada línea');
    $vacio = CalculoImportes::calcular([], 0.18);
    $t->verdadero($vacio['total'] === 0.0 && $vacio['lineas'] === [], 'sin líneas: importes en cero');

    $t->grupo('Monto en letras');
    $t->igual('SON CINCUENTA Y TRES CON 10/100 SOLES', MontoEnLetras::convertir(53.10), '53.10');
    $t->igual('SON MIL DOSCIENTOS CINCUENTA CON 50/100 SOLES', MontoEnLetras::convertir(1250.5), '1250.50');

    $t->grupo('Paginación');
    $p = new Paginacion(23, 2, 10);
    $t->verdadero($p->paginas === 3 && $p->offset() === 10 && $p->desde() === 11 && $p->hasta() === 20, '23 resultados de 10 en 10: página 2 muestra 11–20');
    $t->igual(3, (new Paginacion(23, 99, 10))->pagina, 'página fuera de rango → la última');
    $t->igual(Paginacion::POR_DEFECTO, (new Paginacion(5, 1, 7))->porPagina, 'tamaño no permitido → el predeterminado');
    $t->igual([1, null, 9, 10, 11, null, 20], (new Paginacion(200, 10, 10))->numeros(), 'números con saltos: 1 … 9 10 11 … 20');
    $t->igual('?cliente=demo&por_pagina=10&pagina=3', $p->enlace(3, ['cliente' => 'demo', 'pagina' => '2', 'vacio' => '']), 'el enlace conserva los filtros');
    $t->igual(1, Paginacion::desdeUrl(50, ['pagina' => ['x'], 'por_pagina' => '999999999'])->pagina, 'valores extraños en la URL se ignoran');
};

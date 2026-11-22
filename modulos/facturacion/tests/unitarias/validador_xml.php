<?php
// Validador del XML (ValidadorXmlService): un XML correcto pasa y uno con datos que no coinciden se rechaza.
// Sin base de datos: el comprobante y el emisor se arman aquí.
declare(strict_types=1);

use App\Services\ValidadorXmlService;
use App\Services\XmlAnulacionService;
use App\Services\XmlComprobanteService;
use Tests\Prueba;

return function (Prueba $t): void {
    $emisor = [
        'ruc' => '20000000001', 'razon_social' => 'AGRICOLA PUCCHUN S.A.C.', 'nombre_comercial' => 'Agrícola Pucchún',
        'ubigeo' => '150101', 'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'LIMA',
        'direccion' => 'Av. Demostración 123', 'codigo_establecimiento' => '0000',
    ];
    $linea = static fn (string $codigo, string $descripcion, float $cantidad, float $precio): array => [
        'codigo_producto' => $codigo, 'descripcion' => $descripcion, 'cantidad' => $cantidad, 'unidad_medida' => 'NIU',
        'precio_unitario' => $precio, 'subtotal' => round($cantidad * $precio, 2), 'impuesto' => round(round($cantidad * $precio, 2) * 0.18, 2),
        'total' => round($cantidad * $precio, 2) + round(round($cantidad * $precio, 2) * 0.18, 2),
    ];
    $detalles = [$linea('PROD-001', 'Mango Kent (caja de 10 kg)', 4, 45.00), $linea('PROD-002', 'Limón sutil (malla de 10 kg)', 2, 32.50)];
    $factura = [
        'tipo' => 'factura', 'serie' => 'F001', 'numero' => 7, 'fecha_emision' => '2026-10-20 10:15:00',
        'cliente' => 'Comercial Demo del Norte S.A.C.', 'tipo_documento' => 'RUC', 'numero_documento' => '20123456789',
        'detalles' => $detalles, 'motivo' => null,
        'subtotal' => array_sum(array_column($detalles, 'subtotal')),
        'impuesto' => array_sum(array_column($detalles, 'impuesto')),
        'total' => array_sum(array_column($detalles, 'total')),
    ];
    $validador = new ValidadorXmlService();
    $xml = (new XmlComprobanteService())->generar($factura, $emisor, 0.18);
    // Cambia un valor dentro del XML generado (como si se hubiera alterado o armado mal)
    $alterar = static fn (string $de, string $a): string => preg_replace('/' . preg_quote($de, '/') . '/', $a, $xml, 1);
    $errores = static fn (string $x, array $c = []) => $validador->validar($x, $c + $factura, $emisor);
    $contiene = static fn (array $lista, string $texto): bool => (bool) array_filter($lista, static fn (string $e): bool => str_contains($e, $texto));

    $t->grupo('Validador del XML: XML correcto');
    $t->igual([], $errores($xml), 'una factura generada correctamente no tiene errores');
    $boleta = ['tipo' => 'boleta', 'serie' => 'B001', 'cliente' => 'María Ejemplo Pérez', 'tipo_documento' => 'DNI', 'numero_documento' => '45678912'] + $factura;
    $t->igual([], $validador->validar((new XmlComprobanteService())->generar($boleta, $emisor, 0.18), $boleta, $emisor), 'una boleta generada correctamente no tiene errores');
    $nota = ['tipo' => 'nota_credito', 'serie' => 'FC01', 'numero' => 3, 'motivo' => 'Descuento por fruta dañada', 'codigo_motivo' => '09',
        'tipo_referencia' => 'factura', 'serie_referencia' => 'F001', 'numero_referencia' => 7] + $factura;
    $t->igual([], $validador->validar((new XmlComprobanteService())->generar($nota, $emisor, 0.18), $nota, $emisor), 'una nota de crédito generada correctamente no tiene errores');

    $t->grupo('Validador del XML: datos que no coinciden');
    $t->verdadero($contiene($errores($alterar('>20000000001<', '>20999999999<')), 'RUC del emisor en la firma'), 'detecta un RUC distinto en los datos de la firma');
    $emisorAlterado = preg_replace('/(<cac:AccountingSupplierParty>.*?<cbc:ID[^>]*>)20000000001</s', '${1}20999999999<', $xml, 1);
    $t->verdadero($contiene($errores($emisorAlterado), 'RUC del emisor'), 'detecta un RUC del emisor distinto');
    $t->verdadero($contiene($errores($xml, ['cliente' => 'Otro Cliente S.A.C.']), 'nombre del cliente'), 'detecta un cliente distinto al del comprobante');
    $t->verdadero($contiene($errores($xml, ['numero' => 8]), 'serie y número'), 'detecta un número de comprobante distinto');
    $t->verdadero($contiene($errores($xml, ['fecha_emision' => '2026-10-21 09:00:00']), 'fecha de emisión'), 'detecta una fecha de emisión distinta');
    $total = number_format($factura['total'], 2, '.', '');
    $t->verdadero($contiene($errores(str_replace(">{$total}<", '>999.99<', $xml)), 'total'), 'detecta un total alterado');
    $t->verdadero($contiene($errores($xml, ['total' => $factura['total'] + 10]), 'total'), 'detecta un total del comprobante que no cuadra con el XML');
    $t->verdadero($contiene($errores($alterar('<cbc:PriceAmount currencyID="PEN">45.00<', '<cbc:PriceAmount currencyID="PEN">46.00<')), 'Línea 1'), 'detecta un valor unitario alterado en una línea');
    $t->verdadero($contiene($errores($alterar('Mango Kent (caja de 10 kg)', 'Otro producto')), 'descripción'), 'detecta una descripción de producto distinta');
    $una = $factura;
    array_pop($una['detalles']);
    $t->verdadero($contiene($errores($xml, ['detalles' => $una['detalles']]), 'líneas'), 'detecta una cantidad de líneas distinta');

    $t->grupo('Validador del XML: estructura');
    $t->verdadero($contiene($errores(preg_replace('/\s*<cbc:IssueDate>[^<]*<\/cbc:IssueDate>/', '', $xml, 1)), 'fecha de emisión'), 'detecta que falta la fecha de emisión');
    $t->verdadero($contiene($errores(preg_replace('/\s*<cac:AccountingCustomerParty>.*?<\/cac:AccountingCustomerParty>/s', '', $xml, 1)), 'cliente'), 'detecta que falta el cliente');
    $t->igual(['El XML no está bien formado.'], $errores('<Invoice><sin cerrar>'), 'rechaza un XML mal formado');
    $t->verdadero($contiene($errores(str_replace('Invoice-2"', 'CreditNote-2"', $xml)), 'raíz'), 'rechaza un elemento raíz que no corresponde al tipo');

    $t->grupo('Validador del XML: esquema XSD oficial de UBL 2.1');
    if (!ValidadorXmlService::hayEsquemas()) {
        $t->omitir('Faltan los esquemas en storage/xsd/: php bin/descargar_esquemas_xsd.php');
    } else {
        $t->igual([], $errores($xml), 'el XML sin firmar cumple el XSD (el bloque de la firma aún vacío se acepta)');
        $desordenado = preg_replace('/(\s*<cbc:UBLVersionID>2\.1<\/cbc:UBLVersionID>)(\s*<cbc:CustomizationID>2\.0<\/cbc:CustomizationID>)/', '$2$1', $xml, 1);
        $t->verdadero($contiene($errores($desordenado), 'Esquema UBL 2.1'), 'el XSD detecta elementos fuera de orden');
        $t->verdadero($contiene($errores($alterar('<cbc:DocumentCurrencyCode>', '<cbc:Inventado>x</cbc:Inventado><cbc:DocumentCurrencyCode>')), 'Esquema UBL 2.1'), 'el XSD detecta un elemento que no existe en UBL');
    }

    $t->grupo('Validador del XML de anulación');
    $referencia = new \DateTimeImmutable('2026-10-20');
    $generacion = new \DateTimeImmutable('2026-10-22');
    $anulacion = new XmlAnulacionService();
    $baja = $anulacion->generar('comunicacion_baja', 'RA-20261022-1', $referencia, $generacion, $emisor, [['comprobante' => $factura, 'motivo' => 'Error en el RUC del cliente']]);
    $t->igual([], $validador->validarAnulacion($baja, 'comunicacion_baja', 'RA-20261022-1', $referencia, $emisor, [['comprobante' => $factura, 'motivo' => 'Error en el RUC del cliente']]), 'una comunicación de baja correcta no tiene errores');
    $t->verdadero($contiene($validador->validarAnulacion($baja, 'comunicacion_baja', 'RA-20261022-1', $referencia, $emisor, [['comprobante' => ['numero' => 9] + $factura, 'motivo' => 'x']]), 'número'), 'la baja detecta un comprobante distinto');
    $t->verdadero($contiene($validador->validarAnulacion($baja, 'comunicacion_baja', 'RA-20261022-2', $referencia, $emisor, [['comprobante' => $factura, 'motivo' => 'x']]), 'identificador'), 'la baja detecta un identificador distinto');
    $resumen = $anulacion->generar('resumen_diario', 'RC-20261022-1', $referencia, $generacion, $emisor, [['comprobante' => $boleta, 'motivo' => 'Venta cancelada']]);
    $t->igual([], $validador->validarAnulacion($resumen, 'resumen_diario', 'RC-20261022-1', $referencia, $emisor, [['comprobante' => $boleta, 'motivo' => 'Venta cancelada']]), 'un resumen diario de anulación correcto no tiene errores');
    $t->verdadero(str_contains($resumen, '<cbc:ConditionCode>3</cbc:ConditionCode>'), 'el resumen diario marca la boleta con estado 3 (anulado)');
    $t->verdadero($contiene($validador->validarAnulacion($resumen, 'resumen_diario', 'RC-20261022-1', $referencia, $emisor, [['comprobante' => ['total' => 1.00] + $boleta, 'motivo' => 'x']]), 'total'), 'el resumen detecta un total distinto');
    $t->verdadero($contiene($validador->validarAnulacion($resumen, 'resumen_diario', 'RC-20261022-1', new \DateTimeImmutable('2026-10-19'), $emisor, [['comprobante' => $boleta, 'motivo' => 'x']]), 'fecha'), 'detecta una fecha de referencia distinta a la de emisión');
    $t->igual('comunicacion_baja', XmlAnulacionService::tipoPara($factura), 'una factura (serie F) se anula con comunicación de baja');
    $t->igual('resumen_diario', XmlAnulacionService::tipoPara($boleta), 'una boleta (serie B) se anula con resumen diario');
};

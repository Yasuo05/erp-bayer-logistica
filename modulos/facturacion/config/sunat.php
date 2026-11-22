<?php

/*
 * Códigos de los catálogos de SUNAT que usa el XML UBL 2.1 del comprobante.
 */

return [
    'moneda' => 'PEN',

    // Catálogo 01: tipo de comprobante
    'tipos_comprobante' => [
        'factura'      => '01',
        'boleta'       => '03',
        'nota_credito' => '07',
        'nota_debito'  => '08',
    ],

    // Catálogo 06: tipo de documento de identidad del cliente
    'tipos_documento_identidad' => [
        'DNI' => '1',
        'RUC' => '6',
    ],

    // Días calendario, contados desde el día siguiente a la emisión, para pedir la baja de un comprobante aceptado.
    // Valor a confirmar con la normativa vigente de SUNAT antes de pasar a producción.
    'plazo_anulacion_dias' => 7,

    // Motivo por defecto de cada tipo de nota (si la nota no guardó su código_motivo)
    'motivo_nota' => [
        'nota_credito' => '09',
        'nota_debito'  => '02',
    ],

    // Catálogos 09 (crédito) y 10 (débito): motivos que admite el formulario de notas.
    // Todos son ajustes de monto; los que exigen detalle por ítem o exportación no se usan aquí.
    'motivos_nota' => [
        'nota_credito' => [
            '09' => 'Disminución en el valor',
        ],
        'nota_debito' => [
            '01' => 'Intereses por mora',
            '02' => 'Aumento en el valor',
            '03' => 'Penalidades u otros conceptos',
        ],
    ],
];

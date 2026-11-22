<?php
/**
 * Reglas de mapeo para la ingesta de referencias (database/referencias) y cargas futuras.
 * Ajuste aquí cuando G4/G7 entreguen los datos maestros definitivos: el importador
 * es idempotente y re-aplicará el mapeo sin duplicar registros.
 */
return [
    // Distribuidor que reporta a Bayer (dealerId / dealerName). Placeholder hasta contar con el RUC real.
    'dealer' => [
        'ruc'  => env('DEALER_RUC', '20601234567'),
        'name' => env('DEALER_NAME', 'Distribuidora Agrícola Pucchún S.A.C.'),
    ],

    // Sedes que aparecen como texto en Ventas/Inventario del archivo Bayer → sucursal interna.
    'sedes_bayer' => [
        'Chincha' => ['codigo' => 'SED-CHI', 'nombre' => 'Sede Chincha', 'departamento' => 'Ica', 'provincia' => 'Chincha', 'distrito' => 'Chincha Alta', 'serie_factura' => 'F001', 'serie_boleta' => 'B001', 'serie_guia' => 'T001'],
        'Pisco'   => ['codigo' => 'SED-PIS', 'nombre' => 'Sede Pisco',   'departamento' => 'Ica', 'provincia' => 'Pisco',   'distrito' => 'Pisco',        'serie_factura' => 'F002', 'serie_boleta' => 'B002', 'serie_guia' => 'T002'],
        'Ica'     => ['codigo' => 'SED-ICA', 'nombre' => 'Sede Ica',     'departamento' => 'Ica', 'provincia' => 'Ica',     'distrito' => 'Ica',          'serie_factura' => 'F003', 'serie_boleta' => 'B003', 'serie_guia' => 'T003'],
    ],

    // Ubicación de las sedes demo (01_sedes_almacenes.sql) por ciudad.
    'ciudades' => [
        'Lima'  => ['departamento' => 'Lima',  'provincia' => 'Lima',  'distrito' => 'Lima'],
        'Ica'   => ['departamento' => 'Ica',   'provincia' => 'Ica',   'distrito' => 'Ica'],
        'Piura' => ['departamento' => 'Piura', 'provincia' => 'Piura', 'distrito' => 'Piura'],
    ],

    // Homologación de códigos de cliente del archivo Bayer (C006) hacia el maestro comercial (CL006).
    'cliente_map' => ['pattern' => '/^C(\d+)$/', 'format' => 'CL%03d'],

    // Distritos cuya provincia/departamento difiere de la ciudad de la sede del cliente.
    'distritos_override' => [
        'Callao' => ['departamento' => 'Callao', 'provincia' => 'Callao'],
    ],

    // Unidades de medida de origen → código interno.
    'unidades' => [
        'L'   => ['codigo' => 'L',   'nombre' => 'Litro'],
        'KG'  => ['codigo' => 'KG',  'nombre' => 'Kilogramo'],
        'UND' => ['codigo' => 'UND', 'nombre' => 'Unidad'],
    ],

    // Estados comerciales de origen que se consideran anulados (no se publican ni generan guía).
    'estados_anulados' => ['ANULADA', 'ANULADO', 'CANCELADA'],
];

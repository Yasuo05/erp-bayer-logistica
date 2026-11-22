<?php

return [
    'etiquetas' => [
        'borrador_local'      => 'Borrador',
        'generado_xml'        => 'XML generado',
        'firmado'             => 'XML firmado',
        'enviado'             => 'Enviado',
        'aceptado'            => 'Aceptado',
        'observado'           => 'Observado',
        'rechazado'           => 'Rechazado',
        'pendiente_reintento' => 'Pendiente de reintento',
        'anulado'             => 'Anulado',
        'anulado_local'       => 'Anulado (antes del envío)',
    ],

    // Transiciones compartidas con quienes implementen XML, envío y CDR.
    'transiciones' => [
        'borrador_local'      => ['generado_xml', 'anulado_local'],
        'generado_xml'        => ['firmado', 'anulado_local'],
        'firmado'             => ['enviado', 'anulado_local'],
        'enviado'             => ['aceptado', 'observado', 'rechazado', 'pendiente_reintento'],
        // Observado es válido ante SUNAT: no se reenvía (la observación se revisa y se corrige en los siguientes);
        // como un aceptado, puede anularse con una comunicación de baja
        'observado'           => ['aceptado', 'anulado'],
        // Un rechazo es de contenido: se corrige regenerando el XML; reenviar el mismo XML volvería a ser rechazado
        'rechazado'           => ['generado_xml'],
        // pendiente_reintento solo llega por una falla técnica (error temporal): se reenvía el mismo XML
        'pendiente_reintento' => ['enviado', 'rechazado'],
        'aceptado'            => ['anulado'],
        'anulado'             => [],
        'anulado_local'       => [],
    ],
];


<?php

/*
 * Menú del módulo de Facturación Electrónica (Grupo 1).
 * El orden sigue el flujo de la infografía 18: venta aprobada → comprobante → envío → consulta.
 *
 * Cada opción:
 *   ruta        URL dentro del módulo (debe existir también en config/rutas.php)
 *   titulo      nombre en el menú
 *   icono       nombre en app/Views/iconos.php
 *   descripcion una línea: para qué sirve
 *   funciones   qué hará cuando se implemente
 *   conexion    (opcional) qué recibe o entrega a otros grupos del ERP (infografías 18 y 20)
 */

return [
    [
        'titulo' => null,
        'items'  => [
            [
                'ruta'        => '/',
                'titulo'      => 'Inicio',
                'icono'       => 'layout-dashboard',
                'descripcion' => 'Resumen del módulo, su flujo y sus conexiones con el ERP.',
                'funciones'   => [],
            ],
        ],
    ],

    [
        'titulo'      => 'Emisión',
        'icono'       => 'file-pen-line',
        'descripcion' => 'Convierte las ventas aprobadas en comprobantes electrónicos.',
        'items'       => [
            [
                'ruta'        => '/emision/ventas-por-facturar',
                'titulo'      => 'Ventas por facturar',
                'icono'       => 'inbox',
                'descripcion' => 'Bandeja de ventas aprobadas que envía el módulo Comercial, listas para convertirse en comprobante.',
                'funciones'   => [
                    'Listar las ventas aprobadas pendientes de facturar.',
                    'Indicar qué tipo de comprobante corresponde (factura o boleta).',
                    'Iniciar la emisión del comprobante de una venta.',
                ],
                'conexion'    => [
                    'recibe' => [['Grupo 8', 'Venta aprobada']],
                ],
            ],
            [
                'ruta'        => '/emision/emitir',
                'titulo'      => 'Emitir comprobante',
                'icono'       => 'file-pen-line',
                'descripcion' => 'Genera el comprobante electrónico de una venta aprobada.',
                'funciones'   => [
                    'Tomar los datos de la venta, del cliente y de los productos.',
                    'Asignar la serie y el correlativo que corresponden.',
                    'Calcular importes e impuestos del comprobante.',
                    'Generar el XML y pasarlo a firma y envío.',
                ],
                'conexion'    => [
                    'recibe' => [
                        ['Grupo 4', 'Empresa, sede y configuración'],
                        ['Grupo 5', 'Cliente y producto'],
                        ['Grupo 8', 'Venta aprobada'],
                    ],
                ],
            ],
        ],
    ],

    [
        'titulo'      => 'Comprobantes',
        'icono'       => 'file-text',
        'descripcion' => 'Los cuatro tipos de comprobante que emite el módulo.',
        'items'       => [
            [
                'ruta'        => '/comprobantes/facturas',
                'titulo'      => 'Facturas',
                'icono'       => 'file-text',
                'descripcion' => 'Consulta de las facturas emitidas y de su estado.',
                'funciones'   => [
                    'Listar y filtrar facturas por fecha, cliente, sede y estado.',
                    'Ver el detalle de cada factura.',
                    'Acceder a su PDF, XML y CDR.',
                ],
            ],
            [
                'ruta'        => '/comprobantes/boletas',
                'titulo'      => 'Boletas',
                'icono'       => 'receipt',
                'descripcion' => 'Consulta de las boletas emitidas y de su estado.',
                'funciones'   => [
                    'Listar y filtrar boletas por fecha, cliente, sede y estado.',
                    'Ver el detalle de cada boleta.',
                    'Acceder a su PDF, XML y CDR.',
                ],
            ],
            [
                'ruta'        => '/comprobantes/notas-credito',
                'titulo'      => 'Notas de crédito',
                'icono'       => 'file-minus-2',
                'descripcion' => 'Documentos que corrigen o anulan, total o parcialmente, un comprobante ya emitido.',
                'funciones'   => [
                    'Emitir una nota vinculada a una factura o boleta.',
                    'Registrar el motivo de la nota.',
                    'Seguir el mismo ciclo electrónico que el comprobante.',
                ],
            ],
            [
                'ruta'        => '/comprobantes/notas-debito',
                'titulo'      => 'Notas de débito',
                'icono'       => 'file-plus-2',
                'descripcion' => 'Documentos que incrementan el importe de un comprobante ya emitido.',
                'funciones'   => [
                    'Emitir una nota vinculada a una factura o boleta.',
                    'Registrar el motivo del cargo adicional.',
                    'Seguir el mismo ciclo electrónico que el comprobante.',
                ],
            ],
        ],
    ],

    [
        'titulo'      => 'Envío electrónico',
        'icono'       => 'send',
        'descripcion' => 'Procesamiento electrónico del comprobante y control de errores.',
        'items'       => [
            [
                'ruta'        => '/envio/estado',
                'titulo'      => 'Estado de envíos',
                'icono'       => 'send',
                'descripcion' => 'Seguimiento de cada comprobante en su procesamiento: XML, firma, envío, respuesta y CDR.',
                'funciones'   => [
                    'Ver en qué etapa está cada comprobante.',
                    'Consultar la respuesta recibida y el CDR.',
                    'Informar el estado del comprobante al módulo Comercial.',
                ],
                'conexion'    => [
                    'entrega' => [['Grupo 8', 'Estado del comprobante']],
                ],
            ],
            [
                'ruta'        => '/envio/rechazos',
                'titulo'      => 'Rechazos y reintentos',
                'icono'       => 'triangle-alert',
                'descripcion' => 'Control de errores: comprobantes rechazados u observados y su reenvío.',
                'funciones'   => [
                    'Listar rechazos y observaciones con su motivo.',
                    'Registrar cada intento y la respuesta del gateway.',
                    'Gestionar observaciones, rechazos, errores transitorios y reintentos manuales.',
                    'Conservar ticket, CDR, códigos de respuesta y trazabilidad.',
                ],
            ],
        ],
    ],

    [
        'titulo'      => 'Consultas',
        'icono'       => 'file-down',
        'descripcion' => 'Representación del comprobante y su historial.',
        'items'       => [
            [
                'ruta'        => '/consultas/descargas',
                'titulo'      => 'Consulta y descarga',
                'icono'       => 'file-down',
                'descripcion' => 'Búsqueda de comprobantes y descarga de su PDF, XML y CDR.',
                'funciones'   => [
                    'Buscar un comprobante por serie y número, cliente o fecha.',
                    'Descargar su PDF, XML y CDR.',
                    'Volver a imprimir el comprobante.',
                ],
            ],
            [
                'ruta'        => '/consultas/trazabilidad',
                'titulo'      => 'Trazabilidad',
                'icono'       => 'history',
                'descripcion' => 'Historial de todo lo que ocurrió con cada comprobante.',
                'funciones'   => [
                    'Registrar emisión, envíos, respuestas y reintentos.',
                    'Saber quién hizo cada acción y cuándo.',
                    'Entregar la información de facturación para la analítica del ERP.',
                ],
                'conexion'    => [
                    'entrega' => [['Grupo 9', 'Información de facturación']],
                ],
            ],
        ],
    ],

    [
        'titulo'      => 'Configuración',
        'icono'       => 'settings',
        'descripcion' => 'Control documentario y parámetros del proceso electrónico.',
        'items'       => [
            [
                'ruta'        => '/configuracion/series',
                'titulo'      => 'Series y correlativos',
                'icono'       => 'hash',
                'descripcion' => 'Series de cada tipo de comprobante por sede y el control de su numeración.',
                'funciones'   => [
                    'Definir las series de facturas, boletas y notas.',
                    'Controlar el correlativo de cada serie.',
                    'Asignar las series a cada sede.',
                ],
                'conexion'    => [
                    'recibe' => [['Grupo 4', 'Empresa y sedes']],
                ],
            ],
            [
                'ruta'        => '/configuracion/parametros',
                'titulo'      => 'Parámetros de facturación',
                'icono'       => 'settings',
                'descripcion' => 'Configuración con la que trabaja el proceso electrónico del comprobante.',
                'funciones'   => [
                    'Consultar la tasa de IGV, la moneda y los tipos de comprobante.',
                    'Ver el modo de envío y la vigencia del certificado de firma.',
                    'Comprobar la conexión y que la base de datos esté completa.',
                ],
                'conexion'    => [
                    'recibe' => [['Grupo 4', 'Configuración general']],
                ],
            ],
        ],
    ],
];

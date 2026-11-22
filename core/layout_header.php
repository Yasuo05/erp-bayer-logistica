<?php
// Cabecera de las páginas del ERP: usa el marco común (core/erp_marco.php), el mismo que Facturación y el Portal Bayer.
require_once __DIR__ . '/erp_marco.php';
erp_marco_cabecera([
    'pdo'       => $GLOBALS['pdo'],
    'usuario'   => usuario_actual() ?? [],
    'titulo'    => $titulo ?? 'Inicio',
    'ruta'      => $_SERVER['PHP_SELF'] ?? null,
    'subtitulo' => $subtitulo ?? null,
]);

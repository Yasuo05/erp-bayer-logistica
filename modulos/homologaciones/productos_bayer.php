<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Homologación de productos Bayer', 'tabla' => 'homologacion_productos_bayer', 'campos' => [
  'partner_id'      => ['label' => 'Partner', 'req' => true, 'fk' => ['partners', 'id', 'nombre']],
  'producto_id'     => ['label' => 'Producto interno', 'req' => true, 'fk' => ['productos', 'id', 'nombre']],
  'material_id'     => ['label' => 'Material ID (Bayer)', 'req' => true, 'buscar' => true],
  'material_name'   => ['label' => 'Material (Bayer)', 'buscar' => true],
  'unidad_bayer_id' => ['label' => 'Unidad Bayer', 'fk' => ['homologacion_unidades', 'id', 'external_unit_name']],
]]);
<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Homologación de sucursales', 'tabla' => 'homologacion_sucursales', 'campos' => [
  'partner_id'  => ['label' => 'Partner', 'req' => true, 'fk' => ['partners', 'id', 'nombre']],
  'sucursal_id' => ['label' => 'Sucursal interna', 'req' => true, 'fk' => ['sucursales', 'id', 'nombre']],
  'branch_id'   => ['label' => 'Branch ID (Bayer)', 'req' => true, 'buscar' => true],
  'branch_name' => ['label' => 'Branch (Bayer)', 'buscar' => true],
]]);
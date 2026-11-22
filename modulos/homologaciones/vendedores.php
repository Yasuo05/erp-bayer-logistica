<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Homologación de vendedores', 'tabla' => 'homologacion_vendedores', 'campos' => [
  'partner_id'  => ['label' => 'Partner', 'req' => true, 'fk' => ['partners', 'id', 'nombre']],
  'vendedor_id' => ['label' => 'Vendedor interno', 'req' => true, 'fk' => ['vendedores', 'id', 'nombres']],
  'sales_id'    => ['label' => 'Sales ID (Bayer)', 'req' => true, 'buscar' => true],
  'sales_name'  => ['label' => 'Sales (Bayer)', 'buscar' => true],
]]);
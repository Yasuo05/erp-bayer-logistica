<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Homologación de clientes', 'tabla' => 'homologacion_clientes', 'campos' => [
  'partner_id'    => ['label' => 'Partner', 'req' => true, 'fk' => ['partners', 'id', 'nombre']],
  'cliente_id'    => ['label' => 'Cliente interno', 'req' => true, 'fk' => ['clientes', 'id', 'razon_social']],
  'customer_id'   => ['label' => 'Customer ID (Bayer)', 'req' => true, 'buscar' => true],
  'customer_name' => ['label' => 'Customer (Bayer)', 'buscar' => true],
]]);
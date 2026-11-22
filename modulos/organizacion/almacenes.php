<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Almacenes', 'tabla' => 'almacenes', 'campos' => [
  'sucursal_id' => ['label' => 'Sucursal', 'req' => true, 'fk' => ['sucursales', 'id', 'nombre']],
  'codigo'      => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombre'      => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'tipo'        => ['label' => 'Tipo'],
  'estado'      => campo_estado(),
]]);
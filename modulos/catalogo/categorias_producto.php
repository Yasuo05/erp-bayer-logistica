<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Categorías de producto', 'tabla' => 'categorias_producto', 'campos' => [
  'nombre'      => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'descripcion' => ['label' => 'Descripción', 'buscar' => true],
]]);
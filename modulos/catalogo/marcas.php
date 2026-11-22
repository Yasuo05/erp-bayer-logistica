<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Marcas', 'tabla' => 'marcas', 'campos' => [
  'nombre'     => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'fabricante' => ['label' => 'Fabricante', 'buscar' => true],
]]);
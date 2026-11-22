<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Partners', 'tabla' => 'partners', 'campos' => [
  'codigo' => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombre' => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'tipo'   => ['label' => 'Tipo'],
]]);
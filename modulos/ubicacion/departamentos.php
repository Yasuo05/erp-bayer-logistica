<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Departamentos', 'tabla' => 'departamentos', 'campos' => [
  'nombre' => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
]]);
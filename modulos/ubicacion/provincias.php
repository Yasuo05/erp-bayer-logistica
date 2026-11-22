<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Provincias', 'tabla' => 'provincias', 'campos' => [
  'departamento_id' => ['label' => 'Departamento', 'req' => true, 'fk' => ['departamentos', 'id', 'nombre']],
  'nombre'          => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
]]);
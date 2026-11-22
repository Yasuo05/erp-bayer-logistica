<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Tipos de documento', 'tabla' => 'tipos_documento', 'campos' => [
  'codigo'      => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombre'      => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'sunat_code'  => ['label' => 'Código SUNAT'],
]]);
<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Vendedores', 'tabla' => 'vendedores', 'campos' => [
  'codigo'    => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombres'   => ['label' => 'Nombres', 'req' => true, 'buscar' => true],
  'apellidos' => ['label' => 'Apellidos', 'req' => true, 'buscar' => true],
  'email'     => ['label' => 'Correo', 'tipo' => 'email'],
  'estado'    => campo_estado(),
]]);
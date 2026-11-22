<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Empresas', 'tabla' => 'empresas', 'campos' => [
  'ruc'              => ['label' => 'RUC', 'req' => true, 'buscar' => true],
  'razon_social'     => ['label' => 'Razón social', 'req' => true, 'buscar' => true],
  'nombre_comercial' => ['label' => 'Nombre comercial', 'buscar' => true],
  'estado'           => campo_estado(),
]]);
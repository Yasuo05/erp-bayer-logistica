<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Sucursales', 'tabla' => 'sucursales', 'campos' => [
  'empresa_id'  => ['label' => 'Empresa', 'req' => true, 'fk' => ['empresas', 'id', 'razon_social']],
  'codigo'      => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombre'      => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'direccion'   => ['label' => 'Dirección'],
  'distrito_id' => ['label' => 'Distrito', 'fk' => ['distritos', 'id', 'nombre']],
  // Código del establecimiento anexo registrado en SUNAT; 0000 es el domicilio fiscal
  'codigo_establecimiento' => ['label' => 'Cód. establecimiento SUNAT', 'req' => true, 'default' => '0000'],
  'estado'      => campo_estado(),
], 'validar' => function (array $v) {
  if (!preg_match('/^\d{4}$/', (string)$v['codigo_establecimiento'])) {
    throw new Exception('El código de establecimiento SUNAT debe tener 4 dígitos (0000 para el domicilio fiscal).');
  }
}]);

<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Unidades de medida', 'tabla' => 'unidades_medida', 'campos' => [
  'codigo'       => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombre'       => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'abreviatura'  => ['label' => 'Abreviatura', 'req' => true],
  // Catálogo 03 de SUNAT (UN/ECE rec 20): BX caja, KGM kilogramo, LTR litro, NIU unidad, TU tubo...
  'codigo_sunat' => ['label' => 'Código SUNAT', 'req' => true, 'buscar' => true],
  'factor_base'  => ['label' => 'Factor base', 'tipo' => 'number', 'req' => true],
], 'validar' => function (array $v) {
  if (!preg_match('/^[A-Z0-9]{2,3}$/', (string)$v['codigo_sunat'])) {
    throw new Exception('El código SUNAT debe tener 2 o 3 caracteres en mayúsculas del catálogo 03 (p. ej. BX, KGM, NIU).');
  }
}]);

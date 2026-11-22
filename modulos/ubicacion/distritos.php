<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Distritos', 'tabla' => 'distritos', 'campos' => [
  'provincia_id' => ['label' => 'Provincia', 'req' => true, 'fk' => ['provincias', 'id', 'nombre']],
  'nombre'       => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'ubigeo'       => ['label' => 'Ubigeo (INEI)', 'req' => true, 'buscar' => true],
], 'validar' => function (array $v, $id) {
  global $pdo;
  if (!preg_match('/^\d{6}$/', (string)$v['ubigeo'])) throw new Exception('El ubigeo debe tener 6 dígitos (p. ej. 150501).');
  $st = $pdo->prepare('SELECT nombre FROM distritos WHERE ubigeo = ? AND id <> ?');
  $st->execute([$v['ubigeo'], $id === '' ? 0 : (int)$id]);
  if ($otro = $st->fetchColumn()) throw new Exception("El ubigeo {$v['ubigeo']} ya está asignado a $otro.");
}]);

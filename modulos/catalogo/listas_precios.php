<?php require_once __DIR__ . '/../../core/crud_base.php';
// Listas de precios con vigencia (G5 · Inf. 15 «precio base, listas de precios, vigencias»).
// La cotización (G8) usa el precio vigente en la fecha: primero el de la sede, luego el general; si no hay, el precio de lista del producto.
crud_pagina(['titulo' => 'Listas de precios', 'tabla' => 'lista_precios', 'campos' => [
  'producto_id'   => ['label' => 'Producto', 'req' => true, 'fk' => ['productos', 'id', 'nombre']],
  'sucursal_id'   => ['label' => 'Sede (vacío = todas)', 'fk' => ['sucursales', 'id', 'nombre']],
  'precio'        => ['label' => 'Precio S/ (sin IGV)', 'req' => true, 'tipo' => 'number'],
  'vigente_desde' => ['label' => 'Vigente desde', 'req' => true, 'tipo' => 'date'],
  'vigente_hasta' => ['label' => 'Vigente hasta (vacío = sin fin)', 'tipo' => 'date'],
  'observacion'   => ['label' => 'Observación', 'buscar' => true],
], 'validar' => function (array $v, $id) {
  global $pdo;
  if (!is_numeric($v['precio']) || (float)$v['precio'] < 0) throw new Exception('El precio debe ser un monto mayor o igual a cero.');
  foreach (['vigente_desde', 'vigente_hasta'] as $c) if ($v[$c] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v[$c])) throw new Exception('Las fechas deben tener el formato AAAA-MM-DD.');
  if ($v['vigente_hasta'] !== null && $v['vigente_hasta'] < $v['vigente_desde']) throw new Exception('«Vigente hasta» no puede ser anterior a «Vigente desde».');
  // No puede haber dos precios del mismo producto y sede con vigencias que se crucen
  $st = $pdo->prepare('SELECT COUNT(*) FROM lista_precios WHERE producto_id = ? AND sucursal_id <=> ? AND id <> ?
      AND vigente_desde <= COALESCE(?, \'9999-12-31\') AND COALESCE(vigente_hasta, \'9999-12-31\') >= ?');
  $st->execute([$v['producto_id'], $v['sucursal_id'], $id === '' ? 0 : (int)$id, $v['vigente_hasta'], $v['vigente_desde']]);
  if ($st->fetchColumn() > 0) throw new Exception('Ya hay un precio de ese producto (y sede) con una vigencia que se cruza con estas fechas.');
}]);

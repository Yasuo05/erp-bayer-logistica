<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Productos', 'tabla' => 'productos', 'campos' => [
  'codigo'         => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'nombre'         => ['label' => 'Nombre', 'req' => true, 'buscar' => true],
  'presentacion'   => ['label' => 'Presentación'],
  'categoria_id'   => ['label' => 'Categoría', 'req' => true, 'fk' => ['categorias_producto', 'id', 'nombre']],
  'marca_id'       => ['label' => 'Marca', 'req' => true, 'fk' => ['marcas', 'id', 'nombre']],
  'unidad_base_id' => ['label' => 'Unidad base', 'req' => true, 'fk' => ['unidades_medida', 'id', 'nombre']],
  // Precios (G5): el precio de lista se precarga en las cotizaciones de Comercial (G8)
  'costo_unitario' => ['label' => 'Costo unitario S/', 'tipo' => 'number'],
  'precio_venta'   => ['label' => 'Precio de lista S/ (sin IGV)', 'tipo' => 'number', 'req' => true],
  'es_bayer'       => ['label' => 'Producto Bayer', 'default' => 0, 'opciones' => [0 => 'No', 1 => 'Sí']],
  'estado'         => campo_estado(),
], 'validar' => function (array $v) {
  foreach (['costo_unitario' => 'El costo', 'precio_venta' => 'El precio de lista'] as $campo => $nombre) {
    if ($v[$campo] !== null && (!is_numeric($v[$campo]) || (float)$v[$campo] < 0)) throw new Exception("$nombre debe ser un número mayor o igual a cero.");
  }
}]);

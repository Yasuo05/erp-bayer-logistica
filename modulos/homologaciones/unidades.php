<?php require_once __DIR__ . '/../../core/crud_base.php';
crud_pagina(['titulo' => 'Homologación de unidades', 'tabla' => 'homologacion_unidades', 'campos' => [
  'partner_id'         => ['label' => 'Partner', 'req' => true, 'fk' => ['partners', 'id', 'nombre']],
  'unidad_id'          => ['label' => 'Unidad interna', 'req' => true, 'fk' => ['unidades_medida', 'id', 'nombre']],
  'external_unit_code' => ['label' => 'Código externo', 'req' => true, 'buscar' => true],
  'external_unit_name' => ['label' => 'Nombre externo', 'buscar' => true],
]]);
<?php require_once __DIR__ . '/../../core/crud_base.php';
require_once __DIR__ . '/clientes_ruc.php';
// Maestro de proveedores (G5). Lo usa Logística (G7) en órdenes de compra y cuentas por pagar (los días de pago fijan el vencimiento).
crud_pagina(['titulo' => 'Proveedores', 'tabla' => 'proveedores', 'campos' => [
  'codigo'    => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'ruc'       => ['label' => 'RUC', 'req' => true, 'buscar' => true],
  'nombre'    => ['label' => 'Razón social', 'req' => true, 'buscar' => true],
  'contacto'  => ['label' => 'Contacto'],
  'telefono'  => ['label' => 'Teléfono'],
  'correo'    => ['label' => 'Correo', 'tipo' => 'email'],
  'dias_pago' => ['label' => 'Días de pago (vencimiento de la cuenta por pagar)', 'tipo' => 'number', 'default' => 30],
  'estado'    => campo_estado(),
], 'validar' => function (array $v, $id) {
  global $pdo;
  if (!ruc_valido((string)$v['ruc'])) throw new Exception('El RUC del proveedor no es válido (11 dígitos con dígito verificador).');
  if ($v['correo'] !== null && !filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) throw new Exception('El correo no es válido.');
  if ($v['dias_pago'] !== null && (!ctype_digit((string)$v['dias_pago']) || (int)$v['dias_pago'] > 365)) throw new Exception('Los días de pago deben ser un número entre 0 y 365.');
  $st = $pdo->prepare('SELECT COUNT(*) FROM proveedores WHERE (codigo = ? OR ruc = ?) AND id <> ?');
  $st->execute([$v['codigo'], $v['ruc'], $id === '' ? 0 : (int)$id]);
  if ($st->fetchColumn() > 0) throw new Exception('Ya existe un proveedor con ese código o RUC.');
}]);

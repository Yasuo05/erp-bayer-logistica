<?php require_once __DIR__ . '/../../core/crud_base.php';

require_once __DIR__ . '/clientes_ruc.php';

crud_pagina(['titulo' => 'Clientes', 'tabla' => 'clientes', 'campos' => [
  'codigo'          => ['label' => 'Código', 'req' => true, 'buscar' => true],
  'tipo_doc'        => ['label' => 'Tipo doc.', 'req' => true, 'fk' => ['tipos_documento', 'codigo', 'nombre']],
  'nro_doc'         => ['label' => 'N° documento', 'req' => true, 'buscar' => true],
  'razon_social'    => ['label' => 'Razón social', 'req' => true, 'buscar' => true],
  'tipo_cliente'    => ['label' => 'Tipo de cliente', 'opciones' => ['Empresa' => 'Empresa', 'Fundo' => 'Fundo', 'Cooperativa' => 'Cooperativa', 'Agricultor' => 'Agricultor', 'Distribuidor' => 'Distribuidor']],
  'sector'          => ['label' => 'Sector / cultivo'],
  'departamento_id' => ['label' => 'Departamento', 'fk' => ['departamentos', 'id', 'nombre']],
  'provincia_id'    => ['label' => 'Provincia', 'fk' => ['provincias', 'id', 'nombre']],
  'distrito_id'     => ['label' => 'Distrito', 'fk' => ['distritos', 'id', 'nombre']],
  // Contacto y condiciones de venta (Inf. 15: datos comerciales, contactos y condiciones)
  'contacto'        => ['label' => 'Contacto'],
  'telefono'        => ['label' => 'Teléfono'],
  'correo'          => ['label' => 'Correo', 'tipo' => 'email'],
  'vendedor_id'     => ['label' => 'Vendedor asignado', 'fk' => ['vendedores', 'id', 'apellidos']],
  'limite_credito'  => ['label' => 'Límite de crédito S/ (vacío = sin crédito)', 'tipo' => 'number'],
  'dias_credito'    => ['label' => 'Días de crédito', 'tipo' => 'number'],
  'estado'          => ['label' => 'Estado', 'default' => 'ACTIVO', 'opciones' => ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']],
], 'estado_valores' => ['ACTIVO', 'INACTIVO'], 'validar' => function (array $v, $id) {
  global $pdo;
  $tipo = strtoupper((string)$v['tipo_doc']);
  $nro  = (string)$v['nro_doc'];
  if ($tipo === 'RUC' && !ruc_valido($nro)) {
    throw new Exception('El RUC no es válido: debe tener 11 dígitos, empezar con 10, 15, 16, 17 o 20 y su último dígito es de verificación.');
  }
  if ($tipo === 'DNI' && !preg_match('/^\d{8}$/', $nro)) {
    throw new Exception('El DNI debe tener exactamente 8 dígitos.');
  }
  if ($v['correo'] !== null && !filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) throw new Exception('El correo no es válido.');
  if ($v['limite_credito'] !== null && (!is_numeric($v['limite_credito']) || (float)$v['limite_credito'] < 0)) throw new Exception('El límite de crédito debe ser un monto mayor o igual a cero.');
  if ($v['dias_credito'] !== null && (!ctype_digit((string)$v['dias_credito']) || (int)$v['dias_credito'] > 365)) throw new Exception('Los días de crédito deben ser un número entre 0 y 365.');
  $st = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE tipo_doc = ? AND nro_doc = ? AND id <> ?');
  $st->execute([$tipo, $nro, $id === '' ? 0 : (int)$id]);
  if ($st->fetchColumn() > 0) {
    throw new Exception("Ya existe un cliente con $tipo $nro.");
  }
}]);

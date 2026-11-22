<?php require_once __DIR__ . '/../../core/crud_base.php';
// Sedes asignadas a cada usuario (G4). Un usuario sin sedes asignadas trabaja con todas.
requerir_login();
if ((usuario_actual()['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Solo el administrador gestiona las sedes de los usuarios.'); }
crud_pagina(['titulo' => 'Sedes por usuario', 'tabla' => 'usuario_sucursal', 'campos' => [
  'usuario_id'  => ['label' => 'Usuario', 'req' => true, 'fk' => ['usuarios', 'id', 'nombre']],
  'sucursal_id' => ['label' => 'Sede', 'req' => true, 'fk' => ['sucursales', 'id', 'nombre']],
], 'validar' => function (array $v, $id) {
  global $pdo;
  $st = $pdo->prepare('SELECT COUNT(*) FROM usuario_sucursal WHERE usuario_id = ? AND sucursal_id = ? AND id <> ?');
  $st->execute([$v['usuario_id'], $v['sucursal_id'], $id === '' ? 0 : (int)$id]);
  if ($st->fetchColumn() > 0) throw new Exception('Ese usuario ya tiene asignada esa sede.');
}]);

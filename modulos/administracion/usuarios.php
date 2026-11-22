<?php
/** Usuarios (G4 · Inf. 14): alta, rol, estado, contraseña y sedes asignadas. Solo el administrador. */
require_once __DIR__ . '/../../core/crud_base.php';
requerir_login();
if ((usuario_actual()['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Solo el administrador gestiona usuarios.'); }

function usr_url() { return BASE_URL . '/modulos/administracion/usuarios.php'; }
function usr_flash($t, $m) { $_SESSION['usr_flash'] = [$t, $m]; }
$roles = $pdo->query('SELECT id, nombre FROM roles WHERE estado = 1 ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$yo = (int)usuario_actual()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    try {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        $rol = (int)($_POST['rol_id'] ?? 0);
        $estado = (int)($_POST['estado'] ?? 1) === 1 ? 1 : 0;
        $clave = (string)($_POST['clave'] ?? '');
        if ($nombre === '' || mb_strlen($nombre) > 120) throw new RuntimeException('Indica el nombre (máx. 120 caracteres).');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Indica un correo válido.');
        if (!isset($roles[$rol])) throw new RuntimeException('Selecciona un rol.');
        if (($id === 0 || $clave !== '') && mb_strlen($clave) < 8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?');
        $st->execute([$email, $id]);
        if ($st->fetchColumn() > 0) throw new RuntimeException('Ya existe un usuario con ese correo.');
        if ($id === $yo && ($estado === 0 || mb_strtolower($roles[$rol]) !== 'administrador')) throw new RuntimeException('No puedes desactivarte ni quitarte el rol de administrador.');
        $pdo->beginTransaction();
        if ($id === 0) {
            $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, estado) VALUES (?,?,?,?)')->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT), $estado]);
            $id = (int)$pdo->lastInsertId();
            $accion = 'crear_usuario';
        } else {
            $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ?, estado = ? WHERE id = ?')->execute([$nombre, $email, $estado, $id]);
            if ($clave !== '') $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
            $accion = 'editar_usuario';
        }
        // Un rol por usuario (el login toma un solo rol)
        $pdo->prepare('DELETE FROM usuario_rol WHERE usuario_id = ?')->execute([$id]);
        $pdo->prepare('INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)')->execute([$id, $rol]);
        $pdo->commit();
        auditar('seguridad', $accion, $id, ['email' => $email, 'rol' => $roles[$rol], 'estado' => $estado, 'cambio_clave' => $clave !== '']);
        usr_flash('ok', $accion === 'crear_usuario' ? 'Usuario creado. Asigna sus sedes en «Sedes por usuario» si debe trabajar solo en algunas.' : 'Usuario actualizado.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        usr_flash('err', $e instanceof PDOException ? 'No se pudo guardar el usuario.' : $e->getMessage());
    }
    header('Location: ' . usr_url()); exit;
}

$titulo = 'Usuarios';
require __DIR__ . '/../../core/layout_header.php';
$f = $_SESSION['usr_flash'] ?? null; unset($_SESSION['usr_flash']);
if ($f): ?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($f[1])?>,<?=json_encode($f[0])?>));</script><?php endif;
$usuarios = $pdo->query("SELECT u.id, u.nombre, u.email, u.estado, u.created_at, ur.rol_id, r.nombre rol,
        (SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') FROM usuario_sucursal us JOIN sucursales s ON s.id = us.sucursal_id WHERE us.usuario_id = u.id) sedes,
        (SELECT MAX(a.fecha_hora) FROM auditoria_acciones a WHERE a.usuario_id = u.id AND a.accion = 'inicio_sesion') ultimo_ingreso
    FROM usuarios u LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id LEFT JOIN roles r ON r.id = ur.rol_id ORDER BY u.nombre")->fetchAll();
$editar = null;
foreach ($usuarios as $u) if ((int)$u['id'] === (int)($_GET['editar'] ?? 0)) $editar = $u;
$card = 'background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:20px';
?>
<div class="comercial-card" style="<?=$card?>;padding:22px">
  <h2 style="margin:0 0 12px;font-size:16px"><?= $editar ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
    <label class="form-group"><span class="field-label">Nombre</span><input class="custom-input" name="nombre" required maxlength="120" value="<?=h($editar['nombre'] ?? '')?>"></label>
    <label class="form-group"><span class="field-label">Correo</span><input class="custom-input" type="email" name="email" required value="<?=h($editar['email'] ?? '')?>"></label>
    <label class="form-group"><span class="field-label">Rol</span><select class="custom-select" name="rol_id" required><?php foreach ($roles as $rid => $rn): ?><option value="<?=$rid?>" <?= (int)($editar['rol_id'] ?? 0) === (int)$rid ? 'selected' : '' ?>><?=h($rn)?></option><?php endforeach; ?></select></label>
    <label class="form-group"><span class="field-label">Estado</span><select class="custom-select" name="estado"><option value="1">Activo</option><option value="0" <?= isset($editar) && !(int)$editar['estado'] ? 'selected' : '' ?>>Inactivo</option></select></label>
    <label class="form-group"><span class="field-label"><?= $editar ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></span><input class="custom-input" type="password" name="clave" minlength="8" <?= $editar ? '' : 'required' ?> autocomplete="new-password"></label>
    <button class="btn btn-primary-action"><?= $editar ? 'Guardar cambios' : 'Crear usuario' ?></button>
    <?php if ($editar): ?><a class="btn btn-sec" href="<?=h(usr_url())?>">Cancelar</a><?php endif; ?>
  </form>
  <p class="small-note" style="margin:10px 0 0">El rol define los permisos (Administración › Roles y permisos). Las sedes se asignan en «Sedes por usuario»; sin sedes asignadas, el usuario trabaja con todas.</p>
</div>
<div class="comercial-card" style="<?=$card?>;overflow:hidden"><div style="overflow:auto"><table class="detalle-tabla" style="width:100%;min-width:820px;border-collapse:collapse">
<thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Sedes</th><th>Estado</th><th>Último ingreso</th><th></th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?><tr><td><?=h($u['nombre'])?></td><td><?=h($u['email'])?></td><td><?=h($u['rol'] ?? 'sin rol')?></td><td><?=h($u['sedes'] ?? 'Todas')?></td><td><?= (int)$u['estado'] ? 'Activo' : 'Inactivo' ?></td><td><?=h($u['ultimo_ingreso'] ?? '—')?></td><td><a class="btn btn-sec" href="<?=h(usr_url() . '?editar=' . (int)$u['id'])?>">Editar</a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../../core/layout_footer.php';

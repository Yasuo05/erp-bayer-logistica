<?php
/** Roles y permisos (G4 · Inf. 14): matriz rol × permiso que usa puede() en todos los módulos. Solo el administrador. */
require_once __DIR__ . '/../../core/crud_base.php';
requerir_login();
if ((usuario_actual()['rol'] ?? '') !== 'administrador') { http_response_code(403); exit('Solo el administrador gestiona roles y permisos.'); }

$roles = $pdo->query('SELECT id, nombre, descripcion FROM roles ORDER BY id')->fetchAll();
$permisos = $pdo->query('SELECT id, nombre, descripcion FROM permisos ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    try {
        $marcados = $_POST['p'] ?? [];
        if (!is_array($marcados)) throw new RuntimeException('Datos no válidos.');
        $antes = $pdo->query("SELECT CONCAT(rol_id, '-', permiso_id) FROM rol_permiso")->fetchAll(PDO::FETCH_COLUMN);
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM rol_permiso');
        $ins = $pdo->prepare('INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)');
        $despues = [];
        foreach ($roles as $r) {
            foreach ($permisos as $p) {
                // El rol Administrador conserva siempre todos los permisos (evita quedarse sin acceso)
                $activo = mb_strtolower($r['nombre']) === 'administrador' || isset($marcados[$r['id']][$p['id']]);
                if ($activo) { $ins->execute([$r['id'], $p['id']]); $despues[] = $r['id'] . '-' . $p['id']; }
            }
        }
        $pdo->commit();
        auditar('seguridad', 'editar_permisos', null, ['agregados' => array_values(array_diff($despues, $antes)), 'quitados' => array_values(array_diff($antes, $despues))]);
        $_SESSION['rol_flash'] = ['ok', 'Permisos actualizados. Se aplican desde la siguiente acción de cada usuario.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['rol_flash'] = ['err', $e instanceof PDOException ? 'No se pudieron guardar los permisos.' : $e->getMessage()];
    }
    header('Location: ' . BASE_URL . '/modulos/administracion/roles.php'); exit;
}

$titulo = 'Roles y permisos';
require __DIR__ . '/../../core/layout_header.php';
$f = $_SESSION['rol_flash'] ?? null; unset($_SESSION['rol_flash']);
if ($f): ?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($f[1])?>,<?=json_encode($f[0])?>));</script><?php endif;
$actual = [];
foreach ($pdo->query('SELECT rol_id, permiso_id FROM rol_permiso') as $x) $actual[$x['rol_id']][$x['permiso_id']] = true;
?>
<form method="post" class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden">
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  <div style="overflow:auto"><table class="detalle-tabla" style="width:100%;border-collapse:collapse">
    <thead><tr><th>Rol</th><?php foreach ($permisos as $p): ?><th style="text-align:center" title="<?=h($p['descripcion'] ?? '')?>"><?=h(ucfirst($p['nombre']))?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($roles as $r): $esAdmin = mb_strtolower($r['nombre']) === 'administrador'; ?>
      <tr><td><strong><?=h($r['nombre'])?></strong><div class="small-note"><?=h($r['descripcion'] ?? '')?></div></td>
      <?php foreach ($permisos as $p): ?><td style="text-align:center"><input type="checkbox" name="p[<?=$r['id']?>][<?=$p['id']?>]" value="1" <?= !empty($actual[$r['id']][$p['id']]) || $esAdmin ? 'checked' : '' ?> <?= $esAdmin ? 'disabled title="El administrador conserva todos los permisos"' : '' ?>></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div style="padding:16px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-top:1px solid #e2e8f0">
    <p class="small-note" style="margin:0">Ver: consultar · Crear: registrar documentos y maestros · Editar: modificar, confirmar y cambiar estados · Eliminar: borrar registros. El rol Bayer no entra al ERP: consulta lo publicado en el Portal Bayer.</p>
    <button class="btn btn-primary-action">Guardar permisos</button>
  </div>
</form>
<?php require __DIR__ . '/../../core/layout_footer.php';

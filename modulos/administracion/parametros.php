<?php
/** Parámetros corporativos (G4): IGV, moneda base y vigencia de cotizaciones. Solo el administrador los modifica. */
require_once __DIR__ . '/../../core/crud_base.php';
requerir_login();
if (!in_array(usuario_actual()['rol'] ?? '', ['administrador', 'supervisor', 'gerencia'], true)) { http_response_code(403); exit('Sin acceso a los parámetros.'); }
$puedeEditar = (usuario_actual()['rol'] ?? '') === 'administrador';

// Reglas de validación por parámetro
$reglas = [
    'igv_tasa' => fn($v) => is_numeric($v) && (float)$v >= 0 && (float)$v <= 100 ? null : 'El IGV debe ser un porcentaje entre 0 y 100.',
    'moneda_base' => fn($v) => in_array($v, ['PEN', 'USD'], true) ? null : 'La moneda base debe ser PEN o USD.',
    'vigencia_cotizacion_dias' => fn($v) => ctype_digit($v) && (int)$v >= 1 && (int)$v <= 120 ? null : 'La vigencia debe ser un número de días entre 1 y 120.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    try {
        if (!$puedeEditar) throw new RuntimeException('Solo el administrador modifica los parámetros.');
        $clave = (string)($_POST['clave'] ?? '');
        $valor = strtoupper(trim((string)($_POST['valor'] ?? '')));
        if ($clave !== 'moneda_base') $valor = trim((string)$_POST['valor']);
        if (!isset($reglas[$clave])) throw new RuntimeException('Parámetro no válido.');
        if ($error = $reglas[$clave]($valor)) throw new RuntimeException($error);
        $st = $pdo->prepare('SELECT valor FROM parametros WHERE clave = ?'); $st->execute([$clave]); $antes = $st->fetchColumn();
        $pdo->prepare('UPDATE parametros SET valor = ?, actualizado_por = ? WHERE clave = ?')->execute([$valor, usuario_actual()['id'], $clave]);
        auditar('configuracion', 'editar_parametro', null, ['clave' => $clave, 'antes' => $antes, 'despues' => $valor]);
        $_SESSION['par_flash'] = ['ok', 'Parámetro actualizado.'];
    } catch (Throwable $e) {
        $_SESSION['par_flash'] = ['err', $e instanceof PDOException ? 'No se pudo guardar.' : $e->getMessage()];
    }
    header('Location: ' . BASE_URL . '/modulos/administracion/parametros.php'); exit;
}

$titulo = 'Parámetros';
require __DIR__ . '/../../core/layout_header.php';
$f = $_SESSION['par_flash'] ?? null; unset($_SESSION['par_flash']);
if ($f): ?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($f[1])?>,<?=json_encode($f[0])?>));</script><?php endif;
$filas = $pdo->query('SELECT p.*, u.nombre usuario FROM parametros p LEFT JOIN usuarios u ON u.id = p.actualizado_por ORDER BY p.clave')->fetchAll();
?>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden"><div style="overflow:auto">
<table class="detalle-tabla" style="width:100%;min-width:760px;border-collapse:collapse"><thead><tr><th>Parámetro</th><th>Descripción</th><th>Valor</th><th>Última modificación</th></tr></thead><tbody>
<?php foreach ($filas as $p): ?><tr>
  <td><strong><?=h($p['clave'])?></strong></td><td class="small-note"><?=h($p['descripcion'] ?? '')?></td>
  <td><?php if ($puedeEditar): ?><form method="post" style="display:flex;gap:6px"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="clave" value="<?=h($p['clave'])?>"><input class="custom-input" name="valor" value="<?=h($p['valor'])?>" style="width:110px" required><button class="btn btn-sec">Guardar</button></form><?php else: ?><?=h($p['valor'])?><?php endif; ?></td>
  <td class="small-note"><?=h($p['actualizado_en'])?> <?=h($p['usuario'] ?? '')?></td>
</tr><?php endforeach; ?></tbody></table></div>
<p class="small-note" style="padding:12px 22px;margin:0">El IGV se usa como tasa por defecto en las cotizaciones (Comercial) y es la tasa con la que Facturación emite: una venta con otra tasa no se puede facturar. Los comprobantes ya emitidos conservan la tasa con la que se emitieron.</p></div>
<?php require __DIR__ . '/../../core/layout_footer.php';

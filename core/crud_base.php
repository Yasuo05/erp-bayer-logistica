<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permisos.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function campo_estado() { return ['label' => 'Estado', 'default' => 1, 'opciones' => [1 => 'Activo', 0 => 'Inactivo']]; }

function crud_pagina(array $c) {
    global $pdo;
    requerir_login();
    if (!puede('ver')) { http_response_code(403); exit('Sin acceso.'); }

    $t = $c['tabla']; $pk = $c['pk'] ?? 'id'; $campos = $c['campos'];
    // Valores de «activo» e «inactivo» del campo estado (1/0 por defecto; p. ej. 'ACTIVO'/'INACTIVO' en clientes)
    $ev = $c['estado_valores'] ?? [1, 0];
    $GLOBALS['crud_estado_valores'] = $ev;
    $self = $_SERVER['PHP_SELF'];

    // ACCIÓN AJAX: Cambiar estado (Inline Toggle)
    if (isset($_POST['accion_ajax']) && $_POST['accion_ajax'] === 'toggle_estado') {
        header('Content-Type: application/json');
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Recarga la página.']); exit;
        }
        if (!puede('editar')) {
            echo json_encode(['ok' => false, 'msg' => 'Sin permiso para editar']); exit;
        }
        $id = $_POST['id'] ?? null;
        $nuevoEstado = (int)($_POST['estado'] ?? 0);
        if ($id !== null && isset($campos['estado'])) {
            $st = $pdo->prepare("UPDATE `$t` SET `estado` = ? WHERE `$pk` = ?");
            $st->execute([$nuevoEstado ? $ev[0] : $ev[1], $id]);
            auditar('maestros', 'estado_' . $t, (int)$id, ['tabla' => $t, 'estado' => $nuevoEstado]);
            echo json_encode(['ok' => true, 'nuevo_estado' => $nuevoEstado, 'msg' => 'Estado actualizado a ' . ($nuevoEstado ? 'Activo' : 'Inactivo')]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
        }
        exit;
    }

    // Opciones de selects relacionados (FK)
    $opciones = [];
    foreach ($campos as $n => $f) {
        $v = trim($_POST[$n] ?? ''); if ($v === '' && isset($f['default'])) $v = $f['default'];
        if (isset($f['fk'])) {
            [$ft, $fid, $ftxt] = $f['fk'];
            $opciones[$n] = $pdo->query("SELECT `$fid` id, `$ftxt` txt FROM `$ft` ORDER BY `$ftxt`")->fetchAll(PDO::FETCH_KEY_PAIR);
        } elseif (isset($f['opciones'])) {
            $opciones[$n] = $f['opciones'];
        }
    }

    // Guardar / eliminar
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validar();
        try {
            if (($_POST['accion'] ?? '') === 'eliminar' && puede('eliminar')) {
                $st = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk` = ?"); $st->execute([$_POST[$pk]]); $antes = $st->fetch() ?: [];
                $pdo->prepare("DELETE FROM `$t` WHERE `$pk` = ?")->execute([$_POST[$pk]]);
                auditar('maestros', 'eliminar_' . $t, (int)$_POST[$pk], ['tabla' => $t, 'antes' => $antes]);
                $_SESSION['flash'] = ['ok', 'Registro eliminado correctamente.'];
            } elseif (($_POST['accion'] ?? '') === 'guardar') {
                $id = $_POST[$pk] ?? '';
                $cols = []; $vals = [];
                foreach ($campos as $n => $f) {
                    $v = trim($_POST[$n] ?? '');
                    if (!empty($f['req']) && $v === '') throw new Exception("El campo «{$f['label']}» es obligatorio.");
                    $cols[] = $n; $vals[] = ($v === '' ? null : $v);
                }
                // Validación propia del módulo: recibe los valores y lanza Exception con el mensaje para el usuario
                if (isset($c['validar']) && is_callable($c['validar'])) {
                    $c['validar'](array_combine($cols, $vals), $id);
                }
                if ($id !== '' && puede('editar')) {
                    $set = implode(',', array_map(fn($x) => "`$x`=?", $cols));
                    $st = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk` = ?"); $st->execute([$id]); $antes = $st->fetch() ?: [];
                    $pdo->prepare("UPDATE `$t` SET $set WHERE `$pk`=?")->execute([...$vals, $id]);
                    // Solo se registran los campos que cambiaron (valor anterior → nuevo)
                    $cambios = [];
                    foreach (array_combine($cols, $vals) as $col => $nuevo) {
                        if ((string)($antes[$col] ?? '') !== (string)($nuevo ?? '')) $cambios[$col] = [$antes[$col] ?? null, $nuevo];
                    }
                    auditar('maestros', 'editar_' . $t, (int)$id, ['tabla' => $t, 'cambios' => $cambios]);
                    $_SESSION['flash'] = ['ok', 'Registro actualizado correctamente.'];
                } elseif ($id === '' && puede('crear')) {
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $pdo->prepare("INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES ($ph)")->execute($vals);
                    auditar('maestros', 'crear_' . $t, (int)$pdo->lastInsertId(), ['tabla' => $t, 'valores' => array_combine($cols, $vals)]);
                    $_SESSION['flash'] = ['ok', 'Registro creado con éxito.'];
                } else { throw new Exception('No tienes permiso para realizar esta acción.'); }
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['err', $e->getCode() == 23000 ? 'Operación denegada: dato duplicado o en uso por otra tabla.' : 'Error al guardar el registro.'];
        } catch (Exception $e) {
            $_SESSION['flash'] = ['err', $e->getMessage()];
        }
        $redir = $self . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        header('Location: ' . $redir); exit;
    }

    // Filtros rápidos por estado
    $tieneEstado = isset($campos['estado']);
    $filtroEstado = $_GET['estado'] ?? 'todos';

    // Listado + búsqueda + paginación
    $q = trim($_GET['q'] ?? ''); $pag = max(1, (int)($_GET['p'] ?? 1)); $por = 15;
    $buscables = array_keys(array_filter($campos, fn($f) => !empty($f['buscar'])));
    
    $whereParts = [];
    $params = [];

    if ($q !== '' && $buscables) {
        $whereParts[] = '(' . implode(' OR ', array_map(fn($x) => "`$x` LIKE ?", $buscables)) . ')';
        foreach ($buscables as $_) $params[] = "%$q%";
    }

    if ($tieneEstado && ($filtroEstado === '1' || $filtroEstado === '0')) {
        $whereParts[] = '`estado` = ?';
        $params[] = $filtroEstado === '1' ? $ev[0] : $ev[1];
    }

    $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Exportar a Excel: todos los registros que cumplen la búsqueda y el filtro (no solo la página visible)
    if (($_GET['exportar'] ?? '') === 'xlsx') {
        require_once __DIR__ . '/excel.php';
        $st = $pdo->prepare("SELECT * FROM `$t` $where ORDER BY `$pk` ASC LIMIT 50000"); $st->execute($params);
        $columnas = ['ID' => 'entero'];
        foreach ($campos as $n => $f) {
            if (!empty($f['oculto'])) continue;
            // Encabezado limpio: sin la ayuda entre paréntesis del formulario («Límite de crédito S/ (vacío = sin crédito)» → «Límite de crédito S/»)
            $enc = trim((string)preg_replace('/\s*\(.*\)\s*$/', '', $f['label'])) ?: $n;
            $columnas[$enc] = ($n !== 'estado' && !isset($opciones[$n]) && ($f['tipo'] ?? '') === 'number') ? (str_starts_with($n, 'dias') ? 'entero' : 'decimal')
                            : ((($f['tipo'] ?? '') === 'date') ? 'fecha' : 'texto');
        }
        $salida = [];
        foreach ($st as $r) {
            $fila = [(int)$r[$pk]];
            foreach ($campos as $n => $f) {
                if (!empty($f['oculto'])) continue;
                $v = $r[$n] ?? null;
                if ($n === 'estado') $v = ((string)$v === (string)$ev[0]) ? 'Activo' : 'Inactivo';
                elseif (isset($opciones[$n]) && $v !== null) $v = $opciones[$n][$v] ?? $v;
                $fila[] = $v;
            }
            $salida[] = $fila;
        }
        $base = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $c['titulo']) ?: 'datos'), '_'));
        excel_descargar($base . '_' . excel_ahora('Ymd_Hi') . '.xlsx', excel_generar($c['titulo'], $columnas, $salida));
    }

    // Conteos para píldoras de filtrado rápido
    $conteoTodos = 0; $conteoActivos = 0; $conteoInactivos = 0;
    if ($tieneEstado) {
        $conteoTodos = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $stc = $pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE `estado` = ?");
        $stc->execute([$ev[0]]); $conteoActivos = (int)$stc->fetchColumn();
        $stc->execute([$ev[1]]); $conteoInactivos = (int)$stc->fetchColumn();
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM `$t` $where"); $st->execute($params);
    $total = (int)$st->fetchColumn(); $paginas = max(1, ceil($total / $por));
    $off = ($pag - 1) * $por;
    $st = $pdo->prepare("SELECT * FROM `$t` $where ORDER BY `$pk` DESC LIMIT $por OFFSET $off"); $st->execute($params);
    $filas = $st->fetchAll();

    // Si es petición AJAX para recarga asíncrona de tabla (Paginación suave)
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        renderTablaTbody($filas, $campos, $pk, $opciones, $q, $tieneEstado, $filtroEstado, $self);
        exit;
    }

    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    $titulo = $c['titulo'];
    require __DIR__ . '/layout_header.php';
    ?>

    <!-- TOAST NOTIFICATIONS CONTAINER -->
    <div id="toast-container" class="toast-container"></div>
    <?php if ($flash): ?>
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          mostrarToast(<?= json_encode($flash[1]) ?>, <?= json_encode($flash[0]) ?>);
        });
      </script>
    <?php endif; ?>

    <!-- BARRA SUPERIOR DE ACCIONES Y BÚSQUEDA EN VIVO -->
    <div class="barra">
      <div class="barra-izq">
        <form method="get" class="buscador" id="form-buscador" onsubmit="return false;">
          <?php if ($tieneEstado && $filtroEstado !== 'todos'): ?>
            <input type="hidden" name="estado" id="filtro-estado-val" value="<?= h($filtroEstado) ?>">
          <?php endif; ?>
          <div class="search-input-wrap">
            <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="search" name="q" id="input-live-search" value="<?= h($q) ?>" placeholder="Buscar en vivo en <?= strtolower(h($titulo)) ?>..." autocomplete="off">
            <a href="javascript:void(0)" id="btn-clear-search" class="clear-search" style="<?= $q === '' ? 'display:none;' : '' ?>" onclick="limpiarLiveSearch()" title="Limpiar búsqueda">✕</a>
          </div>
        </form>

        <button type="button" class="btn btn-sec btn-export" onclick="exportarExcel()" title="Descargar en Excel todos los registros de la búsqueda y el filtro actuales">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          <span>Exportar</span>
        </button>
      </div>

      <?php if (puede('crear')): ?>
        <button class="btn btn-primary-action" onclick="crudNuevo()">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          <span>Nuevo <?= h(rtrim($titulo, 's')) ?></span>
        </button>
      <?php endif; ?>
    </div>

    <!-- PÍLDORAS DE FILTRADO RÁPIDO (FACETED FILTERS) -->
    <?php if ($tieneEstado): ?>
      <div class="filter-pills-bar">
        <a href="javascript:void(0)" onclick="filtrarPorEstado('todos')" class="pill-item <?= $filtroEstado === 'todos' ? 'pill-active' : '' ?>" id="pill-todos">
          Todos <span class="pill-count"><?= $conteoTodos ?></span>
        </a>
        <a href="javascript:void(0)" onclick="filtrarPorEstado('1')" class="pill-item <?= $filtroEstado === '1' ? 'pill-active pill-active-green' : '' ?>" id="pill-activos">
          <span class="pill-dot dot-active"></span> Activos <span class="pill-count"><?= $conteoActivos ?></span>
        </a>
        <a href="javascript:void(0)" onclick="filtrarPorEstado('0')" class="pill-item <?= $filtroEstado === '0' ? 'pill-active pill-active-gray' : '' ?>" id="pill-inactivos">
          <span class="pill-dot dot-inactive"></span> Inactivos <span class="pill-count"><?= $conteoInactivos ?></span>
        </a>
      </div>
    <?php endif; ?>

    <!-- TABLA DE DATOS CON ORDENAMIENTO EN VIVO -->
    <div class="tabla-wrap">
      <table id="tabla-datos" class="tabla-interactiva">
        <thead><tr>
          <th style="width: 70px;" class="th-sortable" onclick="ordenarTablaPorColumna(0)">
            <span>#</span> <span class="sort-icon">⇅</span>
          </th>
          <?php 
          $colIdx = 1;
          foreach ($campos as $f): 
            if (!empty($f['oculto'])) continue; 
          ?>
            <th class="th-sortable" onclick="ordenarTablaPorColumna(<?= $colIdx ?>)">
              <span><?= h($f['label']) ?></span> <span class="sort-icon">⇅</span>
            </th>
          <?php 
            $colIdx++;
          endforeach; 
          ?>
          <th style="text-align: right; width: 150px;">Acciones</th>
        </tr></thead>
        <tbody id="tabla-tbody">
          <?php renderTablaTbody($filas, $campos, $pk, $opciones, $q, $tieneEstado, $filtroEstado, $self); ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINACIÓN ASÍNCRONA -->
    <div class="paginacion" id="paginacion-container">
      <?php for ($i = 1; $i <= $paginas; $i++): 
        $queryArr = ['p' => $i];
        if ($q !== '') $queryArr['q'] = $q;
        if ($tieneEstado && $filtroEstado !== 'todos') $queryArr['estado'] = $filtroEstado;
      ?>
        <a class="<?= $i == $pag ? 'activa' : '' ?>" href="javascript:void(0)" onclick="irPaginaAjax(<?= $i ?>)"><?= $i ?></a>
      <?php endfor; ?>
      <span class="paginacion-info" id="total-registros-label"><?= $total ?> registro(s) en total</span>
    </div>

    <!-- MODAL FUSIÓN MAESTRA CENTRADO -->
    <dialog id="modal" class="fusion-modal">
      <div class="fusion-modal-card">
        <!-- Cabecera Corporativa Azul Marino con Ribete Lima -->
        <div class="fusion-modal-header">
          <div class="modal-header-left">
            <div class="modal-header-tag" id="modal-badge">Nuevo Registro</div>
            <h3 id="modal-titulo" class="modal-title">Registrar en <?= h($titulo) ?></h3>
            <p class="modal-subtitle">Completa los campos requeridos con asterisco (<span class="req-star">*</span>) para guardar en la base de datos.</p>
          </div>
          <button type="button" class="modal-close-btn" onclick="crudCerrar()" title="Cerrar (Esc)">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>

        <form method="post" class="fusion-modal-form" id="form-crud">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="<?= h($pk) ?>" id="f-<?= h($pk) ?>">

          <!-- Cuerpo Modular Bento a 2 Columnas -->
          <div class="fusion-modal-body">
            <div class="modal-grid">
              <?php foreach ($campos as $n => $f): 
                $tipo = $f['tipo'] ?? 'text';
                $esFullWidth = ($tipo === 'textarea' || count($campos) <= 2);
                $gridClass = $esFullWidth ? 'col-full' : 'col-half';
              ?>
                <div class="form-group <?= $gridClass ?>">
                  <div class="label-row">
                    <label for="f-<?= h($n) ?>" class="field-label">
                      <?= h($f['label']) ?>
                      <?php if (!empty($f['req'])): ?><span class="req-star">*</span><?php endif; ?>
                    </label>
                    <span class="char-counter" id="counter-<?= h($n) ?>" style="display:none;">0 caract.</span>
                  </div>

                  <div class="input-wrapper">
                    <?php if (isset($opciones[$n])): ?>
                      <select name="<?= h($n) ?>" id="f-<?= h($n) ?>" class="custom-select" <?= !empty($f['req']) ? 'required' : '' ?>>
                        <option value="">— Seleccionar opción —</option>
                        <?php foreach ($opciones[$n] as $k => $txt): ?>
                          <option value="<?= h($k) ?>"><?= h($txt) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <div class="select-chevron">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                      </div>

                    <?php elseif ($tipo === 'textarea'): ?>
                      <textarea name="<?= h($n) ?>" id="f-<?= h($n) ?>" rows="3" class="custom-textarea" placeholder="Ingresa detalles o descripción..."></textarea>

                    <?php else: ?>
                      <input type="<?= h($tipo) ?>" name="<?= h($n) ?>" id="f-<?= h($n) ?>" class="custom-input" <?= $tipo === 'number' ? 'step="any"' : '' ?> <?= !empty($f['req']) ? 'required' : '' ?> placeholder="Escribe aquí...">
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Pie de Acciones con Atajo Visual -->
          <div class="fusion-modal-footer">
            <div class="footer-hint">
              <span class="kbd-hint">Esc</span>
              <span>para cancelar</span>
            </div>
            <div class="footer-actions">
              <button type="button" class="btn btn-sec btn-cancel" onclick="crudCerrar()">Cancelar</button>
              <button type="submit" class="btn btn-save" id="btn-submit">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Guardar Registro</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </dialog>

    <!-- MODAL PERSONALIZADO DE CONFIRMACIÓN DE ELIMINACIÓN -->
    <dialog id="modal-confirm-delete" class="fusion-modal modal-confirm">
      <div class="fusion-modal-card confirm-card">
        <div class="confirm-icon-box">
          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#EF4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <h3 class="confirm-title">¿Eliminar registro?</h3>
        <p class="confirm-desc">Estás a punto de eliminar <b id="del-item-name">este elemento</b>. Esta acción es permanente y no se podrá recuperar.</p>
        
        <form method="post" id="form-delete">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="accion" value="eliminar">
          <input type="hidden" name="<?= h($pk) ?>" id="del-pk-input">
          <div class="confirm-footer">
            <button type="button" class="btn btn-sec" onclick="cerrarConfirmDelete()">Cancelar</button>
            <button type="submit" class="btn btn-peligro-solido">Sí, eliminar</button>
          </div>
        </form>
      </div>
    </dialog>

    <script>
      const CRUD_PK = <?= json_encode($pk) ?>, 
            CRUD_CAMPOS = <?= json_encode(array_keys($campos)) ?>,
            CRUD_TITULO = <?= json_encode($titulo) ?>;
    </script>
    <?php
    require __DIR__ . '/layout_footer.php';
}

// Función auxiliar para renderizar filas del TBODY (reutilizable en carga inicial y AJAX)
function renderTablaTbody($filas, $campos, $pk, $opciones, $q, $tieneEstado, $filtroEstado, $self) {
    if (!$filas) {
        ?>
        <tr>
          <td colspan="<?= count($campos) + 2 ?>" class="vacio-cell">
            <div class="empty-state-wrap">
              <div class="empty-icon-box">
                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
              </div>
              <h4>No se encontraron registros</h4>
              <p>No existen datos<?= $q !== '' ? ' para la búsqueda «' . h($q) . '»' : '' ?> en este módulo.</p>
              <?php if ($q !== '' || ($tieneEstado && $filtroEstado !== 'todos')): ?>
                <a href="<?= h($self) ?>" class="btn btn-sec btn-sm" style="margin-top: 10px;">Limpiar todos los filtros</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php
        return;
    }

    foreach ($filas as $r):
      $idVal = $r[$pk];
    ?>
      <tr data-row-id="<?= h($idVal) ?>" class="tr-data-row">
        <td><span class="row-id">#<?= h($idVal) ?></span></td>
        <?php foreach ($campos as $n => $f): if (!empty($f['oculto'])) continue;
          $v = $r[$n]; 
          if ($n === 'estado') {
              $esActivo = ((string)$v === (string)($GLOBALS['crud_estado_valores'][0] ?? 1));
              ?>
              <td>
                <label class="switch-toggle" title="Cambiar estado en vivo">
                  <input type="checkbox" <?= $esActivo ? 'checked' : '' ?> onchange="toggleEstadoAjax(<?= h($idVal) ?>, this.checked, this)">
                  <span class="slider-round"></span>
                </label>
                <span class="switch-label-text"><?= $esActivo ? 'Activo' : 'Inactivo' ?></span>
              </td>
              <?php
              continue;
          }
          if (isset($opciones[$n])) $v = $opciones[$n][$v] ?? $v; ?>
          <td class="col-val-text"><?= h($v) ?></td>
        <?php endforeach; ?>
        <td class="acciones">
          <?php if (puede('editar')): ?>
            <button class="btn btn-sec btn-sm" onclick='crudEditar(<?= h(json_encode($r)) ?>)' title="Editar registro">
              Editar
            </button>
          <?php endif; ?>
          <?php if (puede('eliminar')): ?>
            <button type="button" class="btn btn-peligro btn-sm" onclick="confirmarEliminar('<?= h($pk) ?>', '<?= h($r[$pk]) ?>', '<?= h(addslashes($r['nombre'] ?? $r['codigo'] ?? '#' . $r[$pk])) ?>')" title="Eliminar registro">
              Eliminar
            </button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach;
}

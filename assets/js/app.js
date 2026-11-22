const modal = document.getElementById('modal');
const modalTitulo = document.getElementById('modal-titulo');
const modalBadge = document.getElementById('modal-badge');
const btnSubmit = document.getElementById('btn-submit');
const modalConfirmDelete = document.getElementById('modal-confirm-delete');

// ===== CRUD MODAL: NUEVO =====
function crudNuevo() {
  if (!modal) return;
  const form = modal.querySelector('form');
  if (form) form.reset();
  
  const pkInput = document.getElementById('f-' + CRUD_PK);
  if (pkInput) pkInput.value = '';
  
  if (modalBadge) {
    modalBadge.textContent = 'Nuevo Registro';
    modalBadge.className = 'modal-header-tag tag-nuevo';
  }
  if (modalTitulo) {
    modalTitulo.textContent = 'Registrar en ' + (typeof CRUD_TITULO !== 'undefined' ? CRUD_TITULO : 'Módulo');
  }
  if (btnSubmit) {
    btnSubmit.querySelector('span').textContent = 'Guardar Registro';
  }
  
  actualizarContadores();
  modal.showModal();
}

// ===== CRUD MODAL: EDITAR =====
function crudEditar(fila) {
  if (!modal) return;
  const pkInput = document.getElementById('f-' + CRUD_PK);
  if (pkInput) pkInput.value = fila[CRUD_PK];

  CRUD_CAMPOS.forEach(c => {
    const el = document.getElementById('f-' + c);
    if (el) {
      el.value = (fila[c] !== null && fila[c] !== undefined) ? fila[c] : '';
    }
  });

  if (modalBadge) {
    modalBadge.textContent = 'Editando #' + fila[CRUD_PK];
    modalBadge.className = 'modal-header-tag tag-edicion';
  }
  if (modalTitulo) {
    modalTitulo.textContent = 'Actualizar ' + (typeof CRUD_TITULO !== 'undefined' ? CRUD_TITULO : 'Registro');
  }
  if (btnSubmit) {
    btnSubmit.querySelector('span').textContent = 'Guardar Cambios';
  }

  actualizarContadores();
  modal.showModal();
}

function crudCerrar() {
  if (modal && modal.open) {
    modal.close();
  }
}

// ===== MODAL DE CONFIRMACIÓN DE ELIMINACIÓN =====
function confirmarEliminar(pkName, pkValue, itemName) {
  if (!modalConfirmDelete) {
    return confirm('¿Confirma que desea eliminar este registro?');
  }
  document.getElementById('del-pk-input').value = pkValue;
  document.getElementById('del-pk-input').name = pkName;
  document.getElementById('del-item-name').textContent = '«' + itemName + '»';
  modalConfirmDelete.showModal();
}

function cerrarConfirmDelete() {
  if (modalConfirmDelete && modalConfirmDelete.open) {
    modalConfirmDelete.close();
  }
}

// ===== SISTEMA DE NOTIFICACIONES TOAST FLOTANTES =====
function mostrarToast(mensaje, tipo = 'ok', duracionMs = 3800) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast-item toast-${tipo}`;

  const icono = tipo === 'ok'
    ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>'
    : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';

  toast.innerHTML = `
    <div class="toast-icon">${icono}</div>
    <div class="toast-content">${mensaje}</div>
    <button type="button" class="toast-close" onclick="this.parentElement.remove()">✕</button>
    <div class="toast-progress"></div>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-leaving');
    setTimeout(() => toast.remove(), 300);
  }, duracionMs);
}

// ===== 1. BÚSQUEDA Y FILTRADO EN VIVO (LIVE SEARCH EN TABLA) =====
let liveSearchTimer = null;
const liveSearchInput = document.getElementById('input-live-search');
const btnClearSearch = document.getElementById('btn-clear-search');

if (liveSearchInput) {
  liveSearchInput.addEventListener('input', (e) => {
    const val = e.target.value.toLowerCase().trim();
    if (btnClearSearch) btnClearSearch.style.display = val ? 'inline' : 'none';

    clearTimeout(liveSearchTimer);
    liveSearchTimer = setTimeout(() => {
      filtrarFilasEnVivo(val);
    }, 150);
  });
}

function filtrarFilasEnVivo(termino) {
  const tbody = document.getElementById('tabla-tbody');
  if (!tbody) return;
  const filas = tbody.querySelectorAll('.tr-data-row');

  let visibles = 0;
  filas.forEach(fila => {
    const textoFila = fila.innerText.toLowerCase();
    const coincide = textoFila.includes(termino);
    fila.style.display = coincide ? '' : 'none';
    if (coincide) visibles++;
  });

  let emptyRow = document.getElementById('live-empty-row');
  if (visibles === 0 && filas.length > 0) {
    if (!emptyRow) {
      emptyRow = document.createElement('tr');
      emptyRow.id = 'live-empty-row';
      emptyRow.innerHTML = `
        <td colspan="100" class="vacio-cell">
          <div class="empty-state-wrap" style="padding:40px 20px;">
            <h4>Sin coincidencias</h4>
            <p>No se encontraron resultados locales para «<b>${termino}</b>».</p>
          </div>
        </td>
      `;
      tbody.appendChild(emptyRow);
    }
    emptyRow.style.display = '';
  } else if (emptyRow) {
    emptyRow.style.display = 'none';
  }
}

function limpiarLiveSearch() {
  if (liveSearchInput) {
    liveSearchInput.value = '';
    if (btnClearSearch) btnClearSearch.style.display = 'none';
    filtrarFilasEnVivo('');
  }
}

// ===== 3. SWITCH RÁPIDO DE ESTADO EN VIVO (INLINE TOGGLE AJAX) =====
function toggleEstadoAjax(id, nuevoEstadoBool, checkboxEl) {
  const nuevoEstadoNum = nuevoEstadoBool ? 1 : 0;
  const labelText = checkboxEl.closest('td').querySelector('.switch-label-text');
  
  checkboxEl.disabled = true;

  const formData = new FormData();
  formData.append('accion_ajax', 'toggle_estado');
  formData.append('csrf', document.querySelector('input[name="csrf"]')?.value || '');
  formData.append('id', id);
  formData.append('estado', nuevoEstadoNum);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    checkboxEl.disabled = false;
    if (data.ok) {
      if (labelText) {
        labelText.textContent = nuevoEstadoNum ? 'Activo' : 'Inactivo';
      }
      mostrarToast(data.msg, 'ok', 2600);
    } else {
      checkboxEl.checked = !nuevoEstadoBool;
      mostrarToast(data.msg || 'No se pudo actualizar el estado', 'err');
    }
  })
  .catch(err => {
    checkboxEl.disabled = false;
    checkboxEl.checked = !nuevoEstadoBool;
    mostrarToast('Error de red al actualizar estado', 'err');
  });
}

// ===== 4. ORDENAMIENTO DINÁMICO DE COLUMNAS (CLICK TO SORT) =====
let ordenAsc = true;
let ultimaColumnaIndex = -1;

function ordenarTablaPorColumna(n) {
  const tabla = document.getElementById('tabla-datos');
  if (!tabla) return;
  const tbody = document.getElementById('tabla-tbody');
  const filas = Array.from(tbody.querySelectorAll('.tr-data-row'));

  if (ultimaColumnaIndex === n) {
    ordenAsc = !ordenAsc;
  } else {
    ordenAsc = true;
    ultimaColumnaIndex = n;
  }

  // Actualizar indicadores visuales en los th
  tabla.querySelectorAll('.th-sortable .sort-icon').forEach((icon, idx) => {
    if (idx === n) {
      icon.textContent = ordenAsc ? '▲' : '▼';
      icon.style.color = 'var(--bayer-green)';
    } else {
      icon.textContent = '⇅';
      icon.style.color = '';
    }
  });

  filas.sort((a, b) => {
    const celdaA = a.children[n]?.innerText.trim() || '';
    const celdaB = b.children[n]?.innerText.trim() || '';

    // Comparar como número si ambos son numéricos
    const numA = parseFloat(celdaA.replace(/[^0-9.-]+/g, ''));
    const numB = parseFloat(celdaB.replace(/[^0-9.-]+/g, ''));

    if (!isNaN(numA) && !isNaN(numB) && !celdaA.includes(' ') && !celdaB.includes(' ')) {
      return ordenAsc ? numA - numB : numB - numA;
    }
    return ordenAsc ? celdaA.localeCompare(celdaB, 'es') : celdaB.localeCompare(celdaA, 'es');
  });

  filas.forEach(f => tbody.appendChild(f));
}

// ===== 6. PAGINACIÓN Y FILTRADO POR ESTADO ASÍNCRONO (AJAX PAGINATION) =====
let estadoActualFiltro = 'todos';

function filtrarPorEstado(estado) {
  estadoActualFiltro = estado;
  
  // Actualizar clases de píldoras
  ['todos', '1', '0'].forEach(st => {
    const el = document.getElementById(st === 'todos' ? 'pill-todos' : (st === '1' ? 'pill-activos' : 'pill-inactivos'));
    if (el) {
      el.classList.toggle('pill-active', st === estado);
      if (st === '1') el.classList.toggle('pill-active-green', estado === '1');
      if (st === '0') el.classList.toggle('pill-active-gray', estado === '0');
    }
  });

  irPaginaAjax(1);
}

function irPaginaAjax(pagina) {
  const tbody = document.getElementById('tabla-tbody');
  if (!tbody) return;

  const url = new URL(window.location.href);
  url.searchParams.set('p', pagina);
  if (estadoActualFiltro !== 'todos') {
    url.searchParams.set('estado', estadoActualFiltro);
  } else {
    url.searchParams.delete('estado');
  }
  url.searchParams.set('ajax', '1');

  tbody.style.opacity = '0.35';
  tbody.style.transition = 'opacity 0.2s';

  fetch(url.toString())
    .then(r => r.text())
    .then(html => {
      tbody.innerHTML = html;
      tbody.style.opacity = '1';

      // Actualizar URL del navegador sin recargar
      url.searchParams.delete('ajax');
      window.history.pushState({}, '', url.toString());

      // Actualizar paginador activo
      const paginador = document.getElementById('paginacion-container');
      if (paginador) {
        paginador.querySelectorAll('a').forEach(a => {
          a.classList.toggle('activa', a.textContent.trim() == pagina);
        });
      }

      if (liveSearchInput && liveSearchInput.value) {
        filtrarFilasEnVivo(liveSearchInput.value.toLowerCase().trim());
      }
    })
    .catch(() => {
      tbody.style.opacity = '1';
      window.location.href = url.toString().replace('&ajax=1', '').replace('?ajax=1', '');
    });
}

// ===== EXPORTACIÓN A EXCEL =====
// El servidor arma un .xlsx con todos los registros que cumplen la búsqueda y el filtro de estado actuales
// (antes se copiaba la tabla visible a un CSV con comas, que Excel en español abre en una sola columna).
function exportarExcel() {
  const url = new URL(window.location.href);
  ['p', 'ajax', 'estado'].forEach((k) => url.searchParams.delete(k));
  const buscador = document.getElementById('input-live-search');
  if (buscador && buscador.value.trim() !== '') url.searchParams.set('q', buscador.value.trim()); else url.searchParams.delete('q');
  const pill = document.querySelector('.pill-item.pill-active');
  const estado = pill ? ({ 'pill-activos': '1', 'pill-inactivos': '0' })[pill.id] : null;
  if (estado) url.searchParams.set('estado', estado);
  url.searchParams.set('exportar', 'xlsx');
  window.location.href = url.toString();
  mostrarToast('Preparando el archivo de Excel…', 'ok');
}

// ===== CONTADOR DE CARACTERES =====
function actualizarContadores() {
  document.querySelectorAll('.custom-input, .custom-textarea').forEach(input => {
    const id = input.id.replace('f-', '');
    const counter = document.getElementById('counter-' + id);
    if (counter) {
      const len = input.value.length;
      counter.textContent = `${len} caract.`;
      counter.style.display = 'inline';
    }
  });
}

document.addEventListener('input', (e) => {
  if (e.target.matches('.custom-input, .custom-textarea')) {
    const id = e.target.id.replace('f-', '');
    const counter = document.getElementById('counter-' + id);
    if (counter) {
      const len = e.target.value.length;
      counter.textContent = `${len} caract.`;
    }
  }
});

// Cerrar modales en backdrop
[modal, modalConfirmDelete].forEach(dlg => {
  if (dlg) {
    dlg.addEventListener('click', (e) => {
      const rect = dlg.getBoundingClientRect();
      if (
        e.clientX < rect.left ||
        e.clientX > rect.right ||
        e.clientY < rect.top ||
        e.clientY > rect.bottom
      ) {
        dlg.close();
      }
    });
  }
});

// ===== MENÚ RESPONSIVO MÓVIL / TABLET =====
(function () {
  const toggleBtn = document.getElementById('nav-toggle-btn');
  const navLinks = document.getElementById('nav-links');
  if (!toggleBtn || !navLinks) return;

  toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = navLinks.classList.toggle('open');
    toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  navLinks.querySelectorAll('.dropdown-trigger').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      if (window.innerWidth <= 1440) {
        e.preventDefault();
        e.stopPropagation();
        const parent = btn.closest('.nav-dropdown');
        if (!parent) return;
        const wasOpen = parent.classList.contains('open');
        navLinks.querySelectorAll('.nav-dropdown.open').forEach((d) => d.classList.remove('open'));
        if (!wasOpen) parent.classList.add('open');
      }
    });
  });

  document.addEventListener('click', (e) => {
    if (navLinks.classList.contains('open') && !navLinks.contains(e.target) && !toggleBtn.contains(e.target)) {
      navLinks.classList.remove('open');
      toggleBtn.setAttribute('aria-expanded', 'false');
    }
  });
})();

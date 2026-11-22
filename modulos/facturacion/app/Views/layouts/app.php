<?php
/**
 * Layout del módulo: se muestra dentro del marco común del ERP (core/erp_marco.php),
 * con la misma barra superior, menú, selector de sede y migas de pan que el resto del sistema.
 * Las opciones del módulo están en el grupo «Facturación» del menú del ERP (core/menu.php).
 *
 * @var string $titulo
 * @var string $contenido
 * @var array  $menu
 * @var string $rutaActual
 * @var array  $sesion
 */

require_once dirname(BASE_PATH, 2) . '/core/erp_marco.php';

$opcionActual = \App\Core\Menu::buscar($rutaActual);
erp_marco_cabecera([
    'pdo'      => \App\Core\Database::conexion(),
    'usuario'  => $_SESSION['usuario'] ?? ['nombre' => $sesion['usuario'] ?? '', 'rol' => $sesion['rol'] ?? ''],
    'titulo'   => $rutaActual === '/' ? 'Panel de facturación' : ($opcionActual['titulo'] ?? $titulo),
    'embebido' => true,
    // ?v= cambia cada vez que se edita el CSS: el navegador no se queda con la versión anterior
    'css'      => [asset('css/app.css') . '?v=' . filemtime(BASE_PATH . '/public/assets/css/app.css')],
    'head'     => '<link rel="icon" href="data:,">',
]);
?>
<div class="fx-pagina">
    <?= $contenido ?>
</div>
<script>
    // Acciones que no se pueden deshacer: el formulario lleva data-confirmar con la pregunta.
    // Además se evita el doble envío (doble clic) desactivando el botón mientras se procesa.
    document.addEventListener('submit', (evento) => {
        // Si otro código ya canceló el envío (ej. onsubmit="return confirm(...)" y se eligió «Cancelar»), no se bloquea nada
        if (evento.defaultPrevented) return;
        const formulario = evento.target;
        const pregunta = formulario.dataset.confirmar;
        if (pregunta && !window.confirm(pregunta)) {
            evento.preventDefault();
            return;
        }
        if ((formulario.getAttribute('method') || '').toLowerCase() === 'post') {
            formulario.querySelectorAll('button[type="submit"], button:not([type])').forEach((boton) => {
                setTimeout(() => { boton.disabled = true; }, 0);
            });
        }
    });
</script>
<?php erp_marco_pie();

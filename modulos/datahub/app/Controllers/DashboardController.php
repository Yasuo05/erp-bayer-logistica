<?php
namespace App\Controllers;

use App\Services\DashboardService;

class DashboardController
{
    public function index(): void
    {
        \require_auth();
        \sync_erp();
        $service = new DashboardService();
        $periodo = \input('periodo');
        // Bayer (proveedor externo) solo ve lo que Pucchún ya publicó (Inf. 20 · portal Bayer)
        $pub = \has_role('BAYER');

        // Sede activa del selector de la cabecera del ERP (la sesión se comparte con los módulos).
        // null = «Todas las sedes». El rol BAYER siempre ve el consolidado de lo publicado.
        $sede = null;
        if (!$pub) {
            $s = $_SESSION['erp_context']['sede']['id'] ?? ($_SESSION['sede_activa'] ?? null);
            $sede = $s !== null ? (int)$s : null;
        }

        \view('dashboard.puchun.index', [
            'kpis' => $service->kpis($pub, $sede),
            'series' => $service->salesByMonth($pub, 12, $sede),
            'top' => $service->topProducts(50, $pub, $periodo, true, $sede),
            'ultimos_documentos' => $service->recentDocuments(8, $pub, $sede),
            'inventario_alertas' => $service->inventoryAlerts(25, $pub, $sede),
            'ventas_sede' => $service->salesByBranch($periodo, true, $pub, $sede),
            'catalogo_split' => $service->catalogSplit($periodo, true, $pub, $sede),
            'selected_periodo' => $periodo,
            // Operación interna (cobranzas, caja, bancos, compras, CxC/CxP): no se muestra al rol BAYER
            'operacion' => \has_role('BAYER') ? null : $service->operacionErp($sede),
        ]);
    }
}

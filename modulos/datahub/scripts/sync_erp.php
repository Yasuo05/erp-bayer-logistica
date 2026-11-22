<?php
/**
 * Sincroniza el Data Hub con la operación del ERP (comprobantes, despachos y stock).
 * Uso: php scripts/sync_erp.php   (se puede programar con cron / Programador de tareas)
 */
require dirname(__DIR__) . '/config/bootstrap.php';
$r = (new App\Services\ErpSyncService())->run();
foreach ($r as $k => $v) echo str_pad($k, 12) . ": $v\n";

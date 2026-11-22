<?php
namespace App\Repositories;

class StockRepository
{
    public function all(): array
    {
        return \db()->query("SELECT 
            sc.id,
            sc.fecha_stock,
            sc.estado_registro,
            a.nombre AS almacen,
            COUNT(sd.id) AS items,
            SUM(sd.cantidad) AS cantidad 
        FROM stock_cabecera sc 
        JOIN almacenes a ON a.id = sc.almacen_id 
        JOIN stock_detalle sd ON sd.stock_id = sc.id 
        GROUP BY sc.id 
        ORDER BY sc.id DESC 
        LIMIT 300")->fetchAll();
    }
}

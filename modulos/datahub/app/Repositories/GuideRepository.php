<?php
namespace App\Repositories;

class GuideRepository
{
    public function all(): array
    {
        return \db()->query("SELECT 
            gc.id,
            gc.numero,
            gc.fecha,
            gc.estado_registro,
            c.razon_social AS cliente,
            v.nombres AS vendedor,
            s.nombre AS sucursal,
            COUNT(gd.id) AS items,
            SUM(gd.cantidad) AS cantidad 
        FROM guias_cabecera gc 
        JOIN clientes c ON c.id = gc.cliente_id 
        JOIN vendedores v ON v.id = gc.vendedor_id 
        JOIN sucursales s ON s.id = gc.sucursal_id 
        JOIN guias_detalle gd ON gd.guia_id = gc.id 
        GROUP BY gc.id 
        ORDER BY gc.id DESC 
        LIMIT 300")->fetchAll();
    }
}

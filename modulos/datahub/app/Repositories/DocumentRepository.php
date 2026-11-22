<?php
namespace App\Repositories;

class DocumentRepository
{
    public function all(): array
    {
        return \db()->query("SELECT 
            dc.id,
            dc.numero,
            dc.fecha,
            dc.monto_total AS importe,
            dc.estado_comercial,
            dc.estado_registro,
            c.razon_social AS cliente,
            v.nombres AS vendedor,
            s.nombre AS sucursal,
            COUNT(dd.id) AS items,
            SUM(dd.cantidad) AS cantidad 
        FROM documentos_cabecera dc 
        JOIN clientes c ON c.id = dc.cliente_id 
        JOIN vendedores v ON v.id = dc.vendedor_id 
        JOIN sucursales s ON s.id = dc.sucursal_id 
        JOIN documentos_detalle dd ON dd.documento_id = dc.id 
        GROUP BY dc.id 
        ORDER BY dc.id DESC 
        LIMIT 300")->fetchAll();
    }
}

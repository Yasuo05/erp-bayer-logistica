<?php
// Revisión de importes antes de emitir, anulación local y cambios manuales de correlativos.
declare(strict_types=1);

use App\Repositories\FacturacionRepository;
use App\Repositories\SeriesRepository;
use App\Core\Database;
use App\Services\DocumentoElectronicoService;
use App\Services\FacturacionService;
use App\Services\PdfComprobanteService;
use App\Services\SeriesService;
use Tests\Escenario;
use Tests\Prueba;

return function (Prueba $t, Escenario $e): void {
    $t->grupo('Revisión de importes antes de emitir');
    $servicio = new FacturacionService();
    $venta = (new FacturacionRepository())->venta($e->venta('RUC'));
    $revision = $servicio->revisarVenta($venta);
    $t->verdadero($revision['tipo'] === 'factura' && $revision['serie'] !== null && $revision['importes']['total'] > 0, 'la revisión muestra tipo, serie prevista y total');
    $argumentos = [$revision['importes']['total'], $revision['tasa'], $revision['huella']];
    $emitir = static fn (string $tipo = 'factura', ?float $total = null, ?float $tasa = null, ?string $huella = null): int => $servicio->emitirVenta(
        (int) $venta['id'], $tipo, $total ?? $argumentos[0], $tasa ?? $argumentos[1], $huella ?? $argumentos[2]
    );
    $t->falla(fn () => $emitir('factura', $revision['importes']['total'] + 1), 'cambiaron', 'no emite si el total difiere de lo revisado');
    $t->falla(fn () => $emitir('factura', null, $revision['tasa'] + 0.01), 'tasa', 'no emite si la tasa de IGV difiere de la revisada');
    $t->falla(fn () => $emitir('boleta'), 'factura', 'un cliente con RUC no recibe boleta');

    $db = Database::conexion();
    $nombreOriginal = (string) $venta['cliente'];
    $db->prepare('UPDATE demo_clientes SET nombre=? WHERE id=?')->execute([$nombreOriginal . ' CAMBIADO', $venta['cliente_id']]);
    $t->falla(fn () => $emitir(), 'cambiaron', 'la huella rechaza un cambio de nombre aunque los importes no cambien');
    $db->prepare('UPDATE demo_clientes SET nombre=? WHERE id=?')->execute([$nombreOriginal, $venta['cliente_id']]);

    $q = $db->prepare('SELECT id,cantidad,precio_unitario FROM demo_venta_detalles WHERE venta_id=? ORDER BY id LIMIT 1');
    $q->execute([$venta['id']]);
    $lineaOriginal = $q->fetch(PDO::FETCH_ASSOC);
    $cantidadOriginal = (float) $lineaOriginal['cantidad'];
    $precioOriginal = (float) $lineaOriginal['precio_unitario'];
    $db->prepare('UPDATE demo_venta_detalles SET cantidad=?,precio_unitario=? WHERE id=?')->execute([$cantidadOriginal / 2, $precioOriginal * 2, $lineaOriginal['id']]);
    try {
        $ventaAlterada = (new FacturacionRepository())->venta((int) $venta['id']);
        $importeAlterado = $servicio->revisarVenta($ventaAlterada)['importes']['total'];
        $t->igual($revision['importes']['total'], $importeAlterado, 'el cambio de cantidad y precio de línea mantiene el total');
        $t->falla(fn () => $emitir(), 'cambiaron', 'la huella rechaza una línea modificada aunque el total sea igual');
    } finally {
        $db->prepare('UPDATE demo_venta_detalles SET cantidad=?,precio_unitario=? WHERE id=?')->execute([$cantidadOriginal, $precioOriginal, $lineaOriginal['id']]);
    }

    $documentoOriginal = (string) $venta['numero_documento'];
    $documentoInvalido = substr($documentoOriginal, 0, 10) . (((int) $documentoOriginal[10] + 1) % 10);
    $db->prepare('UPDATE demo_clientes SET numero_documento=? WHERE id=?')->execute([$documentoInvalido, $venta['cliente_id']]);
    $t->falla(fn () => $emitir(), 'RUC', 'valida el dígito verificador del RUC al emitir');
    $db->prepare('UPDATE demo_clientes SET numero_documento=? WHERE id=?')->execute([$documentoOriginal, $venta['cliente_id']]);

    $ventaDniId = $e->venta('DNI');
    $ventaDni = (new FacturacionRepository())->venta($ventaDniId);
    $revisionDni = $servicio->revisarVenta($ventaDni);
    $db->prepare('UPDATE demo_clientes SET numero_documento=? WHERE id=?')->execute(['1234567', $ventaDni['cliente_id']]);
    try {
        $t->falla(fn () => $servicio->emitirVenta($ventaDniId, 'boleta', $revisionDni['importes']['total'], $revisionDni['tasa'], $revisionDni['huella']), 'DNI', 'valida ocho dígitos de DNI al emitir');
    } finally {
        $db->prepare('UPDATE demo_clientes SET numero_documento=? WHERE id=?')->execute([$ventaDni['numero_documento'], $ventaDni['cliente_id']]);
    }

    $id = $emitir();
    $t->igual($revision['importes']['total'], (float) $e->valor('SELECT total FROM facturacion_comprobantes WHERE id = ?', [$id]), 'emite exactamente el total revisado');
    $t->falla(fn () => $emitir(), 'ya fue facturada', 'una venta se factura una sola vez');

    $t->grupo('Anulación antes del envío');
    (new FacturacionRepository())->anularBorradorDemo($id, 'Error en la venta');
    $t->igual('anulado_local', $e->estado($id), 'un borrador se anula localmente');
    $t->falla(fn () => (new DocumentoElectronicoService())->generarXml($id), 'borrador', 'un anulado ya no genera XML');
    $marca = new ReflectionMethod(PdfComprobanteService::class, 'marcaAgua');
    $t->igual('ANULADO', $marca->invoke(new PdfComprobanteService(), 'anulado_local', null), 'su PDF lleva la marca «ANULADO»');

    $t->grupo('Series y correlativos');
    $series = new SeriesService();
    $nombre = 'Z' . random_int(100, 999);
    // Serie de nota de débito con letra Z: nunca se usa al emitir (las notas usan F o B)
    $serieId = $series->crear(['tipo' => 'nota_debito', 'serie' => $nombre, 'siguiente' => 1, 'descripcion' => Escenario::MARCA]);
    $t->igual('Creación de la serie.', $e->valor('SELECT motivo FROM facturacion_series_cambios WHERE serie_id = ? ORDER BY id LIMIT 1', [$serieId]), 'la creación queda en el historial');
    $t->falla(fn () => $series->actualizar($serieId, ['siguiente' => 5, 'descripcion' => Escenario::MARCA, 'motivo' => '']), 'motivo', 'cambiar el correlativo exige motivo');
    $series->actualizar($serieId, ['siguiente' => 5, 'descripcion' => Escenario::MARCA, 'motivo' => 'Prueba automática']);
    $cambio = [(int) $e->valor('SELECT valor_anterior FROM facturacion_series_cambios WHERE serie_id = ? ORDER BY id DESC LIMIT 1', [$serieId]),
        (int) $e->valor('SELECT valor_nuevo FROM facturacion_series_cambios WHERE serie_id = ? ORDER BY id DESC LIMIT 1', [$serieId])];
    $t->igual([1, 5], $cambio, 'el historial guarda valor anterior y nuevo');
    $t->falla(fn () => (new SeriesRepository())->reservar('factura'), 'transacción', 'no se reserva un número fuera de una transacción');
};

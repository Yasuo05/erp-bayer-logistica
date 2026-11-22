<?php
/**
 * Plantilla del PDF del comprobante (la convierte Dompdf, ver PdfComprobanteService).
 * Dompdf no entiende flex ni grid: el diseño se arma con tablas.
 *
 * @var array       $comprobante  comprobante con 'detalles' (FacturacionRepository::comprobante)
 * @var array       $emisor       emisor tal como estaba al emitir (datos_emision del comprobante)
 * @var array|null  $xml          facturacion_xml (nombre, hash) o null si aún no se generó
 * @var string      $titulo       "FACTURA ELECTRÓNICA", …
 * @var string      $numero       serie-número con ceros: F001-00000001
 * @var string|null $referencia   comprobante que modifica una nota
 * @var float       $tasaIgv      18.0
 * @var string      $montoLetras  "SON … SOLES"
 * @var string|null $marcaAgua    texto diagonal si el PDF no es válido como comprobante
 * @var bool        $simulado     la respuesta del último envío la generó el simulador
 */

$soles    = static fn ($valor): string => number_format((float) $valor, 2, '.', ',');
$cantidad = static fn ($valor): string => rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
$tiposReferencia = \App\Controllers\ConsultaController::TIPOS;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e($titulo . ' ' . $numero) ?></title>
    <style>
        @page { margin: 28px 32px 36px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .marca-agua {
            position: fixed; top: 330px; left: 0; right: 0;
            text-align: center; font-size: 48px; font-weight: bold; color: #dc2626;
            opacity: 0.12; transform: rotate(-28deg);
        }

        .emisor-nombre { font-size: 15px; font-weight: bold; color: #111827; }
        .emisor-comercial { margin-top: 2px; font-size: 10.5px; color: #1275dd; }
        .emisor-dato { margin-top: 3px; color: #4b5563; }

        .caja { border: 1.5px solid #1275dd; border-radius: 6px; text-align: center; }
        .caja-ruc { padding: 9px 6px 6px; font-size: 11px; font-weight: bold; }
        .caja-tipo { padding: 6px; background: #1275dd; color: #ffffff; font-size: 10.5px; font-weight: bold; }
        .caja-numero { padding: 7px 6px 9px; font-size: 13px; font-weight: bold; }

        .datos { margin-top: 16px; border: 1px solid #e5e7eb; }
        .datos td { padding: 5px 9px; }
        .etiqueta { width: 125px; color: #6b7280; }

        .detalle { margin-top: 14px; }
        .detalle th { padding: 6px; background: #1275dd; color: #ffffff; font-size: 8.5px; text-align: left; }
        .detalle td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; }

        .resumen { margin-top: 14px; }
        .letras { padding: 7px 9px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold; }
        .hash { margin-top: 10px; color: #4b5563; word-wrap: break-word; }
        .qr { width: 86px; height: 86px; margin-top: 10px; border: 1px dashed #9ca3af; color: #9ca3af; font-size: 7.5px; text-align: center; }
        .totales td { padding: 5px 9px; border-bottom: 1px solid #e5e7eb; }
        .totales .total td { background: #eaf3fd; font-size: 11px; font-weight: bold; }

        .pie { margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <?php if ($marcaAgua): ?>
        <div class="marca-agua"><?= e($marcaAgua) ?></div>
    <?php endif ?>

    <!-- Emisor y recuadro del comprobante -->
    <table>
        <tr>
            <td style="width: 62%; padding-right: 18px;">
                <div class="emisor-nombre"><?= e($emisor['razon_social']) ?></div>
                <?php if (!empty($emisor['nombre_comercial'])): ?>
                    <div class="emisor-comercial"><?= e($emisor['nombre_comercial']) ?></div>
                <?php endif ?>
                <div class="emisor-dato"><?= e($emisor['direccion']) ?></div>
                <div class="emisor-dato"><?= e($emisor['distrito'] . ' - ' . $emisor['provincia'] . ' - ' . $emisor['departamento']) ?></div>
            </td>
            <td>
                <div class="caja">
                    <div class="caja-ruc">RUC <?= e($emisor['ruc']) ?></div>
                    <div class="caja-tipo"><?= e($titulo) ?></div>
                    <div class="caja-numero"><?= e($numero) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Cliente y datos generales -->
    <table class="datos">
        <tr>
            <td class="etiqueta">Cliente</td>
            <td><strong><?= e($comprobante['cliente']) ?></strong></td>
            <td class="etiqueta">Fecha de emisión</td>
            <td><?= e(date('d/m/Y H:i', strtotime($comprobante['fecha_emision']))) ?></td>
        </tr>
        <tr>
            <td class="etiqueta"><?= e($comprobante['tipo_documento']) ?></td>
            <td><?= e($comprobante['numero_documento']) ?></td>
            <td class="etiqueta">Moneda</td>
            <td>SOLES</td>
        </tr>
        <?php if ($referencia): ?>
            <tr>
                <td class="etiqueta">Documento que modifica</td>
                <td><?= e(($tiposReferencia[$comprobante['tipo_referencia']] ?? '') . ' ' . $referencia) ?></td>
                <td class="etiqueta">Motivo</td>
                <td><?= e((string) $comprobante['motivo']) ?></td>
            </tr>
        <?php endif ?>
    </table>

    <!-- Detalle -->
    <table class="detalle">
        <thead>
            <tr>
                <th class="num" style="width: 9%;">Cant.</th>
                <th style="width: 8%;">U.M.</th>
                <th style="width: 13%;">Código</th>
                <th>Descripción</th>
                <th class="num" style="width: 13%;">V. unitario</th>
                <th class="num" style="width: 13%;">V. venta</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comprobante['detalles'] as $linea): ?>
                <tr>
                    <td class="num"><?= e($cantidad($linea['cantidad'])) ?></td>
                    <td><?= e($linea['unidad_medida'] ?? '') ?></td>
                    <td><?= e($linea['codigo_producto'] ?? '') ?></td>
                    <td><?= e($linea['descripcion']) ?></td>
                    <td class="num"><?= e($soles($linea['precio_unitario'])) ?><?php if ((float) ($linea['descuento'] ?? 0) > 0): ?><br><small>Dscto. <?= e($soles($linea['descuento'])) ?></small><?php endif ?></td>
                    <td class="num"><?= e($soles($linea['subtotal'])) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <!-- Importe en letras, hash, QR y totales -->
    <table class="resumen">
        <tr>
            <td style="padding-right: 18px;">
                <div class="letras"><?= e($montoLetras) ?></div>

                <?php if ($xml && $xml['hash']): ?>
                    <div class="hash"><strong>Resumen (hash):</strong> <?= e($xml['hash']) ?></div>
                <?php else: ?>
                    <div class="hash">Resumen (hash): se mostrará cuando el XML esté firmado.</div>
                <?php endif ?>

                <table style="width: auto;"><tr><td class="qr"><br><br>Código QR<br>(pendiente de la integración SUNAT)</td></tr></table>
            </td>
            <td style="width: 38%;">
                <table class="totales">
                    <tr><td>Op. gravada</td><td class="num">S/ <?= e($soles($comprobante['subtotal'])) ?></td></tr>
                    <tr><td>IGV (<?= e(rtrim(rtrim(number_format($tasaIgv, 2, '.', ''), '0'), '.')) ?>%)</td><td class="num">S/ <?= e($soles($comprobante['impuesto'])) ?></td></tr>
                    <tr class="total"><td>Importe total</td><td class="num">S/ <?= e($soles($comprobante['total'])) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="pie">
        Representación impresa del comprobante electrónico <?= e($numero) ?> · Estado: <?= e(estado_comprobante($comprobante['estado'])) ?><br>
        <?php if ($simulado): ?>
            Respuesta simulada: este comprobante no fue enviado a SUNAT y no tiene validez tributaria.
        <?php elseif ($marcaAgua === 'SIN VALIDEZ TRIBUTARIA'): ?>
            Este comprobante aún no ha sido aceptado por SUNAT.
        <?php endif ?>
    </div>
</body>
</html>

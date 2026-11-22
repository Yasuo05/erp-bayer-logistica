<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Revisa el XML UBL 2.1 antes de guardarlo:
 *  1. estructura: elementos obligatorios de SUNAT presentes y en su lugar;
 *  2. esquema XSD oficial de UBL 2.1, si está en storage/xsd (opcional);
 *  3. coincidencia: serie, número, fecha, emisor, cliente, importes y líneas iguales a los del comprobante.
 */
final class ValidadorXmlService
{
    /** Esquemas de OASIS UBL 2.1 (carpeta xsd/ del paquete oficial) copiados en storage/xsd */
    private const ESQUEMAS = [
        'Invoice'    => 'maindoc/UBL-Invoice-2.1.xsd',
        'CreditNote' => 'maindoc/UBL-CreditNote-2.1.xsd',
        'DebitNote'  => 'maindoc/UBL-DebitNote-2.1.xsd',
    ];

    private DOMXPath $xp;
    private array $errores = [];

    public static function hayEsquemas(): bool
    {
        return is_file(BASE_PATH . '/storage/xsd/' . self::ESQUEMAS['Invoice']);
    }

    /**
     * @param array $c      comprobante con detalles y datos de emisión ya aplicados (cliente, tipo y número de documento)
     * @param array $emisor emisor con el que se generó el XML
     * @return string[] errores encontrados; vacío si el XML es válido
     */
    public function validar(string $xml, array $c, array $emisor): array
    {
        $this->errores = [];
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) {
            return ['El XML no está bien formado.'];
        }

        [$raiz, $nsRaiz, $linea, $cantidad, $totales] = XmlComprobanteService::FORMATOS[$c['tipo']]
            ?? throw new \InvalidArgumentException('Tipo de comprobante sin formato XML.');
        if ($doc->documentElement->localName !== $raiz || $doc->documentElement->namespaceURI !== $nsRaiz) {
            return ["El elemento raíz debe ser {$raiz} con su namespace UBL 2.1."];
        }

        $this->xp = new DOMXPath($doc);
        $this->xp->registerNamespace('doc', $nsRaiz);
        foreach (XmlComprobanteService::NS as $prefijo => $uri) {
            $this->xp->registerNamespace($prefijo, $uri);
        }

        $this->estructura($c['tipo'], $linea, $cantidad, $totales);
        $this->esquema($doc, $raiz);
        if (!$this->errores) {
            $this->coincidencia($c, $emisor, $linea, $cantidad, $totales);
        }

        return $this->errores;
    }

    /**
     * Comunicación de baja o resumen diario: estructura y coincidencia con los comprobantes que anula.
     * (Son formatos propios de SUNAT: los XSD de OASIS no los cubren.)
     *
     * @param array $items lista de ['comprobante' => ..., 'motivo' => ...], igual que XmlAnulacionService::generar()
     * @return string[] errores encontrados; vacío si el XML es válido
     */
    public function validarAnulacion(string $xml, string $tipo, string $identificador, \DateTimeImmutable $referencia, array $emisor, array $items): array
    {
        $this->errores = [];
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) {
            return ['El XML no está bien formado.'];
        }
        [, $raiz, $nsRaiz, $version, $linea] = XmlAnulacionService::FORMATOS[$tipo]
            ?? throw new \InvalidArgumentException('Tipo de documento de anulación desconocido.');
        if ($doc->documentElement->localName !== $raiz || $doc->documentElement->namespaceURI !== $nsRaiz) {
            return ["El elemento raíz debe ser {$raiz} con su namespace de SUNAT."];
        }

        $this->xp = new DOMXPath($doc);
        $this->xp->registerNamespace('doc', $nsRaiz);
        $this->xp->registerNamespace('sac', XmlAnulacionService::SAC);
        foreach (XmlComprobanteService::NS as $prefijo => $uri) {
            $this->xp->registerNamespace($prefijo, $uri);
        }

        $obligatorios = [
            'ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent' => 'bloque para la firma',
            'cbc:UBLVersionID[.="2.0"]'                                => 'versión UBL 2.0',
            "cbc:CustomizationID[.=\"{$version}\"]"                    => "versión de estructura {$version}",
            'cbc:ID'                                                   => 'identificador',
            'cbc:ReferenceDate'                                        => 'fecha de los comprobantes',
            'cbc:IssueDate'                                            => 'fecha de generación',
            'cac:Signature/cac:DigitalSignatureAttachment/cac:ExternalReference/cbc:URI' => 'referencia a la firma',
            'cac:AccountingSupplierParty/cbc:CustomerAssignedAccountID'                   => 'RUC del emisor',
            'cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName' => 'razón social del emisor',
            "sac:{$linea}"                                                                => 'al menos un comprobante',
        ];
        foreach ($obligatorios as $ruta => $nombre) {
            if ($this->xp->query("/doc:*/{$ruta}")->length === 0) {
                $this->errores[] = "Falta {$nombre} ({$ruta}).";
            }
        }
        if ($this->errores) {
            return $this->errores;
        }

        $this->igual('identificador', 'cbc:ID', $identificador);
        $this->igual('fecha de los comprobantes', 'cbc:ReferenceDate', $referencia->format('Y-m-d'));
        $this->igual('RUC del emisor', 'cac:AccountingSupplierParty/cbc:CustomerAssignedAccountID', $emisor['ruc']);

        $lineas = $this->xp->query("/doc:*/sac:{$linea}");
        $items = array_values($items);
        if ($lineas->length !== count($items)) {
            $this->errores[] = "El XML tiene {$lineas->length} comprobantes y se esperaban " . count($items) . '.';
            return $this->errores;
        }

        foreach ($lineas as $i => $nodo) {
            $c = $items[$i]['comprobante'];
            $n = 'Comprobante ' . ($i + 1);
            if (substr((string) $c['fecha_emision'], 0, 10) !== $referencia->format('Y-m-d')) {
                $this->errores[] = "{$n}: su fecha de emisión no es la fecha de referencia del documento.";
            }
            $this->igual("{$n}: número de línea", 'cbc:LineID', (string) ($i + 1), $nodo);
            $this->igual("{$n}: tipo de comprobante", 'cbc:DocumentTypeCode', (string) config("sunat.tipos_comprobante.{$c['tipo']}"), $nodo);

            if ($tipo === 'comunicacion_baja') {
                $this->igual("{$n}: serie", 'sac:DocumentSerialID', $c['serie'], $nodo);
                $this->igual("{$n}: número", 'sac:DocumentNumberID', (string) $c['numero'], $nodo);
                $motivo = $this->valor('sac:VoidReasonDescription', $nodo);
                if ($motivo === '' || mb_strlen($motivo) > EstadoComprobanteService::MAXIMO_MOTIVO_ANULACION) {
                    $this->errores[] = "{$n}: el motivo debe tener entre 1 y " . EstadoComprobanteService::MAXIMO_MOTIVO_ANULACION . ' caracteres.';
                }
                continue;
            }

            $this->igual("{$n}: serie y número", 'cbc:ID', $c['serie'] . '-' . $c['numero'], $nodo);
            $this->igual("{$n}: documento del cliente", 'cac:AccountingCustomerParty/cbc:CustomerAssignedAccountID', $c['numero_documento'], $nodo);
            $this->igual("{$n}: estado anulado", 'cac:Status/cbc:ConditionCode', XmlAnulacionService::ESTADO_ANULADO, $nodo);
            $this->monto("{$n}: total", 'sac:TotalAmount', $c['total'], $nodo);
            $this->monto("{$n}: operaciones gravadas", 'sac:BillingPayment/cbc:PaidAmount', $c['subtotal'], $nodo);
            $this->monto("{$n}: IGV", 'cac:TaxTotal/cbc:TaxAmount', $c['impuesto'], $nodo);
            $gravado = (float) $this->valor('sac:BillingPayment/cbc:PaidAmount', $nodo);
            $igv = (float) $this->valor('cac:TaxTotal/cbc:TaxAmount', $nodo);
            if (abs($gravado + $igv - (float) $this->valor('sac:TotalAmount', $nodo)) >= 0.005) {
                $this->errores[] = "{$n}: los importes no cuadran (gravado + IGV no es igual al total).";
            }
        }

        return $this->errores;
    }

    private function estructura(string $tipo, string $linea, string $cantidad, string $totales): void
    {
        $obligatorios = [
            'ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent' => 'bloque para la firma',
            'cbc:UBLVersionID[.="2.1"]'                                => 'versión UBL 2.1',
            'cbc:CustomizationID[.="2.0"]'                             => 'versión de estructura 2.0',
            'cbc:ID'                                                   => 'serie y número',
            'cbc:IssueDate'                                            => 'fecha de emisión',
            'cbc:DocumentCurrencyCode'                                 => 'moneda',
            'cac:Signature/cac:DigitalSignatureAttachment/cac:ExternalReference/cbc:URI' => 'referencia a la firma',
            'cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID="6"]' => 'RUC del emisor',
            'cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'     => 'razón social del emisor',
            'cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:RegistrationAddress/cbc:AddressTypeCode' => 'código de establecimiento',
            'cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID]'    => 'documento del cliente',
            'cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'     => 'nombre del cliente',
            'cac:TaxTotal/cbc:TaxAmount'                                                          => 'total de impuestos',
            "cac:{$totales}/cbc:PayableAmount"                                                    => 'importe total',
            "cac:{$linea}"                                                                        => 'al menos una línea',
        ];
        if (str_starts_with($tipo, 'nota_')) {
            $obligatorios['cac:DiscrepancyResponse/cbc:ResponseCode'] = 'código de motivo de la nota';
            $obligatorios['cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'] = 'comprobante de referencia';
        } else {
            $obligatorios['cbc:InvoiceTypeCode'] = 'tipo de comprobante';
        }

        foreach ($obligatorios as $ruta => $nombre) {
            if ($this->xp->query("/doc:*/{$ruta}")->length === 0) {
                $this->errores[] = "Falta {$nombre} ({$ruta}).";
            }
        }

        foreach ($this->xp->query("/doc:*/cac:{$linea}") as $i => $nodo) {
            foreach (['cbc:ID', "cbc:{$cantidad}[@unitCode]", 'cbc:LineExtensionAmount', 'cac:TaxTotal/cbc:TaxAmount', 'cac:Item/cbc:Description', 'cac:Price/cbc:PriceAmount'] as $ruta) {
                if ($this->xp->query($ruta, $nodo)->length === 0) {
                    $this->errores[] = 'Línea ' . ($i + 1) . ": falta {$ruta}.";
                }
            }
        }
    }

    private function esquema(DOMDocument $doc, string $raiz): void
    {
        $archivo = BASE_PATH . '/storage/xsd/' . self::ESQUEMAS[$raiz];
        if (!is_file($archivo)) {
            return;
        }

        // Sin firmar, ext:ExtensionContent está vacío y el XSD exige un elemento: se valida una copia
        // con un marcador de otro namespace (el XSD lo acepta sin revisarlo), que la firma reemplazará.
        $contenido = (new DOMXPath($doc));
        $contenido->registerNamespace('ext', XmlComprobanteService::NS['ext']);
        $vacio = $contenido->query('/*/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent[not(*)]')->item(0);
        if ($vacio instanceof DOMElement) {
            $doc = clone $doc;
            $copia = new DOMXPath($doc);
            $copia->registerNamespace('ext', XmlComprobanteService::NS['ext']);
            $copia->query('/*/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent')->item(0)
                ->appendChild($doc->createElementNS('urn:erp-pucchun:firma-pendiente', 'firma:Pendiente'));
        }

        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();
        if (!$doc->schemaValidate($archivo)) {
            foreach (array_slice(libxml_get_errors(), 0, 5) as $error) {
                $this->errores[] = 'Esquema UBL 2.1, línea ' . $error->line . ': ' . trim($error->message);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
    }

    private function coincidencia(array $c, array $emisor, string $linea, string $cantidad, string $totales): void
    {
        $this->igual('serie y número', 'cbc:ID', $c['serie'] . '-' . $c['numero']);
        $this->igual('fecha de emisión', 'cbc:IssueDate', date('Y-m-d', strtotime($c['fecha_emision'])));
        $this->igual('moneda', 'cbc:DocumentCurrencyCode', config('sunat.moneda', 'PEN'));
        $this->igual('RUC del emisor', 'cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID', $emisor['ruc']);
        $this->igual('RUC del emisor en la firma', 'cac:Signature/cac:SignatoryParty/cac:PartyIdentification/cbc:ID', $emisor['ruc']);
        $this->igual('documento del cliente', 'cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID', $c['numero_documento']);
        $this->igual('tipo de documento del cliente', 'cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID/@schemeID', (string) config("sunat.tipos_documento_identidad.{$c['tipo_documento']}"));
        $this->igual('nombre del cliente', 'cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName', $c['cliente']);
        if (str_starts_with($c['tipo'], 'nota_')) {
            $this->igual('comprobante de referencia', 'cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID', $c['serie_referencia'] . '-' . $c['numero_referencia']);
        } else {
            $this->igual('tipo de comprobante', 'cbc:InvoiceTypeCode', (string) config("sunat.tipos_comprobante.{$c['tipo']}"));
        }

        $this->monto('subtotal', "cac:{$totales}/cbc:LineExtensionAmount", $c['subtotal']);
        $this->monto('IGV', 'cac:TaxTotal/cbc:TaxAmount', $c['impuesto']);
        $this->monto('total', "cac:{$totales}/cbc:PayableAmount", $c['total']);

        $lineas = $this->xp->query("/doc:*/cac:{$linea}");
        $detalles = array_values($c['detalles']);
        if ($lineas->length !== count($detalles)) {
            $this->errores[] = "El XML tiene {$lineas->length} líneas y el comprobante " . count($detalles) . '.';
            return;
        }

        $sumaBase = 0.0;
        $sumaIgv = 0.0;
        foreach ($lineas as $i => $nodo) {
            $d = $detalles[$i];
            $n = 'Línea ' . ($i + 1);
            $this->monto("{$n}: cantidad", "cbc:{$cantidad}", $d['cantidad'], $nodo, 0.0005);
            $this->monto("{$n}: valor de venta", 'cbc:LineExtensionAmount', $d['subtotal'], $nodo);
            $this->monto("{$n}: IGV", 'cac:TaxTotal/cbc:TaxAmount', $d['impuesto'], $nodo);
            $this->monto("{$n}: valor unitario", 'cac:Price/cbc:PriceAmount', $d['precio_unitario'], $nodo);
            $this->igual("{$n}: descripción", 'cac:Item/cbc:Description', $d['descripcion'], $nodo);
            // Valor de venta = cantidad × valor unitario − descuento de la línea; IGV = valor de venta × porcentaje
            $base = (float) $this->valor('cbc:LineExtensionAmount', $nodo);
            $descuento = (float) ($this->valor('cac:AllowanceCharge/cbc:Amount', $nodo) ?? 0);
            if (abs($descuento - round((float) ($d['descuento'] ?? 0), 2)) >= 0.005) {
                $this->errores[] = "{$n}: el descuento del XML no coincide con el comprobante.";
            }
            if (abs(round(round((float) $this->valor("cbc:{$cantidad}", $nodo) * (float) $this->valor('cac:Price/cbc:PriceAmount', $nodo), 2) - $descuento, 2) - $base) >= 0.005) {
                $this->errores[] = "{$n}: el valor de venta no es igual a cantidad × valor unitario menos el descuento.";
            }
            $porcentaje = (float) $this->valor('cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:Percent', $nodo);
            if (abs(round($base * $porcentaje / 100, 2) - (float) $this->valor('cac:TaxTotal/cbc:TaxAmount', $nodo)) >= 0.005) {
                $this->errores[] = "{$n}: el IGV no corresponde al {$porcentaje}% del valor de venta.";
            }
            $sumaBase += (float) $this->valor('cbc:LineExtensionAmount', $nodo);
            $sumaIgv += (float) $this->valor('cac:TaxTotal/cbc:TaxAmount', $nodo);
        }

        // Los importes deben cuadrar entre sí: subtotal + IGV = total, y las líneas suman los totales del documento
        $subtotal = (float) $this->valor("cac:{$totales}/cbc:LineExtensionAmount", null);
        $igv = (float) $this->valor('cac:TaxTotal/cbc:TaxAmount', null);
        $total = (float) $this->valor("cac:{$totales}/cbc:PayableAmount", null);
        if (abs($subtotal + $igv - $total) >= 0.005) {
            $this->errores[] = sprintf('Los importes no cuadran: subtotal %.2f + IGV %.2f no es igual al total %.2f.', $subtotal, $igv, $total);
        }
        if (abs($sumaBase - (float) $c['subtotal']) >= 0.005) {
            $this->errores[] = sprintf('La suma de las líneas (%.2f) no coincide con el subtotal (%.2f).', $sumaBase, $c['subtotal']);
        }
        if (abs($sumaIgv - (float) $c['impuesto']) >= 0.005) {
            $this->errores[] = sprintf('La suma del IGV de las líneas (%.2f) no coincide con el IGV total (%.2f).', $sumaIgv, $c['impuesto']);
        }
    }

    private function igual(string $nombre, string $ruta, string $esperado, ?DOMElement $contexto = null): void
    {
        $valor = $this->valor($ruta, $contexto);
        if ($valor !== $esperado) {
            $this->errores[] = "No coincide {$nombre}: el XML tiene «{$valor}» y el comprobante «{$esperado}».";
        }
    }

    private function monto(string $nombre, string $ruta, mixed $esperado, ?DOMElement $contexto = null, float $tolerancia = 0.005): void
    {
        $valor = $this->valor($ruta, $contexto);
        if (!is_numeric($valor) || abs((float) $valor - (float) $esperado) >= $tolerancia) {
            $this->errores[] = sprintf('No coincide %s: el XML tiene «%s» y el comprobante «%s».', $nombre, $valor, $esperado);
        }
    }

    private function valor(string $ruta, ?DOMElement $contexto): string
    {
        $nodo = $contexto
            ? $this->xp->query($ruta, $contexto)->item(0)
            : $this->xp->query("/doc:*/{$ruta}")->item(0);

        return $nodo ? trim($nodo->nodeValue) : '';
    }
}

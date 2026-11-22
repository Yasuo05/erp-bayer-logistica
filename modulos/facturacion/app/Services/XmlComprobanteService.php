<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;

/**
 * Arma el XML UBL 2.1 del comprobante con la estructura que pide SUNAT
 * (factura y boleta = Invoice, nota de crédito = CreditNote, nota de débito = DebitNote).
 * El XML queda sin firmar: la firma va en ext:ExtensionContent (ver FirmaXmlService).
 */
final class XmlComprobanteService
{
    public const NS = [
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
        'ds'  => 'http://www.w3.org/2000/09/xmldsig#',
    ];

    // tipo => [elemento raíz, namespace raíz, elemento de línea, elemento de cantidad, elemento de totales]
    public const FORMATOS = [
        'factura'      => ['Invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'InvoiceLine', 'InvoicedQuantity', 'LegalMonetaryTotal'],
        'boleta'       => ['Invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'InvoiceLine', 'InvoicedQuantity', 'LegalMonetaryTotal'],
        'nota_credito' => ['CreditNote', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2', 'CreditNoteLine', 'CreditedQuantity', 'LegalMonetaryTotal'],
        'nota_debito'  => ['DebitNote', 'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2', 'DebitNoteLine', 'DebitedQuantity', 'RequestedMonetaryTotal'],
    ];

    private DOMDocument $doc;
    private string $moneda;

    /** Nombre oficial del archivo: RUC-TIPO-SERIE-NUMERO.xml (20000000001-01-F001-1.xml) */
    public static function nombreArchivo(array $emisor, array $comprobante): string
    {
        return sprintf('%s-%s-%s-%d.xml', $emisor['ruc'], self::codigoTipo($comprobante['tipo']), $comprobante['serie'], $comprobante['numero']);
    }

    /**
     * @param array $c      comprobante con detalles (FacturacionRepository::comprobante)
     * @param array $emisor datos de la empresa (Grupo 4)
     * @param float $tasa   tasa del IGV (0.18)
     */
    public function generar(array $c, array $emisor, float $tasa): string
    {
        if (!isset(self::FORMATOS[$c['tipo']])) {
            throw new \InvalidArgumentException('Tipo de comprobante sin formato XML.');
        }
        if (empty($c['detalles'])) {
            throw new \InvalidArgumentException('El comprobante no tiene líneas de detalle.');
        }
        $esNota = str_starts_with($c['tipo'], 'nota_');
        if ($esNota && empty($c['serie_referencia'])) {
            throw new \InvalidArgumentException('La nota no tiene comprobante de referencia.');
        }

        [$raiz, $nsRaiz, $etiquetaLinea, $etiquetaCantidad, $etiquetaTotales] = self::FORMATOS[$c['tipo']];
        $this->moneda = config('sunat.moneda', 'PEN');
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        $root = $this->doc->createElementNS($nsRaiz, $raiz);
        $this->doc->appendChild($root);
        foreach (self::NS as $prefijo => $uri) {
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:{$prefijo}", $uri);
        }

        // Aquí se insertará la firma digital
        $extension = $this->el($this->el($root, 'ext:UBLExtensions'), 'ext:UBLExtension');
        $this->el($extension, 'ext:ExtensionContent');

        $fecha = new \DateTimeImmutable($c['fecha_emision']);
        $this->el($root, 'cbc:UBLVersionID', '2.1');
        $this->el($root, 'cbc:CustomizationID', '2.0');
        $this->el($root, 'cbc:ID', $c['serie'] . '-' . $c['numero']);
        $this->el($root, 'cbc:IssueDate', $fecha->format('Y-m-d'));
        $this->el($root, 'cbc:IssueTime', $fecha->format('H:i:s'));
        if (!$esNota) {
            $this->el($root, 'cbc:InvoiceTypeCode', self::codigoTipo($c['tipo']), [
                'listID'         => '0101', // catálogo 51: venta interna
                'listAgencyName' => 'PE:SUNAT',
                'listName'       => 'Tipo de Documento',
                'listURI'        => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01',
            ]);
        }
        $this->el($root, 'cbc:Note', MontoEnLetras::convertir((float) $c['total']), ['languageLocaleID' => '1000']);
        $this->el($root, 'cbc:DocumentCurrencyCode', $this->moneda);

        if ($esNota) {
            $referencia = $c['serie_referencia'] . '-' . $c['numero_referencia'];
            $discrepancia = $this->el($root, 'cac:DiscrepancyResponse');
            $this->el($discrepancia, 'cbc:ReferenceID', $referencia);
            $this->el($discrepancia, 'cbc:ResponseCode', $c['codigo_motivo'] ?? config("sunat.motivo_nota.{$c['tipo']}"));
            $this->el($discrepancia, 'cbc:Description', (string) $c['motivo']);

            $documento = $this->el($this->el($root, 'cac:BillingReference'), 'cac:InvoiceDocumentReference');
            $this->el($documento, 'cbc:ID', $referencia);
            $this->el($documento, 'cbc:DocumentTypeCode', self::codigoTipo($c['tipo_referencia']));
        }

        $this->referenciaFirma($root, $emisor);
        $this->emisor($root, $emisor);
        $this->cliente($root, $c);

        if ($c['tipo'] === 'factura') {
            $pago = $this->el($root, 'cac:PaymentTerms');
            $this->el($pago, 'cbc:ID', 'FormaPago');
            $this->el($pago, 'cbc:PaymentMeansID', 'Contado');
        }

        $impuestos = $this->el($root, 'cac:TaxTotal');
        $this->monto($impuestos, 'cbc:TaxAmount', $c['impuesto']);
        $this->subtotalIgv($impuestos, $c['subtotal'], $c['impuesto'], null);

        $totales = $this->el($root, 'cac:' . $etiquetaTotales);
        $this->monto($totales, 'cbc:LineExtensionAmount', $c['subtotal']);
        $this->monto($totales, 'cbc:TaxInclusiveAmount', $c['total']);
        $this->monto($totales, 'cbc:PayableAmount', $c['total']);

        foreach (array_values($c['detalles']) as $i => $d) {
            $linea = $this->el($root, 'cac:' . $etiquetaLinea);
            $this->el($linea, 'cbc:ID', (string) ($i + 1));
            $this->el($linea, 'cbc:' . $etiquetaCantidad, self::cantidad($d['cantidad']), [
                'unitCode'               => $d['unidad_medida'] ?? 'NIU',
                'unitCodeListID'         => 'UN/ECE rec 20',
                'unitCodeListAgencyName' => 'United Nations Economic Commission for Europe',
            ]);
            $this->monto($linea, 'cbc:LineExtensionAmount', $d['subtotal']);

            // Precio unitario con IGV (tipo 01 del catálogo 16)
            $precio = $this->el($this->el($linea, 'cac:PricingReference'), 'cac:AlternativeConditionPrice');
            $this->monto($precio, 'cbc:PriceAmount', round((float) $d['precio_unitario'] * (1 + $tasa), 2));
            $this->el($precio, 'cbc:PriceTypeCode', '01');

            // Descuento de la línea que reduce la base imponible (catálogo 53, código 00). LineExtensionAmount ya es neto.
            $descuento = round((float) ($d['descuento'] ?? 0), 2);
            if ($descuento > 0) {
                $base = round((float) $d['subtotal'] + $descuento, 2);
                $cargo = $this->el($linea, 'cac:AllowanceCharge');
                $this->el($cargo, 'cbc:ChargeIndicator', 'false');
                $this->el($cargo, 'cbc:AllowanceChargeReasonCode', '00');
                $this->el($cargo, 'cbc:MultiplierFactorNumeric', number_format($descuento / $base, 5, '.', ''));
                $this->monto($cargo, 'cbc:Amount', $descuento);
                $this->monto($cargo, 'cbc:BaseAmount', $base);
            }

            $impuestoLinea = $this->el($linea, 'cac:TaxTotal');
            $this->monto($impuestoLinea, 'cbc:TaxAmount', $d['impuesto']);
            $this->subtotalIgv($impuestoLinea, $d['subtotal'], $d['impuesto'], $tasa);

            $item = $this->el($linea, 'cac:Item');
            $this->el($item, 'cbc:Description', $d['descripcion']);
            if (!empty($d['codigo_producto'])) {
                $this->el($this->el($item, 'cac:SellersItemIdentification'), 'cbc:ID', $d['codigo_producto']);
            }

            // Valor unitario sin IGV
            $this->montoUnitario($this->el($linea, 'cac:Price'), 'cbc:PriceAmount', $d['precio_unitario']);
        }

        return $this->doc->saveXML();
    }

    private function referenciaFirma(DOMElement $root, array $emisor): void
    {
        $firma = $this->el($root, 'cac:Signature');
        $this->el($firma, 'cbc:ID', FirmaXmlService::ID_FIRMA);
        $firmante = $this->el($firma, 'cac:SignatoryParty');
        $this->el($this->el($firmante, 'cac:PartyIdentification'), 'cbc:ID', $emisor['ruc']);
        $this->el($this->el($firmante, 'cac:PartyName'), 'cbc:Name', $emisor['razon_social']);
        $adjunto = $this->el($this->el($firma, 'cac:DigitalSignatureAttachment'), 'cac:ExternalReference');
        $this->el($adjunto, 'cbc:URI', '#' . FirmaXmlService::ID_FIRMA);
    }

    private function emisor(DOMElement $root, array $emisor): void
    {
        $parte = $this->el($this->el($root, 'cac:AccountingSupplierParty'), 'cac:Party');
        $this->documentoIdentidad($parte, '6', $emisor['ruc']);
        if (!empty($emisor['nombre_comercial'])) {
            $this->el($this->el($parte, 'cac:PartyName'), 'cbc:Name', $emisor['nombre_comercial']);
        }

        $entidad = $this->el($parte, 'cac:PartyLegalEntity');
        $this->el($entidad, 'cbc:RegistrationName', $emisor['razon_social']);
        $direccion = $this->el($entidad, 'cac:RegistrationAddress');
        $this->el($direccion, 'cbc:ID', $emisor['ubigeo'], ['schemeAgencyName' => 'PE:INEI', 'schemeName' => 'Ubigeos']);
        $this->el($direccion, 'cbc:AddressTypeCode', $emisor['codigo_establecimiento']);
        $this->el($direccion, 'cbc:CityName', $emisor['provincia']);
        $this->el($direccion, 'cbc:CountrySubentity', $emisor['departamento']);
        $this->el($direccion, 'cbc:District', $emisor['distrito']);
        $this->el($this->el($direccion, 'cac:AddressLine'), 'cbc:Line', $emisor['direccion']);
        $this->el($this->el($direccion, 'cac:Country'), 'cbc:IdentificationCode', 'PE');
    }

    private function cliente(DOMElement $root, array $c): void
    {
        $codigo = config("sunat.tipos_documento_identidad.{$c['tipo_documento']}");
        if ($codigo === null) {
            throw new \InvalidArgumentException("Tipo de documento del cliente no soportado: {$c['tipo_documento']}.");
        }

        $parte = $this->el($this->el($root, 'cac:AccountingCustomerParty'), 'cac:Party');
        $this->documentoIdentidad($parte, $codigo, $c['numero_documento']);
        $this->el($this->el($parte, 'cac:PartyLegalEntity'), 'cbc:RegistrationName', $c['cliente']);
    }

    private function documentoIdentidad(DOMElement $parte, string $tipo, string $numero): void
    {
        $this->el($this->el($parte, 'cac:PartyIdentification'), 'cbc:ID', $numero, [
            'schemeID'         => $tipo,
            'schemeName'       => 'Documento de Identidad',
            'schemeAgencyName' => 'PE:SUNAT',
            'schemeURI'        => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06',
        ]);
    }

    /** Operación gravada con IGV. El porcentaje y la afectación solo van a nivel de línea. */
    private function subtotalIgv(DOMElement $padre, mixed $base, mixed $impuesto, ?float $tasa): void
    {
        $subtotal = $this->el($padre, 'cac:TaxSubtotal');
        $this->monto($subtotal, 'cbc:TaxableAmount', $base);
        $this->monto($subtotal, 'cbc:TaxAmount', $impuesto);

        $categoria = $this->el($subtotal, 'cac:TaxCategory');
        if ($tasa !== null) {
            $this->el($categoria, 'cbc:Percent', self::cantidad($tasa * 100));
            $this->el($categoria, 'cbc:TaxExemptionReasonCode', '10'); // catálogo 07: gravado - operación onerosa
        }
        $esquema = $this->el($categoria, 'cac:TaxScheme');
        $this->el($esquema, 'cbc:ID', '1000');
        $this->el($esquema, 'cbc:Name', 'IGV');
        $this->el($esquema, 'cbc:TaxTypeCode', 'VAT');
    }

    private function monto(DOMElement $padre, string $nombre, mixed $valor): void
    {
        $this->el($padre, $nombre, number_format((float) $valor, 2, '.', ''), ['currencyID' => $this->moneda]);
    }

    /** Valor unitario: hasta 4 decimales (mínimo 2), como se registra en la venta. SUNAT admite hasta 10. */
    private function montoUnitario(DOMElement $padre, string $nombre, mixed $valor): void
    {
        $texto = rtrim(number_format((float) $valor, 4, '.', ''), '0');
        $texto = strlen(substr(strrchr($texto, '.'), 1)) < 2 ? number_format((float) $valor, 2, '.', '') : $texto;
        $this->el($padre, $nombre, $texto, ['currencyID' => $this->moneda]);
    }

    /** Agrega un elemento cbc:/cac:/ext: con su texto (escapado) y atributos */
    private function el(DOMElement $padre, string $nombre, ?string $texto = null, array $atributos = []): DOMElement
    {
        $nodo = $this->doc->createElementNS(self::NS[strstr($nombre, ':', true)], $nombre);
        if ($texto !== null) {
            $nodo->appendChild($this->doc->createTextNode($texto));
        }
        foreach ($atributos as $clave => $valor) {
            $nodo->setAttribute($clave, $valor);
        }
        $padre->appendChild($nodo);

        return $nodo;
    }

    private static function codigoTipo(string $tipo): string
    {
        return config("sunat.tipos_comprobante.{$tipo}") ?? throw new \InvalidArgumentException("Tipo de comprobante desconocido: {$tipo}.");
    }

    /** 4.000 → "4", 2.500 → "2.5" */
    private static function cantidad(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;

/**
 * XML para anular ante SUNAT comprobantes ya aceptados (formatos propios de SUNAT, UBL 2.0):
 *  - Comunicación de baja (VoidedDocuments, RA): facturas y sus notas (series F).
 *  - Resumen diario (SummaryDocuments, RC) con estado 3 "anulado": boletas y sus notas (series B).
 * Queda sin firmar: la firma va en ext:ExtensionContent, igual que en los comprobantes (FirmaXmlService).
 */
final class XmlAnulacionService
{
    public const SAC = 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1';

    // tipo => [prefijo del identificador, elemento raíz, namespace raíz, versión de la estructura, elemento de línea]
    public const FORMATOS = [
        'comunicacion_baja' => ['RA', 'VoidedDocuments', 'urn:sunat:names:specification:ubl:peru:schema:xsd:VoidedDocuments-1', '1.0', 'VoidedDocumentsLine'],
        'resumen_diario'    => ['RC', 'SummaryDocuments', 'urn:sunat:names:specification:ubl:peru:schema:xsd:SummaryDocuments-1', '1.1', 'SummaryDocumentsLine'],
    ];

    /** Resumen diario: código 3 del estado del ítem = anulado */
    public const ESTADO_ANULADO = '3';

    private DOMDocument $doc;

    /** Las series F (facturas y sus notas) se dan de baja con RA; las B (boletas y sus notas), con resumen diario */
    public static function tipoPara(array $comprobante): string
    {
        return strtoupper($comprobante['serie'][0] ?? '') === 'B' ? 'resumen_diario' : 'comunicacion_baja';
    }

    /** RA-20261028-1 */
    public static function identificador(string $tipo, \DateTimeImmutable $fecha, int $correlativo): string
    {
        return self::FORMATOS[$tipo][0] . '-' . $fecha->format('Ymd') . '-' . $correlativo;
    }

    /**
     * @param array $items lista de ['comprobante' => comprobante con detalles y datos de emisión aplicados, 'motivo' => texto]
     *                     Todos deben tener la misma fecha de emisión (fecha de referencia).
     */
    public function generar(string $tipo, string $identificador, \DateTimeImmutable $referencia, \DateTimeImmutable $generacion, array $emisor, array $items): string
    {
        [, $raiz, $nsRaiz, $version, $etiquetaLinea] = self::FORMATOS[$tipo]
            ?? throw new \InvalidArgumentException('Tipo de documento de anulación desconocido.');
        if (!$items) {
            throw new \InvalidArgumentException('No hay comprobantes para anular.');
        }

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $root = $this->doc->createElementNS($nsRaiz, $raiz);
        $this->doc->appendChild($root);
        foreach (XmlComprobanteService::NS + ['sac' => self::SAC] as $prefijo => $uri) {
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:{$prefijo}", $uri);
        }

        $this->el($this->el($this->el($root, 'ext:UBLExtensions'), 'ext:UBLExtension'), 'ext:ExtensionContent');
        $this->el($root, 'cbc:UBLVersionID', '2.0');
        $this->el($root, 'cbc:CustomizationID', $version);
        $this->el($root, 'cbc:ID', $identificador);
        $this->el($root, 'cbc:ReferenceDate', $referencia->format('Y-m-d'));
        $this->el($root, 'cbc:IssueDate', $generacion->format('Y-m-d'));

        $firma = $this->el($root, 'cac:Signature');
        $this->el($firma, 'cbc:ID', FirmaXmlService::ID_FIRMA);
        $firmante = $this->el($firma, 'cac:SignatoryParty');
        $this->el($this->el($firmante, 'cac:PartyIdentification'), 'cbc:ID', $emisor['ruc']);
        $this->el($this->el($firmante, 'cac:PartyName'), 'cbc:Name', $emisor['razon_social']);
        $this->el($this->el($this->el($firma, 'cac:DigitalSignatureAttachment'), 'cac:ExternalReference'), 'cbc:URI', '#' . FirmaXmlService::ID_FIRMA);

        // En UBL 2.0 el emisor se identifica con CustomerAssignedAccountID (RUC) y AdditionalAccountID (6 = RUC)
        $emisorXml = $this->el($root, 'cac:AccountingSupplierParty');
        $this->el($emisorXml, 'cbc:CustomerAssignedAccountID', $emisor['ruc']);
        $this->el($emisorXml, 'cbc:AdditionalAccountID', '6');
        $this->el($this->el($this->el($emisorXml, 'cac:Party'), 'cac:PartyLegalEntity'), 'cbc:RegistrationName', $emisor['razon_social']);

        foreach (array_values($items) as $i => $item) {
            $linea = $this->el($root, 'sac:' . $etiquetaLinea);
            $tipo === 'comunicacion_baja'
                ? $this->lineaBaja($linea, $i + 1, $item['comprobante'], $item['motivo'])
                : $this->lineaResumen($linea, $i + 1, $item['comprobante']);
        }

        return $this->doc->saveXML();
    }

    private function lineaBaja(DOMElement $linea, int $n, array $c, string $motivo): void
    {
        $this->el($linea, 'cbc:LineID', (string) $n);
        $this->el($linea, 'cbc:DocumentTypeCode', self::codigoTipo($c['tipo']));
        $this->el($linea, 'sac:DocumentSerialID', $c['serie']);
        $this->el($linea, 'sac:DocumentNumberID', (string) $c['numero']);
        $this->el($linea, 'sac:VoidReasonDescription', $motivo);
    }

    private function lineaResumen(DOMElement $linea, int $n, array $c): void
    {
        $moneda = config('sunat.moneda', 'PEN');
        $this->el($linea, 'cbc:LineID', (string) $n);
        $this->el($linea, 'cbc:DocumentTypeCode', self::codigoTipo($c['tipo']));
        $this->el($linea, 'cbc:ID', $c['serie'] . '-' . $c['numero']);

        $cliente = $this->el($linea, 'cac:AccountingCustomerParty');
        $this->el($cliente, 'cbc:CustomerAssignedAccountID', $c['numero_documento']);
        $this->el($cliente, 'cbc:AdditionalAccountID', (string) config("sunat.tipos_documento_identidad.{$c['tipo_documento']}"));

        if (str_starts_with($c['tipo'], 'nota_')) {
            $referencia = $this->el($this->el($linea, 'cac:BillingReference'), 'cac:InvoiceDocumentReference');
            $this->el($referencia, 'cbc:ID', $c['serie_referencia'] . '-' . $c['numero_referencia']);
            $this->el($referencia, 'cbc:DocumentTypeCode', self::codigoTipo($c['tipo_referencia']));
        }

        $this->el($this->el($linea, 'cac:Status'), 'cbc:ConditionCode', self::ESTADO_ANULADO);
        $this->el($linea, 'sac:TotalAmount', self::monto($c['total']), ['currencyID' => $moneda]);

        // Operaciones gravadas (código 01) e IGV del comprobante
        $pago = $this->el($linea, 'sac:BillingPayment');
        $this->el($pago, 'cbc:PaidAmount', self::monto($c['subtotal']), ['currencyID' => $moneda]);
        $this->el($pago, 'cbc:InstructionID', '01');

        $impuestos = $this->el($linea, 'cac:TaxTotal');
        $this->el($impuestos, 'cbc:TaxAmount', self::monto($c['impuesto']), ['currencyID' => $moneda]);
        $subtotal = $this->el($impuestos, 'cac:TaxSubtotal');
        $this->el($subtotal, 'cbc:TaxAmount', self::monto($c['impuesto']), ['currencyID' => $moneda]);
        $esquema = $this->el($this->el($subtotal, 'cac:TaxCategory'), 'cac:TaxScheme');
        $this->el($esquema, 'cbc:ID', '1000');
        $this->el($esquema, 'cbc:Name', 'IGV');
        $this->el($esquema, 'cbc:TaxTypeCode', 'VAT');
    }

    private function el(DOMElement $padre, string $nombre, ?string $texto = null, array $atributos = []): DOMElement
    {
        $prefijo = strstr($nombre, ':', true);
        $nodo = $this->doc->createElementNS($prefijo === 'sac' ? self::SAC : XmlComprobanteService::NS[$prefijo], $nombre);
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

    private static function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}

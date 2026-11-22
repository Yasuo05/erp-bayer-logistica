<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * Firma digital XMLDSig "enveloped" del XML UBL, como la pide SUNAT:
 * RSA-SHA1, canonicalización C14N y la firma dentro de ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent.
 */
final class FirmaXmlService
{
    /** Id de ds:Signature; el XML lo referencia en cac:Signature (URI "#SignatureSP") */
    public const ID_FIRMA = 'SignatureSP';

    private const DS  = 'http://www.w3.org/2000/09/xmldsig#';
    private const EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    /**
     * @param string $rutaCertificado archivo .pfx/.p12 (o .pem con certificado y llave privada)
     * @return array{xml: string, hash: string} XML firmado y su DigestValue (hash que va en el PDF/QR)
     */
    public function firmar(string $xml, string $rutaCertificado, string $clave): array
    {
        [$llave, $certificado] = $this->cargarCertificado($rutaCertificado, $clave);

        $doc = self::cargarXml($xml);
        $xp = self::xpath($doc);
        if ($xp->query('//ds:Signature')->length > 0) {
            throw new \RuntimeException('El XML ya está firmado.');
        }
        $contenedor = $xp->query('/*/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent')->item(0);
        if (!$contenedor instanceof DOMElement) {
            throw new \RuntimeException('El XML no tiene el bloque ext:ExtensionContent para la firma.');
        }

        // Transformación "enveloped": el digest es del documento completo sin la firma, que aún no existe.
        $digest = base64_encode(sha1($doc->C14N(), true));

        $firma = $this->el($doc, $contenedor, 'ds:Signature', null, ['Id' => self::ID_FIRMA]);
        $info = $this->el($doc, $firma, 'ds:SignedInfo');
        $this->el($doc, $info, 'ds:CanonicalizationMethod', null, ['Algorithm' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315']);
        $this->el($doc, $info, 'ds:SignatureMethod', null, ['Algorithm' => self::DS . 'rsa-sha1']);
        $referencia = $this->el($doc, $info, 'ds:Reference', null, ['URI' => '']);
        $transformaciones = $this->el($doc, $referencia, 'ds:Transforms');
        $this->el($doc, $transformaciones, 'ds:Transform', null, ['Algorithm' => self::DS . 'enveloped-signature']);
        $this->el($doc, $referencia, 'ds:DigestMethod', null, ['Algorithm' => self::DS . 'sha1']);
        $this->el($doc, $referencia, 'ds:DigestValue', $digest);
        $this->el($doc, $firma, 'ds:SignatureValue', '');
        $datosX509 = $this->el($doc, $this->el($doc, $firma, 'ds:KeyInfo'), 'ds:X509Data');
        $this->el($doc, $datosX509, 'ds:X509Certificate', self::certificadoBase64($certificado));

        // Se vuelve a leer para canonicalizar SignedInfo exactamente como lo hará quien verifique la firma.
        $doc = self::cargarXml($doc->saveXML());
        $xp = self::xpath($doc);
        $signedInfo = $xp->query('//ds:Signature/ds:SignedInfo')->item(0);
        if (!openssl_sign($signedInfo->C14N(), $valor, $llave, OPENSSL_ALGO_SHA1)) {
            throw new \RuntimeException('No se pudo firmar: ' . openssl_error_string());
        }
        $xp->query('//ds:Signature/ds:SignatureValue')->item(0)->nodeValue = base64_encode($valor);

        return ['xml' => $doc->saveXML(), 'hash' => $digest];
    }

    /** Comprueba el digest y la firma de un XML firmado (útil para pruebas y antes de enviarlo). */
    public function verificar(string $xmlFirmado): bool
    {
        $doc = self::cargarXml($xmlFirmado);
        $xp = self::xpath($doc);
        $firma = $xp->query('//ds:Signature')->item(0);
        if (!$firma instanceof DOMElement) {
            return false;
        }

        $digest = $xp->query('ds:SignedInfo/ds:Reference/ds:DigestValue', $firma)->item(0)->nodeValue;
        $valor = base64_decode($xp->query('ds:SignatureValue', $firma)->item(0)->nodeValue);
        $certificado = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(trim($xp->query('ds:KeyInfo/ds:X509Data/ds:X509Certificate', $firma)->item(0)->nodeValue), 64)
            . "-----END CERTIFICATE-----\n";
        $firmaValida = openssl_verify($xp->query('ds:SignedInfo', $firma)->item(0)->C14N(), $valor, $certificado, OPENSSL_ALGO_SHA1) === 1;

        $firma->parentNode->removeChild($firma);

        return $firmaValida && hash_equals($digest, base64_encode(sha1($doc->C14N(), true)));
    }

    /** @return array{0: OpenSSLAsymmetricKey, 1: OpenSSLCertificate} */
    private function cargarCertificado(string $ruta, string $clave): array
    {
        if (!is_file($ruta)) {
            throw new \RuntimeException("No se encontró el certificado digital en «{$ruta}». Para pruebas genera uno con: php bin/generar_certificado_demo.php");
        }
        $contenido = (string) file_get_contents($ruta);

        if (str_ends_with(strtolower($ruta), '.pem')) {
            $llave = openssl_pkey_get_private($contenido, $clave);
            $certificado = openssl_x509_read($contenido);
        } else {
            if (!openssl_pkcs12_read($contenido, $partes, $clave)) {
                throw new \RuntimeException('No se pudo abrir el certificado .pfx: revisa FIRMA_CLAVE en el .env. '
                    . 'Si es un .pfx antiguo, conviértelo a .pem con OpenSSL (opción -legacy).');
            }
            $llave = openssl_pkey_get_private($partes['pkey']);
            $certificado = openssl_x509_read($partes['cert']);
        }
        if (!$llave || !$certificado) {
            throw new \RuntimeException('El certificado no contiene una llave privada y un certificado válidos.');
        }

        $vence = openssl_x509_parse($certificado)['validTo_time_t'] ?? 0;
        if ($vence < time()) {
            throw new \RuntimeException('El certificado digital está vencido desde el ' . date('d/m/Y', $vence) . '.');
        }

        return [$llave, $certificado];
    }

    private static function certificadoBase64(OpenSSLCertificate $certificado): string
    {
        openssl_x509_export($certificado, $pem);

        return preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
    }

    private static function cargarXml(string $xml): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        if (!$doc->loadXML($xml)) {
            throw new \RuntimeException('El XML del comprobante no es válido.');
        }

        return $doc;
    }

    private static function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('ds', self::DS);
        $xp->registerNamespace('ext', self::EXT);

        return $xp;
    }

    private function el(DOMDocument $doc, DOMElement $padre, string $nombre, ?string $texto = null, array $atributos = []): DOMElement
    {
        $nodo = $doc->createElementNS(self::DS, $nombre);
        if ($texto !== null) {
            $nodo->appendChild($doc->createTextNode($texto));
        }
        foreach ($atributos as $clave => $valor) {
            $nodo->setAttribute($clave, $valor);
        }
        $padre->appendChild($nodo);

        return $nodo;
    }
}

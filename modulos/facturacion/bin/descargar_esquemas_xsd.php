<?php
/**
 * Descarga los esquemas XSD oficiales de OASIS UBL 2.1 en storage/xsd/ (unos 3 MB).
 * Con ellos, ValidadorXmlService valida además el XML contra el esquema oficial.
 * Solo baja lo necesario para Invoice, CreditNote y DebitNote, no el paquete completo de 58 MB.
 *
 * Uso: php bin/descargar_esquemas_xsd.php [--forzar]
 */

declare(strict_types=1);

const ORIGEN = 'https://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/';
const ARCHIVOS = [
    'maindoc/UBL-Invoice-2.1.xsd',
    'maindoc/UBL-CreditNote-2.1.xsd',
    'maindoc/UBL-DebitNote-2.1.xsd',
    'common/CCTS_CCT_SchemaModule-2.1.xsd',
    'common/UBL-CommonAggregateComponents-2.1.xsd',
    'common/UBL-CommonBasicComponents-2.1.xsd',
    'common/UBL-CommonExtensionComponents-2.1.xsd',
    'common/UBL-CommonSignatureComponents-2.1.xsd',
    'common/UBL-CoreComponentParameters-2.1.xsd',
    'common/UBL-ExtensionContentDataType-2.1.xsd',
    'common/UBL-QualifiedDataTypes-2.1.xsd',
    'common/UBL-SignatureAggregateComponents-2.1.xsd',
    'common/UBL-SignatureBasicComponents-2.1.xsd',
    'common/UBL-UnqualifiedDataTypes-2.1.xsd',
    'common/UBL-XAdESv132-2.1.xsd',
    'common/UBL-XAdESv141-2.1.xsd',
    'common/UBL-xmldsig-core-schema-2.1.xsd',
];

$destino = dirname(__DIR__) . '/storage/xsd/';
$forzar = in_array('--forzar', $argv, true);
$fallas = 0;

foreach (ARCHIVOS as $archivo) {
    $ruta = $destino . $archivo;
    if (is_file($ruta) && !$forzar) {
        echo "  ya existe  {$archivo}\n";
        continue;
    }
    if (!is_dir(dirname($ruta))) {
        mkdir(dirname($ruta), 0775, true);
    }
    $contenido = @file_get_contents(ORIGEN . $archivo);
    if ($contenido === false || !preg_match('/<(xsd:)?schema\b/', $contenido)) {
        fwrite(STDERR, "  FALLÓ      {$archivo}\n");
        $fallas++;
        continue;
    }
    file_put_contents($ruta, $contenido);
    echo "  descargado {$archivo}\n";
}

echo $fallas ? "\nNo se pudieron descargar {$fallas} archivos. Revisa la conexión y vuelve a intentarlo.\n"
             : "\nEsquemas UBL 2.1 listos en storage/xsd/.\n";
exit($fallas ? 1 : 0);

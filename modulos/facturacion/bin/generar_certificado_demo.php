<?php
/**
 * Genera un certificado digital AUTOFIRMADO solo para probar la firma en local.
 * SUNAT no lo acepta: en producción se usa el certificado .pfx de la empresa.
 *
 * Uso: php bin/generar_certificado_demo.php [--forzar]
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;

Env::cargar(BASE_PATH . '/.env');

$ruta = (string) Env::get('FIRMA_CERTIFICADO', 'storage/certificados/certificado-demo.pfx');
if (!preg_match('#^([a-zA-Z]:)?[\\\\/]#', $ruta)) {
    $ruta = BASE_PATH . '/' . $ruta;
}
$clave = (string) Env::get('FIRMA_CLAVE', '');

if (is_file($ruta) && !in_array('--forzar', $argv, true)) {
    echo "Ya existe el certificado: {$ruta}\nUsa --forzar para reemplazarlo.\n";
    exit(0);
}
if (!is_dir(dirname($ruta))) {
    mkdir(dirname($ruta), 0775, true);
}

// En Windows PHP no siempre encuentra openssl.cnf: se usa uno mínimo temporal.
$cnf = tempnam(sys_get_temp_dir(), 'cnf');
file_put_contents($cnf, "[req]\ndistinguished_name=dn\n[dn]\n");
$opciones = ['config' => $cnf, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];

try {
    $llave = openssl_pkey_new($opciones);
    $csr = $llave ? openssl_csr_new([
        'countryName'      => 'PE',
        'organizationName' => 'AGRICOLA PUCCHUN S.A.C. (DEMO)',
        'commonName'       => 'Certificado de prueba ERP Facturacion',
    ], $llave, $opciones) : false;
    $certificado = $csr ? openssl_csr_sign($csr, null, $llave, 365, $opciones) : false;

    if (!$certificado || !openssl_pkcs12_export_to_file($certificado, $ruta, $llave, $clave)) {
        fwrite(STDERR, 'No se pudo generar el certificado: ' . openssl_error_string() . "\n");
        exit(1);
    }
} finally {
    unlink($cnf);
}

echo "Certificado de prueba creado (válido 1 año): {$ruta}\n";

<?php
// Validación de RUC/DNI, archivo Excel y registro de errores: sin base de datos.
declare(strict_types=1);

use App\Core\Registro;
use App\Services\DocumentoIdentidad;
use App\Services\HojaExcel;
use Tests\Prueba;

return function (Prueba $t): void {
    $t->grupo('Validación de RUC y DNI');
    $t->verdadero(DocumentoIdentidad::rucValido('20131312955'), 'RUC real de SUNAT (20131312955) es válido');
    $t->verdadero(DocumentoIdentidad::rucValido('20100070970'), 'otro RUC real (20100070970) es válido');
    $t->verdadero(!DocumentoIdentidad::rucValido('20131312954'), 'el mismo RUC con el último dígito cambiado no es válido');
    $t->verdadero(!DocumentoIdentidad::rucValido('30131312955'), 'un prefijo que no existe (30) no es válido');
    $t->verdadero(!DocumentoIdentidad::rucValido('2013131295') && !DocumentoIdentidad::rucValido('2013131295A'), 'RUC con 10 dígitos o con letras no es válido');
    $t->igual(5, DocumentoIdentidad::digitoVerificadorRuc('2013131295'), 'calcula el dígito verificador (módulo 11)');
    $t->verdadero(DocumentoIdentidad::dniValido('45678912') && !DocumentoIdentidad::dniValido('4567891'), 'DNI: exactamente 8 dígitos');

    $t->grupo('Archivo Excel (.xlsx)');
    $contenido = HojaExcel::generar('Prueba', ['Nombre', 'Total'], [['Ana & «Ñandú»', 53.1], ['=1+1', 7]], [20, 10]);
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, $contenido);
    $zip = new ZipArchive();
    $abierto = $zip->open($ruta) === true;
    $t->verdadero($abierto && str_starts_with($contenido, 'PK'), 'es un ZIP (formato .xlsx)');
    $partes = ['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'];
    $bienFormados = $abierto && array_reduce($partes, static function (bool $ok, string $parte) use ($zip): bool {
        $doc = new DOMDocument();
        return $ok && @$doc->loadXML((string) $zip->getFromName($parte));
    }, true);
    $t->verdadero($bienFormados, 'tiene sus 6 partes XML y todas están bien formadas');
    $hoja = $abierto ? (string) $zip->getFromName('xl/worksheets/sheet1.xml') : '';
    $t->verdadero(str_contains($hoja, 'Ana &amp; «Ñandú»'), 'textos con tildes y símbolos se guardan escapados');
    $t->verdadero(str_contains($hoja, '<v>53.1</v>') && str_contains($hoja, '<v>7</v>'), 'los importes se guardan como números');
    $t->verdadero(str_contains($hoja, '<t xml:space="preserve">=1+1</t>') && !str_contains($hoja, '<f>'), 'un texto que empieza con «=» no se vuelve fórmula');
    $t->verdadero(str_contains($hoja, 'state="frozen"') && str_contains($hoja, '<autoFilter ref="A1:B3"/>'), 'encabezado fijo y filtros en A1:B3');
    $zip->close();
    @unlink($ruta);

    $t->grupo('Registro de errores');
    $carpeta = sys_get_temp_dir() . '/registro-prueba-' . bin2hex(random_bytes(3));
    $referencia = Registro::error(new RuntimeException('Fallo de prueba'), $carpeta);
    $contenidoLog = (string) @file_get_contents(Registro::archivo($carpeta));
    $t->verdadero((bool) preg_match('/^E-\d{8}-[0-9A-F]{6}$/', $referencia), 'devuelve un código de referencia: ' . $referencia);
    $t->verdadero(str_contains($contenidoLog, $referencia) && str_contains($contenidoLog, 'RuntimeException: Fallo de prueba'), 'el archivo guarda el código, el tipo y el mensaje');
    $t->verdadero((bool) preg_match('/en tests[\\\\\/]unitarias[\\\\\/]documentos_excel_registro\.php:\d+/', $contenidoLog), 'y el archivo y la línea de origen');
    @unlink(Registro::archivo($carpeta));
    @rmdir($carpeta);
};

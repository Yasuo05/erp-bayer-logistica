<?php
/**
 * Exportación a Excel (.xlsx) común a todo el ERP, sin librerías externas.
 *
 * Mismo formato en Maestros, Logística y Portal Bayer (Facturación usa su propia clase HojaExcel con el mismo diseño):
 *   - título y fecha de generación arriba;
 *   - encabezado en negrita blanca sobre el azul del ERP, fijo al desplazarse y con filtro;
 *   - anchos de columna según el contenido;
 *   - números como números (se pueden sumar): enteros sin decimales y montos con dos decimales;
 *   - fechas como fechas de Excel; los códigos (RUC, DNI, series) se mantienen como texto.
 * Se usa .xlsx y no CSV porque el CSV depende del separador regional (en Perú Excel espera «;» y no «,»).
 */

if (!function_exists('excel_generar')) {

/**
 * @param string $titulo      nombre de la hoja y título que va en la primera fila
 * @param array  $columnas    lista de encabezados, o [encabezado => tipo] con tipo: texto | entero | decimal | fecha | fechahora
 * @param array  $filas       cada fila es una lista de valores en el orden de $columnas
 */
function excel_generar(string $titulo, array $columnas, array $filas): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La extensión zip de PHP es necesaria para exportar a Excel.');
    $enc = []; $tipos = [];
    foreach ($columnas as $k => $v) {
        if (is_int($k)) { $enc[] = (string)$v; $tipos[] = 'auto'; } else { $enc[] = (string)$k; $tipos[] = (string)$v; }
    }
    $hoja = mb_substr(trim((string)preg_replace('/[\\\\\/?*\[\]:]/', ' ', $titulo)) ?: 'Datos', 0, 31);
    $nCols = max(1, count($enc));
    $ultCol = excel_columna($nCols);
    $filaEnc = 3;                                  // 1: título · 2: generado el … · 3: encabezados
    $ultFila = $filaEnc + count($filas);

    // Anchos según el contenido más largo de cada columna (entre 8 y 60 caracteres)
    $anchos = array_map(fn($e) => mb_strlen($e) + 4, $enc);
    foreach ($filas as $f) {
        foreach (array_values($f) as $i => $v) {
            if ($i >= $nCols) break;
            $largo = $v instanceof DateTimeInterface ? 16 : mb_strlen(is_float($v) ? number_format($v, 2) : (string)$v) + 2;
            $anchos[$i] = max($anchos[$i], $largo);
        }
    }
    $cols = '';
    foreach ($anchos as $i => $a) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . min(60, max(8, $a)) . '" customWidth="1"/>';

    $datos = '<row r="1" ht="22" customHeight="1">' . excel_celda('A1', $titulo, 'texto', 4) . '</row>'
           . '<row r="2">' . excel_celda('A2', 'Generado el ' . excel_ahora('d/m/Y H:i') . ' · ' . count($filas) . ' registro(s)', 'texto', 5) . '</row>'
           . '<row r="3">';
    foreach ($enc as $i => $e) $datos .= excel_celda(excel_columna($i + 1) . $filaEnc, $e, 'texto', 1);
    $datos .= '</row>';
    foreach (array_values($filas) as $n => $f) {
        $r = $filaEnc + 1 + $n;
        $datos .= '<row r="' . $r . '">';
        foreach (array_values($f) as $i => $v) {
            if ($i >= $nCols) break;
            $datos .= excel_celda(excel_columna($i + 1) . $r, $v, $tipos[$i] ?? 'auto');
        }
        $datos .= '</row>';
    }
    $rango = "A{$filaEnc}:{$ultCol}" . max($filaEnc, $ultFila);

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    try {
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el archivo de Excel.');
        $zip->addFromString('[Content_Types].xml', $xml . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $hojaEsc = excel_texto($hoja);
        $zip->addFromString('xl/workbook.xml', $xml . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $hojaEsc . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . excel_texto(str_replace("'", "''", $hoja)) . '\'!$A$' . $filaEnc . ':$' . $ultCol . '$' . max($filaEnc, $ultFila) . '</definedName></definedNames></workbook>');
        // Estilos: 0 normal · 1 encabezado · 2 decimal · 3 fecha y hora · 4 título · 5 nota · 6 entero · 7 fecha
        $zip->addFromString('xl/styles.xml', $xml . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/><numFmt numFmtId="166" formatCode="dd/mm/yyyy"/></numFmts>'
            . '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF10384F"/><name val="Calibri"/></font><font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF10384F"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $filaEnc . '" topLeftCell="A' . ($filaEnc + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>' . $cols . '</cols><sheetData>' . $datos . '</sheetData>'
            . '<autoFilter ref="' . $rango . '"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>');
        $zip->close();
        return (string)file_get_contents($ruta);
    } finally {
        @unlink($ruta);
    }
}

/** Fecha y hora de Lima (el PHP del servidor puede estar en UTC). */
function excel_ahora(string $formato): string {
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format($formato);
}

/** Envía el archivo al navegador y termina. */
function excel_descargar(string $nombre, string $contenido): never {
    while (ob_get_level() > 0) ob_end_clean();
    $nombre = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $nombre);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store');
    echo $contenido;
    exit;
}

/** Celda según su tipo. «auto»: int → entero, float → decimal, DateTime → fecha y hora, lo demás texto. */
function excel_celda(string $ref, $v, string $tipo = 'auto', int $estiloTexto = 0): string {
    if ($v === null || $v === '') return '';
    if ($tipo === 'auto') {
        $tipo = $v instanceof DateTimeInterface ? 'fechahora' : (is_int($v) ? 'entero' : (is_float($v) ? 'decimal' : 'texto'));
    }
    if (($tipo === 'fecha' || $tipo === 'fechahora') && !$v instanceof DateTimeInterface) {
        try { $v = new DateTimeImmutable((string)$v); } catch (Exception $e) { $tipo = 'texto'; }
    }
    if ($tipo === 'fecha' || $tipo === 'fechahora') {
        $utc = new DateTimeImmutable($v->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
        $serial = rtrim(rtrim(number_format($utc->getTimestamp() / 86400 + 25569, 8, '.', ''), '0'), '.');
        return '<c r="' . $ref . '" s="' . ($tipo === 'fecha' ? 7 : 3) . '"><v>' . $serial . '</v></c>';
    }
    if (($tipo === 'entero' || $tipo === 'decimal') && is_numeric($v)) {
        return '<c r="' . $ref . '" s="' . ($tipo === 'entero' ? 6 : 2) . '"><v>' . ($tipo === 'entero' ? (string)(0 + $v) : (string)(float)$v) . '</v></c>';
    }
    // Texto (inlineStr): nunca se interpreta como fórmula aunque empiece con «=»
    return '<c r="' . $ref . '" t="inlineStr"' . ($estiloTexto ? ' s="' . $estiloTexto . '"' : ' s="0"') . '><is><t xml:space="preserve">' . excel_texto((string)$v) . '</t></is></c>';
}

/** 1 → A, 26 → Z, 27 → AA */
function excel_columna(int $n): string {
    $s = '';
    while ($n > 0) { $r = ($n - 1) % 26; $s = chr(65 + $r) . $s; $n = intdiv($n - $r - 1, 26); }
    return $s;
}

function excel_texto(string $v): string {
    return htmlspecialchars((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

}

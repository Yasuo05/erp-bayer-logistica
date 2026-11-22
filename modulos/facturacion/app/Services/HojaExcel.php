<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Genera un archivo Excel (.xlsx) de una sola hoja, sin librerías externas.
 * Un .xlsx es un ZIP con archivos XML: aquí se arman los mínimos para que Excel lo abra
 * con encabezado resaltado, primera fila fija, filtros y números con dos decimales.
 */
final class HojaExcel
{
    /**
     * @param string[]   $encabezados
     * @param array[]    $filas  cada fila es una lista de valores: los int/float se guardan como número y los DateTimeInterface como fecha
     * @param int[]      $anchos ancho de cada columna (en caracteres)
     * @param string|null $nota  texto opcional que se escribe debajo de los datos, fuera del filtro (ej. un aviso de que el resultado se cortó)
     * @return string    contenido del archivo .xlsx
     */
    public static function generar(string $nombreHoja, array $encabezados, array $filas, array $anchos = [], ?string $nota = null): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('La extensión zip de PHP es necesaria para exportar a Excel.');
        }
        $nombreHoja = mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $nombreHoja) ?: 'Hoja1', 0, 31);
        $ultimaColumna = self::columna(count($encabezados));
        $ultimaFila = count($filas) + 1;

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($ruta === false) {
            throw new \RuntimeException('No se pudo preparar el archivo de Excel.');
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el archivo de Excel.');
            }
            $zip->addFromString('[Content_Types].xml', self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>');
            $zip->addFromString('_rels/.rels', self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/workbook.xml', self::XML . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . self::texto($nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets>'
                . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . self::texto(str_replace("'", "''", $nombreHoja)) . "'!\$A\$1:\${$ultimaColumna}\${$ultimaFila}</definedName></definedNames>"
                . '</workbook>');
            $zip->addFromString('xl/styles.xml', self::estilos());
            $zip->addFromString('xl/worksheets/sheet1.xml', self::hoja($encabezados, $filas, $anchos, "A1:{$ultimaColumna}{$ultimaFila}", $nota));
            $zip->close();

            return (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
        }
    }

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    /** Estilos: 0 normal, 1 encabezado (negrita blanca sobre azul), 2 número con dos decimales, 3 fecha y hora */
    private static function estilos(): string
    {
        return self::XML . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF10384F"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function hoja(array $encabezados, array $filas, array $anchos, string $rango, ?string $nota = null): string
    {
        $columnas = '';
        foreach ($encabezados as $i => $_) {
            $n = $i + 1;
            $columnas .= '<col min="' . $n . '" max="' . $n . '" width="' . (int) ($anchos[$i] ?? 16) . '" customWidth="1"/>';
        }
        $datos = self::fila(1, $encabezados, 1);
        foreach (array_values($filas) as $i => $fila) {
            $datos .= self::fila($i + 2, $fila, 0);
        }
        if ($nota !== null && $nota !== '') {
            // Una fila en blanco después de los datos: queda fuera del rango del filtro y no se mezcla al ordenar
            $datos .= self::fila(count($filas) + 3, [$nota], 0);
        }

        return self::XML . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $columnas . '</cols>'
            . '<sheetData>' . $datos . '</sheetData>'
            . '<autoFilter ref="' . $rango . '"/>'
            . '</worksheet>';
    }

    private static function fila(int $numero, array $valores, int $estiloTexto): string
    {
        $celdas = '';
        foreach (array_values($valores) as $i => $valor) {
            $ref = self::columna($i + 1) . $numero;
            if ($valor instanceof \DateTimeInterface) {
                // Fecha real de Excel (se puede filtrar y ordenar por fecha), no un texto
                $celdas .= '<c r="' . $ref . '" s="3"><v>' . self::serialExcel($valor) . '</v></c>';
            } elseif (is_int($valor) || is_float($valor)) {
                $celdas .= '<c r="' . $ref . '" s="2"><v>' . $valor . '</v></c>';
            } elseif ($valor !== null && $valor !== '') {
                // Texto en la celda (inlineStr): no se interpreta como fórmula aunque empiece con «=»
                $celdas .= '<c r="' . $ref . '" t="inlineStr"' . ($estiloTexto ? ' s="' . $estiloTexto . '"' : '') . '><is><t xml:space="preserve">' . self::texto((string) $valor) . '</t></is></c>';
            }
        }

        return '<row r="' . $numero . '">' . $celdas . '</row>';
    }

    /** Número de serie de Excel (días desde 1900): se conserva la hora tal como está escrita, sin cambiar de zona horaria */
    private static function serialExcel(\DateTimeInterface $fecha): string
    {
        $utc = new \DateTimeImmutable($fecha->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));

        return rtrim(rtrim(number_format($utc->getTimestamp() / 86400 + 25569, 8, '.', ''), '0'), '.');
    }

    /** 1 → A, 26 → Z, 27 → AA */
    private static function columna(int $numero): string
    {
        $letras = '';
        while ($numero > 0) {
            $resto = ($numero - 1) % 26;
            $letras = chr(65 + $resto) . $letras;
            $numero = intdiv($numero - $resto - 1, 26);
        }

        return $letras;
    }

    private static function texto(string $valor): string
    {
        // Quita caracteres de control que el XML no admite
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $valor), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

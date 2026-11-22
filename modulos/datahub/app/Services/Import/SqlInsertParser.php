<?php
namespace App\Services\Import;

/**
 * Parser tolerante de scripts SQL (T-SQL de SQL Server y MySQL) que extrae
 * únicamente las sentencias INSERT ... VALUES (...),(...);
 * Soporta literales N'..', escapes '' , números, NULL y bloques separados por GO.
 * No ejecuta nada: devuelve filas asociativas por tabla.
 */
class SqlInsertParser
{
    /** @return array<string, array<int, array<string, mixed>>> tabla(normalizada) => filas */
    public function parseFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Archivo no encontrado: $path");
        }
        return $this->parse((string)file_get_contents($path));
    }

    public function parse(string $sql): array
    {
        // Quitar BOM y normalizar codificación (si viniera en Windows-1252).
        if (str_starts_with($sql, "\xEF\xBB\xBF")) $sql = substr($sql, 3);
        if (!preg_match('//u', $sql)) {
            $conv = @iconv('Windows-1252', 'UTF-8//IGNORE', $sql);
            if ($conv !== false) $sql = $conv;
        }
        $out = [];
        $re = '/INSERT\s+(?:IGNORE\s+)?INTO\s+([\w\.\[\]`"]+)\s*\(([^)]*)\)\s*VALUES\s*/i';
        $offset = 0;
        $len = strlen($sql);
        while (preg_match($re, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $table = $this->normalizeTable($m[1][0]);
            $cols = array_map(fn($c) => trim($c, " \t\r\n[]`\""), explode(',', $m[2][0]));
            $pos = $m[0][1] + strlen($m[0][0]);
            [$rows, $pos] = $this->readTuples($sql, $pos, $len);
            foreach ($rows as $vals) {
                if (count($vals) !== count($cols)) {
                    throw new \RuntimeException("Columnas/valores no coinciden en $table (".count($cols)." vs ".count($vals).")");
                }
                $out[$table][] = array_combine($cols, $vals);
            }
            $offset = $pos;
        }
        return $out;
    }

    private function normalizeTable(string $raw): string
    {
        $raw = str_replace(['[', ']', '`', '"'], '', $raw);
        $parts = explode('.', $raw);
        return strtolower(end($parts));
    }

    /** Lee tuplas "(a,b),(c,d)" hasta ';' o fin de sentencia. */
    private function readTuples(string $s, int $i, int $len): array
    {
        $rows = [];
        while ($i < $len) {
            $c = $s[$i];
            if ($c === '(') {
                [$vals, $i] = $this->readTuple($s, $i + 1, $len);
                $rows[] = $vals;
                continue;
            }
            if ($c === ';') { $i++; break; }
            if ($c === ',' || ctype_space($c)) { $i++; continue; }
            break; // otra sentencia (p.ej. GO sin ';')
        }
        return [$rows, $i];
    }

    private function readTuple(string $s, int $i, int $len): array
    {
        $vals = [];
        $buf = '';
        $isStr = false;
        $hasValue = false;
        while ($i < $len) {
            $c = $s[$i];
            if ($c === "'" ) {
                // literal de texto
                $i++;
                $str = '';
                while ($i < $len) {
                    if ($s[$i] === "'") {
                        if ($i + 1 < $len && $s[$i + 1] === "'") { $str .= "'"; $i += 2; continue; }
                        $i++;
                        break;
                    }
                    if ($s[$i] === '\\' && $i + 1 < $len && in_array($s[$i + 1], ["'", '\\'], true)) {
                        // escape estilo MySQL
                        $str .= $s[$i + 1]; $i += 2; continue;
                    }
                    $str .= $s[$i++];
                }
                $buf = $str; $isStr = true; $hasValue = true;
                continue;
            }
            if ($c === ',' || $c === ')') {
                $vals[] = $this->cast($buf, $isStr, $hasValue);
                $buf = ''; $isStr = false; $hasValue = false;
                $i++;
                if ($c === ')') break;
                continue;
            }
            if (!$isStr) {
                if (($c === 'N' || $c === 'n') && $i + 1 < $len && $s[$i + 1] === "'" && trim($buf) === '') { $i++; continue; }
                if (!ctype_space($c)) { $buf .= $c; $hasValue = true; }
            }
            $i++;
        }
        return [$vals, $i];
    }

    private function cast(string $buf, bool $isStr, bool $hasValue): mixed
    {
        if ($isStr) return $buf;
        if (!$hasValue || strcasecmp($buf, 'NULL') === 0) return null;
        return is_numeric($buf) ? $buf + 0 : $buf;
    }
}

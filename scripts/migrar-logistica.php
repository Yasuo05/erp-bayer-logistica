<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/db.php';
$pdo->exec('CREATE TABLE IF NOT EXISTS erp_migraciones_logistica (nombre VARCHAR(160) PRIMARY KEY, sha256 CHAR(64) NOT NULL, aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$files = glob(dirname(__DIR__) . '/sql/migrations/*.sql') ?: [];
sort($files);
if (!$files) { fwrite(STDERR, "No hay migraciones logísticas disponibles.\n"); exit(1); }
foreach ($files as $file) {
    $name = basename($file);
    $hash = hash_file('sha256', $file);
    $st = $pdo->prepare('SELECT sha256 FROM erp_migraciones_logistica WHERE nombre = ?');
    $st->execute([$name]);
    $previous = $st->fetchColumn();
    if ($previous !== false) {
        if (!hash_equals($previous, $hash)) { throw new RuntimeException("La migración aplicada cambió: $name. Revisar antes de continuar."); }
        echo "Ya aplicada: $name\n";
        continue;
    }
    // DDL MySQL confirma implícitamente: las migraciones deben poder reanudarse.
    $pdo->exec(file_get_contents($file));
    $pdo->prepare('INSERT INTO erp_migraciones_logistica (nombre, sha256) VALUES (?, ?)')->execute([$name, $hash]);
    echo "Aplicada: $name\n";
}

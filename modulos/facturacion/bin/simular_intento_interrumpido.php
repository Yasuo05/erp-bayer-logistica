<?php

declare(strict_types=1);

/*
 * Herramienta de prueba: deja un intento de envío «interrumpido», como si el proceso se hubiera cortado
 * después de registrar la solicitud y antes de guardar la respuesta. Sirve para probar «Recuperar intento».
 *
 * Uso: php bin/simular_intento_interrumpido.php [id del comprobante]
 * Sin id, usa el primer comprobante en estado «XML firmado». Solo funciona con el envío simulado.
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\EnvioService;
use App\Services\GatewayFactory;

Env::cargar(BASE_PATH . '/.env');
date_default_timezone_set('America/Lima');

if (!GatewayFactory::esDemo()) {
    fwrite(STDERR, "Bloqueado: hay un gateway externo configurado. Esta herramienta solo se usa con el envío simulado.\n");
    exit(1);
}

$db = Database::conexion();
$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    $id = (int) $db->query("SELECT f.id FROM facturacion_comprobantes f JOIN facturacion_xml x ON x.comprobante_id=f.id
        WHERE f.estado='firmado' AND x.hash IS NOT NULL ORDER BY f.id LIMIT 1")->fetchColumn();
}

$q = $db->prepare('SELECT f.estado, x.hash, x.nombre_archivo FROM facturacion_comprobantes f JOIN facturacion_xml x ON x.comprobante_id=f.id WHERE f.id=?');
$q->execute([$id]);
$comprobante = $q->fetch();
if (!$comprobante || !in_array($comprobante['estado'], ['firmado', 'pendiente_reintento'], true) || !$comprobante['hash']) {
    fwrite(STDERR, "No hay un comprobante firmado (o pendiente de reintento) para simular. Emite uno, genera su XML y fírmalo.\n");
    exit(1);
}
$q = $db->prepare("SELECT COUNT(*) FROM facturacion_envios WHERE comprobante_id=? AND estado='enviando'");
$q->execute([$id]);
if ((int) $q->fetchColumn() > 0) {
    fwrite(STDERR, "El comprobante #{$id} ya tiene un intento sin respuesta.\n");
    exit(1);
}

// Igual que EnvioService::enviar() hasta registrar la solicitud; la respuesta nunca llega
$q = $db->prepare('SELECT COALESCE(MAX(intento),0)+1 FROM facturacion_envios WHERE comprobante_id=?');
$q->execute([$id]);
$intento = (int) $q->fetchColumn();
$clave = hash('sha256', $id . '|' . $intento . '|' . $comprobante['hash']);
$minutos = 5;

$db->beginTransaction();
$db->prepare("INSERT INTO facturacion_envios (comprobante_id,intento,origen,idempotencia,estado,solicitado_en)
    VALUES (?,?,'simulado',?,'enviando',NOW() - INTERVAL {$minutos} MINUTE)")->execute([$id, $intento, $clave]);
$envio = (int) $db->lastInsertId();
$db->prepare("INSERT INTO facturacion_envio_eventos (envio_id,etapa,nivel,codigo,detalle,creado_en)
    VALUES (?,'solicitud','info',NULL,?,NOW() - INTERVAL {$minutos} MINUTE)")
    ->execute([$envio, "Envío registrado para {$comprobante['nombre_archivo']}. Clave de control: {$clave}. Respuesta: simulada, sin validez tributaria. (Simulación de intento interrumpido.)"]);
$db->commit();

echo "Intento {$intento} del comprobante #{$id} quedó interrumpido (sin respuesta desde hace {$minutos} minutos).\n";
echo 'Ábrelo en /envio/estado?id=' . $id . ' y usa «Recuperar intento». Se considera interrumpido tras ' . EnvioService::SEGUNDOS_INTERRUMPIDO . " segundos.\n";

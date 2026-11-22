<?php

declare(strict_types=1);

namespace App\Services;

/** Contrato del proveedor. La implementación actual es simulación local, no SUNAT. */
interface GatewayEnvio
{
    /** @return array{tipo:string,codigo:string,mensaje:string,ticket:?string,cdr:?string} */
    public function enviar(string $nombre, string $xml, string $escenario, string $idempotencia): array;
    /** @return array{tipo:string,codigo:string,mensaje:string,ticket:?string,cdr:?string} */
    public function consultar(string $ticket, string $nombre): array;
    /** 'simulado' o 'proveedor': se guarda en cada intento para no confundir resultados simulados con reales. */
    public function origen(): string;
}

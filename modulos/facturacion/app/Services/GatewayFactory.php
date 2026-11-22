<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

final class GatewayFactory
{
    public static function crear(): GatewayEnvio
    { return trim((string)Env::get('ENVIO_GATEWAY_URL',''))===''?new GatewayDemo():new GatewayHttp(); }
    public static function esDemo(): bool { return trim((string)Env::get('ENVIO_GATEWAY_URL',''))===''; }
}

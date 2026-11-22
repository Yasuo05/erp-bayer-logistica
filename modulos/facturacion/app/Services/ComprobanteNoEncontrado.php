<?php

declare(strict_types=1);

namespace App\Services;

/** El comprobante pedido no existe (para responder "no encontrado" en vez de un error general) */
final class ComprobanteNoEncontrado extends \RuntimeException
{
}

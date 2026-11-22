<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\FacturacionRepository;

final class EstadoComprobanteService
{
    /** Estados válidos ante SUNAT: solo estos pueden anularse con una comunicación de baja */
    public const ESTADOS_ANULABLES = ['aceptado', 'observado'];

    /** El motivo viaja en la comunicación de baja, que admite hasta 100 caracteres */
    public const MAXIMO_MOTIVO_ANULACION = 100;

    public function existe(string $estado): bool
    {
        return array_key_exists($estado, config('estados.etiquetas', []));
    }

    public function validarTransicion(string $actual, string $nuevo): void
    {
        if (!$this->existe($actual) || !$this->existe($nuevo)) {
            throw new \InvalidArgumentException('El estado del comprobante no está definido.');
        }

        $permitidos = config("estados.transiciones.{$actual}", []);
        if (!in_array($nuevo, $permitidos, true)) {
            throw new \RuntimeException(
                sprintf('No se permite cambiar el comprobante de «%s» a «%s».', estado_comprobante($actual), estado_comprobante($nuevo))
            );
        }
    }

    /**
     * Además de que la transición esté permitida, el comprobante debe tener lo que respalda el nuevo estado:
     * XML generado, firma verificada, intento de envío o respuesta registrada.
     * Se llama dentro de la transacción de quien hace el cambio, justo antes de actualizar el estado,
     * para que vea los datos recién guardados.
     */
    public function exigirCondiciones(int $id, string $nuevo): void
    {
        $db = Database::conexion();

        $q = $db->prepare('SELECT xml, hash FROM facturacion_xml WHERE comprobante_id = ?');
        $q->execute([$id]);
        $xml = $q->fetch() ?: null;

        $q = $db->prepare('SELECT tipo_respuesta, respondido_en FROM facturacion_envios WHERE comprobante_id = ? ORDER BY intento DESC LIMIT 1');
        $q->execute([$id]);
        $ultimoEnvio = $q->fetch() ?: null;

        $falta = match ($nuevo) {
            'generado_xml' => $xml === null || trim((string) $xml['xml']) === '' || $xml['hash'] !== null
                ? 'falta el XML generado (sin firmar).' : null,
            'firmado' => $xml === null || $xml['hash'] === null || !(new FirmaXmlService())->verificar((string) $xml['xml'])
                ? 'la firma del XML no está registrada o no pasa la verificación.' : null,
            'enviado' => match (true) {
                $xml === null || $xml['hash'] === null => 'falta el XML firmado.',
                $ultimoEnvio === null => 'no hay un intento de envío registrado.',
                default => null,
            },
            'aceptado', 'observado', 'rechazado' => $ultimoEnvio === null || $ultimoEnvio['tipo_respuesta'] !== $nuevo || $ultimoEnvio['respondido_en'] === null
                ? 'el último intento de envío no tiene registrada esa respuesta.' : null,
            // Solo una falla técnica habilita reenviar el mismo XML; un rechazo se corrige regenerándolo
            'pendiente_reintento' => $ultimoEnvio === null || $ultimoEnvio['tipo_respuesta'] !== 'error_transitorio'
                ? 'el último intento no terminó en una falla técnica.' : null,
            'anulado_local' => $ultimoEnvio !== null
                ? 'el comprobante ya tiene intentos de envío; su anulación debe hacerse ante SUNAT.' : null,
            // El plazo y el motivo se comprueban al pedir la baja (verificarAnulacion); aquí, que nada dependa de él
            'anulado' => $this->notasVigentes($id) > 0
                ? 'tiene notas de crédito o débito vigentes; anúlalas primero.' : null,
            default => null,
        };

        if ($falta !== null) {
            throw new \RuntimeException(sprintf('No se puede pasar a «%s»: %s', estado_comprobante($nuevo), $falta));
        }
    }

    /**
     * Comprueba que un comprobante pueda anularse ante SUNAT antes de pedir su baja.
     * Lo usa quien genera y envía la comunicación de baja o el resumen diario.
     */
    public function verificarAnulacion(int $id, string $motivo, ?\DateTimeImmutable $hoy = null): void
    {
        $q = Database::conexion()->prepare('SELECT estado, fecha_emision FROM facturacion_comprobantes WHERE id = ?');
        $q->execute([$id]);
        $comprobante = $q->fetch() ?: throw new \RuntimeException('No se encontró el comprobante.');

        $impedimento = self::impedimentoAnulacion($comprobante, $this->notasVigentes($id), $motivo, $hoy ?? new \DateTimeImmutable());
        if ($impedimento !== null) {
            throw new \RuntimeException('No se puede anular el comprobante: ' . $impedimento);
        }
    }

    /**
     * Por qué no puede anularse un comprobante (null = sí puede). Reglas sin base de datos:
     * debe ser válido ante SUNAT, tener motivo, estar dentro del plazo y no tener notas vigentes.
     * @param array{estado:string, fecha_emision:string} $comprobante
     */
    public static function impedimentoAnulacion(array $comprobante, int $notasVigentes, string $motivo, \DateTimeImmutable $hoy): ?string
    {
        $plazo = (int) config('sunat.plazo_anulacion_dias', 7);
        $limite = (new \DateTimeImmutable(substr((string) $comprobante['fecha_emision'], 0, 10)))->modify("+{$plazo} days");
        $motivo = trim($motivo);

        return match (true) {
            !in_array($comprobante['estado'], self::ESTADOS_ANULABLES, true)
                => sprintf('está «%s»; solo se anula ante SUNAT un comprobante aceptado u observado.', estado_comprobante((string) $comprobante['estado'])),
            $motivo === '' => 'indica el motivo de la anulación.',
            mb_strlen($motivo) > self::MAXIMO_MOTIVO_ANULACION
                => sprintf('el motivo admite como máximo %d caracteres.', self::MAXIMO_MOTIVO_ANULACION),
            $hoy->setTime(0, 0) > $limite
                => sprintf('venció el plazo de %d días para pedir su baja (era hasta el %s).', $plazo, $limite->format('d/m/Y')),
            $notasVigentes > 0 => 'tiene notas de crédito o débito vigentes; anúlalas primero.',
            default => null,
        };
    }

    /** Notas que modifican el comprobante y siguen teniendo efecto */
    private function notasVigentes(int $id): int
    {
        $sinEfecto = FacturacionRepository::ESTADOS_SIN_EFECTO;
        $q = Database::conexion()->prepare('SELECT COUNT(*) FROM facturacion_comprobantes WHERE comprobante_referenciado_id = ? AND estado NOT IN ('
            . implode(',', array_fill(0, count($sinEfecto), '?')) . ')');
        $q->execute([$id, ...$sinEfecto]);

        return (int) $q->fetchColumn();
    }
}

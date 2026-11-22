<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FacturacionRepository;
use App\Core\Env;

final class FacturacionService
{
    public function __construct(private FacturacionRepository $repo = new FacturacionRepository()) {}

    /** Tipo de comprobante que corresponde al cliente: RUC → factura; DNI → boleta */
    public static function tipoParaCliente(string $tipoDocumento): string
    {
        return $tipoDocumento === 'RUC' ? 'factura' : 'boleta';
    }

    /**
     * Revisión previa: importes, tasa y serie con que se emitiría la venta, sin guardar nada.
     * @return array{tipo:string, tasa:float, importes:array, serie:?array, huella:string}
     */
    /**
     * Reglas para facturar una venta del ERP: en soles y con el IGV de cada línea igual a la tasa configurada
     * (Comercial permite escribir la tasa por línea; Facturación emite con una sola tasa por comprobante).
     */
    public static function validarVentaFacturable(array $venta, float $tasa): void
    {
        if (isset($venta['moneda']) && $venta['moneda'] !== 'PEN') {
            throw new \InvalidArgumentException('Solo se facturan ventas en soles (PEN). La venta está en ' . $venta['moneda'] . '.');
        }
        foreach ($venta['detalles'] as $linea) {
            if (array_key_exists('unidad_nombre', $linea) && !preg_match('/^[A-Z0-9]{2,3}$/', (string) $linea['unidad_medida'])) {
                throw new \InvalidArgumentException('La unidad «' . ($linea['unidad_nombre'] ?? '?') . '» de «' . $linea['descripcion']
                    . '» no tiene código SUNAT. Complétalo en Catálogo › Unidades de medida.');
            }
            if (isset($linea['tasa_impuesto']) && abs((float) $linea['tasa_impuesto'] - $tasa * 100) > 0.0001) {
                throw new \InvalidArgumentException('La línea «' . $linea['descripcion'] . '» tiene IGV de ' . (float) $linea['tasa_impuesto'] . '%, distinto del '
                    . ($tasa * 100) . '% configurado. Corrige la venta en Comercial antes de facturar.');
            }
        }
    }

    public function revisarVenta(array $venta): array
    {
        $tipo = self::tipoParaCliente((string) $venta['tipo_documento']);
        $tasa = self::tasaIgv();
        self::validarVentaFacturable($venta, $tasa);

        $revision = [
            'tipo'     => $tipo,
            'tasa'     => $tasa,
            'importes' => CalculoImportes::calcular($venta['detalles'], $tasa),
            'serie'    => (new SeriesService())->previsualizar($tipo, null, isset($venta['sucursal_id']) ? (int) $venta['sucursal_id'] : null),
        ];
        $revision['huella'] = $this->huellaRevision($venta, $tipo, $tasa);

        return $revision;
    }

    /**
     * @param float|null $totalRevisado total que el usuario vio al revisar; si los importes cambiaron desde entonces
     *                                  no se emite (lo emitido debe ser exactamente lo revisado)
     * @param float|null $tasaRevisada tasa de IGV que vio el usuario; evita emitir con una configuración distinta
     * @param string $huellaRevisada HMAC de los datos que el usuario revisó antes de confirmar
     */
    public function emitirVenta(int $ventaId, string $tipo, ?float $totalRevisado = null, ?float $tasaRevisada = null, string $huellaRevisada = ''): int
    {
        if(!in_array($tipo,['factura','boleta'],true))throw new \InvalidArgumentException('Selecciona factura o boleta.');
        if (!preg_match('/^[a-f0-9]{64}$/i', $huellaRevisada)) {
            throw new \InvalidArgumentException('Vuelve a revisar la venta antes de emitir. La huella de revisión no es válida.');
        }

        $tasa = self::tasaIgv();
        return $this->repo->emitirVentaConRevision($ventaId, $tipo, $tasa, function (array $venta) use ($tipo, $totalRevisado, $tasaRevisada, $huellaRevisada, $tasa): void {
            $tipoDoc = (string) $venta['tipo_documento'];
            $numeroDocumento = (string) $venta['numero_documento'];
            if ($tipoDoc === 'RUC' && !DocumentoIdentidad::rucValido($numeroDocumento)) {
                throw new \InvalidArgumentException('El RUC del cliente no es válido. Corrige sus datos antes de emitir.');
            }
            if ($tipoDoc === 'DNI' && !DocumentoIdentidad::dniValido($numeroDocumento)) {
                throw new \InvalidArgumentException('El DNI del cliente debe tener 8 dígitos. Corrige sus datos antes de emitir.');
            }
            if (!in_array($tipoDoc, ['DNI', 'RUC'], true)) {
                throw new \InvalidArgumentException('El cliente debe tener un DNI o RUC válido para emitir.');
            }
            if ($tipo === 'factura' && $tipoDoc !== 'RUC') throw new \InvalidArgumentException('La factura requiere un cliente con RUC.');
            if ($tipo === 'boleta' && $tipoDoc === 'RUC') throw new \InvalidArgumentException('Un cliente con RUC debe usar factura.');
            if ($tipo === 'boleta' && $tipoDoc !== 'DNI') throw new \InvalidArgumentException('La boleta requiere un cliente con DNI.');

            self::validarVentaFacturable($venta, $tasa);
            if ($tasaRevisada === null || !is_finite($tasaRevisada) || abs($tasa - $tasaRevisada) >= 0.000000001) {
                throw new \RuntimeException('La tasa de IGV cambió desde la revisión. Vuelve a revisar la venta antes de emitir.');
            }
            $importes = CalculoImportes::calcular($venta['detalles'], $tasa);
            if ($totalRevisado === null || !is_finite($totalRevisado) || abs($importes['total'] - $totalRevisado) >= 0.005) {
                throw new \RuntimeException('Los importes de la venta cambiaron desde la revisión. Revísalos otra vez antes de emitir.');
            }

            $huellaActual = $this->huellaRevision($venta, $tipo, $tasa);
            if (!hash_equals($huellaActual, strtolower($huellaRevisada))) {
                throw new \RuntimeException('El cliente o las líneas de la venta cambiaron desde la revisión. Revísala otra vez antes de emitir.');
            }
        });
    }

    /** Firma los datos relevantes que se mostraron al revisar la venta; la clave queda en la sesión del servidor. */
    private function huellaRevision(array $venta, string $tipo, float $tasa): string
    {
        $lineas = array_map(static fn (array $linea): array => [
            'codigo' => (string) ($linea['codigo_producto'] ?? ''),
            'descripcion' => (string) ($linea['descripcion'] ?? ''),
            'cantidad' => (string) $linea['cantidad'],
            'unidad' => (string) ($linea['unidad_medida'] ?? ''),
            'precio_unitario' => (string) $linea['precio_unitario'],
            'descuento' => (string) ($linea['descuento'] ?? '0'),
        ], $venta['detalles']);
        $datos = [
            'venta_id' => (int) $venta['id'],
            'cliente' => [
                'id' => (int) $venta['cliente_id'],
                'nombre' => (string) $venta['cliente'],
                'tipo_documento' => (string) $venta['tipo_documento'],
                'numero_documento' => (string) $venta['numero_documento'],
            ],
            'lineas' => $lineas,
            'tasa' => number_format($tasa, 10, '.', ''),
            'tipo' => $tipo,
        ];
        $clave = self::claveParaHuella();

        return hash_hmac('sha256', json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $clave);
    }

    private static function claveParaHuella(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!is_string($_SESSION['_clave_huella_revision'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $_SESSION['_clave_huella_revision'])) {
                $_SESSION['_clave_huella_revision'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['_clave_huella_revision'];
        }
        if (PHP_SAPI === 'cli') {
            static $claveCli = null;
            return $claveCli ??= bin2hex(random_bytes(32));
        }
        throw new \RuntimeException('La sesión no está disponible para verificar la revisión de la venta.');
    }

    /**
     * Nota de crédito o débito sobre una factura o boleta aceptada.
     * El repositorio comprueba, con la referencia bloqueada, que una nota de crédito no supere el saldo.
     */
    public function emitirNota(int $referenciaId,string $tipo,string $codigoMotivo,string $motivo,float $monto): int
    {
        if(!in_array($tipo,['nota_credito','nota_debito'],true))throw new \InvalidArgumentException('Tipo de nota inválido.');
        $motivos=config("sunat.motivos_nota.{$tipo}",[]);
        if(!array_key_exists($codigoMotivo,$motivos))throw new \InvalidArgumentException('Selecciona un motivo válido del catálogo de SUNAT.');
        $motivo=trim($motivo);
        if($motivo==='' || mb_strlen($motivo)>500)throw new \InvalidArgumentException('Describe el ajuste (máximo 500 caracteres).');
        if(!is_finite($monto)||round($monto,2)<=0)throw new \InvalidArgumentException('El ajuste debe ser mayor a cero.');
        if($monto>9999999.99)throw new \InvalidArgumentException('El ajuste supera el máximo permitido.');
        return $this->repo->emitirNota($referenciaId,$tipo,$codigoMotivo,$motivo,round($monto,2));
    }

    public static function tasaIgv(): float
    {
        // Parámetro central del ERP (G4 · Administración › Parámetros); el .env queda como respaldo
        static $tasa = null;
        if ($tasa === null) {
            try {
                $valor = \App\Core\Database::conexion()->query("SELECT valor FROM parametros WHERE clave = 'igv_tasa'")->fetchColumn();
                $tasa = is_numeric($valor) ? round((float) $valor / 100, 6) : (float) Env::get('APP_IGV_RATE', '0.18');
            } catch (\Throwable) {
                $tasa = (float) Env::get('APP_IGV_RATE', '0.18');
            }
        }
        return $tasa;
    }
}

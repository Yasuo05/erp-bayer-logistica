<?php

declare(strict_types=1);

namespace App\Services;

use App\Controllers\ConsultaController;
use App\Core\Paginacion;
use App\Repositories\ConsultaRepository;
use DateTime;

/**
 * Búsqueda paginada de comprobantes con sus archivos (PDF, XML y CDR).
 * La usan "Consulta y descarga" y los listados de Facturas y Boletas.
 */
final class ConsultaComprobantesService
{
    public function __construct(private ConsultaRepository $repo = new ConsultaRepository())
    {
    }

    /**
     * @param array       $entrada  normalmente $_GET
     * @param string|null $tipoFijo si el listado es de un solo tipo (factura o boleta), se ignora el filtro de tipo
     * @return array{filtros: array, comprobantes: array, paginacion: Paginacion, error: ?string}
     */
    public function listar(array $entrada, ?string $tipoFijo = null): array
    {
        $filtros = self::filtros($entrada, $tipoFijo);
        try {
            $paginacion = Paginacion::desdeUrl($this->repo->contar($filtros), $entrada);
            $comprobantes = $this->repo->buscar($filtros, $paginacion->porPagina, $paginacion->offset());
            $error = null;
        } catch (\Throwable $e) {
            $paginacion = new Paginacion(0);
            $comprobantes = [];
            $error = mensaje_error($e);
        }

        return ['filtros' => $filtros, 'comprobantes' => $comprobantes, 'paginacion' => $paginacion, 'error' => $error];
    }

    /** Máximo de comprobantes en una exportación, para no generar archivos enormes */
    public const LIMITE_EXPORTACION = 5000;

    /**
     * Los comprobantes que cumplen los filtros de la pantalla, en un archivo Excel (.xlsx).
     * Si hay más de LIMITE_EXPORTACION, se exportan los primeros y el archivo lo avisa al final.
     * @return array{nombre: string, contenido: string, filas: int, cortado: bool}
     */
    public function exportar(array $entrada): array
    {
        $filtros = self::filtros($entrada);
        $origen = ['simulado' => 'Simulada', 'proveedor' => 'Proveedor'];
        // Se pide uno más que el límite: si llega, es que hay más resultados de los que caben
        $comprobantes = $this->repo->buscar($filtros, self::LIMITE_EXPORTACION + 1);
        $cortado = count($comprobantes) > self::LIMITE_EXPORTACION;
        $nota = null;
        if ($cortado) {
            $comprobantes = array_slice($comprobantes, 0, self::LIMITE_EXPORTACION);
            $nota = sprintf(
                'Aviso: este archivo trae solo los primeros %s de %s comprobantes de la búsqueda. Ajusta los filtros (fechas, tipo o estado) para exportar el resto.',
                number_format(self::LIMITE_EXPORTACION),
                number_format($this->repo->contar($filtros))
            );
        }

        $filas = [];
        foreach ($comprobantes as $c) {
            $filas[] = [
                $c['serie'] . '-' . str_pad((string) $c['numero'], 8, '0', STR_PAD_LEFT),
                ConsultaController::TIPOS[$c['tipo']] ?? $c['tipo'],
                new \DateTimeImmutable($c['fecha_emision']),
                $c['cliente'],
                $c['tipo_documento'] . ' ' . $c['numero_documento'],
                (float) $c['subtotal'],
                (float) $c['impuesto'],
                (float) $c['total'],
                estado_comprobante($c['estado']),
                $origen[$c['origen_respuesta'] ?? ''] ?? 'Sin enviar',
                !$c['xml_archivo'] ? 'No generado' : ($c['xml_hash'] ? 'Firmado' : 'Sin firmar'),
                $c['cdr_envio_id'] ? 'Disponible' : '—',
            ];
        }

        $contenido = HojaExcel::generar(
            'Comprobantes',
            ['Comprobante', 'Tipo', 'Fecha de emisión', 'Cliente', 'Documento', 'Subtotal', 'IGV', 'Total', 'Estado', 'Respuesta', 'XML', 'CDR'],
            $filas,
            [17, 15, 17, 38, 18, 12, 11, 12, 22, 12, 13, 11],
            $nota
        );

        return ['nombre' => 'comprobantes_' . date('Ymd_Hi') . '.xlsx', 'contenido' => $contenido, 'filas' => count($filas), 'cortado' => $cortado];
    }

    /** Solo deja pasar valores válidos; lo demás se trata como "sin filtro" */
    public static function filtros(array $entrada, ?string $tipoFijo = null): array
    {
        // Solo texto: si la URL trae una lista (?tipo[]=x) se ignora
        $valor  = static fn (string $clave): string => is_string($entrada[$clave] ?? null) ? trim($entrada[$clave]) : '';
        $opcion = static fn (string $clave, array $opciones): string => array_key_exists($valor($clave), $opciones) ? $valor($clave) : '';

        $filtros = [
            'tipo'    => $tipoFijo ?? $opcion('tipo', ConsultaController::TIPOS),
            'numero'  => mb_substr($valor('numero'), 0, 20),
            'cliente' => mb_substr($valor('cliente'), 0, 160),
            'estado'  => $opcion('estado', config('estados.etiquetas', [])),
            'desde'   => self::fecha($valor('desde')),
            'hasta'   => self::fecha($valor('hasta')),
        ];

        // Si las fechas vienen al revés, se intercambian
        if ($filtros['desde'] !== '' && $filtros['hasta'] !== '' && $filtros['desde'] > $filtros['hasta']) {
            [$filtros['desde'], $filtros['hasta']] = [$filtros['hasta'], $filtros['desde']];
        }

        return $filtros;
    }

    /** Fecha real en formato AAAA-MM-DD (lo que envía <input type="date">), o '' */
    public static function fecha(string $valor): string
    {
        if ($valor === '') {
            return '';
        }
        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);

        return $fecha && $fecha->format('Y-m-d') === $valor ? $valor : '';
    }
}

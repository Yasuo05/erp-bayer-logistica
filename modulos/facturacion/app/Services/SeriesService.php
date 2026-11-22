<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SeriesRepository;
use PDOException;

final class SeriesService
{
    private const TIPOS = ['factura', 'boleta', 'nota_credito', 'nota_debito'];

    public function __construct(private SeriesRepository $repo = new SeriesRepository())
    {
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    /** Historial de cambios manuales de correlativos */
    public function cambios(): array
    {
        return $this->repo->cambios();
    }

    public function crear(array $datos): int
    {
        [$tipo, $serie, $siguiente, $descripcion] = $this->validar($datos);
        // Sede de la serie (G4): vacío = serie general para todas las sedes
        $sucursal = ctype_digit((string) ($datos['sucursal_id'] ?? '')) ? (int) $datos['sucursal_id'] : null;
        try {
            return $this->repo->crear($tipo, $serie, $siguiente, $descripcion, $this->usuario(), $sucursal);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new \InvalidArgumentException('Ya existe esa serie para el tipo seleccionado.');
            }
            throw $e;
        }
    }

    public function actualizar(int $id, array $datos): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Serie inválida.');
        }
        $siguiente = filter_var($datos['siguiente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($siguiente === false) {
            throw new \InvalidArgumentException('El siguiente correlativo debe ser un entero mayor que cero.');
        }
        $descripcion = $this->descripcion($datos['descripcion'] ?? null);
        // El motivo solo es obligatorio si cambia el correlativo (lo decide el repositorio con el valor actual)
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if (mb_strlen($motivo) > 500) {
            throw new \InvalidArgumentException('El motivo admite como máximo 500 caracteres.');
        }
        $this->repo->actualizar($id, (int) $siguiente, $descripcion, $motivo, $this->usuario());
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Serie inválida.');
        }
        $this->repo->cambiarActivo($id, $activo);
    }

    /** @return array{serie:string,numero:int} */
    public function reservarCorrelativo(string $tipo, ?string $serie = null, ?string $prefijo = null, ?int $sucursalId = null): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de comprobante inválido.');
        }
        return $this->repo->reservar($tipo, $serie, $prefijo, $sucursalId);
    }

    /** Serie y número que tocarían al emitir, sin reservarlos. @return array{serie:string,siguiente:int}|null */
    public function previsualizar(string $tipo, ?string $prefijo = null, ?int $sucursalId = null): ?array
    {
        return in_array($tipo, self::TIPOS, true) ? $this->repo->previsualizar($tipo, $prefijo, $sucursalId) : null;
    }

    private function validar(array $datos): array
    {
        $tipo = (string) ($datos['tipo'] ?? '');
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Selecciona un tipo de comprobante válido.');
        }

        $serie = strtoupper(trim((string) ($datos['serie'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{1,4}$/', $serie)) {
            throw new \InvalidArgumentException('La serie debe tener entre 1 y 4 letras o números.');
        }
        if (SeriesRepository::esSeriePrueba($tipo, $serie)) {
            throw new \InvalidArgumentException("La serie {$serie} está reservada para las pruebas automáticas. Usa otro nombre.");
        }

        $siguiente = filter_var($datos['siguiente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($siguiente === false) {
            throw new \InvalidArgumentException('El correlativo inicial debe ser un entero mayor que cero.');
        }

        return [$tipo, $serie, (int) $siguiente, $this->descripcion($datos['descripcion'] ?? null)];
    }

    /** Quién hace el cambio: por ahora el usuario simulado de config/app.php; luego, la sesión del ERP (Grupo 4) */
    private function usuario(): string
    {
        return (string) config('app.sesion_simulada.usuario', 'Sistema');
    }

    private function descripcion(mixed $valor): ?string
    {
        $descripcion = trim((string) $valor);
        if (mb_strlen($descripcion) > 120) {
            throw new \InvalidArgumentException('La descripción admite como máximo 120 caracteres.');
        }
        return $descripcion === '' ? null : $descripcion;
    }
}


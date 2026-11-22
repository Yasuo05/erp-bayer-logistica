# Capa de negocio (Services)

Aquí van las **reglas de negocio** de facturación (infografía 11, capa 3): validaciones, cálculos, asignación de serie y correlativo, armado del comprobante.

- Un servicio **no** imprime HTML ni lee `$_POST` directamente: eso lo hace el controlador.
- Un servicio **no** escribe SQL: para eso usa un repositorio (`app/Repositories`).

Ejemplo futuro: `ComprobanteService::emitir($ventaAprobada)`.

Vacía por ahora: el módulo es un esqueleto sin funcionalidad.

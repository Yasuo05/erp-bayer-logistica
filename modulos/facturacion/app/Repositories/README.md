# Capa de datos (Repositories)

Aquí va **todo el acceso a la base de datos** (infografía 11, capa 4): consultas, inserciones y transacciones con PDO, usando `App\Core\Database::conexion()`.

- Siempre consultas preparadas (`prepare` + `execute`), nunca concatenar datos del usuario en el SQL.
- Solo tablas propias de facturación (comprobantes, series, envíos…). Los datos de otros grupos se **consultan**, no se modifican (infografía 20: "cada dato tiene un equipo responsable").

Ejemplo futuro: `ComprobanteRepository::guardar(array $comprobante)`.

Vacía por ahora: el módulo es un esqueleto sin funcionalidad.

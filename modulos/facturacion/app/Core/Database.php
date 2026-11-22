<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Conexión única a MySQL/MariaDB (datos en .env).
 * Se conecta recién cuando alguien la usa: mientras el módulo no tenga funcionalidad, casi no se usa.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_DATABASE', 'erp_pucchun')
            );

            self::$pdo = new PDO($dsn, Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Fechas en hora de Perú aunque el servidor MySQL esté en UTC
            self::$pdo->exec("SET time_zone = '-05:00'");
        }

        return self::$pdo;
    }

    /** Para la página de inicio: indica si la base de datos está lista */
    public static function estado(): array
    {
        $nombre = Env::get('DB_DATABASE', 'erp_pucchun');

        try {
            self::conexion();
            return ['ok' => true, 'mensaje' => "MySQL conectado a la base «{$nombre}»"];
        } catch (PDOException $e) {
            // 1049 = la base de datos no existe (MySQL sí responde)
            if (str_contains($e->getMessage(), '[1049]')) {
                return ['ok' => false, 'mensaje' => "MySQL responde, pero falta crear la base «{$nombre}» (importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql))"];
            }
            return ['ok' => false, 'mensaje' => 'No se pudo conectar a MySQL: ¿está encendido en XAMPP? Revisa los datos del .env'];
        }
    }
}

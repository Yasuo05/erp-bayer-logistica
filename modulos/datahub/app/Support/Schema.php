<?php
namespace App\Support;

/** Utilidades de esquema idempotentes (compatibles con MySQL 5.7+/8 y MariaDB 10.x). */
class Schema
{
    public function __construct(private \PDO $pdo) {}

    public function hasTable(string $table): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    public function hasColumn(string $table, string $column): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }

    public function hasIndex(string $table, string $index): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $st->execute([$table, $index]);
        return (bool)$st->fetchColumn();
    }

    public function addColumn(string $table, string $column, string $definition): void
    {
        if (!$this->hasColumn($table, $column)) {
            $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    public function addIndex(string $table, string $index, string $columns, bool $unique = false): void
    {
        if (!$this->hasIndex($table, $index)) {
            $this->pdo->exec('ALTER TABLE `'.$table.'` ADD '.($unique ? 'UNIQUE ' : '').'INDEX `'.$index.'` ('.$columns.')');
        }
    }

    public function addForeignKey(string $table, string $name, string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'SET NULL'): void
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
        $st->execute([$table, $name]);
        if (!$st->fetchColumn()) {
            $this->pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$refTable`(`$refColumn`) ON DELETE $onDelete");
        }
    }
}

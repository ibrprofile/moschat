<?php

declare(strict_types=1);

namespace MosChat\Core;

use PDO;
use PDOException;
use PDOStatement;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) env_val('DB_HOST', '127.0.0.1');
        $port = (string) env_val('DB_PORT', '3306');
        $db = (string) env_val('DB_DATABASE', 'moschat');
        $user = (string) env_val('DB_USERNAME', 'root');
        $pass = (string) env_val('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new PDOException('Database connection failed', (int) $e->getCode(), $e);
        }

        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $fields = implode(', ', array_map(static fn ($c) => "`{$c}`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        self::query(
            "INSERT INTO `{$table}` ({$fields}) VALUES ({$placeholders})",
            array_values($data)
        );
        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): void
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $value) {
            $sets[] = "`{$col}` = ?";
            $params[] = $value;
        }
        $params = array_merge($params, $whereParams);
        self::query(
            "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where}",
            $params
        );
    }

    public static function begin(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function delete(string $table, string $where, array $whereParams = []): void
    {
        self::query(
            "DELETE FROM `{$table}` WHERE {$where}",
            $whereParams
        );
    }

    public static function rollBack(): void
    {
        if (self::connection()->inTransaction()) {
            self::connection()->rollBack();
        }
    }
}

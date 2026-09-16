<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MosChat\Core\Database;

function load_env(string $path): void
{
    if (!is_file($path)) {
        fwrite(STDERR, ".env not found. Copy .env.example to .env\n");
        exit(1);
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        $_SERVER[trim($name)] = trim($value);
    }
}

load_env(dirname(__DIR__) . '/.env');

$migrationsDir = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationsDir . '/*.php') ?: [];
sort($files);

$pdo = Database::connection();

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  applied_at DATETIME NOT NULL,
  UNIQUE KEY uq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

foreach ($files as $file) {
    $name = basename($file);
    $exists = $pdo->prepare('SELECT id FROM schema_migrations WHERE migration = ?');
    $exists->execute([$name]);
    if ($exists->fetch()) {
        echo "Skip {$name}\n";
        continue;
    }

    echo "Apply {$name}...\n";
    $sql = require $file;
    if (!is_string($sql)) {
        throw new RuntimeException("Migration {$name} must return SQL string");
    }

    $pdo->exec($sql);
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (?, UTC_TIMESTAMP())');
    $stmt->execute([$name]);
    echo "OK {$name}\n";
}

$seed = dirname(__DIR__) . '/database/seeds/001_system.php';
if (is_file($seed)) {
    echo "Seeding 001_system...\n";
    require $seed;
    echo "Seed OK\n";
}

$seed2 = dirname(__DIR__) . '/database/seeds/002_test_data.php';
if (is_file($seed2)) {
    echo "Seeding 002_test_data...\n";
    require $seed2;
    echo "Seed OK\n";
}

echo "Done.\n";

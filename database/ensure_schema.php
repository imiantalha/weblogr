<?php

declare(strict_types=1);

/**
 * Railway-safe database bootstrap.
 *
 * Creates only tables that are missing from the configured database using
 * the canonical schema in database/weblogr.sql. Existing tables and rows
 * are never dropped or replaced.
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$db = getenv('DB_NAME') ?: 'weblogr';

if ($user === '' || $db === '') {
    fwrite(STDERR, "Database configuration is incomplete.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($host, $user, $password, $db, $port);
    $mysqli->set_charset('utf8mb4');

    $schemaPath = __DIR__ . '/weblogr.sql';
    $schema = file_get_contents($schemaPath);

    if ($schema === false) {
        throw new RuntimeException('Unable to read canonical database schema.');
    }

    preg_match_all('/CREATE TABLE `[^`]+`\\s*\\(.*?\\) ENGINE=.*?;/s', $schema, $matches);

    if (empty($matches[0])) {
        throw new RuntimeException('No CREATE TABLE statements found in canonical schema.');
    }

    $created = 0;
    $existing = 0;

    foreach ($matches[0] as $statement) {
        if (!preg_match('/CREATE TABLE `([^`]+)`/i', $statement, $tableMatch)) {
            continue;
        }

        $table = $tableMatch[1];
        $result = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");

        if ($result->num_rows > 0) {
            $existing++;
            echo "[skip] {$table} already exists\n";
            continue;
        }

        $mysqli->query($statement);
        $created++;
        echo "[create] {$table}\n";
    }

    echo "Database bootstrap complete: {$created} created, {$existing} already present.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database bootstrap failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

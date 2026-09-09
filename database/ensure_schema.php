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

    // Only extract CREATE TABLE statements. This intentionally ignores the
    // destructive DROP TABLE and other fresh-install statements in the file.
    preg_match_all(
        '/CREATE\s+TABLE\s+`([^`]+)`\s*\(.*?\)\s*ENGINE\s*=.*?;/is',
        $schema,
        $matches,
        PREG_SET_ORDER
    );

    if (empty($matches)) {
        throw new RuntimeException('No CREATE TABLE statements found in canonical schema.');
    }

    $expectedTables = [];
    foreach ($matches as $match) {
        $expectedTables[$match[1]] = $match[0];
    }

    $created = [];
    $existing = [];

    echo 'Weblogr schema check: database=' . $db . ', expected=' . count($expectedTables) . " tables\n";

    foreach ($expectedTables as $table => $statement) {
        $escapedTable = $mysqli->real_escape_string($table);
        $result = $mysqli->query(
            "SELECT 1 FROM information_schema.tables " .
            "WHERE table_schema = DATABASE() AND table_name = '{$escapedTable}' LIMIT 1"
        );

        if ($result->num_rows > 0) {
            $existing[] = $table;
            echo "[exists] {$table}\n";
            continue;
        }

        // CREATE TABLE IF NOT EXISTS makes this safe if another deployment
        // creates the table between the check and the CREATE statement.
        $safeStatement = preg_replace(
            '/^CREATE\s+TABLE\s+/i',
            'CREATE TABLE IF NOT EXISTS ',
            $statement,
            1
        );

        if ($safeStatement === null) {
            throw new RuntimeException("Unable to prepare CREATE TABLE statement for {$table}.");
        }

        $mysqli->query($safeStatement);
        $created[] = $table;
        echo "[created] {$table}\n";
    }

    echo 'Weblogr schema check complete: ' . count($created) .
        ' created, ' . count($existing) . ' already present, ' .
        count($expectedTables) . " expected.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database bootstrap failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

<?php
/**
 * Database connection (PDO).
 * Real prepared statements are used everywhere (EMULATE_PREPARES = false), which is the main
 * defence against SQL injection: user input is never concatenated into SQL text.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c   = (array) config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['host'] ?? 'localhost',
        $c['name'] ?? '',
        $c['charset'] ?? 'utf8mb4'
    );

    try {
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        $GLOBALS['db_failed'] = true;
        abort(500, config('app.debug', false)
            ? $e->getMessage()
            : 'The database is not reachable right now. Please try again in a moment.');
    }

    return $pdo;
}

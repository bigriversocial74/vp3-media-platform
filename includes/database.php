<?php
declare(strict_types=1);

function vp3_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $options = vp3_config('database.options', []);
    if (!is_array($options)) {
        $options = [];
    }
    $options += [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO(
        (string)vp3_config('database.dsn'),
        (string)vp3_config('database.username'),
        (string)vp3_config('database.password'),
        $options
    );
    return $pdo;
}

function vp3_db_available(): bool
{
    try {
        vp3_db()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        vp3_log('warning', 'Database unavailable', ['message' => $e->getMessage()]);
        return false;
    }
}

function vp3_transaction(callable $callback): mixed
{
    $pdo = vp3_db();
    $pdo->beginTransaction();
    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

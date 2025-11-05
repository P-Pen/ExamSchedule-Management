<?php
declare(strict_types=1);

function examDatabasePath(): string
{
    $envPath = getenv('EXAM_DB_PATH');
    if ($envPath !== false && trim($envPath) !== '') {
        return $envPath;
    }

    $directory = getenv('EXAM_DB_DIR');
    if ($directory === false || trim($directory) === '') {
        $directory = __DIR__ . '/../data';
    }

    $filename = getenv('EXAM_DB_NAME');
    if ($filename === false || trim($filename) === '') {
        $filename = 'exam.db';
    }

    return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
}

function examDatabase(): SQLite3
{
    static $connection = null;

    if ($connection instanceof SQLite3) {
        return $connection;
    }

    $path = examDatabasePath();
    $directory = dirname($path);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建数据库目录: ' . $directory);
        }
    }

    $connection = new SQLite3($path);

    if (method_exists($connection, 'enableExceptions')) {
        $connection->enableExceptions(true);
    }

    if (method_exists($connection, 'busyTimeout')) {
        $connection->busyTimeout(3000);
    }

    $connection->exec('PRAGMA foreign_keys = ON');

    return $connection;
}

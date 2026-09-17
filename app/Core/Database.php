<?php

declare(strict_types=1);

namespace MeatinOS\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }
        $cfg = config('database');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']);
        self::$connection = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $timezone = new \DateTimeZone((string) config('app.timezone', 'Asia/Kolkata'));
        $offsetSeconds = $timezone->getOffset(new \DateTimeImmutable('now', $timezone));
        $sign = $offsetSeconds < 0 ? '-' : '+';
        $absolute = abs($offsetSeconds);
        $offset = sprintf('%s%02d:%02d', $sign, intdiv($absolute, 3600), intdiv($absolute % 3600, 60));
        self::$connection->exec('SET time_zone=' . self::$connection->quote($offset));
        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}

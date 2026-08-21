<?php

namespace App;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/config.php';
            $db = $config['db'];

            self::$connection = new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
                $db['user'],
                $db['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            // Without strict mode, MySQL silently truncates over-length values (e.g. a title
            // longer than its VARCHAR column) instead of raising an error — the caller sees
            // "ok": true while their data was quietly cut short. Strict mode turns that into
            // a catchable exception (surfaced as a generic error via _bootstrap.php) instead.
            self::$connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
        }

        return self::$connection;
    }
}

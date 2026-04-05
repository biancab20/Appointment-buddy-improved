<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection !== null)
            return self::$connection;

        $host = 'mysql';
        $db = 'developmentdb';
        $user = 'developer';
        $pass = 'secret123';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $maxRetries = 10;
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                self::$connection = new PDO($dsn, $user, $pass, $options);
                return self::$connection;
            } catch (PDOException $e) {
                if ($i === $maxRetries - 1)
                    throw $e;
                sleep(2);
            }
        }

        throw new PDOException("Could not connect to DB");
    }
}

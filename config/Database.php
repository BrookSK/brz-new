<?php
namespace Config;

class Database {
    private static $hosts = ['127.0.0.1', 'localhost'];
    private static $db_name = 'novobr';
    private static $username = 'novobr';
    private static $password = '33537095Ab12$';
    private static $conn;

    public static function getConnection() {
        if (self::$conn instanceof \PDO) {
            return self::$conn;
        }

        $lastException = null;

        foreach (self::$hosts as $host) {
            try {
                self::$conn = new \PDO(
                    'mysql:host=' . $host . ';dbname=' . self::$db_name . ';charset=utf8mb4',
                    self::$username,
                    self::$password,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
                    ]
                );
                // Remover NO_AUTO_VALUE_ON_ZERO do sql_mode para evitar id=0 em auto_increment
                try {
                    self::$conn->exec("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO', '')");
                } catch (\PDOException $e) {
                    // Silencioso - prosseguir mesmo se falhar
                }
                return self::$conn;
            } catch (\PDOException $exception) {
                $lastException = $exception;
            }
        }

        echo "Connection error: " . $lastException->getMessage();
        return self::$conn;
    }
}

<?php
namespace Config;

class Database {
    private static $host = 'localhost';
    private static $db_name = 'novobr';
    private static $username = 'novobr';
    private static $password = '33537095Ab12$';
    private static $conn;

    public static function getConnection() {
        try {
            if (self::$conn instanceof \PDO) {
                return self::$conn;
            }

            self::$conn = new \PDO(
                'mysql:host=' . self::$host . ';dbname=' . self::$db_name . ';charset=utf8mb4',
                self::$username,
                self::$password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]
            );
        } catch(\PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return self::$conn;
    }
}

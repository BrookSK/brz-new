<?php
namespace Config;

class Database {
    private static $host = 'localhost';
    private static $db_name = 'novobr';
    private static $username = 'novobr';
    private static $password = '33537095Ab12$';
    private static $conn;

    public static function getConnection() {
        self::$conn = null;

        try {
            self::$conn = new \PDO('mysql:host=' . self::$host . ';dbname=' . self::$db_name, self::$username, self::$password);
            self::$conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return self::$conn;
    }
}

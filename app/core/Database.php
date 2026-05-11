<?php

class Database
{
    public static function getConnection(): mysqli
    {
        $cfg  = require APP_ROOT . '/config/database.php';
        $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['dbname']);

        if ($conn->connect_error) {
            exit('Database connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8');
        return $conn;
    }
}

// Global alias so existing code calling getConnection() keeps working
function getConnection(): mysqli
{
    return Database::getConnection();
}

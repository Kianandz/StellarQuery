<?php
namespace App\Models;

use PDO;
use PDOException;
use Exception;

class DatabaseModel {
    public static function writeLog($filename, $msg) {
        $date = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        @file_put_contents(__DIR__ . '/../../' . $filename, "[$date] [IP: $ip] $msg" . PHP_EOL, FILE_APPEND);
    }

    public static function safeName($name) { 
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $name); 
    }

    public static function connect($h, $p, $u, $pw) {
        return new PDO("mysql:host=$h;port=$p;charset=utf8mb4", $u, $pw, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
}
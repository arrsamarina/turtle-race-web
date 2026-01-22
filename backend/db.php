<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = "pg";
$port     = "5432";
$dbname   = "studs";
$user     = "s368753";
$password = "8nyIPy9Zu7lSzEyO";

header('Content-Type: application/json; charset=utf-8');

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        global $host, $port, $dbname, $user, $password;

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";

        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}


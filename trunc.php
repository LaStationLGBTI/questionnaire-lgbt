<?php
require_once 'conf.php'; 

$host = $DB_HOSTNAME;
$user = $DB_USERNAME;
$password = $DB_PASSWORD;
$database = $DB_NAME;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "TRUNCATE TABLE stationr2";
    $pdo->exec($sql);

    echo "Таблица stationr2 успешно очищена.";
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>

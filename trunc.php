<?php
require_once 'conf.php'; 
header('Content-Type: application/json');


try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("TRUNCATE TABLE stationr2");
    echo json_encode(['status' => 'success', 'message' => 'Table stationr2 has been truncated']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error truncating table: ' . $e->getMessage()]);
}
?>

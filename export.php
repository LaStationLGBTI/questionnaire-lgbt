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

    $backupFile = $database . '_backup_' . date("Ymd_His") . '.sql';
    $handle = fopen($backupFile, 'w+');
    if ($handle === false) {
        throw new Exception("Не удалось создать файл: $backupFile");
    }

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        fwrite($handle, "\n\n" . $row['Create Table'] . ";\n\n");
        $stmt = $pdo->query("SELECT * FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $rowData = array_map(function ($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, $row);
            fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(', ', $rowData) . ");\n");
        }
    }

    fclose($handle);
    echo "export $backupFile";

} catch (Exception $e) {
    echo "err: " . $e->getMessage();
    file_put_contents('error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
}

if (file_exists($backupFile)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($backupFile));
    readfile($backupFile);
    exit;
} else {
    echo "Ошибка: Файл $backupFile не найден.";
}
?>

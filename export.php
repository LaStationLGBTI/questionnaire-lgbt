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
    // Подключение через PDO
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Имя файла для дампа
    $backupFile = $database . '_backup_' . date("Ymd_His") . '.sql';
    $handle = fopen($backupFile, 'w+');
    if ($handle === false) {
        throw new Exception("Не удалось создать файл: $backupFile");
    }

    // Получение списка таблиц
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Экспорт таблиц
    foreach ($tables as $table) {
        // Получение структуры таблицы
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        fwrite($handle, "\n\n" . $row['Create Table'] . ";\n\n");

        // Получение данных таблицы
        $stmt = $pdo->query("SELECT * FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $rowData = array_map(function ($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, $row);
            fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(', ', $rowData) . ");\n");
        }
    }

    fclose($handle);
    echo "База данных успешно экспортирована в $backupFile";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    file_put_contents('error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
}
?>

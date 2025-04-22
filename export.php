<?php
require_once 'conf.php'; 
$host = $DB_HOSTNAME;
$user = $DB_USERNAME;
$password = $DB_PASSWORD;
$database = $DB_NAME;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
php
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if (!class_exists('mysqli')) {
        throw new Exception("Расширение mysqli не найдено. Установите php-mysql.");
    }

    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Ошибка подключения: " . $conn->connect_error);
    }

    $backupFile = $database . '_backup_' . date("Ymd_His") . '.sql';
    $handle = fopen($backupFile, 'w+');
    if ($handle === false) {
        throw new Exception("Не удалось создать файл: $backupFile");
    }

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if ($result === false) {
        throw new Exception("Ошибка при получении списка таблиц: " . $conn->error);
    }
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        if ($result === false) {
            throw new Exception("Ошибка при получении структуры таблицы $table: " . $conn->error);
        }
        $row = $result->fetch_row();
        fwrite($handle, "\n\n" . $row[1] . ";\n\n");

        $result = $conn->query("SELECT * FROM `$table`");
        if ($result === false) {
            throw new Exception("Ошибка при получении данных таблицы $table: " . $conn->error);
        }
        while ($row = $result->fetch_row()) {
            $rowData = array_map(function ($value) use ($conn) {
                return $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
            }, $row);
            fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(', ', $rowData) . ");\n");
        }
    }

    fclose($handle);
    $conn->close();

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
        throw new Exception("file $backupFile not found.");
    }

} catch (Exception $e) {
    echo "err: " . $e->getMessage();
    file_put_contents('error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
}
?>

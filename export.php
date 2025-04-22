<?php
require_once 'conf.php'; 
$host = $DB_HOSTNAME;
$user = $DB_USERNAME;
$password = $DB_PASSWORD;
$database = $DB_NAME;

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("err: " . $conn->connect_error);
}
$backupFile = $database . '_backup_' . date("Ymd_His") . '.sql';
$handle = fopen($backupFile, 'w+');
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}
foreach ($tables as $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    fwrite($handle, "\n\n" . $row[1] . ";\n\n");
    $result = $conn->query("SELECT * FROM `$table`");
    $numFields = $result->field_count;
    while ($row = $result->fetch_row()) {
        $rowData = array_map(function ($value) use ($conn) {
            return $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
        }, $row);
        fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(', ', $rowData) . ");\n");
    }
}

fclose($handle);
$conn->close();

echo "exp $backupFile";
?>

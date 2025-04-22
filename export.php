<?php
require_once 'conf.php'; 
try {
				$conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
				$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
				echo "Erreur connection: " . $e->getMessage();
			}
$host = $DB_HOSTNAME;
$user = $DB_USERNAME;
$password = $DB_PASSWORD;
$database = $DB_NAME;
$backupFile = $database . '_backup_' . date("Ymd_His") . '.sql';
$command = "mysqldump --host=$host --user=$user --password=$password $database > $backupFile";
exec($command);
if (file_exists($backupFile)) {
    echo "export $backupFile";
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
    echo "err";
}
?>

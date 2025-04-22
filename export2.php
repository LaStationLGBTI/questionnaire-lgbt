<?php
$backupFile = 'questionnaire-lgbt_backup_20250422_132144.sql';

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
}?>

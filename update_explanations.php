<?php
// Подключаем конфигурацию базы данных
require_once 'conf.php';

// Настройки для корректного отображения
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Устанавливаем соединение с базой данных
try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Соединение с базой данных установлено успешно.<br>";
} catch (PDOException $e) {
    die("❌ Ошибка подключения к базе данных: " . $e->getMessage());
}

echo "<h1>Обновление expliq (метод: 1 строка = 1 запись)</h1>";

$textFile = 'Module 2 FINAL(1).csv'; // Название вашего файла
$startId = 23; // Начальный ID для обновления

if (!file_exists($textFile)) {
    die("❌ Ошибка: Файл не найден: " . htmlspecialchars($textFile));
}

// Читаем все строки из файла в массив.
// Пустые строки будут пропущены.
$lines = file($textFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines === false) {
    die("❌ Ошибка: Не удалось прочитать файл.");
}

echo "🚀 Начинаем обновление... Найдено непустых строк в файле: " . count($lines) . "<br><hr>";

// Готовим запрос к БД
$updateQuery = $pdo->prepare("UPDATE GSDatabase SET expliq = ? WHERE id = ?");

// Обрабатываем каждую строку из файла
foreach ($lines as $index => $line) {
    $explanation = trim($line);
    
    // Определяем ID для текущей строки. Первая строка (индекс 0) -> ID 23, вторая (индекс 1) -> ID 24 и т.д.
    $targetId = $startId + $index;

    // Обновляем запись в БД, если строка не пустая
    if (!empty($explanation)) {
        $updateQuery->execute([$explanation, $targetId]);
        
        // --- ИЗМЕНЕНИЕ ЗДЕСЬ: mb_substr заменен на substr ---
        echo "✔️ <b>ID: $targetId</b> &lt;-- Записаны данные: \"" . htmlspecialchars(substr($explanation, 0, 70)) . "...\"<br>";
    } else {
        echo "⚠️ <b>ID: $targetId</b> - Пропущено, так как строка в файле пустая.<br>";
    }
}

echo "<hr>🎉 Обновление завершено.<br>";

?>

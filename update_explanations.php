<?php
// Подключаем конфигурацию базы данных
require_once 'conf.php';

// Устанавливаем соединение с базой данных
try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Соединение с базой данных установлено успешно.<br>";
} catch (PDOException $e) {
    // В случае ошибки выводим сообщение и прекращаем выполнение
    die("❌ Ошибка подключения к базе данных: " . $e->getMessage());
}

// --- Шаг 1: Добавление колонки 'expliq', если она не существует ---
try {
    // Проверяем, существует ли уже такая колонка
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `GSDatabase` LIKE 'expliq'");
    
    if ($checkColumn->rowCount() == 0) {
        // Если колонки нет, добавляем её
        $pdo->exec("ALTER TABLE GSDatabase ADD COLUMN expliq TEXT NULL DEFAULT NULL AFTER answer");
        echo "✅ Колонка 'expliq' успешно добавлена в таблицу GSDatabase.<br>";
    } else {
        echo "ℹ️ Колонка 'expliq' уже существует в таблице.<br>";
    }
} catch (PDOException $e) {
    die("❌ Ошибка при добавлении колонки: " . $e->getMessage());
}

// --- Шаг 2: Чтение CSV-файла и обновление базы данных ---
$csvFile = 'Module 2 FINAL(1).csv';
$startId = 23;
$endId = 51;
$currentId = $startId;

// Проверяем, существует ли CSV-файл
if (!file_exists($csvFile)) {
    die("❌ Ошибка: CSV-файл не найден по пути: " . $csvFile);
}

// Открываем CSV-файл для чтения
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    // Подготавливаем SQL-запрос для обновления данных
    $updateQuery = $pdo->prepare("UPDATE GSDatabase SET expliq = ? WHERE id = ?");

    // Пропускаем первую строку с заголовками в CSV
    fgetcsv($handle, 2000, ",");

    echo "🚀 Начинаем обновление записей...<br>";

    // Читаем CSV-файл построчно
    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE && $currentId <= $endId) {
        // Убеждаемся, что в строке есть 11-й элемент (индекс 10)
        if (isset($data[10])) {
            $explanation = trim($data[10]);

            // Выполняем запрос на обновление
            $updateQuery->execute([$explanation, $currentId]);
            echo "✔️ Обновлена запись с ID: $currentId.<br>";

            // Переходим к следующему ID
            $currentId++;
        }
    }
    fclose($handle);
    echo "🎉 Обновление успешно завершено.<br>";
} else {
    echo "❌ Не удалось открыть CSV-файл для чтения.";
}
?>

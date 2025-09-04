<?php
// Подключаем конфигурацию базы данных
require_once 'conf.php';

// Устанавливаем соединение с базой данных
try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Соединение с базой данных установлено успешно.<br>";
} catch (PDOException $e) {
    die("❌ Ошибка подключения к базе данных: " . $e->getMessage());
}

// --- Шаг 1: Удаление старой колонки 'expliq', если она существует ---
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `GSDatabase` LIKE 'expliq'");
    if ($checkColumn->rowCount() > 0) {
        $pdo->exec("ALTER TABLE GSDatabase DROP COLUMN expliq");
        echo "✅ Старая колонка 'expliq' успешно удалена.<br>";
    } else {
        echo "ℹ️ Колонка 'expliq' не найдена, удаление не требуется.<br>";
    }
} catch (PDOException $e) {
    die("❌ Ошибка при удалении колонки: " . $e->getMessage());
}

// --- Шаг 2: Создание новой колонки в правильном месте ---
try {
    // Добавляем колонку expliq ПОСЛЕ колонки answer
    $pdo->exec("ALTER TABLE GSDatabase ADD COLUMN expliq TEXT NULL DEFAULT NULL AFTER qtype");
    echo "✅ Новая колонка 'expliq' успешно создана в правильной позиции (после 'answer').<br>";
} catch (PDOException $e) {
    die("❌ Ошибка при создании новой колонки: " . $e->getMessage());
}

// --- Шаг 3: Чтение CSV-файла и обновление базы данных (с правильным разделителем) ---
$csvFile = 'Module 2 FINAL(1).csv';
$startId = 23;
$endId = 51;
$currentId = $startId;

if (!file_exists($csvFile)) {
    die("❌ Ошибка: CSV-файл не найден по пути: " . $csvFile);
}

// Открываем CSV-файл для чтения
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $updateQuery = $pdo->prepare("UPDATE GSDatabase SET expliq = ? WHERE id = ?");

    // Пропускаем первую строку с заголовками
    fgetcsv($handle);

    echo "🚀 Начинаем обновление данных в новой колонке...<br>";

    // Читаем CSV-файл построчно, используя точку с запятой (;) как разделитель
    while (($data = fgetcsv($handle, 2000, ";")) !== FALSE && $currentId <= $endId) {
        // Убеждаемся, что в строке есть 11-й элемент (индекс 10) и он не пустой
        if (isset($data[10]) && !empty(trim($data[10]))) {
            $explanation = trim($data[10]);
            
            // Выполняем запрос на обновление
            $updateQuery->execute([$explanation, $currentId]);
            echo "✔️ ID: $currentId - Данные успешно записаны.<br>";

            $currentId++;
        } else {
            // Если строка в CSV пустая, просто пропускаем ее, но ID увеличиваем
             echo "⚠️ ID: $currentId - Пропущена пустая строка в CSV.<br>";
             $currentId++;
        }
    }
    fclose($handle);
    echo "🎉 Обновление данных успешно завершено.<br>";
} else {
    echo "❌ Не удалось открыть CSV-файл для чтения.";
}

?>

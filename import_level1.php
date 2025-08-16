<?php
// Подключаем конфигурационный файл
require_once 'conf.php';

// --- НАСТРОЙКИ ---
$csvFilePath = 'MODULE 01 v02.csv';
$targetTable = 'GSDatabase';

// --- ПАРАМЕТРЫ ДЛЯ ЗАПИСИ В БД ---
$levelToSet = 1;
$qtypeToSet = 'echelle';
$answerToSet = 0;

echo "<h1>Импорт вопросов в таблицу '$targetTable'</h1>";

// Проверка существования файла
if (!file_exists($csvFilePath)) {
    die("<p style='color:red;'>Ошибка: Файл '$csvFilePath' не найден. Убедитесь, что он находится в той же папке, что и этот скрипт.</p>");
}

try {
    // Подключаемся к базе данных
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Открываем CSV файл для чтения
    $fileHandle = fopen($csvFilePath, 'r');
    if ($fileHandle === false) {
        die("<p style='color:red;'>Не удалось открыть файл CSV.</p>");
    }

    // Пропускаем первую строку (заголовки)
    fgetcsv($fileHandle);

    // Подготавливаем SQL-запрос для вставки данных
    $sql = "INSERT INTO `$targetTable` (level, question, rep1, rep2, rep3, rep4, rep5, answer, qtype) 
            VALUES (:level, :question, :rep1, :rep2, :rep3, :rep4, :rep5, :answer, :qtype)";
    $stmt = $pdo->prepare($sql);

    $rowCount = 0;
    // Читаем файл построчно
    while (($data = fgetcsv($fileHandle, 1000, ';')) !== false) {
        // Проверяем, что строка не пустая и содержит вопрос
        if (empty($data[1])) {
            continue;
        }

        // Привязываем параметры к запросу
        $stmt->bindValue(':level', $levelToSet, PDO::PARAM_INT);
        $stmt->bindValue(':question', $data[1]); // колонка 'question'
        $stmt->bindValue(':rep1', $data[2] ?? 'null'); // колонка 'réponse 0'
        $stmt->bindValue(':rep2', $data[3] ?? 'null'); // колонка 'réponse 1'
        $stmt->bindValue(':rep3', $data[4] ?? 'null'); // колонка 'réponse 2'
        $stmt->bindValue(':rep4', $data[5] ?? 'null'); // колонка 'réponse 3'
        $stmt->bindValue(':rep5', $data[6] ?? 'null'); // колонка 'réponse 4'
        $stmt->bindValue(':answer', $answerToSet, PDO::PARAM_INT);
        $stmt->bindValue(':qtype', $qtypeToSet);

        // Выполняем запрос
        $stmt->execute();
        $rowCount++;
    }

    fclose($fileHandle);

    echo "<p style='color:green; font-weight:bold;'>Импорт успешно завершен! Добавлено $rowCount записей.</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Произошла ошибка: " . $e->getMessage() . "</p>";
}
?>

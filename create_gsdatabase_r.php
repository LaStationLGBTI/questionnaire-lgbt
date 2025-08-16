<?php
// Подключаем конфигурационный файл
require_once 'conf.php';

// --- НАСТРОЙКИ ---
$sourceTable = 'stationr2';     // Исходная таблица
$newTableName = 'GSDatabaseR'; // Имя новой таблицы

echo "<h1>Попытка создания таблицы '$newTableName' по структуре '$sourceTable'...</h1>";

try {
    // Подключаемся к базе данных
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Получаем SQL-код для создания исходной таблицы
    $stmt = $pdo->query("SHOW CREATE TABLE `$sourceTable`");
    $tableStructure = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tableStructure) {
        throw new Exception("Не удалось получить структуру таблицы '$sourceTable'. Убедитесь, что она существует.");
    }

    $createStatement = $tableStructure['Create Table'];

    // 2. Заменяем имя таблицы в SQL-коде на новое
    $newCreateStatement = preg_replace(
        "/CREATE TABLE `$sourceTable`/",
        "CREATE TABLE `$newTableName`",
        $createStatement
    );

    // 3. Выполняем измененный SQL-код для создания новой таблицы
    $pdo->exec($newCreateStatement);

    echo "<p style='color:green; font-weight:bold;'>Успешно! Таблица '$newTableName' создана со структурой таблицы '$sourceTable'.</p>";

} catch (PDOException $e) {
    // Обрабатываем ошибки базы данных
    if ($e->errorInfo[1] == 1050) { // Код ошибки 1050 означает "Таблица уже существует"
        echo "<p style='color:orange; font-weight:bold;'>Внимание: Таблица '$newTableName' уже существует. Никаких действий не предпринято.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>Ошибка базы данных: " . $e->getMessage() . "</p>";
    }
} catch (Exception $e) {
    // Обрабатываем другие ошибки
    echo "<p style='color:red; font-weight:bold;'>Произошла ошибка: " . $e->getMessage() . "</p>";
}
?>

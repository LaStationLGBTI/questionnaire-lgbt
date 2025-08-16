<?php
require_once 'conf.php';

$targetTable = 'GSDatabase';

echo "<h1>Очистка таблицы '$targetTable'</h1>";

try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL-команда для удаления всех данных из таблицы
    $pdo->exec("TRUNCATE TABLE `$targetTable`");

    echo "<p style='color:green; font-weight:bold;'>Таблица '$targetTable' успешно очищена!</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Произошла ошибка: " . $e->getMessage() . "</p>";
}
?>

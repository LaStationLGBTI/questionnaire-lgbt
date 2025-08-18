<?php
// setup.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conf.php';

try {
    // Подключаемся к базе данных
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL-запрос для создания таблицы
    $sql = "CREATE TABLE IF NOT EXISTS GSDatabaseT (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level INT NOT NULL UNIQUE,
        titre VARCHAR(255) NOT NULL,
        text TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Выполняем запрос
    $pdo->exec($sql);

    echo "<h1>Успех!</h1>";
    echo "<p>Таблица 'GSDatabaseT' успешно создана (или уже существует).</p>";
    echo "<p>Теперь вы можете удалить этот файл (setup.php) с сервера.</p>";

} catch (PDOException $e) {
    // В случае ошибки выводим сообщение
    die("<h1>Ошибка!</h1><p>Не удалось создать таблицу: " . $e->getMessage() . "</p>");
}
?>

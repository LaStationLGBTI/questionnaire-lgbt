<?php
// Подключаем конфигурацию
require_once 'conf.php';

// Уровни, которые нужно удалить
$levels_to_delete = [9999, 3];

echo "<h1>Скрипт удаления уровней</h1>";

try {
    // Подключаемся к базе данных
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Соединение с базой данных установлено.<br><hr>";

    // Готовим запросы на удаление
    $delete_questions = $pdo->prepare("DELETE FROM GSDatabase WHERE level = ?");
    $delete_level_info = $pdo->prepare("DELETE FROM GSDatabaseT WHERE level = ?");

    // Проходим по каждому уровню в списке и удаляем его
    foreach ($levels_to_delete as $level) {
        echo "<h3>Удаление уровня $level...</h3>";

        // 1. Удаляем все вопросы этого уровня
        $delete_questions->execute([$level]);
        $question_count = $delete_questions->rowCount();
        echo "✔️ Из 'GSDatabase' удалено вопросов: $question_count.<br>";

        // 2. Удаляем информацию о самом уровне
        $delete_level_info->execute([$level]);
        $level_count = $delete_level_info->rowCount();
        echo "✔️ Из 'GSDatabaseT' удалена информация об уровне: $level_count.<br>";
    }

    echo "<hr>🎉 Операция успешно завершена.";

} catch (PDOException $e) {
    die("❌ Ошибка: " . $e->getMessage());
}
?>

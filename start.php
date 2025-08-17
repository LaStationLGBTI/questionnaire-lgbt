<?php
// Always start a new session and clear the old one when choosing a questionnaire
session_start();
session_unset();

require_once 'conf.php'; // Include DB configuration

$levels = [];
$error_message = '';

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all unique levels from the question table
    // The levels will be sorted in ascending order
    $stmt = $pdo->query("SELECT DISTINCT level FROM GSDatabase ORDER BY level ASC");
    $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_message = "Database connection error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выберите анкету</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .level-list a {
            display: block;
            background-color: #007bff;
            color: white;
            padding: 15px 20px;
            margin: 10px 0;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .level-list a:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .error {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Выберите вариант анкеты</h1>
        <?php if ($error_message): ?>
            <p class="error"><?= htmlspecialchars($error_message) ?></p>
        <?php elseif (empty($levels)): ?>
            <p>Нет доступных анкет для выбора.</p>
        <?php else: ?>
            <div class="level-list">
                <?php foreach ($levels as $level): ?>
                    <a href="index.php?level=<?= htmlspecialchars($level) ?>">
                        Пройти анкету (Уровень <?= htmlspecialchars($level) ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

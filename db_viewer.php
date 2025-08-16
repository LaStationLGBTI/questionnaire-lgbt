<?php
// Подключаем конфигурационный файл с данными для доступа к БД
require_once 'conf.php';

// Переменные для подключения
$host = $DB_HOSTNAME;
$dbname = $DB_NAME;
$user = $DB_USERNAME;
$password = $DB_PASSWORD;
$charset = 'utf8mb4';

// Переменные для работы скрипта
$pdo = null;
$tables = [];
$selectedTable = $_GET['table'] ?? null;
$columns = [];
$rows = [];
$error_message = '';

try {
    // 1. Устанавливаем соединение с базой данных
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $password, $options);

    // 2. Получаем список всех таблиц в базе данных
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Если таблица выбрана, получаем её содержимое
    if ($selectedTable && in_array($selectedTable, $tables)) {
        // Получаем названия столбцов
        $columns = $pdo->query("DESCRIBE `$selectedTable`")->fetchAll(PDO::FETCH_COLUMN);

        // Получаем первые 100 строк данных из таблицы
        $stmt = $pdo->query("SELECT * FROM `$selectedTable` LIMIT 100");
        $rows = $stmt->fetchAll();
    } elseif ($selectedTable) {
        $error_message = "Таблица '$selectedTable' не найдена.";
    }

} catch (PDOException $e) {
    // В случае ошибки подключения выводим сообщение
    $error_message = "Ошибка подключения к базе данных: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр базы данных '<?= htmlspecialchars($dbname) ?>'</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background-color: #f4f6f9;
            color: #333;
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 1px solid #dee2e6;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            overflow-y: auto;
        }
        .sidebar h2 {
            margin-top: 0;
            font-size: 1.2em;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar li a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .sidebar li a:hover {
            background-color: #e9ecef;
        }
        .sidebar li a.active {
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
        }
        .content-placeholder {
            text-align: center;
            color: #888;
            margin-top: 50px;
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            background-color: #fff;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            text-align: left;
            vertical-align: top;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: pre-wrap; /* Перенос длинных строк */
            word-break: break-word;
        }
        thead {
            background-color: #007bff;
            color: #ffffff;
        }
        tbody tr:nth-of-type(even) {
            background-color: #f8f9fa;
        }
        tbody tr:hover {
            background-color: #e9ecef;
        }
        h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Таблицы</h2>
        <ul>
            <?php foreach ($tables as $table): ?>
                <li>
                    <a href="?table=<?= htmlspecialchars($table) ?>" class="<?= ($table === $selectedTable) ? 'active' : '' ?>">
                        <?= htmlspecialchars($table) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="main-content">
        <?php if ($error_message): ?>
            <div class="error"><?= htmlspecialchars($error_message) ?></div>

        <?php elseif ($selectedTable): ?>
            <h3>Содержимое таблицы: <strong><?= htmlspecialchars($selectedTable) ?></strong></h3>
            <p>Отображаются первые 100 строк.</p>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= htmlspecialchars($column) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="<?= count($columns) ?>" style="text-align: center;">Таблица пуста.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <td><?= htmlspecialchars($row[$column] ?? 'NULL') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="content-placeholder">
                <h2>Добро пожаловать!</h2>
                <p>Выберите таблицу из списка слева, чтобы просмотреть её содержимое.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

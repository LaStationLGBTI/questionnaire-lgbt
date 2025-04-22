<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка и импорт SQL-файла</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        input[type="file"] {
            margin: 10px 0;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Загрузка SQL-файла</h2>
        <form action="" method="post" enctype="multipart/form-data">
            <label for="sql_file">Выберите SQL-файл:</label><br>
            <input type="file" name="sql_file" id="sql_file" accept=".sql" required>
            <br>
            <button type="submit" name="submit">Загрузить и импортировать</button>
        </form>

        <?php
require_once 'conf.php'; 
$host = $DB_HOSTNAME;
$user = $DB_USERNAME;
$password = $DB_PASSWORD;
$database = $DB_NAME;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Подключение к базе данных
    $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL-запрос для добавления колонки lang
    $sql = "ALTER TABLE `stationr2` ADD `lang` VARCHAR(10) NOT NULL DEFAULT 'fr' AFTER `reponse`";

    // Выполнение запроса
    $conn->exec($sql);
    echo "Колонка `lang` успешно добавлена в таблицу `stationr2`.";

} catch (PDOException $e) {
    // Вывод ошибки, если что-то пошло не так
    echo "Ошибка: " . $e->getMessage();
}

// Закрытие соединения
$conn = null;


        if (isset($_POST['submit']) && isset($_FILES['sql_file'])) {
            try {


                // Проверка загруженного файла
                $file = $_FILES['sql_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Ошибка загрузки файла: " . $file['error']);
                }

                // Проверка расширения файла
                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (strtolower($fileExtension) !== 'sql') {
                    throw new Exception("Файл должен иметь расширение .sql");
                }

                // Чтение SQL-файла
                $sql = file_get_contents($file['tmp_name']);
                if ($sql === false) {
                    throw new Exception("Ошибка чтения файла: " . $file['name']);
                }

                // Подключение к базе данных через PDO
                $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Разделение SQL-файла на отдельные команды
                $queries = array_filter(array_map('trim', explode(';', $sql)), function($query) {
                    return !empty($query) && !preg_match('/^\s*(--|\/\*)/', $query);
                });

                // Выполнение каждой команды
                foreach ($queries as $query) {
                    $pdo->exec($query);
                }

                // Проверка создания таблицы stationq2
                $stmt = $pdo->query("SHOW TABLES LIKE 'stationq2'");
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Таблица stationq2 не была создана.");
                }

                // Проверка количества записей в таблице
                $stmt = $pdo->query("SELECT COUNT(*) AS count FROM stationq2");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $recordCount = $row['count'];

                // Проверка содержимого (например, первый вопрос)
                $stmt = $pdo->query("SELECT question FROM stationq2 WHERE id = 1");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $firstQuestion = $row['question'] ?? '';

                // Вывод результата
                echo "<p class='success'>Таблица stationq2 успешно импортирована!</p>";
                echo "<p>Количество записей в таблице: $recordCount</p>";
                echo "<p>Первый вопрос: " . htmlspecialchars($firstQuestion) . "</p>";

            } catch (Exception $e) {
                echo "<p class='error'>Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
                file_put_contents('error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        ?>
    </div>
</body>
</html>

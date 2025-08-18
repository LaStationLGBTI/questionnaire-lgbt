<?php
// add_level.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conf.php';

$message = '';

// Проверяем, была ли отправлена форма
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
$titre = $_POST['titre'];
$text = $_POST['text'];

    // Простая проверка, что все поля заполнены
if ($level && !empty($titre)) { // Поле text может быть пустым
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // --- ВОТ КЛЮЧЕВАЯ ЧАСТЬ ---
            // Этот запрос вставляет новую строку, а если level уже существует,
            // он обновляет поля titre и text для этой строки.
            $sql = "INSERT INTO GSDatabaseT (level, titre, text) VALUES (:level, :titre, :text)
                    ON DUPLICATE KEY UPDATE titre = :titre, text = :text";
            
            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':level' => $level,
                ':titre' => $titre,
                ':text'  => $text
            ]);

            $message = "<p style='color: green;'><strong>Ok!</strong> Information de module {$level} et ajoutée/modifiée.</p>";

        } catch (PDOException $e) {
            $message = "<p style='color: red;'><strong>Ошибка!</strong> Erreur de sauvgarde: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p style='color: red;'><strong>Ошибка!</strong> Il faut remplir "Titre" et "Text".</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Module</title>
    <style>
        body { font-family: sans-serif; margin: 2em; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: auto; padding: 2em; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; }
        label { display: block; margin-top: 1em; font-weight: bold; }
        input[type="number"], input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-top: 0.5em;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        textarea { height: 150px; resize: vertical; }
        button {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 1.5em;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ajouter module/modifier</h1>
        
        <?php echo $message; // Отображение сообщения об успехе или ошибке ?>

        <form action="add_level.php" method="POST">
            <div>
                <label for="level">Module (Level):</label>
                <input type="number" id="level" name="level" required>
            </div>
            <div>
                <label for="titre">Titre (Titre):</label>
                <input type="text" id="titre" name="titre" required>
            </div>
            <div>
                <label for="text">Text (Text):</label>
                <textarea id="text" name="text" required></textarea>
            </div>
            <button type="submit">Sauvgarder</button>
        </form>
    </div>
</body>
</html>

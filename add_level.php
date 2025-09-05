<?php
// Подключаем конфигурацию и запускаем сессию
require_once 'conf.php';
session_start();

$message = '';
$level_created = isset($_SESSION['level_created']) ? $_SESSION['level_created'] : null;
$level_titre = isset($_SESSION['level_titre']) ? $_SESSION['level_titre'] : '';

// Обработка создания уровня
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_level'])) {
    $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre']);
    $text = trim($_POST['text']);

    if ($level && !empty($titre)) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Проверяем, существует ли уже такой уровень
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM GSDatabaseT WHERE level = ?");
            $stmt_check->execute([$level]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = "<p class='error'>Ошибка: Уровень с номером $level уже существует.</p>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO GSDatabaseT (level, titre, text) VALUES (?, ?, ?)");
                $stmt->execute([$level, $titre, $text]);
                $_SESSION['level_created'] = $level;
                $_SESSION['level_titre'] = $titre;
                $message = "<p class='success'>Уровень '$titre' (ID: $level) успешно создан. Теперь можно добавлять вопросы.</p>";
                header('Location: ' . $_SERVER['PHP_SELF']); // Перезагружаем страницу, чтобы обновить состояние
                exit();
            }
        } catch (PDOException $e) {
            $message = "<p class='error'>Ошибка базы данных: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p class='error'>Пожалуйста, введите корректный номер уровня и заголовок.</p>";
    }
}

// Обработка добавления вопроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    if ($level_created) {
        $level = $level_created;
        $qtype = $_POST['qtype'];
        $question = trim($_POST['question']);
        $rep1 = trim($_POST['rep1']);
        $rep2 = trim($_POST['rep2']);
        $rep3 = trim($_POST['rep3']);
        $rep4 = trim($_POST['rep4']);
        $rep5 = trim($_POST['rep5']);
        $answer = filter_input(INPUT_POST, 'answer', FILTER_VALIDATE_INT);
        // --- НОВОЕ ПОЛЕ ---
        $expliq = trim($_POST['expliq']); // Получаем значение объяснения

        if (!empty($question) && !empty($rep1) && !empty($rep2) && $answer) {
             try {
                $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // --- ОБНОВЛЕННЫЙ ЗАПРОС ---
                $stmt_q = $pdo->prepare(
                    "INSERT INTO GSDatabase (level, qtype, question, rep1, rep2, rep3, rep4, rep5, answer, expliq) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                // --- ОБНОВЛЕННЫЕ ДАННЫЕ ДЛЯ ЗАПРОСА ---
                $stmt_q->execute([$level, $qtype, $question, $rep1, $rep2, $rep3, $rep4, $rep5, $answer, $expliq]);

                $message = "<p class='success'>Вопрос успешно добавлен в уровень '$level_titre'!</p>";

            } catch (PDOException $e) {
                $message = "<p class='error'>Ошибка при добавлении вопроса: " . $e->getMessage() . "</p>";
            }
        } else {
            $message = "<p class='error'>Пожалуйста, заполните все обязательные поля для вопроса (вопрос, ответы 1 и 2, правильный ответ).</p>";
        }
    }
}

// Сброс сессии для создания нового уровня
if (isset($_POST['reset_level'])) {
    unset($_SESSION['level_created']);
    unset($_SESSION['level_titre']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить новый уровень и вопросы</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; line-height: 1.6; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 0.8rem; border-radius: 5px; border: 1px solid #ddd; box-sizing: border-box; }
        textarea { resize: vertical; }
        button { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; }
        button:hover { background-color: #0056b3; }
        .reset-button { background-color: #6c757d; }
        .reset-button:hover { background-color: #5a6268; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; }
        .info { color: #0c5460; background: #d1ecf1; border: 1px solid #bee5eb; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; }
        .question-form { border-top: 2px solid #eee; margin-top: 2rem; padding-top: 2rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Панель добавления уровней</h1>
        <?php if ($message) echo $message; ?>

        <?php if (!$level_created): ?>
            <h2>Шаг 1: Создать новый уровень</h2>
            <form action="" method="post">
                <div class="form-group">
                    <label for="level">Номер уровня (например, 101, 102):</label>
                    <input type="number" id="level" name="level" required>
                </div>
                <div class="form-group">
                    <label for="titre">Заголовок уровня:</label>
                    <input type="text" id="titre" name="titre" required>
                </div>
                <div class="form-group">
                    <label for="text">Описание/Текст для уровня (поддерживает HTML):</label>
                    <textarea id="text" name="text" rows="8"></textarea>
                </div>
                <button type="submit" name="create_level">Создать уровень</button>
            </form>
        <?php else: ?>
            <div class="info">
                Вы добавляете вопросы в уровень: <strong><?php echo htmlspecialchars($level_titre) . " (ID: " . htmlspecialchars($level_created) . ")"; ?></strong>
                <form action="" method="post" style="display:inline; margin-left: 20px;">
                    <button type="submit" name="reset_level" class="reset-button">Создать другой уровень</button>
                </form>
            </div>
            
            <div class="question-form">
                <h2>Шаг 2: Добавить вопрос</h2>
                <form action="" method="post">
                    <div class="form-group">
                        <label for="qtype">Тип вопроса:</label>
                        <select id="qtype" name="qtype">
                            <option value="qcm">QCM (Один правильный ответ)</option>
                            <option value="echelle">Echelle (Оценочная шкала)</option>
                            <option value="lien">Lien (Связывание)</option>
                            <option value="mct">MCT (Множественный выбор)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="question">Текст вопроса:</label>
                        <textarea id="question" name="question" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="expliq">Explication de la réponse (объяснение ответа):</p></label>
                        <textarea id="expliq" name="expliq" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="rep1">Ответ 1 (обязательно):</label>
                        <input type="text" id="rep1" name="rep1" required>
                    </div>
                    <div class="form-group">
                        <label for="rep2">Ответ 2 (обязательно):</label>
                        <input type="text" id="rep2" name="rep2" required>
                    </div>
                    <div class="form-group">
                        <label for="rep3">Ответ 3:</label>
                        <input type="text" id="rep3" name="rep3">
                    </div>
                    <div class="form-group">
                        <label for="rep4">Ответ 4:</label>
                        <input type="text" id="rep4" name="rep4">
                    </div>
                     <div class="form-group">
                        <label for="rep5">Ответ 5:</label>
                        <input type="text" id="rep5" name="rep5">
                    </div>
                    <div class="form-group">
                        <label for="answer">Номер правильного ответа (для QCM):</label>
                        <input type="number" id="answer" name="answer" required value="0">
                    </div>
                    <button type="submit" name="add_question">Добавить вопрос</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

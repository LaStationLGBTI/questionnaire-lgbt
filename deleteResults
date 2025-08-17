<?php
// Подключаем конфигурацию и запускаем сессию
require_once 'conf.php';
session_start();

// Используем ту же систему входа, что и на других страницах администратора
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: clear_results.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_SESSION['login_attempts'] < 3) {
        $login = $_POST['identifiant'];
        $pass = $_POST['mot_de_passe'];
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT passconn FROM stationl1 WHERE loginconn = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            if ($user && $pass === $user['passconn']) {
                $_SESSION['is_logged_in'] = true;
                $_SESSION['login_attempts'] = 0;
            } else {
                $_SESSION['login_attempts']++;
                $login_error = "Identifiant ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $login_error = "Erreur de connexion : " . $e->getMessage();
        }
    }
}

// Логика удаления данных
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Выполняем команду TRUNCATE
            $pdo->exec("TRUNCATE TABLE GSDatabaseR");
            
            $message = "<p class='success'>Успех! Все записи из таблицы <strong>GSDatabaseR</strong> были удалены.</p>";
        } catch (PDOException $e) {
            $message = "<p class='error'>Ошибка при удалении данных: " . $e->getMessage() . "</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Очистка таблицы результатов</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 2rem 3rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 600px; width: 100%; }
        button { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; }
        button:hover { background-color: #0056b3; }
        .delete-button { background-color: #dc3545; }
        .delete-button:hover { background-color: #c82333; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; }
        .warning { color: #856404; background: #fff3cd; border: 1px solid #ffeeba; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
            
            <form action="" method="post" class="logout-form">
                <button type="submit" name="logout">Déconnexion</button>
            </form>

            <h1>Очистка таблицы результатов</h1>
            
            <?php if ($message) echo $message; ?>

            <div class="warning">
                <strong>ВНИМАНИЕ!</strong> Это действие безвозвратно удалит <strong>ВСЕ</strong> записи из таблицы <code>GSDatabaseR</code>. ID записей будет сброшен на 1.
            </div>
            
            <form action="" method="post" onsubmit="return confirm('Вы абсолютно уверены, что хотите удалить все результаты? Это действие нельзя отменить.');">
                <button type="submit" name="confirm_delete" class="delete-button">Да, я уверен, удалить все результаты</button>
            </form>

        <?php elseif ($_SESSION['login_attempts'] >= 3) : ?>
            <h1>Доступ заблокирован</h1>
            <p class="error">Ваш доступ заблокирован после 3 неудачных попыток.</p>
        <?php else : ?>
            <h1>Вход для администратора</h1>
             <?php if (isset($login_error)) : ?><p class="error"><?php echo $login_error; ?></p><?php endif; ?>
            <form action="" method="post" style="text-align: left;">
                <div style="margin-bottom: 1rem;">
                    <label for="identifiant">Логин:</label>
                    <input type="text" id="identifiant" name="identifiant" required style="width: 100%; padding: 0.5rem; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label for="mot_de_passe">Пароль:</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required style="width: 100%; padding: 0.5rem; box-sizing: border-box;">
                </div>
                <button type="submit" name="login">Войти</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

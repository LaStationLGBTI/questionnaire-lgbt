<?php
// Подключаем конфигурацию и запускаем сессию
require_once 'conf.php';
session_start();

// Инициализируем счетчик попыток входа
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Обработка выхода из системы
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: db_viewer.php');
    exit();
}

// Обработка входа
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultats du Questionnaire</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 1200px; margin: auto; }
        .login-container { max-width: 500px; margin-top: 10vh; }
        h1, h2 { color: #5a5a5a; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; word-break: break-word; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; }
        button:hover { background-color: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
        .logout-form button:hover { background-color: #5a6268; }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
        
        <div class="container">
            <form action="" method="post" class="logout-form">
                <button type="submit" name="logout">Déconnexion</button>
            </form>
            
            <h1>Résultats des Questionnaires</h1>

            <?php
            try {
                $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // ================== ИСПРАВЛЕНИЕ ЗДЕСЬ ==================
                // 1. Убрали `timestamp` из SQL-запроса
                $stmt = $pdo->query("SELECT id, ip, genre, orientation, reponse, repmail, lang FROM GSDatabaseR ORDER BY id DESC");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($results) > 0) {
                    echo "<table>";
                    // 2. Убрали `Date` из заголовка таблицы
                    echo "<thead><tr><th>ID</th><th>IP</th><th>Genre</th><th>Orientation</th><th>Réponses</th><th>Email</th><th>Langue</th></tr></thead>";
                    echo "<tbody>";
                    foreach ($results as $row) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['ip']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['genre']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['orientation']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['reponse']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['repmail']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['lang']) . "</td>";
                        // 3. Убрали вывод ячейки с датой
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p>Aucun résultat trouvé dans la base de données.</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>Erreur de connexion à la base de données : " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

    <?php elseif ($_SESSION['login_attempts'] >= 3) : ?>

        <div class="container login-container">
            <h1>Accès Bloqué</h1>
            <p class="error">Votre accès est bloqué après 3 tentatives infructueuses.</p>
        </div>

    <?php else : ?>

        <div class="container login-container">
            <h1>Accès Administrateur</h1>
            <?php if (isset($login_error)) : ?>
                <p class="error"><?php echo $login_error; ?></p>
                <p>Tentative <?php echo $_SESSION['login_attempts']; ?> sur 3.</p>
            <?php endif; ?>
            <form action="" method="post">
                <div class="form-group">
                    <label for="identifiant">Identifiant :</label>
                    <input type="text" id="identifiant" name="identifiant" required>
                </div>
                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe :</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                </div>
                <button type="submit" name="login">Connexion</button>
            </form>
        </div>

    <?php endif; ?>

</body>
</html>

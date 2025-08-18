<?php
// add_level.php - СИСТЕМА АУТЕНТИФИКАЦИИ ДОБАВЛЕНА
require_once 'conf.php';
session_start();

// Инициализация счетчика попыток входа
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Обработка выхода
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: add_level.php');
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

// --- Логика сохранения данных (работает только если пользователь вошёл) ---
$message = '';
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_data'])) {
        $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
        $titre = trim($_POST['titre']);
        $text = trim($_POST['text']);

        if ($level) {
            try {
                $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->prepare("SELECT id FROM GSDatabaseT WHERE level = ?");
                $stmt->execute([$level]);
                $existing_record = $stmt->fetch();

                if ($existing_record) {
                    $update_fields = [];
                    $params = ['level' => $level];
                    if (!empty($titre)) {
                        $update_fields[] = "titre = :titre";
                        $params['titre'] = $titre;
                    }
                    if (!empty($text)) {
                        $update_fields[] = "text = :text";
                        $params['text'] = $text;
                    }
                    if (!empty($update_fields)) {
                        $sql = "UPDATE GSDatabaseT SET " . implode(', ', $update_fields) . " WHERE level = :level";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $message = "<p style='color: green;'><strong>Succès !</strong> Les données pour le niveau {$level} ont été mises à jour.</p>";
                    } else {
                        $message = "<p style='color: orange;'><strong>Info :</strong> Rien à mettre à jour. Les champs étaient vides.</p>";
                    }
                } else {
                    if (empty($titre)) {
                        $message = "<p style='color: red;'><strong>Erreur !</strong> Le champ 'Titre' est obligatoire pour un nouveau niveau.</p>";
                    } else {
                        $sql = "INSERT INTO GSDatabaseT (level, titre, text) VALUES (:level, :titre, :text)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['level' => $level, 'titre' => $titre, 'text'  => $text]);
                        $message = "<p style='color: green;'><strong>Succès !</strong> Le nouveau niveau {$level} a été ajouté.</p>";
                    }
                }
            } catch (PDOException $e) {
                $message = "<p style='color: red;'><strong>Erreur !</strong> Impossible d'enregistrer les données : " . $e->getMessage() . "</p>";
            }
        } else {
            $message = "<p style='color: red;'><strong>Erreur !</strong> Veuillez remplir le champ 'Niveau'.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter/Modifier la description</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        .login-container { max-width: 500px; margin-top: 10vh; }
        h1 { color: #5a5a5a; text-align: center; }
        label { display: block; margin-top: 1em; font-weight: bold; }
        input[type="number"], input[type="text"], textarea { width: 100%; padding: 10px; margin-top: 0.5em; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        textarea { height: 150px; resize: vertical; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        input[type="password"] { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; margin-top: 1.5em; }
        button:hover { background-color: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; margin-top: 0;}
        .logout-form button:hover { background-color: #5a6268; }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
        
        <div class="container">
            <form action="" method="post" class="logout-form">
                <button type="submit" name="logout">Déconnexion</button>
            </form>
            
            <h1>Ajouter / Modifier une description</h1>
            
            <?php echo $message; ?>

            <form action="add_level.php" method="POST">
                <div>
                    <label for="level">Niveau (Level) :</label>
                    <input type="number" id="level" name="level" required>
                </div>
                <div>
                    <label for="titre">Titre :</label>
                    <input type="text" id="titre" name="titre">
                </div>
                <div>
                    <label for="text">Texte :</label>
                    <textarea id="text" name="text"></textarea>
                </div>
                <button type="submit" name="save_data">Enregistrer les données</button>
            </form>
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

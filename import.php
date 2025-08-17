<?php
require_once 'conf.php';
session_start();

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: import.php');
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

$import_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']) {
        if (isset($_FILES['questionnaire_file']) && $_FILES['questionnaire_file']['error'] === UPLOAD_ERR_OK) {
            
            $file_tmp_path = $_FILES['questionnaire_file']['tmp_name'];
            if (strtolower(pathinfo($_FILES['questionnaire_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                $import_message = "<p class='error'>Erreur : Veuillez sélectionner un fichier au format CSV.</p>";
            } else {
                try {
                    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // ================== НОВАЯ ЛОГИКА ИМПОРТА ==================

                    ini_set('auto_detect_line_endings', TRUE);
                    $handle = fopen($file_tmp_path, 'r');

                    // Пропускаем BOM
                    if (fgets($handle, 4) !== "\xef\xbb\xbf") {
                        rewind($handle);
                    }

                    // Пропускаем заголовок
                    fgetcsv($handle, 2000, ";");

                    $all_rows = [];
                    $level_to_check = null;
                    while (($data = fgetcsv($handle, 2000, ";")) !== FALSE) {
                        // Пропускаем пустые строки или строки с недостаточным количеством колонок
                        if (count($data) < 2 || empty($data[1])) {
                            continue;
                        }
                        $all_rows[] = $data;
                        if ($level_to_check === null) {
                            $level_to_check = trim($data[0]); // Уровень теперь в ПЕРВОЙ колонке (индекс 0)
                        }
                    }
                    fclose($handle);

                    if (!$level_to_check || empty($all_rows)) {
                         throw new Exception("Impossible de déterminer le niveau ou aucune donnée trouvée.");
                    }

                    // Проверяем уровень на дубликат в БД
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM GSDatabase WHERE level = ?");
                    $stmt->execute([$level_to_check]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("Le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> existe déjà.");
                    }
                    
                    // Все проверки пройдены, импортируем
                    $pdo->beginTransaction();
                    $sql = "INSERT INTO GSDatabase (level, question, rep1, rep2, rep3, rep4, rep5, answer, qtype, image, sound) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    
                    foreach ($all_rows as $row) {
                        $stmt->execute([
                            trim($row[0]) ?? null,      // level
                            trim($row[1]) ?? null,      // question
                            trim($row[2]) ?? null,      // rep1
                            trim($row[3]) ?? null,      // rep2
                            trim($row[4]) ?? null,      // rep3
                            trim($row[5]) ?? 'null',    // rep4, используем 'null' если пусто
                            trim($row[6]) ?? 'null',    // rep5, используем 'null' если пусто
                            trim($row[7]) ?? null,      // answer
                            trim($row[8]) ?? null,      // qtype
                            null,                       // image (нет в CSV)
                            null                        // sound (нет в CSV)
                        ]);
                    }
                    
                    $pdo->commit();
                    $import_message = "<p class='success'>Importation réussie. <strong>" . count($all_rows) . "</strong> questions ajoutées pour le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong>.</p>";

                } catch (Exception $e) {
                    if(isset($pdo) && $pdo->inTransaction()){ $pdo->rollBack(); }
                    $import_message = "<p class='error'>Erreur : " . $e->getMessage() . "</p>";
                }
            }
        } else {
             $import_message = "<p class='error'>Erreur lors du téléchargement du fichier.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Importation de Questionnaire</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 2rem 3rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 100%; }
        h1 { color: #5a5a5a; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="file"] { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; }
        button:hover { background-color: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
        .logout-form button:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
            <form action="import.php" method="post" class="logout-form">
                <button type="submit" name="logout">Déconnexion</button>
            </form>
            <h1>Importer un Questionnaire</h1>
            <?php if ($import_message) echo $import_message; ?>
            <form action="import.php" method="post" enctype="multipart/form-data" style="margin-top: 2rem;">
                <div class="form-group">
                    <label for="questionnaire_file">Sélectionnez un fichier de questionnaire (.csv) :</label>
                    <input type="file" id="questionnaire_file" name="questionnaire_file" accept=".csv" required>
                </div>
                <button type="submit" name="upload">Importer le fichier</button>
            </form>
        <?php elseif ($_SESSION['login_attempts'] >= 3) : ?>
            <h1>Accès Bloqué</h1>
            <p class="error" name="session_bloquee">Votre accès est bloqué. Veuillez contacter l'administrateur.</p>
        <?php else : ?>
            <h1>Accès Administrateur</h1>
            <?php if (isset($login_error)) : ?>
                <p class="error"><?php echo $login_error; ?></p>
                <p>Tentative <?php echo $_SESSION['login_attempts']; ?> sur 3.</p>
            <?php endif; ?>
            <form action="import.php" method="post">
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
        <?php endif; ?>
    </div>
</body>
</html>

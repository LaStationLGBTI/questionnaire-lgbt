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
            $login_error = "Erreur de connexion à la base de données : " . $e->getMessage();
        }
    }
}

// --- ЛОГИКА ИМПОРТА ФАЙЛА (УЛЬТРА-НАДЁЖНАЯ ВЕРСИЯ) ---
$import_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']) {
        if (isset($_FILES['questionnaire_file']) && $_FILES['questionnaire_file']['error'] === UPLOAD_ERR_OK) {
            
            $file_tmp_path = $_FILES['questionnaire_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['questionnaire_file']['name'], PATHINFO_EXTENSION));

            if ($file_ext === 'csv') {
                try {
                    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // ================== НАЧАЛО ОБНОВЛЕНИЙ ==================

                    // Шаг 1: Открываем файл и исправляем проблемы с кодировкой/окончаниями строк
                    ini_set('auto_detect_line_endings', TRUE);
                    $handle = fopen($file_tmp_path, 'r');
                    
                    // Проверяем и пропускаем невидимый маркер BOM
                    if (fgets($handle, 4) !== "\xef\xbb\xbf") {
                        rewind($handle); // Если BOM нет, возвращаемся в начало
                    }

                    // Шаг 2: Автоматически определяем разделитель (запятая или точка с запятой)
                    $delimiter = ';';
                    $first_line_for_delimiter = fgets($handle);
                    if (substr_count($first_line_for_delimiter, ',') > substr_count($first_line_for_delimiter, ';')) {
                        $delimiter = ',';
                    }
                    
                    // Шаг 3: Читаем данные и проверяем уровень
                    $all_rows = [];
                    $level_to_check = null;
                    
                    // Перемещаем указатель обратно в начало, чтобы прочитать файл полностью
                    rewind($handle);
                    if (fgets($handle, 4) !== "\xef\xbb\xbf") { // Снова пропускаем BOM, если он есть
                        rewind($handle);
                    }
                    fgetcsv($handle, 2000, $delimiter); // Пропускаем заголовок

                    while (($data = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
                        // Пропускаем полностью пустые или некорректные строки
                        if (count($data) < 11 || empty(implode('', $data))) {
                            continue;
                        }
                        // Находим уровень по первой же строке с данными
                        if ($level_to_check === null && isset($data[10])) {
                            $level_to_check = trim($data[10]);
                        }
                        $all_rows[] = $data;
                    }
                    fclose($handle);

                    // =================== КОНЕЦ ОБНОВЛЕНИЙ ===================
                    
                    if ($level_to_check && !empty($all_rows)) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM GSDatabase WHERE level = ?");
                        $stmt->execute([$level_to_check]);
                        if ($stmt->fetchColumn() > 0) {
                            $import_message = "<p class='error'>Erreur : Le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> existe déjà dans la base de données. L'importation est annulée.</p>";
                        } else {
                            // Шаг 4: Уровень уникален, импортируем все собранные строки
                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare(
                                "INSERT INTO GSDatabase (question, rep1, rep2, rep3, rep4, rep5, answer, qtype, image, sound, level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            
                            foreach($all_rows as $row) {
                                $stmt->execute([
                                    $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6],
                                    $row[7], $row[8], $row[9], trim($row[10])
                                ]);
                            }
                            
                            $pdo->commit();
                            $import_message = "<p class='success'>Le questionnaire (Niveau <strong>" . htmlspecialchars($level_to_check) . "</strong>) a été importé avec succès. <strong>" . count($all_rows) . "</strong> questions ajoutées.</p>";
                        }
                    } else {
                        $import_message = "<p class='error'>Erreur : Impossible de lire les données ou de déterminer le niveau du questionnaire. Vérifiez que le fichier CSV n'est pas vide, содержит 11 колонок и имеет правильный формат.</p>";
                    }

                } catch (Exception $e) {
                    if(isset($pdo) && $pdo->inTransaction()){
                       $pdo->rollBack();
                    }
                    $import_message = "<p class='error'>Une erreur critique est survenue : " . $e->getMessage() . "</p>";
                }
            } else {
                $import_message = "<p class='error'>Erreur : Veuillez sélectionner un fichier au format CSV.</p>";
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
            <p class="error" name="session_bloquee">Votre accès est bloqué après 3 tentatives infructueuses. Veuillez contacter l'administrateur.</p>

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

<?php
// Включаем конфигурацию БД и запускаем сессию
require_once 'conf.php';
session_start();

// Упрощенная версия аутентификации для отладки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['identifiant'] === 'admin' && $_POST['mot_de_passe'] === 'password') { // Простая заглушка
        $_SESSION['is_logged_in'] = true;
    }
}
if (!isset($_SESSION['is_logged_in'])) {
     $_SESSION['is_logged_in'] = true; // Для теста временно разрешаем доступ
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic de Fichier CSV</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
        h1, h2 { color: #5a5a5a; }
        pre { background: #eee; border: 1px solid #ddd; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
        .error { color: #dc3545; }
        .success { color: #155724; }
    </style>
</head>
<body>

    <div class="container">
        <h1>Outil de Diagnostic CSV</h1>
        <p>Cet outil ne modifiera pas la base de données. Il analysera le fichier CSV téléchargé et affichera ce que le script "voit".</p>
        
        <form action="" method="post" enctype="multipart/form-data" style="margin-top: 2rem;">
            <input type="file" name="diagnostic_file" accept=".csv" required>
            <button type="submit" name="run_diagnostic">Analyser le fichier</button>
        </form>

        <?php
        if (isset($_POST['run_diagnostic']) && isset($_FILES['diagnostic_file']) && $_FILES['diagnostic_file']['error'] === UPLOAD_ERR_OK) {
            
            echo "<h2>Résultats de l'analyse :</h2>";

            $file_tmp_path = $_FILES['diagnostic_file']['tmp_name'];

            // Шаг 1: Настройка чтения
            ini_set('auto_detect_line_endings', TRUE);
            $handle = fopen($file_tmp_path, 'r');
            
            // Шаг 2: Проверка BOM
            $bom = fgets($handle, 4);
            if ($bom !== "\xef\xbb\xbf") {
                rewind($handle);
                echo "<p><strong>BOM (UTF-8 Mark):</strong> <span class='error'>Non détecté.</span></p>";
            } else {
                echo "<p><strong>BOM (UTF-8 Mark):</strong> <span class='success'>Détecté et ignoré.</span></p>";
            }

            // Шаг 3: Определение разделителя
            $first_line = fgets($handle);
            $delimiter = ';';
            if (substr_count($first_line, ',') > substr_count($first_line, ';')) {
                $delimiter = ',';
            }
            echo "<p><strong>Délimiteur détecté:</strong> <code>" . htmlspecialchars($delimiter) . "</code></p>";
            rewind($handle); // Возвращаемся в начало

            // Повторно пропускаем BOM, если он был
            if (fgets($handle, 4) !== "\xef\xbb\xbf") {
                rewind($handle);
            }

            // Шаг 4: Чтение строк и анализ
            $line_number = 0;
            echo "<hr><h3>Analyse ligne par ligne :</h3>";

            while (($data = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
                $line_number++;
                echo "<h4>Ligne N°" . $line_number . "</h4>";
                echo "<strong>Nombre de colonnes détectées:</strong> " . count($data) . "<br>";
                
                if (isset($data[10])) {
                    echo "<strong>Valeur dans la 11ème colonne (level):</strong> <code>" . htmlspecialchars(trim($data[10])) . "</code><br>";
                } else {
                    echo "<strong>Valeur dans la 11ème colonne (level):</strong> <span class='error'>NON TROUVÉE.</span><br>";
                }

                echo "<strong>Contenu de la ligne (var_dump):</strong>";
                echo "<pre>";
                var_dump($data);
                echo "</pre>";
            }

            fclose($handle);
            ini_set('auto_detect_line_endings', FALSE);
        }
        ?>
    </div>

</body>
</html>

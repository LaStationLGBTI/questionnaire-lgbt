<?php
// add_level.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conf.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre']);
    $text = trim($_POST['text']);

    if ($level) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 1. Проверяем, существует ли запись с таким level
            $stmt = $pdo->prepare("SELECT id FROM GSDatabaseT WHERE level = ?");
            $stmt->execute([$level]);
            $existing_record = $stmt->fetch();

            if ($existing_record) {
                // --- ЛОГИКА ОБНОВЛЕНИЯ ---
                // Запись существует, обновляем только заполненные поля
                
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
                // --- ЛОГИКА ДОБАВЛЕНИЯ НОВОЙ ЗАПИСИ ---
                // Запись не существует, создаем новую
                
                if (empty($titre)) {
                    $message = "<p style='color: red;'><strong>Erreur !</strong> Le champ 'Titre' est obligatoire pour un nouveau niveau.</p>";
                } else {
                    $sql = "INSERT INTO GSDatabaseT (level, titre, text) VALUES (:level, :titre, :text)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'level' => $level,
                        'titre' => $titre,
                        'text'  => $text
                    ]);
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter/Modifier la description d'un niveau</title>
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
        <h1>Ajouter / Modifier la description d'un niveau</h1>
        
        <?php echo $message; // Отображение сообщения об успехе или ошибке ?>

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
            <button type="submit">Enregistrer les données</button>
        </form>
    </div>
</body>
</html>

<?php
// On inclut la configuration et on démarre la session
require_once 'conf.php';
session_start();

$message = '';
$level_created = isset($_SESSION['level_created']) ? $_SESSION['level_created'] : null;
$level_titre = isset($_SESSION['level_titre']) ? $_SESSION['level_titre'] : '';

// Traitement de la création du niveau
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_level'])) {
    $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre']);
    $text = trim($_POST['text']);

    if ($level && !empty($titre)) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si le niveau existe déjà
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM GSDatabaseT WHERE level = ?");
            $stmt_check->execute([$level]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = "<p class='error'>Erreur : Le niveau numéro $level existe déjà.</p>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO GSDatabaseT (level, titre, text) VALUES (?, ?, ?)");
                $stmt->execute([$level, $titre, $text]);
                $_SESSION['level_created'] = $level;
                $_SESSION['level_titre'] = $titre;
                $message = "<p class='success'>Le niveau '$titre' (ID: $level) a été créé avec succès. Vous pouvez maintenant ajouter des questions.</p>";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        } catch (PDOException $e) {
            $message = "<p class='error'>Erreur de base de données : " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p class='error'>Veuillez saisir un numéro de niveau et un titre valides.</p>";
    }
}

// Traitement de l'ajout d'une question
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
        $expliq = trim($_POST['expliq']);

        if (!empty($question) && !empty($rep1) && !empty($rep2) && $answer !== null) {
             try {
                $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $stmt_q = $pdo->prepare(
                    "INSERT INTO GSDatabase (level, qtype, question, rep1, rep2, rep3, rep4, rep5, answer, expliq) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_q->execute([$level, $qtype, $question, $rep1, $rep2, $rep3, $rep4, $rep5, $answer, $expliq]);

                $message = "<p class='success'>La question a été ajoutée avec succès au niveau '$level_titre' !</p>";

            } catch (PDOException $e) {
                $message = "<p class='error'>Erreur lors de l'ajout de la question : " . $e->getMessage() . "</p>";
            }
        } else {
            $message = "<p class='error'>Veuillez remplir tous les champs obligatoires pour la question (question, réponses 1 et 2, numéro de la bonne réponse).</p>";
        }
    }
}

// Réinitialiser la session pour créer un nouveau niveau
if (isset($_POST['reset_level'])) {
    unset($_SESSION['level_created']);
    unset($_SESSION['level_titre']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un nouveau niveau et des questions</title>
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
        <h1>Panneau d'ajout de niveaux</h1>
        <?php if ($message) echo $message; ?>

        <?php if (!$level_created): ?>
            <h2>Étape 1 : Créer un nouveau niveau</h2>
            <form action="" method="post">
                <div class="form-group">
                    <label for="level">Numéro du niveau (ex: 101, 102) :</label>
                    <input type="number" id="level" name="level" required>
                </div>
                <div class="form-group">
                    <label for="titre">Titre du niveau :</label>
                    <input type="text" id="titre" name="titre" required>
                </div>
                <div class="form-group">
                    <label for="text">Description/Texte pour le niveau (supporte le HTML) :</label>
                    <textarea id="text" name="text" rows="8"></textarea>
                </div>
                <button type="submit" name="create_level">Créer le niveau</button>
            </form>
        <?php else: ?>
            <div class="info">
                Vous ajoutez des questions au niveau : <strong><?php echo htmlspecialchars($level_titre) . " (ID: " . htmlspecialchars($level_created) . ")"; ?></strong>
                <form action="" method="post" style="display:inline; margin-left: 20px;">
                    <button type="submit" name="reset_level" class="reset-button">Créer un autre niveau</button>
                </form>
            </div>
            
            <div class="question-form">
                <h2>Étape 2 : Ajouter une question</h2>
                <form action="" method="post">
                    <div class="form-group">
                        <label for="qtype">Type de question :</label>
                        <select id="qtype" name="qtype">
                            <option value="qcm">QCM (Une seule bonne réponse)</option>
                            <option value="echelle">Échelle (Évaluation)</option>
                            <option value="lien">Lien (Association)</option>
                            <option value="mct">MCT (Choix multiples)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="question">Texte de la question :</label>
                        <textarea id="question" name="question" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="expliq">Explication de la réponse :</label>
                        <textarea id="expliq" name="expliq" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="rep1">Réponse 1 (obligatoire) :</label>
                        <input type="text" id="rep1" name="rep1" required>
                    </div>
                    <div class="form-group">
                        <label for="rep2">Réponse 2 (obligatoire) :</label>
                        <input type="text" id="rep2" name="rep2" required>
                    </div>
                    <div class="form-group">
                        <label for="rep3">Réponse 3 :</label>
                        <input type="text" id="rep3" name="rep3" placeholder="Optionnel">
                    </div>
                    <div class="form-group">
                        <label for="rep4">Réponse 4 :</label>
                        <input type="text" id="rep4" name="rep4" placeholder="Optionnel">
                    </div>
                     <div class="form-group">
                        <label for="rep5">Réponse 5 :</label>
                        <input type="text" id="rep5" name="rep5" placeholder="Optionnel">
                    </div>
                    <div class="form-group">
                        <label for="answer">Numéro de la bonne réponse (pour QCM) :</label>
                        <input type="number" id="answer" name="answer" required value="0" min="0">
                    </div>
                    <button type="submit" name="add_question">Ajouter la question</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

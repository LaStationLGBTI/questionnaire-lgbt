<?php
require_once 'conf.php';
session_start();

// --- Niveaux à supprimer ---
$levels_to_delete = ['2', '3'];

// --- Section 1 : Authentification (identique aux autres pages admin) ---
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: delete_modules_2_3.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_SESSION['login_attempts'] < 3) {
        $login = $_POST['identifiant'];
        $pass = $_POST['mot_de_passe'];
        try {
            $pdo_login = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo_login->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo_login->prepare("SELECT passconn FROM stationl1 WHERE loginconn = ?");
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

// --- Section 2 : Comptage et suppression ---
$message = '';
$counts = [];

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Traitement de la suppression (en transaction : tout ou rien)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_modules'])) {
            $placeholders = implode(',', array_fill(0, count($levels_to_delete), '?'));
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM GSDatabase WHERE level IN ($placeholders)");
            $stmt->execute($levels_to_delete);
            $deleted = $stmt->rowCount();
            $pdo->commit();
            $message = "<div class='message success'><strong>$deleted</strong> question(s) supprimée(s) pour les modules "
                . htmlspecialchars(implode(' et ', $levels_to_delete)) . ".</div>";
        }

        // Comptage actuel des questions par niveau (pour l'aperçu)
        $placeholders = implode(',', array_fill(0, count($levels_to_delete), '?'));
        $stmt = $pdo->prepare("SELECT level, COUNT(*) AS nb FROM GSDatabase WHERE level IN ($placeholders) GROUP BY level");
        $stmt->execute($levels_to_delete);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['level']] = $row['nb'];
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        $message = "<div class='error'>Erreur de base de données : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$total_to_delete = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Supprimer les modules 2 et 3</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 600px; margin: 5vh auto; }
        h1, h2 { color: #5a5a5a; text-align: center; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1rem; }
        button { color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; }
        button[name="login"] { background-color: #007bff; }
        button[name="login"]:hover { background-color: #0056b3; }
        button[name="delete_modules"] { background-color: #dc3545; }
        button[name="delete_modules"]:hover { background-color: #c82333; }
        button[disabled] { background-color: #adb5bd; cursor: not-allowed; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .message.success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; text-align: center; margin-bottom: 1rem; }
        .warning { color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 1rem; border-radius: 5px; text-align: center; margin: 1rem 0; }
        .summary { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .summary th, .summary td { border: 1px solid #ddd; padding: 0.6rem 1rem; text-align: center; }
        .summary th { background-color: #f4f4f9; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
        .logout-form button:hover { background-color: #5a6268; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>

    <div class="container">
        <form action="delete_modules_2_3.php" method="post" class="logout-form">
            <button type="submit" name="logout">Déconnexion</button>
        </form>

        <a href="gestion_gsdatabase.php" class="back-link">&larr; Retour à la gestion des questions</a>
        <h1>Supprimer les modules 2 et 3</h1>

        <?php echo $message; ?>

        <h2>Questions actuellement présentes</h2>
        <table class="summary">
            <tr><th>Module (level)</th><th>Nombre de questions</th></tr>
            <?php foreach ($levels_to_delete as $lvl) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($lvl); ?></td>
                    <td><?php echo (int)($counts[$lvl] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr><th>Total</th><th><?php echo (int)$total_to_delete; ?></th></tr>
        </table>

        <?php if ($total_to_delete > 0) : ?>
            <div class="warning">
                Cette action supprimera définitivement <strong><?php echo (int)$total_to_delete; ?></strong>
                question(s) de la table <code>GSDatabase</code>. Cette opération est irréversible.
            </div>
            <form action="delete_modules_2_3.php" method="post"
                  onsubmit="return confirm('Confirmer la suppression définitive des modules 2 et 3 (<?php echo (int)$total_to_delete; ?> questions) ?');">
                <button type="submit" name="delete_modules">Supprimer définitivement les modules 2 et 3</button>
            </form>
        <?php else : ?>
            <p style="text-align:center;">Aucune question à supprimer pour les modules 2 et 3.</p>
        <?php endif; ?>
    </div>

<?php elseif ($_SESSION['login_attempts'] >= 3) : ?>
    <div class="container">
        <h1>Accès Bloqué</h1>
        <p class="error">Votre accès est bloqué après 3 tentatives infructueuses.</p>
    </div>
<?php else : ?>
    <div class="container">
        <h1>Accès Administrateur</h1>
        <?php if (isset($login_error)) : ?>
            <p class="error"><?php echo $login_error; ?></p>
            <p style="text-align:center;">Tentative <?php echo $_SESSION['login_attempts']; ?> sur 3.</p>
        <?php endif; ?>
        <form action="delete_modules_2_3.php" method="post">
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

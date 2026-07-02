<?php
// Configuration, session durcie, anti-force-brute, CSRF : tout est dans auth.php
require_once 'auth.php';
$login_error = admin_handle_auth();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Visualiseur de la Base de Données</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 95%; margin: auto; }
        .login-container { max-width: 500px; margin-top: 10vh; }
        h1 { color: #5a5a5a; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; word-break: break-word; }
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
        .tabs { margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .tabs a { padding: 10px 15px; text-decoration: none; color: #007bff; border: 1px solid transparent; border-bottom: none; border-radius: 5px 5px 0 0; }
        .tabs a.active { font-weight: bold; border-color: #ddd; border-bottom: 2px solid #fff; background-color: #fff; position: relative; top: 2px;}
    </style>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>

        <div class="container">
            <form action="" method="post" class="logout-form">
                <?php echo csrf_input(); ?>
                <button type="submit" name="logout">Déconnexion</button>
            </form>

            <h1>Visualiseur de la Base de Données</h1>

            <?php
            // ================== LES CHANGEMENTS COMMENCENT ICI ==================
            try {
                $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // --- MODIFICATION 1 : Ajout d'une logique pour le troisième tableau ---
                $current_view_param = $_GET['view'] ?? 'results'; // 'results' par défaut

                if ($current_view_param === 'questions') {
                    $view = 'GSDatabase';
                } elseif ($current_view_param === 'texts') {
                    $view = 'GSDatabaseT';
                } else {
                    $view = 'GSDatabaseR';
                }

                // --- MODIFICATION 2 : Ajout d'un bouton pour un nouvel onglet ---
                echo '<div class="tabs">';
                echo '<a href="?view=results" class="' . ($view === 'GSDatabaseR' ? 'active' : '') . '">Voir les Résultats (GSDatabaseR)</a>';
                echo '<a href="?view=questions" class="' . ($view === 'GSDatabase' ? 'active' : '') . '">Voir les Questions (GSDatabase)</a>';
                echo '<a href="?view=texts" class="' . ($view === 'GSDatabaseT' ? 'active' : '') . '">Voir les Textes (GSDatabaseT)</a>'; // Nouvelle chaîne
                echo '</div>';

                echo "<h2>Affichage de la table : `$view`</h2>";

                // Obtenir les noms des colonnes
                $stmt_cols = $pdo->query("DESCRIBE `$view`");
                $columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

                // Nous obtenons toutes les données du tableau
                $stmt_data = $pdo->query("SELECT * FROM `$view` ORDER BY id DESC");
                $results = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

                if (count($results) > 0) {
                    echo "<table>";
                    // Création dynamique d'un en-tête de tableau
                    echo "<thead><tr>";
                    foreach ($columns as $col) {
                        echo "<th>" . htmlspecialchars($col) . "</th>";
                    }
                    echo "</tr></thead>";

                    // Affichage dynamique des chaînes
// NOUVEAU CODE CORRIGÉ
echo "<tbody>";
foreach ($results as $row) {
    echo "<tr>";
    foreach ($columns as $col) {
        // Échappement obligatoire : ces données proviennent des réponses au questionnaire (XSS stocké).
        echo "<td>" . htmlspecialchars((string) $row[$col], ENT_QUOTES, 'UTF-8') . "</td>";
    }
    echo "</tr>";
}
echo "</tbody></table>";
                } else {
                    echo "<p>Aucun résultat trouvé dans la table `$view`.</p>";
                }

            } catch (PDOException $e) {
                error_log('[db_viewer] ' . $e->getMessage());
                echo "<p class='error'>Erreur de connexion à la base de données.</p>";
            }
            // =================== LES CHANGEMENTS S'ARRÊTENT ICI ===================
            ?>
        </div>

    <?php elseif (admin_login_throttled()['blocked']) : ?>

        <div class="container login-container">
            <h1>Accès Bloqué</h1>
            <p class="error">Trop de tentatives de connexion. Veuillez réessayer plus tard.</p>
        </div>

    <?php else : ?>

        <div class="container login-container">
            <h1>Accès Administrateur</h1>
            <?php if (isset($login_error) && $login_error) : ?>
                <p class="error"><?php echo htmlspecialchars($login_error); ?></p>
            <?php endif; ?>
            <form action="" method="post">
                <?php echo csrf_input(); ?>
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

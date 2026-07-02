<?php
// Configuration, session durcie, anti-force-brute, CSRF : tout est dans auth.php
require_once 'auth.php';
$login_error = admin_handle_auth();

// Logique de suppression des données
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    admin_require_csrf();
    if (admin_is_logged_in()) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Exécution du la commande TRUNCATE
            $pdo->exec("TRUNCATE TABLE GSDatabaseR");

            $message = "<p class='success'>Succès ! Tous les enregistrements de la table <strong>GSDatabaseR</strong> ont été supprimés.</p>";
        } catch (PDOException $e) {
            error_log('[deleteResults] ' . $e->getMessage());
            $message = "<p class='error'>Erreur lors de la suppression des données.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nettoyage de la table des résultats</title>
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
        <?php if (admin_is_logged_in()) : ?>

            <form action="" method="post" class="logout-form">
                <?php echo csrf_input(); ?>
                <button type="submit" name="logout">Déconnexion</button>
            </form>

            <h1>Nettoyage de la table des résultats</h1>

            <?php if ($message) echo $message; ?>

            <div class="warning">
                <strong>ATTENTION !</strong> Cette action supprimera de manière irréversible <strong>TOUS</strong> les enregistrements de la table <code>GSDatabaseR</code>. L'ID des enregistrements sera réinitialisé à 1.
            </div>

            <form action="" method="post" onsubmit="return confirm('Êtes-vous absolument certain de vouloir supprimer tous les résultats ? Cette action est irréversible.');">
                <?php echo csrf_input(); ?>
                <button type="submit" name="confirm_delete" class="delete-button">Oui, je suis sûr, supprimer tous les résultats</button>
            </form>

        <?php elseif (admin_login_throttled()['blocked']) : ?>
            <h1>Accès bloqué</h1>
            <p class="error">Trop de tentatives de connexion. Veuillez réessayer plus tard.</p>
        <?php else : ?>
            <h1>Connexion administrateur</h1>
             <?php if (isset($login_error) && $login_error) : ?><p class="error"><?php echo htmlspecialchars($login_error); ?></p><?php endif; ?>
            <form action="" method="post" style="text-align: left;">
                <?php echo csrf_input(); ?>
                <div style="margin-bottom: 1rem;">
                    <label for="identifiant">Identifiant :</label>
                    <input type="text" id="identifiant" name="identifiant" required style="width: 100%; padding: 0.5rem; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label for="mot_de_passe">Mot de passe :</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required style="width: 100%; padding: 0.5rem; box-sizing: border-box;">
                </div>
                <button type="submit" name="login">Se connecter</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

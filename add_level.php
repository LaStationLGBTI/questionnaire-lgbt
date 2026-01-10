<?php
// On inclut la configuration et on démarre la session
require_once 'conf.php';
session_start();

// --- SECTION 1 : Logique d'entrée et de sortie (copié depuis gestion_gsdatabase.php) ---

$login_error = null;

// Initialisation du compteur de tentatives de connexion
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Traitement de la déconnexion
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Traitement de l'entrée
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
                header('Location: ' . $_SERVER['PHP_SELF']); // Recharger pour supprimer le POST
                exit();
            } else {
                $_SESSION['login_attempts']++;
                $login_error = "Identifiant ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $login_error = "Erreur de connexion : " . $e->getMessage();
        }
    }
}


// --- SECTION 2 : Logique de gestion des niveaux (uniquement si l'utilisateur est connecté) ---

$message = '';
$action = 'menu'; // Valeur par défaut
$id = null;
$pdo = null;

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {

    // --- NOUVEAU : Gestion des messages de redirection ---
    if (isset($_GET['created'])) {
        $message = "<p class='success'>Niveau créé avec succès !</p>";
    }
    if (isset($_GET['updated'])) {
        $message = "<p class='success'>Niveau mis à jour avec succès !</p>";
    }
    if (isset($_GET['deleted'])) {
        $message = "<p class='success'>Niveau (et toutes ses questions associées) supprimé avec succès !</p>";
    }

    // --- Routage basé sur l'action ---
    $action = $_GET['action'] ?? 'menu';
    $id = $_REQUEST['id'] ?? null; // 'id' sera notre 'level'

    // Connexion PDO globale
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("<p class='error' style='padding: 20px; max-width: 800px; margin: auto;'>Erreur de connexion à la base de données : " . $e->getMessage() . "</p>");
    }

    // --- LOGIQUE DE TRAITEMENT POST (Création, Mise à jour, Suppression) ---

    // 1. Traitement de la création du niveau
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_level'])) {
        $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
        $titre = trim($_POST['titre']);
        $text = trim($_POST['text']);

        if ($level && !empty($titre)) {
            try {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM GSDatabaseT WHERE level = ?");
                $stmt_check->execute([$level]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = "<p class='error'>Erreur : Le niveau numéro $level existe déjà.</p>";
                    $action = 'create';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO GSDatabaseT (level, titre, text) VALUES (?, ?, ?)");
                    $stmt->execute([$level, $titre, $text]);
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?action=menu&created=true');
                    exit();
                }
            } catch (PDOException $e) {
                $message = "<p class='error'>Erreur de base de données : " . $e->getMessage() . "</p>";
                $action = 'create';
            }
        } else {
            $message = "<p class='error'>Veuillez saisir un numéro de niveau et un titre valides.</p>";
            $action = 'create';
        }
    }

    // 2. Traitement de la mise à jour du niveau
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_level'])) {
        $level_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $titre = trim($_POST['titre']);
        $text = trim($_POST['text']);

        if ($level_id && !empty($titre)) {
            try {
                $stmt = $pdo->prepare("UPDATE GSDatabaseT SET titre = ?, text = ? WHERE level = ?");
                $stmt->execute([$titre, $text, $level_id]);
                header('Location: ' . $_SERVER['PHP_SELF'] . '?action=menu&updated=true');
                exit();
            } catch (PDOException $e) {
                $message = "<p class='error'>Erreur lors de la mise à jour : " . $e->getMessage() . "</p>";
            }
        } else {
            $message = "<p class='error'>Le titre ne peut pas être vide.</p>";
        }
    }

    // 3. Traitement de la suppression du niveau
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_level'])) {
        $level_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($level_id) {
            try {
                $pdo->beginTransaction();
                $stmt_q = $pdo->prepare("DELETE FROM GSDatabase WHERE level = ?");
                $stmt_q->execute([$level_id]);
                $stmt_t = $pdo->prepare("DELETE FROM GSDatabaseT WHERE level = ?");
                $stmt_t->execute([$level_id]);
                $pdo->commit();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?action=menu&deleted=true');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "<p class='error'>Erreur lors de la suppression : " . $e->getMessage() . "</p>";
            }
        }
    }
} // --- Fin du bloc "if (is_logged_in)" ---

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Niveaux</title>
    <style>
        .admin-nav {
    text-align: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #eee;
}
.admin-nav .nav-button {
    background-color: #17a2b8; /* Turquoise pour se démarquer */
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.9rem;
    transition: background-color 0.3s;
}
.admin-nav .nav-button:hover {
    background-color: #138496;
}
        /* Styles de base (conservés) */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; line-height: 1.6; padding: 20px; margin: 0; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="number"], textarea, select { width: 100%; padding: 0.8rem; border-radius: 5px; border: 1px solid #ddd; box-sizing: border-box; }
        input[type="number"][readonly] { background-color: #eee; cursor: not-allowed; }
        textarea { resize: vertical; min-height: 150px; }

        /* Styles de boutons (conservés) */
        button, .button-link { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; }
        button:hover, .button-link:hover { background-color: #0056b3; }

        /* Styles de messages (conservés) */
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; }

        /* Styles de gestion (conservés) */
        .action-menu { text-align: center; margin: 2rem 0; }
        .action-menu .button-link { width: auto; margin: 0 10px; padding: 1rem 2rem; }
        .back-link { display: inline-block; margin-bottom: 1.5rem; color: #007bff; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { text-decoration: underline; }
        .form-actions { display: flex; gap: 1rem; justify-content: space-between; margin-top: 2rem; }
        .form-actions button { width: auto; flex-grow: 1; }
        .form-actions button[name="delete_level"] { background-color: #dc3545; }
        .form-actions button[name="delete_level"]:hover { background-color: #c82333; }

        /* --- NOUVEAUX STYLES (de gestion_gsdatabase.php) --- */
        .login-container { max-width: 500px; margin-top: 10vh; }
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

        <h1>Panneau de Gestion des Niveaux</h1>
        <?php if ($message) echo $message; // Affichage global des messages ?>

        <?php
        // VUE 1: Menu principal
        if ($action === 'menu'):
        ?>
            <div class="admin-nav">
    <a href="gestion_gsdatabase.php" class="nav-button">Aller à la Gestion des Questions &rarr;</a>
</div>
            <div class="action-menu">
                <h2>Que souhaitez-vous faire ?</h2>
                <a href="?action=create" class="button-link">Créer un nouveau niveau</a>
                <a href="?action=edit" class="button-link">Modifier ou Supprimer un niveau</a>
            </div>

        <?php
        // VUE 2: Formulaire de création
        elseif ($action === 'create'):
        ?>
            <a href="?action=menu" class="back-link">&larr; Retour au menu</a>
            <h2>Créer un nouveau niveau</h2>
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

        <?php
        // VUE 3: Sélecteur pour l'édition/suppression
        elseif ($action === 'edit' && !$id):
        ?>
            <a href="?action=menu" class="back-link">&larr; Retour au menu</a>
            <h2>Étape 1 : Choisir un niveau à Modifier/Supprimer</h2>
            <?php
                $existing_levels = [];
                try {
                    $stmt_levels = $pdo->query("SELECT level, titre FROM GSDatabaseT ORDER BY level ASC");
                    $existing_levels = $stmt_levels->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    echo "<p class='error'>Impossible de charger la liste des niveaux existants.</p>";
                }
            ?>
            <form action="" method="GET">
                <input type="hidden" name="action" value="edit">
                <div class="form-group">
                    <label for="id">Sélectionnez un niveau :</label>
                    <select id="id" name="id" required>
                        <option value="">-- Choisissez --</option>
                        <?php foreach ($existing_levels as $lvl): ?>
                            <option value="<?= htmlspecialchars($lvl['level']) ?>">
                                <?= htmlspecialchars($lvl['level']) ?> - <?= htmlspecialchars($lvl['titre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Charger ce niveau</button>
            </form>

        <?php
        // VUE 4: Formulaire d'édition/suppression
        elseif ($action === 'edit' && $id):
            $level_data = null;
            if($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM GSDatabaseT WHERE level = ?");
                $stmt->execute([$id]);
                $level_data = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$level_data):
                echo "<p class='error'>Le niveau avec l'ID $id n'a pas été trouvé.</p>";
                echo '<a href="?action=edit" class="back-link">&larr; Essayer un autre ID</a>';
            else:
        ?>
            <a href="?action=edit" class="back-link">&larr; Retour au choix du niveau</a>
            <h2>Modifier/Supprimer le niveau : <?= htmlspecialchars($level_data['titre']) ?></h2>

            <form action="" method="post">
                <input type="hidden" name="id" value="<?= htmlspecialchars($level_data['level']) ?>">
                <div class="form-group">
                    <label for="level_display">Numéro du niveau :</label>
                    <input type="number" id="level_display" value="<?= htmlspecialchars($level_data['level']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="titre">Titre du niveau :</label>
                    <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($level_data['titre']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="text">Description/Texte pour le niveau (supporte le HTML) :</label>
                    <textarea id="text" name="text" rows="8"><?= htmlspecialchars($level_data['text']) ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_level">Mettre à jour</button>
                    <button type="submit" name="delete_level"
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce niveau ?\n\nATTENTION : Toutes les questions associées à ce niveau (dans GSDatabase) seront également supprimées de façon irréversible.');">
                        Supprimer le niveau
                    </button>
                </div>
            </form>
        <?php
            endif; // fin du if ($level_data)
        endif; // fin du routage $action
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
            <p style="text-align:center;">Tentative <?php echo $_SESSION['login_attempts']; ?> sur 3.</p>
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

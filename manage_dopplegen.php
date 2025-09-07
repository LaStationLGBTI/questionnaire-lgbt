<?php
// Inclure la configuration et démarrer la session
require_once 'conf.php';
session_start();

$uploadDir = 'dopplegenImages/';
$message = '';

// --- Logique de connexion et déconnexion ---
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
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

// ---- Logique de la page (UNIQUEMENT SI CONNECTÉ) ----
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- LOGIQUE D'AJOUT (PANNEAU GAUCHE) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
        $category = trim($_POST['category']);
        $name = trim($_POST['name']);
        $imageFile = $_FILES['image'];

        $_SESSION['last_category'] = $category; // Запоминаем категорию

        if (!empty($category) && !empty($name) && $imageFile['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
            $safeFilename = uniqid('img_', true) . '.' . strtolower($fileExtension);
            $targetFile = $uploadDir . $safeFilename;
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array(strtolower($fileExtension), $allowedTypes)) {
                if (move_uploaded_file($imageFile['tmp_name'], $targetFile)) {
                    $stmt = $pdo->prepare("INSERT INTO dopplegen (category, name, image_name) VALUES (?, ?, ?)");
                    $stmt->execute([$category, $name, $safeFilename]);
                    $message = "<p class='msg success'>Entrée ajoutée avec succès !</p>";
                } else {
                    $message = "<p class='msg error'>Erreur lors du téléchargement de l'image.</p>";
                }
            } else {
                $message = "<p class='msg error'>Type de fichier non autorisé.</p>";
            }
        } else {
            $message = "<p class='msg error'>Veuillez remplir tous les champs et choisir une image.</p>";
        }
    }

    // --- LOGIQUE DE SUPPRESSION (PANNEAU DROIT) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
        $id_to_delete = $_POST['delete_id'];
        $stmt_find = $pdo->prepare("SELECT image_name FROM dopplegen WHERE id = ?");
        $stmt_find->execute([$id_to_delete]);
        $entry = $stmt_find->fetch();

        if ($entry) {
            $imagePath = $uploadDir . $entry['image_name'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            $stmt_delete = $pdo->prepare("DELETE FROM dopplegen WHERE id = ?");
            $stmt_delete->execute([$id_to_delete]);
            $message = "<p class='msg success'>Entrée supprimée avec succès.</p>";
        }
    }

    // --- LOGIQUE D'AFFICHAGE (PANNEAU DROIT) ---
    $categories_list_stmt = $pdo->query("SELECT DISTINCT category FROM dopplegen ORDER BY category ASC");
    $categories_list = $categories_list_stmt->fetchAll(PDO::FETCH_ASSOC);

    $selected_category = '';
    $entries = [];
    $category_count_message = ''; // Новая переменная для сообщения о количестве

    if (isset($_GET['category_select']) && !empty($_GET['category_select'])) {
        $selected_category = $_GET['category_select'];
        $stmt_entries = $pdo->prepare("SELECT id, name, image_name FROM dopplegen WHERE category = ? ORDER BY name ASC");
        $stmt_entries->execute([$selected_category]);
        $entries = $stmt_entries->fetchAll(PDO::FETCH_ASSOC);

        // --- НОВЫЙ БЛОК: Логика подсчета для Dopplegen ---
        $count = count($entries);
        $tiers = [7, 13, 21, 31, 57]; // Уровни игры
        $needed = 0;
        $next_tier = 0;

        foreach ($tiers as $tier) {
            if ($count < $tier) {
                $needed = $tier - $count;
                $next_tier = $tier;
                break; // Нашли следующую цель
            }
        }

        if ($count == 0) {
            $category_count_message = "<p class='error'>Catégorie \"".htmlspecialchars($selected_category)."\". <strong>Total: 0</strong>. <br>Il faut <strong>7</strong> symboles pour le premier niveau de jeu.</p>";
        } elseif ($needed > 0) {
            // Не хватает до следующего уровня
            $category_count_message = "<p class='info'>Catégorie \"".htmlspecialchars($selected_category)."\". <strong>Total: $count</strong>. <br>Il manque <strong>$needed</strong> symboles pour atteindre le prochain palier (<strong>$next_tier</strong> symboles).</p>";
        } elseif ($count >= 57) {
            // Достигнут максимальный уровень
            $category_count_message = "<p class='success'>Catégorie \"".htmlspecialchars($selected_category)."\". <strong>Total: $count</strong>. <br>Vous avez assez de symboles (57+) pour le jeu maximal !</p>";
        } else {
            // Количество точно совпадает с одним из уровней (7, 13, 21 or 31)
            $category_count_message = "<p class='success'>Catégorie \"".htmlspecialchars($selected_category)."\". <strong>Total: $count</strong>. <br>C'est un compte parfait pour un jeu !</p>";
        }
        // --- КОНЕЦ НОВОГО БЛОКА ---
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Dopplegen</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; box-sizing: border-box; }
        h1, h2 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        ul { margin-top: 5px; }
        li { margin-bottom: 2px; }
        .main-container { display: flex; flex-wrap: wrap; gap: 30px; }
        .panel { background: #fff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; }
        .panel-left { flex: 1; min-width: 300px; }
        .panel-right { flex: 2; min-width: 450px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"], select, input[type="file"] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1rem;
        }
        button {
            background-color: #007bff; color: white; padding: 10px 18px; border: none; border-radius: 5px;
            font-size: 1rem; cursor: pointer; transition: background-color 0.3s;
        }
        button:hover { background-color: #0056b3; }
        .delete-button { background-color: #dc3545; }
        .delete-button:hover { background-color: #c82333; }
        .logout-button { background-color: #6c757d; position: absolute; top: 20px; right: 20px; }
        .msg { padding: 12px; border-radius: 5px; margin-top: 15px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .login-container { max-width: 400px; margin: 50px auto; padding: 2rem; background: #fff; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        
        .results-table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        .results-table th, .results-table td {
            border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle;
        }
        .results-table th { background-color: #f9f9f9; }
        .results-table img {
            max-width: 100px; max-height: 100px; height: auto; width: auto; border-radius: 4px;
        }
        .results-table form { margin: 0; }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
    
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Déconnexion</button>
        </form>
        
        <h1>Panneau de gestion "Dopplegen"</h1>
        <?php if ($message) echo $message; // Affiche les messages de succès ou d'erreur ?>

        <div class="info" style="font-size: 0.9em; line-height: 1.5; padding: 15px; margin-bottom: 20px;">
            <strong>Règles du générateur de jeu :</strong> Le générateur (sur la page 'generate_dopplegen.php') crée un jeu "parfait". Pour fonctionner, il a besoin d'un nombre spécifique de symboles uniques dans une catégorie :
            <ul style="padding-left: 20px;">
                <li><strong>Jeu Ordre 2 :</strong> 7 symboles requis (Crée 7 cartes de 3 symboles)</li>
                <li><strong>Jeu Ordre 3 :</strong> 13 symboles requis (Crée 13 cartes de 4 symboles)</li>
                <li><strong>Jeu Ordre 4 :</strong> 21 symboles requis (Crée 21 cartes de 5 symboles)</li>
                <li><strong>Jeu Ordre 5 :</strong> 31 symboles requis (Crée 31 cartes de 6 symboles)</li>
                <li><strong>Jeu Ordre 7 :</strong> 57 symboles requis (Crée 57 cartes de 8 symboles)</li>
            </ul>
            Si vous sélectionnez une catégorie avec 40 symboles, le générateur créera le jeu d'ordre 5 (utilisant 31 de vos 40 symboles).
        </div>
        <div class="main-container">
            
            <div class="panel panel-left">
                <h2>Ajouter une nouvelle entrée</h2>
                <form action="manage_dopplegen.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="category">Catégorie :</label>
                        <input type="text" id="category" name="category" required 
                               value="<?= htmlspecialchars(isset($_SESSION['last_category']) ? $_SESSION['last_category'] : '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="name">Nom / Titre :</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Image :</label>
                        <input type="file" id="image" name="image" required accept="image/png, image/jpeg, image/gif, image/webp">
                    </div>
                    <button type="submit" name="add_entry">Ajouter</button>
                </form>
            </div>

            <div class="panel panel-right">
                <h2>Gérer les entrées existantes</h2>
                
                <form action="manage_dopplegen.php" method="GET">
                    <div class="form-group">
                        <label for="category_select">Filtrer par catégorie :</label>
                        <select id="category_select" name="category_select">
                            <option value="">-- Choisissez une catégorie --</option>
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($cat['category'] == $selected_category) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Afficher</button>
                </form>

                <?php if (!empty($category_count_message)) echo $category_count_message; ?>
                <?php if (!empty($selected_category) && empty($entries)): ?>
                    <?php if (empty($category_count_message)): // Показать это, только если сообщение о счете еще не отображено (т.е. категория выбрана, но пуста) ?>
                        <p style="margin-top: 20px;">Aucune entrée trouvée pour cette catégorie.</p>
                    <?php endif; ?>
                <?php elseif (!empty($entries)): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($uploadDir . $entry['image_name']) ?>" alt="<?= htmlspecialchars($entry['name']) ?>">
                                </td>
                                <td><?= htmlspecialchars($entry['name']) ?></td>
                                <td>
                                    <form action="manage_dopplegen.php?category_select=<?= urlencode($selected_category) ?>" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cette entrée ?');">
                                        <input type="hidden" name="delete_id" value="<?= $entry['id'] ?>">
                                        <button type="submit" name="delete_entry" class="delete-button">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($_SESSION['login_attempts'] >= 3) : ?>
        <div class="login-container">
            <h1>Accès Bloqué</h1>
            <p class="error">Vous avez échoué 3 tentatives de connexion. Accès verrouillé.</p>
        </div>
    <?php else : ?>
        <div class="login-container">
            <h1>Connexion Administrateur</h1>
            <?php if (isset($login_error)) : ?><p class="error"><?php echo $login_error; ?></p><?php endif; ?>
            <form action="" method="post">
                <div class="form-group">
                    <label for="identifiant">Identifiant :</label>
                    <input type="text" id="identifiant" name="identifiant" required>
                </div>
                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe :</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                </div>
                <button type="submit" name="login">Se connecter</button>
            </form>
        </div>
    <?php endif; ?>

</body>
</html>

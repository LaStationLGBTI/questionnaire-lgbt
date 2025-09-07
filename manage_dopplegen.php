<?php
require_once 'conf.php';
session_start();

$uploadDir = 'dopplegenImages/';
$message = '';
$pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- LOGIQUE D'AJOUT (PANNEAU GAUCHE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $category = trim($_POST['category']);
    $name = trim($_POST['name']);
    $imageFile = $_FILES['image'];

    if (!empty($category) && !empty($name) && $imageFile['error'] === UPLOAD_ERR_OK) {
        
        // Créer un nom de fichier unique pour éviter les conflits
        $fileExtension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        $safeFilename = uniqid('img_', true) . '.' . strtolower($fileExtension);
        $targetFile = $uploadDir . $safeFilename;

        // Vérifier le type de fichier (sécurité basique)
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            
            if (move_uploaded_file($imageFile['tmp_name'], $targetFile)) {
                // Si le fichier est téléchargé, insérer dans la BDD
                $stmt = $pdo->prepare("INSERT INTO dopplegen (category, name, image_name) VALUES (?, ?, ?)");
                $stmt->execute([$category, $name, $safeFilename]);
                $message = "<p class='msg success'>Entrée ajoutée avec succès !</p>";
            } else {
                $message = "<p class='msg error'>Erreur lors du téléchargement de l'image.</p>";
            }
        } else {
            $message = "<p class='msg error'>Type de fichier non autorisé. (Autorisés: jpg, png, gif, webp)</p>";
        }
    } else {
        $message = "<p class='msg error'>Veuillez remplir tous les champs et choisir une image.</p>";
    }
}

// --- LOGIQUE DE SUPPRESSION (PANNEAU DROIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $id_to_delete = $_POST['delete_id'];
    
    // 1. Récupérer le nom de l'image pour la supprimer du serveur
    $stmt_find = $pdo->prepare("SELECT image_name FROM dopplegen WHERE id = ?");
    $stmt_find->execute([$id_to_delete]);
    $entry = $stmt_find->fetch();

    if ($entry) {
        $imagePath = $uploadDir . $entry['image_name'];
        if (file_exists($imagePath)) {
            unlink($imagePath); // Supprimer le fichier image
        }
        
        // 2. Supprimer l'entrée de la base de données
        $stmt_delete = $pdo->prepare("DELETE FROM dopplegen WHERE id = ?");
        $stmt_delete->execute([$id_to_delete]);
        $message = "<p class='msg success'>Entrée supprimée avec succès.</p>";
    }
}


// --- LOGIQUE D'AFFICHAGE (PANNEAU DROIT) ---
// Récupérer la liste de toutes les catégories distinctes pour le sélecteur
$categories_list_stmt = $pdo->query("SELECT DISTINCT category FROM dopplegen ORDER BY category ASC");
$categories_list = $categories_list_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les entrées pour la catégorie sélectionnée
$selected_category = '';
$entries = [];
if (isset($_GET['category_select']) && !empty($_GET['category_select'])) {
    $selected_category = $_GET['category_select'];
    $stmt_entries = $pdo->prepare("SELECT id, name, image_name FROM dopplegen WHERE category = ? ORDER BY name ASC");
    $stmt_entries->execute([$selected_category]);
    $entries = $stmt_entries->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Dopplegen</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        h1, h2 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .main-container { display: flex; flex-wrap: wrap; gap: 30px; }
        .panel { background: #fff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; }
        .panel-left { flex: 1; min-width: 300px; }
        .panel-right { flex: 2; min-width: 450px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: #555; }
        input[type="text"], select, input[type="file"] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1rem;
        }
        button {
            background-color: #007bff; color: white; padding: 10px 18px; border: none; border-radius: 5px;
            font-size: 1rem; cursor: pointer; transition: background-color 0.3s;
        }
        button:hover { background-color: #0056b3; }
        .delete-button { background-color: #dc3545; }
        .delete-button:hover { background-color: #c82333; }
        .msg { padding: 12px; border-radius: 5px; margin-top: 15px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Styles pour le tableau de droite */
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

    <h1>Panneau de gestion "Dopplegen"</h1>
    <?php if ($message) echo $message; // Affiche les messages de succès ou d'erreur ?>

    <div class="main-container">
        
        <div class="panel panel-left">
            <h2>Ajouter une nouvelle entrée</h2>
            <form action="manage_dopplegen.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="category">Catégorie :</label>
                    <input type="text" id="category" name="category" required>
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
                        <option value="">-- Toutes les catégories --</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($cat['category'] == $selected_category) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Afficher</button>
            </form>

            <?php if (!empty($selected_category) && empty($entries)): ?>
                <p style="margin-top: 20px;">Aucune entrée trouvée pour cette catégorie.</p>
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

</body>
</html>

<?php
require_once 'conf.php'; // Votre fichier de configuration DB

echo "<h1>Installation du module Dopplegen</h1>";

// --- 1. Création du dossier pour les images ---
$uploadDir = 'dopplegenImages/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "<p style='color:green;'>✅ Dossier '$uploadDir' créé avec succès.</p>";
    } else {
        echo "<p style='color:red;'>❌ Échec de la création du dossier '$uploadDir'. Veuillez le créer manuellement.</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Le dossier '$uploadDir' existe déjà.</p>";
}

// --- 2. Création de la table dans la base de données ---
try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Définition de la structure de la table
    $sql = "CREATE TABLE IF NOT EXISTS dopplegen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        image_name VARCHAR(255) NOT NULL,
        INDEX category_index (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Exécution de la requête
    $pdo->exec($sql);

    echo "<p style='color:green;'>✅ Table 'dopplegen' créée avec succès (ou vérifiée).</p>";
    echo "<h2>Installation terminée !</h2>";
    echo "<p>Vous pouvez maintenant supprimer ce fichier et utiliser 'manage_dopplegen.php'.</p>";

} catch (PDOException $e) {
    die("<p style='color:red;'>❌ Erreur de base de données : " . $e->getMessage() . "</p>");
}
?>

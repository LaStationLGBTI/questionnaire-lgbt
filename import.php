<?php
// Configuration, session durcie, anti-force-brute, CSRF : tout est dans auth.php
require_once 'auth.php';
$login_error = admin_handle_auth();

$import_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    admin_require_csrf();
    if (admin_is_logged_in()) {
        if (isset($_FILES['questionnaire_file']) && $_FILES['questionnaire_file']['error'] === UPLOAD_ERR_OK) {

            $file_tmp_path = $_FILES['questionnaire_file']['tmp_name'];
            if (strtolower(pathinfo($_FILES['questionnaire_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                $import_message = "<p class='error'>Erreur : Veuillez sélectionner un fichier au format CSV.</p>";
            } else {
                try {
                    // Langue cible de l'import : 'fr' (par défaut) ou 'en'.
                    // En mode 'en', les QCM sont insérés dans GSDatabase_en et reliés
                    // aux QCM français (GSDatabase.id) via la colonne fr_id, pour les statistiques.
                    $lang = (isset($_POST['lang']) && $_POST['lang'] === 'en') ? 'en' : 'fr';

                    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    ini_set('auto_detect_line_endings', TRUE);
                    $handle = fopen($file_tmp_path, 'r');

                    if (fgets($handle, 4) !== "\xef\xbb\xbf") {
                        rewind($handle);
                    }

                    fgetcsv($handle, 2000, ";"); // Nous ignorons le titre

                    $all_rows = [];
                    $level_to_check = null;
                    while (($data = fgetcsv($handle, 2000, ";")) !== FALSE) {
                        if (count($data) < 2 || empty($data[1])) {
                            continue;
                        }
                        $all_rows[] = $data;
                        if ($level_to_check === null) {
                            $level_to_check = trim($data[0]); // Niveau dans la PREMIÈRE colonne (indice 0)
                        }
                    }
                    fclose($handle);

                    if (!$level_to_check || empty($all_rows)) {
                         throw new Exception("Impossible de déterminer le niveau ou aucune donnée trouvée.");
                    }

                    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
                    $text  = isset($_POST['text'])  ? trim($_POST['text'])  : '';

                    if ($lang === 'en') {
                        // ================== IMPORT ANGLAIS (lié au français) ==================

                        // 0. Création des tables anglaises si absentes (sans FK pour éviter
                        //    tout échec lié à un moteur/charset différent ; voir sql_english_tables.sql
                        //    pour la version avec clé étrangère).
                        $pdo->exec("CREATE TABLE IF NOT EXISTS GSDatabase_en (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            fr_id INT NOT NULL,
                            level INT NOT NULL,
                            question TEXT, rep1 TEXT, rep2 TEXT, rep3 TEXT, rep4 TEXT, rep5 TEXT,
                            answer VARCHAR(20), qtype VARCHAR(20), expliq TEXT,
                            UNIQUE KEY uq_fr_id (fr_id), KEY idx_level (level)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
                        $pdo->exec("CREATE TABLE IF NOT EXISTS GSDatabaseT_en (
                            level INT NOT NULL PRIMARY KEY,
                            titre VARCHAR(255), text TEXT
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

                        // 1. Le module français doit déjà exister : on récupère ses ids
                        //    dans l'ordre d'insertion (= ordre du CSV) pour faire la liaison.
                        $stmt = $pdo->prepare("SELECT id FROM GSDatabase WHERE level = ? ORDER BY id ASC");
                        $stmt->execute([$level_to_check]);
                        $fr_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                        if (empty($fr_ids)) {
                            throw new Exception("Le module français du niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> n'existe pas dans GSDatabase. Importez d'abord le module français : le lien vers les QCM français est nécessaire pour les statistiques.");
                        }
                        if (count($fr_ids) !== count($all_rows)) {
                            throw new Exception("Liaison impossible : le module français du niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> contient <strong>" . count($fr_ids) . "</strong> questions, mais le CSV anglais en contient <strong>" . count($all_rows) . "</strong>. Les deux fichiers doivent avoir le même nombre de questions, dans le même ordre.");
                        }

                        // 2. Refus de ré-import du même niveau anglais
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM GSDatabase_en WHERE level = ?");
                        $stmt->execute([$level_to_check]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception("Le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> existe déjà dans GSDatabase_en (version anglaise).");
                        }

                        $pdo->beginTransaction();

                        // 3. Titre + description anglais (optionnel) dans GSDatabaseT_en
                        if ($titre !== '') {
                            $stmt_t = $pdo->prepare("SELECT COUNT(*) FROM GSDatabaseT_en WHERE level = ?");
                            $stmt_t->execute([$level_to_check]);
                            if ($stmt_t->fetchColumn() > 0) {
                                throw new Exception("Le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> existe déjà dans GSDatabaseT_en (titre/description anglais). Supprimez-le d'abord ou laissez le titre vide.");
                            }
                            $stmt_t = $pdo->prepare("INSERT INTO GSDatabaseT_en (level, titre, text) VALUES (?, ?, ?)");
                            $stmt_t->execute([$level_to_check, $titre, $text]);
                        }

                        // 4. Questions anglaises, chacune reliée à son équivalent français (fr_id)
                        $sql = "INSERT INTO GSDatabase_en (fr_id, level, question, rep1, rep2, rep3, rep4, rep5, answer, qtype, expliq)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        foreach ($all_rows as $i => $row) {
                            $stmt->execute([
                                $fr_ids[$i],                            // fr_id : lien vers la question FR
                                trim($row[0]) ?? null,                  // level
                                trim($row[1]) ?? null,                  // question
                                trim($row[2]) ?? null,                  // rep1
                                trim($row[3]) ?? null,                  // rep2
                                trim($row[4]) ?? null,                  // rep3
                                trim($row[5]) ?? 'null',                // rep4
                                trim($row[6]) ?? 'null',                // rep5
                                trim($row[7]) ?? null,                  // answer
                                trim($row[8]) ?? null,                  // qtype
                                isset($row[9]) ? trim($row[9]) : null   // expliq
                            ]);
                        }

                        $pdo->commit();
                        $level_msg = ($titre !== '') ? " Le titre et la description anglais ont également été créés dans GSDatabaseT_en." : "";
                        $import_message = "<p class='success'>Importation anglaise réussie. <strong>" . count($all_rows) . "</strong> questions ajoutées dans GSDatabase_en pour le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong>, reliées aux QCM français." . $level_msg . "</p>";

                        // =====================================================================
                    } else {
                        // ================== IMPORT FRANÇAIS (comportement d'origine) ==================

                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM GSDatabase WHERE level = ?");
                        $stmt->execute([$level_to_check]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception("Le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> existe déjà.");
                        }

                        $pdo->beginTransaction();

                        // 0. Création éventuelle du niveau (titre + description) dans GSDatabaseT
                        if ($titre !== '') {
                            $stmt_t = $pdo->prepare("SELECT COUNT(*) FROM GSDatabaseT WHERE level = ?");
                            $stmt_t->execute([$level_to_check]);
                            if ($stmt_t->fetchColumn() > 0) {
                                throw new Exception("Le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong> existe déjà dans GSDatabaseT (titre/description). Supprimez-le d'abord ou laissez le titre vide.");
                            }
                            $stmt_t = $pdo->prepare("INSERT INTO GSDatabaseT (level, titre, text) VALUES (?, ?, ?)");
                            $stmt_t->execute([$level_to_check, $titre, $text]);
                        }

                        // 1. Suppression de l'image et du son de la requête
                        $sql = "INSERT INTO GSDatabase (level, question, rep1, rep2, rep3, rep4, rep5, answer, qtype, expliq)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);

                        foreach ($all_rows as $row) {
                            // 2. Nous avons vérifié que le nombre de valeurs transmises (10) correspond à la requête
                            $stmt->execute([
                                trim($row[0]) ?? null,      // level
                                trim($row[1]) ?? null,      // question
                                trim($row[2]) ?? null,      // rep1
                                trim($row[3]) ?? null,      // rep2
                                trim($row[4]) ?? null,      // rep3
                                trim($row[5]) ?? 'null',    // rep4
                                trim($row[6]) ?? 'null',    // rep5
                                trim($row[7]) ?? null,      // answer
                                trim($row[8]) ?? null,      // qtype
                                isset($row[9]) ? trim($row[9]) : null // expliq (colonne J, optionnelle)
                            ]);
                        }

                        $pdo->commit();
                        $level_msg = ($titre !== '') ? " Le titre et la description du niveau ont également été créés dans GSDatabaseT." : "";
                        $import_message = "<p class='success'>Importation réussie. <strong>" . count($all_rows) . "</strong> questions ajoutées pour le niveau <strong>" . htmlspecialchars($level_to_check) . "</strong>." . $level_msg . "</p>";
                    }

                } catch (Exception $e) {
                    if(isset($pdo) && $pdo->inTransaction()){ $pdo->rollBack(); }
                    $import_message = "<p class='error'>Erreur : " . $e->getMessage() . "</p>";
                }
            }
        } else {
             $import_message = "<p class='error'>Erreur lors du téléchargement du fichier.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Importation de Questionnaire</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 2rem 3rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 100%; }
        h1 { color: #5a5a5a; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="file"] { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; }
        button:hover { background-color: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
        .logout-form button:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (admin_is_logged_in()) : ?>
            <form action="import.php" method="post" class="logout-form">
                <?php echo csrf_input(); ?>
                <button type="submit" name="logout">Déconnexion</button>
            </form>
            <h1>Importer un Questionnaire</h1>
            <?php if ($import_message) echo $import_message; ?>
            <form action="import.php" method="post" enctype="multipart/form-data" style="margin-top: 2rem;">
                <?php echo csrf_input(); ?>
                <div class="form-group">
                    <label for="lang">Langue du questionnaire :</label>
                    <select id="lang" name="lang" style="width:100%; padding:0.8rem; border:1px solid #ddd; border-radius:5px; box-sizing:border-box; font-family:inherit; font-size:1rem;">
                        <option value="fr" selected>Français (GSDatabase / GSDatabaseT)</option>
                        <option value="en">English — lié au français (GSDatabase_en / GSDatabaseT_en)</option>
                    </select>
                    <small style="display:block; margin-top:0.4rem; color:#666;">L'import anglais relie chaque QCM à son équivalent français (pour les statistiques). Le module français du même niveau doit déjà être importé.</small>
                </div>
                <div class="form-group">
                    <label for="titre">Titre du module (optionnel — crée le niveau dans GSDatabaseT) :</label>
                    <input type="text" id="titre" name="titre" placeholder="Ex : Violences sexuelles, sexistes, consentement et emprise">
                </div>
                <div class="form-group">
                    <label for="text">Description / Avertissement du module (HTML accepté, optionnel) :</label>
                    <textarea id="text" name="text" rows="8" style="width:100%; padding:0.8rem; border:1px solid #ddd; border-radius:5px; box-sizing:border-box; resize:vertical; font-family:inherit; font-size:1rem;"></textarea>
                </div>
                <div class="form-group">
                    <label for="questionnaire_file">Sélectionnez un fichier de questionnaire (.csv) :</label>
                    <input type="file" id="questionnaire_file" name="questionnaire_file" accept=".csv" required>
                </div>
                <button type="submit" name="upload">Importer le fichier</button>
            </form>
        <?php elseif (admin_login_throttled()['blocked']) : ?>
            <h1>Accès Bloqué</h1>
            <p class="error" name="session_bloquee">Trop de tentatives de connexion. Veuillez réessayer plus tard.</p>
        <?php else : ?>
            <h1>Accès Administrateur</h1>
            <?php if (isset($login_error) && $login_error) : ?>
                <p class="error"><?php echo htmlspecialchars($login_error); ?></p>
            <?php endif; ?>
            <form action="import.php" method="post">
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
        <?php endif; ?>
    </div>
</body>
</html>

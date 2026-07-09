<?php
// Panneau d'administration unifié : questions (GSDatabase), niveaux (GSDatabaseT),
// import CSV et visualiseur de base de données.
// Fusion de gestion_gsdatabase.php + add_level.php + import.php + db_viewer.php.
// Configuration, session durcie, anti-force-brute, CSRF : tout est dans auth.php
require_once 'auth.php';
$login_error = admin_handle_auth(basename($_SERVER['SCRIPT_NAME']), basename($_SERVER['SCRIPT_NAME']));

// Fallback si l'extension mbstring est absente du conteneur (troncature UTF-8 approximative,
// mais pas d'erreur fatale sur l'onglet Base de données).
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return strlen($s); }
    function mb_substr($s, $start, $length = null) { return $length === null ? substr($s, $start) : substr($s, $start, $length); }
}

$page = $_GET['page'] ?? 'questions';
if (!in_array($page, ['questions', 'levels', 'import', 'database'], true)) { $page = 'questions'; }

$message = '';        // Messages pour les onglets Questions / Niveaux
$import_message = ''; // Messages pour l'onglet Import
$action = $_GET['action'] ?? 'menu';
$id = $_REQUEST['id'] ?? null;
$pdo = null;

// Langue du contenu édité : fr (GSDatabase / GSDatabaseT) ou en (GSDatabase_en / GSDatabaseT_en).
// Permet de modifier / supprimer aussi le contenu anglais depuis les onglets Questions et Niveaux.
$qlang       = (isset($_REQUEST['qlang']) && $_REQUEST['qlang'] === 'en') ? 'en' : 'fr';
$q_table     = ($qlang === 'en') ? 'GSDatabase_en'  : 'GSDatabase';
$t_table     = ($qlang === 'en') ? 'GSDatabaseT_en' : 'GSDatabaseT';
$lang_suffix = ($qlang === 'en') ? ' (EN)' : '';
$lang_qs     = ($qlang === 'en') ? '&qlang=en' : '';

if (admin_is_logged_in()) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log('[admin] ' . $e->getMessage());
        die("<p style='padding: 20px; max-width: 800px; margin: auto;'>Erreur de connexion à la base de données.</p>");
    }

    // ==================== ONGLET QUESTIONS (GSDatabase) ====================
    try {
        // Création d'une nouvelle question (français uniquement : une question EN doit être
        // créée via l'import, qui la relie à sa question française par fr_id)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_question'])) {
            admin_require_csrf();
            if ($qlang === 'en') {
                $message = "<div class='error'>La création d'une question anglaise se fait via l'onglet Import CSV (liaison fr_id obligatoire).</div>";
            } else {
                $answer = ($_POST['qtype'] === 'qcm') ? $_POST['answer'] : 0;
                $sql = "INSERT INTO GSDatabase (level, question, rep1, rep2, rep3, rep4, rep5, answer, qtype, expliq) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['level'], $_POST['question'], $_POST['rep1'], $_POST['rep2'],
                    $_POST['rep3'], $_POST['rep4'], $_POST['rep5'], $answer,
                    $_POST['qtype'], $_POST['expliq']
                ]);
                $message = "<div class='message success'>Question ajoutée avec succès !</div>";
            }
        }

        // Mise à jour d'une question (FR ou EN selon qlang ; fr_id des questions EN jamais modifié)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
            admin_require_csrf();
            $answer = ($_POST['qtype'] === 'qcm') ? $_POST['answer'] : 0;
            $sql = "UPDATE `$q_table` SET level=?, question=?, rep1=?, rep2=?, rep3=?, rep4=?, rep5=?, answer=?, qtype=?, expliq=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['level'], $_POST['question'], $_POST['rep1'], $_POST['rep2'],
                $_POST['rep3'], $_POST['rep4'], $_POST['rep5'], $answer,
                $_POST['qtype'], $_POST['expliq'], $_POST['id']
            ]);
            $message = "<div class='message success'>Question" . $lang_suffix . " mise à jour avec succès !</div>";
        }

        // Suppression d'une question (FR ou EN selon qlang)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
            admin_require_csrf();
            $stmt = $pdo->prepare("DELETE FROM `$q_table` WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?page=questions&deleted=true' . $lang_qs);
            exit();
        }
    } catch (PDOException $e) {
        error_log('[admin/questions] ' . $e->getMessage());
        $message = "<div class='error'>Erreur de base de données.</div>";
    }

    if ($page === 'questions' && isset($_GET['deleted'])) {
        $message = "<div class='message success'>Question supprimée avec succès !</div>";
    }

    // ==================== ONGLET NIVEAUX (GSDatabaseT) ====================
    if ($page === 'levels') {
        if (isset($_GET['created'])) { $message = "<p class='success'>Niveau créé avec succès !</p>"; }
        if (isset($_GET['updated'])) { $message = "<p class='success'>Niveau mis à jour avec succès !</p>"; }
        if (isset($_GET['deleted'])) { $message = "<p class='success'>Niveau (et toutes ses questions associées) supprimé avec succès !</p>"; }
    }

    // Création du niveau
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_level'])) {
        admin_require_csrf();
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
                    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?page=levels&action=menu&created=true');
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

    // Mise à jour du niveau
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_level'])) {
        admin_require_csrf();
        $level_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $titre = trim($_POST['titre']);
        $text = trim($_POST['text']);

        if ($level_id && !empty($titre)) {
            try {
                $stmt = $pdo->prepare("UPDATE `$t_table` SET titre = ?, text = ? WHERE level = ?");
                $stmt->execute([$titre, $text, $level_id]);
                header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?page=levels&action=menu&updated=true' . $lang_qs);
                exit();
            } catch (PDOException $e) {
                $message = "<p class='error'>Erreur lors de la mise à jour : " . $e->getMessage() . "</p>";
            }
        } else {
            $message = "<p class='error'>Le titre ne peut pas être vide.</p>";
        }
    }

    // Suppression du niveau (et de ses questions de la même langue : la suppression du
    // niveau EN ne touche jamais le contenu français, et inversement)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_level'])) {
        admin_require_csrf();
        $level_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($level_id) {
            try {
                $pdo->beginTransaction();
                $stmt_q = $pdo->prepare("DELETE FROM `$q_table` WHERE level = ?");
                $stmt_q->execute([$level_id]);
                $stmt_t = $pdo->prepare("DELETE FROM `$t_table` WHERE level = ?");
                $stmt_t->execute([$level_id]);
                $pdo->commit();
                header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?page=levels&action=menu&deleted=true' . $lang_qs);
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "<p class='error'>Erreur lors de la suppression : " . $e->getMessage() . "</p>";
            }
        }
    }

    // ==================== ONGLET IMPORT CSV ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
        admin_require_csrf();
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

                    ini_set('auto_detect_line_endings', TRUE);
                    $handle = fopen($file_tmp_path, 'r');

                    if (fgets($handle, 4) !== "\xef\xbb\xbf") {
                        rewind($handle);
                    }

                    // Longueur 0 = illimitée : le champ #text (description HTML) peut dépasser 2000 octets
                    fgetcsv($handle, 0, ";"); // Nous ignorons le titre

                    $all_rows = [];
                    $level_to_check = null;
                    $csv_titre = null;
                    $csv_text  = null;
                    while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                        // Lignes méta optionnelles pour GSDatabaseT : "#titre;<titre>" et "#text;<description HTML>"
                        if (isset($data[0]) && strpos(trim($data[0]), '#') === 0) {
                            $meta_key = strtolower(substr(trim($data[0]), 1));
                            $meta_val = isset($data[1]) ? trim($data[1]) : '';
                            if ($meta_key === 'titre')     { $csv_titre = $meta_val; }
                            elseif ($meta_key === 'text')  { $csv_text  = $meta_val; }
                            continue;
                        }
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

                    // Le titre/texte du CSV (lignes #titre/#text) prime sur les champs du formulaire
                    $titre = ($csv_titre !== null && $csv_titre !== '') ? $csv_titre : (isset($_POST['titre']) ? trim($_POST['titre']) : '');
                    $text  = ($csv_text  !== null && $csv_text  !== '') ? $csv_text  : (isset($_POST['text'])  ? trim($_POST['text'])  : '');

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

    // ==================== ONGLET BASE DE DONNÉES ====================
    // Suppression d'une réponse (utile pour purger les réponses de test de GSDatabaseR)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_response'])) {
        admin_require_csrf();
        $resp_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($resp_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM GSDatabaseR WHERE id = ?");
                $stmt->execute([$resp_id]);
            } catch (PDOException $e) {
                error_log('[admin/database] ' . $e->getMessage());
            }
        }
        // Retour à la même vue, avec les filtres et la page courante conservés
        $back = ['page' => 'database', 'view' => 'results', 'resp_deleted' => 1];
        foreach (['level', 'q', 'p'] as $keep) {
            if (isset($_POST[$keep]) && $_POST[$keep] !== '') { $back[$keep] = $_POST[$keep]; }
        }
        header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?' . http_build_query($back));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration du Questionnaire</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; line-height: 1.6; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
        .login-container { max-width: 500px; margin-top: 10vh; }
        h1, h2 { color: #5a5a5a; text-align: center; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="number"], input[type="file"], textarea, select { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1rem; font-family: inherit; }
        input[type="number"][readonly] { background-color: #eee; cursor: not-allowed; }
        textarea { resize: vertical; min-height: 100px; }
        button, .button-link { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; }
        button:hover, .button-link:hover { background-color: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .success, .message.success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; margin-bottom: 1rem; text-align: center; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
        .logout-form button:hover { background-color: #5a6268; }
        .action-menu { text-align: center; margin: 2rem 0; }
        .action-menu .button-link { width: auto; margin: 0 10px; }
        .form-actions { display: flex; gap: 1rem; justify-content: space-between; margin-top: 2rem; }
        .form-actions button { width: auto; flex-grow: 1; }
        .form-actions button[name="delete_question"], .form-actions button[name="delete_level"] { background-color: #dc3545; }
        .form-actions button[name="delete_question"]:hover, .form-actions button[name="delete_level"]:hover { background-color: #c82333; }
        .hidden { display: none; }
        .radio-group label { display: inline-block; margin-right: 20px; font-weight: normal; }
        .back-link { display: inline-block; margin-bottom: 1.5rem; color: #007bff; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { text-decoration: underline; }
        .hint { display: block; margin-top: 0.4rem; color: #666; font-size: 0.85rem; font-weight: normal; }

        /* Onglets de navigation entre les sections */
        .admin-tabs { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #eee; flex-wrap: wrap; }
        .admin-tabs a { padding: 0.6rem 1.4rem; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 0.95rem; background-color: #e9ecef; color: #495057; transition: background-color 0.3s; }
        .admin-tabs a:hover { background-color: #dde1e5; }
        .admin-tabs a.active { background-color: #17a2b8; color: white; }

        /* Onglet Base de données : conteneur élargi, navigation par catégories, tableau */
        .container-wide { max-width: 95%; }
        .db-nav { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; margin-bottom: 1.5rem; }
        .db-group { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 0.8rem 1rem; min-width: 200px; }
        .db-group-title { display: block; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #868e96; margin-bottom: 0.6rem; text-align: center; }
        .db-group a { display: block; padding: 0.45rem 0.8rem; margin-bottom: 0.35rem; border-radius: 5px; text-decoration: none; font-size: 0.9rem; color: #495057; background: #fff; border: 1px solid #dee2e6; transition: background-color 0.2s; }
        .db-group a:hover { background-color: #e9ecef; }
        .db-group a.active { background-color: #17a2b8; border-color: #17a2b8; color: white; }
        .db-group a .count { float: right; background: #e9ecef; color: #495057; border-radius: 10px; padding: 0 0.5rem; font-size: 0.8rem; font-weight: bold; }
        .db-group a.active .count { background: rgba(255,255,255,0.25); color: white; }
        .db-toolbar { display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 0.5rem; }
        .db-toolbar .db-info { color: #666; font-size: 0.9rem; }
        .db-toolbar form { display: flex; gap: 0.5rem; align-items: center; margin: 0; }
        .db-toolbar select { width: auto; padding: 0.4rem 0.6rem; }
        .db-toolbar button { width: auto; padding: 0.4rem 1rem; font-size: 0.9rem; }
        table.db-table { width: 100%; border-collapse: collapse; margin-top: 1rem; table-layout: fixed; }
        .db-table th, .db-table td { border: 1px solid #ddd; padding: 10px; text-align: left; word-break: break-word; font-size: 0.9rem; }
        .db-table th { background-color: #f8f9fa; font-weight: bold; }
        .db-table tr:nth-child(even) { background-color: #f2f2f2; }
        .db-table .col-actions { width: 110px; text-align: center; }
        .btn-small { display: inline-block; padding: 0.3rem 0.7rem; font-size: 0.85rem; border-radius: 5px; background-color: #007bff; color: white; text-decoration: none; border: none; cursor: pointer; width: auto; }
        .btn-small:hover { background-color: #0056b3; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .cell-toggle { font-size: 0.8rem; color: #007bff; white-space: nowrap; }
        .db-pagination { display: flex; gap: 1.5rem; justify-content: center; align-items: center; margin-top: 1.5rem; font-size: 0.95rem; }
        .db-pagination a { color: #007bff; text-decoration: none; font-weight: bold; }
        .db-pagination a:hover { text-decoration: underline; }
        .db-pagination .disabled { color: #adb5bd; }
    </style>
</head>
<body>

<?php if (admin_is_logged_in()) : ?>

    <div class="container<?php echo $page === 'database' ? ' container-wide' : ''; ?>">
        <form action="" method="post" class="logout-form">
            <?php echo csrf_input(); ?>
            <button type="submit" name="logout">Déconnexion</button>
        </form>

        <h1>Administration du Questionnaire</h1>

        <div class="admin-tabs">
            <a href="?page=questions" class="<?php echo $page === 'questions' ? 'active' : ''; ?>">Questions</a>
            <a href="?page=levels" class="<?php echo $page === 'levels' ? 'active' : ''; ?>">Niveaux</a>
            <a href="?page=import" class="<?php echo $page === 'import' ? 'active' : ''; ?>">Import CSV</a>
            <a href="?page=database" class="<?php echo $page === 'database' ? 'active' : ''; ?>">Base de données</a>
        </div>

        <?php if ($page === 'questions') : ?>
            <?php echo $message; ?>

            <?php if ($action === 'menu') : ?>
                <div class="action-menu">
                    <h2>Gestion des questions — que souhaitez-vous faire ?</h2>
                    <a href="?page=questions&action=create" class="button-link">Créer une nouvelle question</a>
                    <a href="?page=questions&action=edit" class="button-link">Modifier ou Supprimer une question</a>
                </div>

            <?php elseif ($action === 'edit' && !$id) : ?>
                <a href="?page=questions" class="back-link">&larr; Retour au menu</a>
                <h2>Modifier une question</h2>
                <form action="" method="GET">
                    <input type="hidden" name="page" value="questions">
                    <input type="hidden" name="action" value="edit">
                    <div class="form-group">
                        <label for="qlang">Langue :</label>
                        <select id="qlang" name="qlang">
                            <option value="fr" <?php echo $qlang === 'fr' ? 'selected' : ''; ?>>Français (GSDatabase)</option>
                            <option value="en" <?php echo $qlang === 'en' ? 'selected' : ''; ?>>English (GSDatabase_en)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id">Entrez l'ID de la question à modifier :</label>
                        <input type="number" id="id" name="id" required>
                    </div>
                    <button type="submit">Rechercher et Modifier</button>
                </form>

            <?php elseif ($action === 'create' || ($action === 'edit' && $id)) :
                $question_data = null;
                $is_edit_mode = false;
                $question_found = true;
                if ($action === 'edit' && $id) {
                    $is_edit_mode = true;
                    $stmt = $pdo->prepare("SELECT * FROM `$q_table` WHERE id = ?");
                    $stmt->execute([$id]);
                    $question_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$question_data) { $question_found = false; }
                }
            ?>
                <?php if (!$question_found) : ?>
                    <div class='error'>Question avec l'ID <?php echo htmlspecialchars($id); ?> non trouvée dans <?php echo htmlspecialchars($q_table); ?>.</div>
                    <a href="?page=questions&action=edit<?php echo $lang_qs; ?>" class="back-link">Essayer un autre ID</a>
                <?php else : ?>
                    <a href="?page=questions" class="back-link">&larr; Retour au menu</a>
                    <h2><?php echo $is_edit_mode ? 'Modifier la question' . $lang_suffix : 'Créer une nouvelle question'; ?></h2>

                    <form action="?page=questions" method="POST" id="question-form">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="qlang" value="<?php echo $qlang; ?>">
                        <?php if ($is_edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($question_data['id']); ?>">
                        <?php endif; ?>
                        <?php if ($qlang === 'en' && isset($question_data['fr_id'])): ?>
                            <div class="form-group">
                                <label>Question française liée (fr_id) :</label>
                                <input type="number" value="<?php echo htmlspecialchars($question_data['fr_id']); ?>" readonly>
                                <small class="hint">La liaison fr_id (statistiques FR+EN) n'est pas modifiable ici — <a href="?page=questions&action=edit&id=<?php echo urlencode($question_data['fr_id']); ?>">voir la question française</a>.</small>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="level">Module (level) :</label>
                            <input type="text" id="level" name="level" value="<?php echo htmlspecialchars($question_data['level'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="question">Question :</label>
                            <textarea id="question" name="question" required><?php echo htmlspecialchars($question_data['question'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Type de question (qtype) :</label>
                            <div class="radio-group">
                                <label><input type="radio" name="qtype" value="qcm" <?php echo ($question_data['qtype'] ?? 'qcm') === 'qcm' ? 'checked' : ''; ?>> QCM</label>
                                <label><input type="radio" name="qtype" value="echelle" <?php echo ($question_data['qtype'] ?? '') === 'echelle' ? 'checked' : ''; ?>> Échelle</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="rep1">Réponse 1 :</label>
                            <input type="text" id="rep1" name="rep1" value="<?php echo htmlspecialchars($question_data['rep1'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="rep2">Réponse 2 :</label>
                            <input type="text" id="rep2" name="rep2" value="<?php echo htmlspecialchars($question_data['rep2'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="rep3">Réponse 3 :</label>
                            <input type="text" id="rep3" name="rep3" value="<?php echo htmlspecialchars($question_data['rep3'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="rep4">Réponse 4 :</label>
                            <input type="text" id="rep4" name="rep4" value="<?php echo htmlspecialchars($question_data['rep4'] ?? ''); ?>" required>
                        </div>
                        <div id="rep5-group" class="form-group hidden">
                            <label for="rep5">Réponse 5 :</label>
                            <input type="text" id="rep5" name="rep5" value="<?php echo htmlspecialchars($question_data['rep5'] ?? ''); ?>">
                        </div>

                        <div id="answer-group" class="form-group hidden">
                            <label for="answer">Numéro de la bonne réponse (pour QCM) :</label>
                            <input type="number" id="answer" name="answer" min="1" max="4" value="<?php echo htmlspecialchars($question_data['answer'] ?? '1'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="expliq">Explication de la réponse :</label>
                            <textarea id="expliq" name="expliq"><?php echo htmlspecialchars($question_data['expliq'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <?php if ($is_edit_mode): ?>
                                <button type="submit" name="update_question">Mettre à jour</button>
                                <button type="submit" name="delete_question" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette question ? Cette action est irréversible.');">Supprimer la question</button>
                            <?php else: ?>
                                <button type="submit" name="create_question">Créer la question</button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($page === 'levels') : ?>
            <?php if ($message) echo $message; ?>

            <?php if ($action === 'menu') : ?>
                <div class="action-menu">
                    <h2>Gestion des niveaux — que souhaitez-vous faire ?</h2>
                    <a href="?page=levels&action=create" class="button-link">Créer un nouveau niveau</a>
                    <a href="?page=levels&action=edit" class="button-link">Modifier ou Supprimer un niveau</a>
                </div>

            <?php elseif ($action === 'create') : ?>
                <a href="?page=levels" class="back-link">&larr; Retour au menu</a>
                <h2>Créer un nouveau niveau</h2>
                <form action="?page=levels" method="post">
                    <?php echo csrf_input(); ?>
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

            <?php elseif ($action === 'edit' && !$id) : ?>
                <a href="?page=levels" class="back-link">&larr; Retour au menu</a>
                <h2>Étape 1 : Choisir un niveau à Modifier/Supprimer<?php echo $lang_suffix; ?></h2>
                <p style="text-align:center; font-size:0.9rem;">
                    Langue :
                    <a href="?page=levels&action=edit" <?php echo $qlang === 'fr' ? 'style="font-weight:bold;"' : ''; ?>>Français</a> |
                    <a href="?page=levels&action=edit&qlang=en" <?php echo $qlang === 'en' ? 'style="font-weight:bold;"' : ''; ?>>English</a>
                </p>
                <?php
                    $existing_levels = [];
                    try {
                        $stmt_levels = $pdo->query("SELECT level, titre FROM `$t_table` ORDER BY level ASC");
                        $existing_levels = $stmt_levels->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        echo "<p class='error'>Impossible de charger la liste des niveaux existants" . ($qlang === 'en' ? " (la table anglaise n'existe qu'après le premier import EN)" : "") . ".</p>";
                    }
                ?>
                <form action="" method="GET">
                    <input type="hidden" name="page" value="levels">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="qlang" value="<?php echo $qlang; ?>">
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

            <?php elseif ($action === 'edit' && $id) :
                $level_data = null;
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM `$t_table` WHERE level = ?");
                        $stmt->execute([$id]);
                        $level_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log('[admin/levels] ' . $e->getMessage());
                    }
                }

                if (!$level_data):
                    echo "<p class='error'>Le niveau avec l'ID " . htmlspecialchars($id) . " n'a pas été trouvé dans " . htmlspecialchars($t_table) . ".</p>";
                    echo '<a href="?page=levels&action=edit' . $lang_qs . '" class="back-link">&larr; Essayer un autre ID</a>';
                else:
            ?>
                <a href="?page=levels&action=edit<?php echo $lang_qs; ?>" class="back-link">&larr; Retour au choix du niveau</a>
                <h2>Modifier/Supprimer le niveau<?php echo $lang_suffix; ?> : <?= htmlspecialchars($level_data['titre']) ?></h2>

                <form action="?page=levels" method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="qlang" value="<?php echo $qlang; ?>">
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
                        <textarea id="text" name="text" rows="8"><?= htmlspecialchars((string) $level_data['text']) ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_level">Mettre à jour</button>
                        <button type="submit" name="delete_level"
                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce niveau ?\n\nATTENTION : Toutes les questions <?php echo $qlang === 'en' ? 'ANGLAISES (GSDatabase_en)' : 'associées (GSDatabase)'; ?> de ce niveau seront également supprimées de façon irréversible.');">
                            Supprimer le niveau
                        </button>
                    </div>
                </form>
            <?php
                endif; // fin du if ($level_data)
            endif; // fin du routage $action (levels)
            ?>

        <?php elseif ($page === 'import') : ?>
            <h2>Importer un Questionnaire (CSV)</h2>
            <?php if ($import_message) echo $import_message; ?>
            <form action="?page=import" method="post" enctype="multipart/form-data" style="margin-top: 2rem;">
                <?php echo csrf_input(); ?>
                <div class="form-group">
                    <label for="lang">Langue du questionnaire :</label>
                    <select id="lang" name="lang">
                        <option value="fr" selected>Français (GSDatabase / GSDatabaseT)</option>
                        <option value="en">English — lié au français (GSDatabase_en / GSDatabaseT_en)</option>
                    </select>
                    <small class="hint">L'import anglais relie chaque QCM à son équivalent français (pour les statistiques). Le module français du même niveau doit déjà être importé.</small>
                </div>
                <div class="form-group">
                    <label for="titre">Titre du module (optionnel — crée le niveau dans GSDatabaseT) :</label>
                    <input type="text" id="titre" name="titre" placeholder="Ex : Violences sexuelles, sexistes, consentement et emprise">
                    <small class="hint">Peut aussi être fourni dans le CSV via les lignes <code>#titre;…</code> et <code>#text;…</code> (elles priment sur ces champs).</small>
                </div>
                <div class="form-group">
                    <label for="text">Description / Avertissement du module (HTML accepté, optionnel) :</label>
                    <textarea id="text" name="text" rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label for="questionnaire_file">Sélectionnez un fichier de questionnaire (.csv) :</label>
                    <input type="file" id="questionnaire_file" name="questionnaire_file" accept=".csv" required>
                </div>
                <button type="submit" name="upload">Importer le fichier</button>
            </form>

        <?php elseif ($page === 'database') : ?>
            <h2>Visualiseur de la Base de Données</h2>
            <?php
            // Tables consultables, groupées par catégorie pour s'y retrouver facilement.
            // Clé = paramètre ?view (compatible avec les anciens liens db_viewer.php).
            $db_groups = [
                'Résultats' => [
                    'results' => ['table' => 'GSDatabaseR', 'label' => 'Réponses des participants'],
                ],
                'Contenu français' => [
                    'questions' => ['table' => 'GSDatabase',  'label' => 'Questions'],
                    'texts'     => ['table' => 'GSDatabaseT', 'label' => 'Modules (titres/descriptions)'],
                ],
                'Contenu anglais' => [
                    'questions_en' => ['table' => 'GSDatabase_en',  'label' => 'Questions EN'],
                    'texts_en'     => ['table' => 'GSDatabaseT_en', 'label' => 'Modules EN'],
                ],
                'Accès par clé' => [
                    'keys'       => ['table' => 'access_keys', 'label' => 'Clés d\'accès'],
                    'access_log' => ['table' => 'access_log',  'label' => 'Journal des connexions (IP)'],
                ],
            ];
            $db_views = [];
            foreach ($db_groups as $group_tables) { $db_views += $group_tables; }

            $current_view = $_GET['view'] ?? 'results';
            if (!isset($db_views[$current_view])) { $current_view = 'results'; }
            $view = $db_views[$current_view]['table'];

            if (isset($_GET['resp_deleted'])) {
                echo "<p class='success'>Réponse supprimée avec succès.</p>";
            }
            try {
                // Migration auto : ajoute GSDatabaseR.created_at (date de la réponse) si absente.
                // Les lignes existantes restent NULL (date inconnue), les nouvelles sont horodatées.
                try {
                    $cols_r = $pdo->query("DESCRIBE `GSDatabaseR`")->fetchAll(PDO::FETCH_COLUMN);
                    if (!in_array('created_at', $cols_r)) {
                        $pdo->exec("ALTER TABLE GSDatabaseR ADD COLUMN created_at DATETIME NULL DEFAULT NULL");
                        $pdo->exec("ALTER TABLE GSDatabaseR MODIFY created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
                    }
                } catch (PDOException $e) {
                    error_log('[admin/database] migration created_at : ' . $e->getMessage());
                }

                // Existence + nombre de lignes de chaque table (les tables EN ne sont
                // créées qu'au premier import anglais), pour les badges de navigation.
                $db_counts = [];
                foreach ($db_views as $key => $info) {
                    $stmt_exists = $pdo->prepare("SHOW TABLES LIKE ?");
                    $stmt_exists->execute([$info['table']]);
                    $db_counts[$key] = ($stmt_exists->fetchColumn() === false)
                        ? null
                        : (int) $pdo->query("SELECT COUNT(*) FROM `{$info['table']}`")->fetchColumn();
                }

                echo '<div class="db-nav">';
                foreach ($db_groups as $group_title => $group_tables) {
                    echo '<div class="db-group"><span class="db-group-title">' . htmlspecialchars($group_title) . '</span>';
                    foreach ($group_tables as $key => $info) {
                        $active = ($key === $current_view) ? ' active' : '';
                        $count_badge = ($db_counts[$key] === null) ? '—' : $db_counts[$key];
                        echo '<a href="?page=database&view=' . $key . '" class="' . trim($active) . '">'
                           . htmlspecialchars($info['label'])
                           . ' <span class="count">' . $count_badge . '</span></a>';
                    }
                    echo '</div>';
                }
                echo '</div>';

                echo "<h2 style='font-size:1.1rem;'>Table : <code>" . htmlspecialchars($view) . "</code></h2>";

                if ($db_counts[$current_view] === null) {
                    echo "<p>La table `" . htmlspecialchars($view) . "` n'existe pas encore. Elle sera créée automatiquement à la première utilisation (import anglais pour les tables EN, première connexion par clé pour le journal).</p>";
                } else {
                    // Noms des colonnes
                    $stmt_cols = $pdo->query("DESCRIBE `$view`");
                    $columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

                    $where = [];
                    $params = [];

                    // Filtre par niveau (module) si la table a une colonne `level`
                    $level_filter = null;
                    $levels = [];
                    if (in_array('level', $columns)) {
                        $levels = $pdo->query("SELECT DISTINCT level FROM `$view` ORDER BY level ASC")->fetchAll(PDO::FETCH_COLUMN);
                        if (isset($_GET['level']) && $_GET['level'] !== '' && in_array($_GET['level'], $levels)) {
                            $level_filter = $_GET['level'];
                            $where[] = "level = ?";
                            $params[] = $level_filter;
                        }
                    }

                    // Recherche : LIKE sur toutes les colonnes de la table
                    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
                    if ($search !== '') {
                        $like_parts = [];
                        foreach ($columns as $col) {
                            $like_parts[] = "CAST(`$col` AS CHAR) LIKE ?";
                            $params[] = '%' . $search . '%';
                        }
                        $where[] = '(' . implode(' OR ', $like_parts) . ')';
                    }

                    $where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

                    // Colonne de tri : `id` si présente (GSDatabaseT_en n'a pas d'id -> tri par sa 1re colonne)
                    $order_col = in_array('id', $columns) ? 'id' : $columns[0];

                    // Nombre total (filtres inclus) + pagination
                    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM `$view`" . $where_sql);
                    $stmt_total->execute($params);
                    $total_rows = (int) $stmt_total->fetchColumn();

                    $per_page = 200;
                    $total_pages = max(1, (int) ceil($total_rows / $per_page));
                    $cur_page = isset($_GET['p']) ? max(1, min($total_pages, (int) $_GET['p'])) : 1;
                    $offset = ($cur_page - 1) * $per_page;

                    $stmt_data = $pdo->prepare("SELECT * FROM `$view`" . $where_sql . " ORDER BY `$order_col` DESC LIMIT $per_page OFFSET $offset");
                    $stmt_data->execute($params);
                    $results = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

                    // URL de base pour la pagination (filtres conservés)
                    $base_params = ['page' => 'database', 'view' => $current_view];
                    if ($level_filter !== null) { $base_params['level'] = $level_filter; }
                    if ($search !== '') { $base_params['q'] = $search; }
                    $page_url = function ($p) use ($base_params) {
                        return '?' . http_build_query($base_params + ['p' => $p]);
                    };

                    // Action par ligne selon la table : édition (FR et EN) ou suppression (résultats)
                    $row_action = null;
                    $action_lang_qs = (substr($current_view, -3) === '_en') ? '&qlang=en' : '';
                    if (($current_view === 'questions' || $current_view === 'questions_en') && in_array('id', $columns)) {
                        $row_action = 'edit_question';
                    } elseif ($current_view === 'texts' || $current_view === 'texts_en') {
                        $row_action = 'edit_level';
                    } elseif ($current_view === 'results' && in_array('id', $columns)) {
                        $row_action = 'delete_response';
                    }

                    // Barre d'outils : info d'affichage + recherche + filtre par module
                    echo '<div class="db-toolbar">';
                    $info = $total_rows . ' ligne(s)';
                    if ($level_filter !== null) { $info .= ' — module ' . htmlspecialchars($level_filter); }
                    if ($search !== '') { $info .= ' — recherche « ' . htmlspecialchars($search) . ' »'; }
                    echo '<span class="db-info">' . $info . '</span>';

                    echo '<form action="" method="GET">';
                    echo '<input type="hidden" name="page" value="database">';
                    echo '<input type="hidden" name="view" value="' . htmlspecialchars($current_view) . '">';
                    if (count($levels) > 1) {
                        echo '<label for="level_filter" style="margin:0; font-size:0.9rem;">Module :</label>';
                        echo '<select id="level_filter" name="level">';
                        echo '<option value="">Tous</option>';
                        foreach ($levels as $lvl) {
                            $sel = ((string) $lvl === (string) $level_filter) ? ' selected' : '';
                            echo '<option value="' . htmlspecialchars($lvl) . '"' . $sel . '>' . htmlspecialchars($lvl) . '</option>';
                        }
                        echo '</select>';
                    }
                    echo '<input type="text" name="q" value="' . htmlspecialchars($search) . '" placeholder="Rechercher…" style="width:200px; padding:0.4rem 0.6rem;">';
                    echo '<button type="submit">Filtrer</button>';
                    if ($level_filter !== null || $search !== '') {
                        echo '<a href="?page=database&view=' . $current_view . '" class="back-link" style="margin:0;">Réinitialiser</a>';
                    }
                    echo '</form>';
                    echo '</div>';

                    if (count($results) > 0) {
                        echo "<table class='db-table'>";
                        echo "<thead><tr>";
                        foreach ($columns as $col) {
                            echo "<th>" . htmlspecialchars($col) . "</th>";
                        }
                        if ($row_action !== null) { echo "<th class='col-actions'>Actions</th>"; }
                        echo "</tr></thead>";
                        echo "<tbody>";
                        $truncate_at = 200; // au-delà, la cellule est repliée avec un lien « tout afficher »
                        foreach ($results as $row) {
                            echo "<tr>";
                            foreach ($columns as $col) {
                                // Échappement obligatoire : données issues des réponses au questionnaire (XSS stocké).
                                $val = (string) $row[$col];
                                if (mb_strlen($val) > $truncate_at) {
                                    $short = htmlspecialchars(mb_substr($val, 0, $truncate_at), ENT_QUOTES, 'UTF-8');
                                    $full  = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                                    echo "<td><span class='cell-short'>" . $short . "…</span><span class='cell-full hidden'>" . $full . "</span> "
                                       . "<a href='#' class='cell-toggle' onclick='return toggleCell(this)'>tout afficher</a></td>";
                                } else {
                                    echo "<td>" . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . "</td>";
                                }
                            }
                            if ($row_action === 'edit_question') {
                                echo "<td class='col-actions'><a class='btn-small' href='?page=questions&action=edit&id=" . urlencode($row['id']) . $action_lang_qs . "'>Modifier</a></td>";
                            } elseif ($row_action === 'edit_level') {
                                echo "<td class='col-actions'><a class='btn-small' href='?page=levels&action=edit&id=" . urlencode($row['level']) . $action_lang_qs . "'>Modifier</a></td>";
                            } elseif ($row_action === 'delete_response') {
                                echo "<td class='col-actions'><form action='?page=database' method='POST' style='margin:0;' onsubmit=\"return confirm('Supprimer cette réponse ? Cette action est irréversible.');\">"
                                   . csrf_input()
                                   . "<input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>"
                                   . "<input type='hidden' name='level' value='" . htmlspecialchars((string) $level_filter) . "'>"
                                   . "<input type='hidden' name='q' value='" . htmlspecialchars($search) . "'>"
                                   . "<input type='hidden' name='p' value='" . $cur_page . "'>"
                                   . "<button type='submit' name='delete_response' class='btn-small btn-danger'>Supprimer</button>"
                                   . "</form></td>";
                            }
                            echo "</tr>";
                        }
                        echo "</tbody></table>";

                        // Pagination
                        if ($total_pages > 1) {
                            echo '<div class="db-pagination">';
                            echo ($cur_page > 1)
                                ? '<a href="' . $page_url($cur_page - 1) . '">&larr; Précédent</a>'
                                : '<span class="disabled">&larr; Précédent</span>';
                            echo '<span>Page ' . $cur_page . ' sur ' . $total_pages . '</span>';
                            echo ($cur_page < $total_pages)
                                ? '<a href="' . $page_url($cur_page + 1) . '">Suivant &rarr;</a>'
                                : '<span class="disabled">Suivant &rarr;</span>';
                            echo '</div>';
                        }
                    } else {
                        echo "<p>Aucun résultat trouvé dans la table `" . htmlspecialchars($view) . "`.</p>";
                    }
                }
            } catch (PDOException $e) {
                error_log('[admin/database] ' . $e->getMessage());
                echo "<p class='error'>Erreur de base de données.</p>";
            }
            ?>
        <?php endif; ?>
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

<script>
// Onglet Base de données : déplier/replier les cellules longues
function toggleCell(link) {
    const td = link.closest('td');
    td.querySelector('.cell-short').classList.toggle('hidden');
    td.querySelector('.cell-full').classList.toggle('hidden');
    link.textContent = (link.textContent === 'tout afficher') ? 'réduire' : 'tout afficher';
    return false;
}

// Formulaire dynamique de l'onglet Questions : rep5/answer selon le qtype
function handleQTypeChange() {
    const qtype = document.querySelector('input[name="qtype"]:checked').value;
    const rep5Group = document.getElementById('rep5-group');
    const answerGroup = document.getElementById('answer-group');

    if (qtype === 'echelle') {
        rep5Group.classList.remove('hidden');
        answerGroup.classList.add('hidden');
    } else { // qcm
        rep5Group.classList.add('hidden');
        answerGroup.classList.remove('hidden');
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const qtypeRadios = document.querySelectorAll('input[name="qtype"]');
    if (qtypeRadios.length > 0) {
        qtypeRadios.forEach(radio => radio.addEventListener('change', handleQTypeChange));
        // Nous appelons la fonction lors du chargement afin de définir l'état correct du formulaire
        handleQTypeChange();
    }
});
</script>

</body>
</html>

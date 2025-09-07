<?php
// Inclure la configuration et démarrer la session
require_once 'conf.php';
session_start();

// --- Logique de connexion (IDENTIQUE) ---
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


// --- Logique du générateur Dobble (UNIQUEMENT SI CONNECTÉ) ---
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    $all_cards_indices = [];
    $symbols_to_use = [];
    $generation_error = '';
    $generation_message = '';
    $uploadDir = 'dopplegenImages/';

    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Récupérer TOUS les symboles de la BDD
        $stmt = $pdo->query("SELECT id, name, image_name FROM dopplegen ORDER BY id ASC");
        $all_symbols_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_symbols_available = count($all_symbols_db);

        // 2. Déterminer le plus grand ordre (n) possible
        $n = 0; // Ordre
        if ($total_symbols_available >= 57) {
            $n = 7;
        } elseif ($total_symbols_available >= 31) {
            $n = 5;
        } elseif ($total_symbols_available >= 21) {
            $n = 4;
        } elseif ($total_symbols_available >= 13) {
            $n = 3;
        } elseif ($total_symbols_available >= 7) {
            $n = 2;
        }

        // 3. S'il n'y a pas assez de symboles, définir une erreur. Sinon, générer.
        if ($n === 0) {
            $generation_error = "Erreur : Vous avez besoin d'au moins 7 symboles dans la base de données pour générer un jeu. Vous n'en avez que $total_symbols_available.";
        } else {
            // Calculer les propriétés du jeu
            $symbols_needed = $n * $n + $n + 1;
            $symbols_per_card = $n + 1;
            
            // Prendre seulement les symboles nécessaires pour cet ordre
            $symbols_to_use = array_slice($all_symbols_db, 0, $symbols_needed);
            
            $generation_message = "Jeu généré (Ordre <strong>$n</strong>). Total cartes : <strong>$symbols_needed</strong>. Symboles par carte : <strong>$symbols_per_card</strong>. (Basé sur vos <strong>$total_symbols_available</strong> symboles disponibles)";

            // --- ALGORITHME DU PLAN PROJECTIF (GÉNÉRIQUE) ---
            
            // Carte 0 (La ligne à l'infini)
            $card_zero = [];
            for($i = 0; $i < $symbols_per_card; $i++) {
                $card_zero[] = $i; // Indices 0 à n
            }
            $all_cards_indices[] = $card_zero;

            // Ensemble de cartes 2 : Lignes avec pente (n*n cartes)
            for ($i = 0; $i < $n; $i++) { // Pente 'i'
                for ($j = 0; $j < $n; $j++) { // Ordonnée à l'origine 'j'
                    
                    $card = [];
                    // 1. Le point à l'infini pour cette pente (symbole 1 à n)
                    $card[] = $i + 1; 

                    // 2. Les n points affines
                    for ($x = 0; $x < $n; $x++) {
                        $y = ($i * $x + $j) % $n;
                        // Mapper (x, y) à notre index de symbole (commençant après les points infinis)
                        // L'index de symbole est (n+1) + (x*n) + y
                        $symbol_index = ($n + 1) + ($x * $n) + $y;
                        $card[] = $symbol_index;
                    }
                    $all_cards_indices[] = $card;
                }
            }

            // Ensemble de cartes 3 : Lignes verticales (n cartes)
            for ($j = 0; $j < $n; $j++) { // Ligne x = j
                
                $card = [];
                // 1. Le point à l'infini "vertical" (symbole 0)
                $card[] = 0;

                // 2. Les n points affines
                for ($y = 0; $y < $n; $y++) {
                    $x = $j;
                    $symbol_index = ($n + 1) + ($x * $n) + $y;
                    $card[] = $symbol_index;
                }
                $all_cards_indices[] = $card;
            }
        }

    } catch (PDOException $e) {
        $generation_error = "Erreur de base de données : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur de Cartes Dopplegen</title>
    <style>
        /* Styles de base (écran) */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; box-sizing: border-box; }
        h1, h2 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .login-container { max-width: 400px; margin: 50px auto; padding: 2rem; background: #fff; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 1rem; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1rem;
        }
        button {
            background-color: #007bff; color: white; padding: 10px 18px; border: none; border-radius: 5px;
            font-size: 1rem; cursor: pointer; transition: background-color 0.3s;
        }
        .logout-button { background-color: #6c757d; position: absolute; top: 20px; right: 20px; }
        .print-button { background-color: #28a745; position: fixed; bottom: 20px; right: 20px; z-index: 100; }
        
        /* Conteneur des cartes */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding-bottom: 80px; /* Espace pour le bouton d'impression */
        }
        .dobble-card {
            background: #fff;
            border: 2px dashed #ccc;
            border-radius: 15px;
            padding: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            aspect-ratio: 1 / 1;
            position: relative;
            /* Nouveau layout flexible pour les symboles */
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-items: center;
        }
        .card-header {
            position: absolute;
            top: 5px;
            left: 15px;
            font-size: 0.8rem;
            color: #aaa;
        }
        .dobble-card .symbol {
            /* Ajuster la taille des symboles en fonction du nombre */
            flex-basis: 28%; /* ~3 symboles par ligne */
            max-width: 28%;
            height: auto;
            margin: 2%;
            object-fit: contain;
        }

        /* --- Styles pour l'impression PDF --- */
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print, .logout-button, .print-button, h1, .info, .error {
                display: none !important; /* Cacher l'interface non nécessaire */
            }
            .cards-container {
                display: grid;
                grid-template-columns: 1fr 1fr; /* 2 cartes par ligne sur une page A4 */
                gap: 10mm;
                page-break-inside: avoid;
            }
            .dobble-card {
                box-shadow: none;
                border: 2px solid #000;
                border-radius: 10px;
                page-break-inside: avoid; /* Empêcher une carte d'être coupée entre deux pages */
            }
            .card-header { display: none; }
        }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
    
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button no-print">Déconnexion</button>
        </form>
        
        <button onclick="window.print()" class="print-button no-print">🖨️ Imprimer / Exporter en PDF</button>

        <h1>Générateur de Cartes Dopplegen</h1>
        
        <?php if (!empty($generation_error)): ?>
            <p class="error"><?= $generation_error ?></p>
        
        <?php elseif (empty($all_cards_indices)): ?>
            <p class="error">Une erreur inconnue est survenue lors de la génération.</p>
            
        <?php else: ?>
            <p class="info"><?= $generation_message ?></p>
            <div class="cards-container">
                
                <?php foreach ($all_cards_indices as $card_index => $symbol_indices_array): ?>
                    <div class="dobble-card">
                        <div class="card-header no-print">Carte <?= $card_index + 1 ?></div>
                        
                        <?php 
                        // Mélanger les symboles sur la carte pour un aspect aléatoire
                        shuffle($symbol_indices_array); 
                        ?>
                        
                        <?php foreach ($symbol_indices_array as $key => $symbol_db_index): ?>
                            <?php $symbol_data = $symbols_to_use[$symbol_db_index]; ?>
                            <img src="<?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?>" 
                                 alt="<?= htmlspecialchars($symbol_data['name']) ?>" 
                                 title="<?= htmlspecialchars($symbol_data['name']) ?>"
                                 class="symbol">
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>
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
                </form-group">
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

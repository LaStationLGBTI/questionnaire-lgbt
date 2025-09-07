<?php
// Inclure la configuration et démarrer la session
require_once 'conf.php';
session_start();

// --- Logique de connexion (IDENTIQUE AU FICHIER 1) ---
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
    $all_cards = [];
    $symbols = [];
    $generation_error = '';
    $uploadDir = 'dopplegenImages/';

    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Récupérer 57 symboles de la BDD
        // Nous prenons simplement les 57 premiers trouvés.
        $stmt = $pdo->query("SELECT id, name, image_name FROM dopplegen LIMIT 57");
        $symbols = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Vérifier si nous avons assez de symboles
        if (count($symbols) < 57) {
            $generation_error = "Erreur : Impossible de générer le jeu. Il faut au moins 57 symboles (images) dans la base de données. Vous n'en avez que " . count($symbols) . ".";
        } else {
            
            // --- ALGORITHME DU PLAN PROJECTIF (ORDRE n=7) ---
            // Total symboles : n^2 + n + 1 = 49 + 7 + 1 = 57 (indices 0 à 56 de notre array $symbols)
            // Total cartes : 57
            // Symboles par carte : n + 1 = 8

            $n = 7;
            $all_cards_indices = []; // Contiendra 57 tableaux, chacun avec 8 indices de symboles (0-56)

            // Carte 0 (La ligne à l'infini)
            // Contient les 8 premiers symboles (indices 0 à 7)
            $all_cards_indices[] = [0, 1, 2, 3, 4, 5, 6, 7];

            // Ensemble de cartes 2 : Lignes avec pente (n*n = 49 cartes)
            for ($i = 0; $i < $n; $i++) { // Pente 'i'
                for ($j = 0; $j < $n; $j++) { // Ordonnée à l'origine 'j'
                    
                    $card = [];
                    // 1. Le point à l'infini pour cette pente (symbole 1 à 7)
                    $card[] = $i + 1; 

                    // 2. Les n points affines sur la ligne y = ix + j (mod n)
                    for ($x = 0; $x < $n; $x++) {
                        $y = ($i * $x + $j) % $n;
                        
                        // Mapper (x, y) à notre index de symbole (8 à 56)
                        // Symbole P(x, y) = 8 + (x * 7) + y
                        $symbol_index = 8 + ($x * $n) + $y;
                        $card[] = $symbol_index;
                    }
                    $all_cards_indices[] = $card;
                }
            }

            // Ensemble de cartes 3 : Lignes verticales (n = 7 cartes)
            for ($j = 0; $j < $n; $j++) { // Ligne x = j
                
                $card = [];
                // 1. Le point à l'infini "vertical" (symbole 0)
                $card[] = 0;

                // 2. Les n points affines sur cette ligne (où x=j)
                for ($y = 0; $y < $n; $y++) {
                    $x = $j;
                    // Symbole P(x, y) = 8 + (x * 7) + y
                    $symbol_index = 8 + ($x * $n) + $y;
                    $card[] = $symbol_index;
                }
                $all_cards_indices[] = $card;
            }
            // À ce stade, $all_cards_indices contient 57 cartes (arrays) de 8 indices (int)
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
            border-radius: 15px; /* Moins circulaire pour mieux placer les images */
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            aspect-ratio: 1 / 1; /* Garantit que la carte est carrée */
            display: grid;
            grid-template-columns: 1fr 1fr 1fr; /* Grille 3x3 pour 8 symboles + 1 centre vide */
            grid-template-rows: 1fr 1fr 1fr;
            place-items: center;
            position: relative;
        }
        .card-header {
            position: absolute;
            top: 5px;
            left: 15px;
            font-size: 0.8rem;
            color: #aaa;
        }
        .dobble-card .symbol {
            max-width: 80%;
            max-height: 80%;
            width: auto;
            height: auto;
        }
        /* Placer les 8 symboles dans une grille 3x3 (laissant le centre vide) */
        .symbol-0 { grid-area: 1 / 1; }
        .symbol-1 { grid-area: 1 / 2; }
        .symbol-2 { grid-area: 1 / 3; }
        .symbol-3 { grid-area: 2 / 1; }
        .symbol-4 { grid-area: 2 / 3; }
        .symbol-5 { grid-area: 3 / 1; }
        .symbol-6 { grid-area: 3 / 2; }
        .symbol-7 { grid-area: 3 / 3; }

        /* --- Styles pour l'impression PDF --- */
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print, .logout-button, .print-button, h1 {
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
        }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
    
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button no-print">Déconnexion</button>
        </form>
        
        <button onclick="window.print()" class="print-button no-print">🖨️ Imprimer / Exporter en PDF</button>

        <h1>Générateur de Cartes Dopplegen (Ordre 7)</h1>
        
        <?php if (!empty($generation_error)): ?>
            <p class="error"><?= $generation_error ?></p>
        
        <?php elseif (empty($all_cards_indices)): ?>
            <p class="error">Une erreur inconnue est survenue lors de la génération.</p>
            
        <?php else: ?>
            <p>Jeu de <strong><?= count($all_cards_indices) ?> cartes</strong> généré avec succès, utilisant <strong><?= count($symbols) ?> symboles</strong> uniques. Chaque carte contient 8 symboles.</p>
            <div class="cards-container">
                
                <?php foreach ($all_cards_indices as $card_index => $symbol_indices_array): ?>
                    <div class="dobble-card">
                        <div class="card-header no-print">Carte <?= $card_index + 1 ?></div>
                        
                        <?php 
                        // Mélanger les symboles sur la carte pour un aspect aléatoire
                        shuffle($symbol_indices_array); 
                        ?>
                        
                        <?php foreach ($symbol_indices_array as $key => $symbol_db_index): ?>
                            <?php $symbol_data = $symbols[$symbol_db_index]; ?>
                            <img src="<?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?>" 
                                 alt="<?= htmlspecialchars($symbol_data['name']) ?>" 
                                 title="<?= htmlspecialchars($symbol_data['name']) ?>"
                                 class="symbol symbol-<?= $key ?>">
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

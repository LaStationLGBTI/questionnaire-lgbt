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
    $categories_list = [];
    $generation_error = '';
    $generation_message = '';
    $selected_category_get = '';
    $uploadDir = 'dopplegenImages/';

    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Toujours récupérer la liste des catégories pour le formulaire
        $categories_list_stmt = $pdo->query("SELECT DISTINCT category FROM dopplegen ORDER BY category ASC");
        $categories_list = $categories_list_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Vérifier si une génération est demandée (via le bouton)
        if (isset($_GET['generate'])) {
            $selected_category_get = $_GET['category'];
            
            // Préparer la requête SQL pour récupérer les symboles
            $sql = "SELECT id, name, image_name FROM dopplegen";
            $params = [];
            
            if (!empty($selected_category_get)) {
                $sql .= " WHERE category = ?";
                $params[] = $selected_category_get;
            }
            $sql .= " ORDER BY RAND()"; // Mélanger les symboles pour un jeu différent à chaque fois
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $all_symbols_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_symbols_available = count($all_symbols_db);

            // 3. Déterminer l'ordre (n) possible
            $n = 0; 
            if ($total_symbols_available >= 57) $n = 7;
            elseif ($total_symbols_available >= 31) $n = 5;
            elseif ($total_symbols_available >= 21) $n = 4;
            elseif ($total_symbols_available >= 13) $n = 3;
            elseif ($total_symbols_available >= 7) $n = 2;

            // 4. Générer ou définir une erreur
            if ($n === 0) {
                $generation_error = "Erreur : Vous avez besoin d'au moins 7 symboles dans cette catégorie pour générer un jeu. Symboles trouvés : $total_symbols_available.";
            } else {
                $symbols_needed = $n * $n + $n + 1;
                $symbols_per_card = $n + 1;
                $symbols_to_use = array_slice($all_symbols_db, 0, $symbols_needed);
                
                $generation_message = "Jeu généré (Ordre <strong>$n</strong>). Total cartes : <strong>$symbols_needed</strong>. Symboles par carte : <strong>$symbols_per_card</strong>. (Utilisant $symbols_needed symboles sur $total_symbols_available trouvés)";

                // --- ALGORITHME DU PLAN PROJECTIF (GÉNÉRIQUE) ---
                $card_zero = [];
                for($i = 0; $i < $symbols_per_card; $i++) $card_zero[] = $i;
                $all_cards_indices[] = $card_zero;

                for ($i = 0; $i < $n; $i++) {
                    for ($j = 0; $j < $n; $j++) {
                        $card = [$i + 1];
                        for ($x = 0; $x < $n; $x++) {
                            $y = ($i * $x + $j) % $n;
                            $card[] = ($n + 1) + ($x * $n) + $y;
                        }
                        $all_cards_indices[] = $card;
                    }
                }
                for ($j = 0; $j < $n; $j++) {
                    $card = [0];
                    for ($y = 0; $y < $n; $y++) {
                        $card[] = ($n + 1) + ($j * $n) + $y;
                    }
                    $all_cards_indices[] = $card;
                }
            }
        } // Fin de if(isset($_GET['generate']))

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
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 10px; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 1rem; border-radius: 5px; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"], select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1rem;
        }
        button {
            background-color: #007bff; color: white; padding: 10px 18px; border: none; border-radius: 5px;
            font-size: 1rem; cursor: pointer; transition: background-color 0.3s;
        }
        .logout-button { background-color: #6c757d; position: absolute; top: 20px; right: 20px; }
        .print-button { background-color: #28a745; position: fixed; bottom: 20px; right: 20px; z-index: 100; }
        
        .generator-form {
            background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        /* --- STYLES DE CARTE (GRID) --- */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding-bottom: 80px; 
        }
        .dobble-card {
            background: #fff;
            border: 2px dashed #ccc;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            aspect-ratio: 1 / 1;
            position: relative;
            overflow: hidden; 
            padding: 15px;
            box-sizing: border-box;
            display: grid; 
        }
        .card-header {
            position: absolute; top: 10px; left: 20px; font-size: 0.8rem; color: #aaa; z-index: 1;
        }
        .symbol-cell {
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden; 
            width: 100%;
            height: 100%;
        }
        .dobble-card .symbol {
            max-width: 100%; 
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        .dobble-card .symbol:hover {
            transform: scale(1.2) !important;
            z-index: 10;
            position: relative;
        }

        /* --- GRILLES DE LAYOUT (Без изменений) --- */
        .layout-k8 { grid-template: 1fr 1fr 1fr / 1fr 1fr 1fr; place-items: center; }
        .layout-k8 .symbol-cell-0 { grid-area: 1 / 1; }
        .layout-k8 .symbol-cell-1 { grid-area: 1 / 2; }
        .layout-k8 .symbol-cell-2 { grid-area: 1 / 3; }
        .layout-k8 .symbol-cell-3 { grid-area: 2 / 1; }
        .layout-k8 .symbol-cell-4 { grid-area: 2 / 3; } 
        .layout-k8 .symbol-cell-5 { grid-area: 3 / 1; }
        .layout-k8 .symbol-cell-6 { grid-area: 3 / 2; }
        .layout-k8 .symbol-cell-7 { grid-area: 3 / 3; }

        .layout-k6 { grid-template: 1fr 1fr 1fr / 1fr 1fr; place-items: center; gap: 5px; }
        .layout-k6 .symbol-cell-0 { grid-area: 1 / 1; }
        .layout-k6 .symbol-cell-1 { grid-area: 1 / 2; }
        .layout-k6 .symbol-cell-2 { grid-area: 2 / 1; }
        .layout-k6 .symbol-cell-3 { grid-area: 2 / 2; }
        .layout-k6 .symbol-cell-4 { grid-area: 3 / 1; }
        .layout-k6 .symbol-cell-5 { grid-area: 3 / 2; }
        
        .layout-k5 { grid-template: 1fr 1fr 1fr / 1fr 1fr 1fr; place-items: center; }
        .layout-k5 .symbol-cell-0 { grid-area: 1 / 1; }
        .layout-k5 .symbol-cell-1 { grid-area: 1 / 3; }
        .layout-k5 .symbol-cell-2 { grid-area: 2 / 2; }
        .layout-k5 .symbol-cell-3 { grid-area: 3 / 1; }
        .layout-k5 .symbol-cell-4 { grid-area: 3 / 3; }

        .layout-k4 { grid-template: 1fr 1fr 1fr / 1fr 1fr 1fr; place-items: center; padding: 10%; }
        .layout-k4 .symbol-cell-0 { grid-area: 1 / 1; }
        .layout-k4 .symbol-cell-1 { grid-area: 1 / 3; }
        .layout-k4 .symbol-cell-2 { grid-area: 3 / 1; }
        .layout-k4 .symbol-cell-3 { grid-area: 3 / 3; }
        
        .layout-k3 { grid-template: 1fr 1fr / 1fr 1fr; place-items: center; gap: 5px; padding: 15%; }
        .layout-k3 .symbol-cell-0 { grid-area: 1 / 1; grid-column-start: 1; grid-column-end: 3; }
        .layout-k3 .symbol-cell-1 { grid-area: 2 / 1; }
        .layout-k3 .symbol-cell-2 { grid-area: 2 / 2; }

        
        /* --- НОВЫЙ БЛОК CSS: Стили для Легенды (Скрыто на экране) --- */
        #symbol-legend {
            display: none;
        }


        /* --- Styles d'impression (6 cartes par page A4) --- */
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print, .logout-button, .print-button, h1, .info, .error, .generator-form {
                display: none !important; 
            }
            .cards-container {
                width: 100%; 
                display: grid;
                grid-template-columns: 1fr 1fr; 
                grid-auto-rows: 80mm; 
                gap: 5mm;              
                padding: 0;           
                margin: 0 auto;        
                box-sizing: border-box;
            }
            .dobble-card {
                box-shadow: none;
                border: 2px solid #000;
                border-radius: 50%;
                page-break-inside: avoid;
                width: 80mm;  
                height: 80mm; 
                padding: 7px; 
                margin: 0;
                box-sizing: border-box;
            }
            .card-header { display: none; }
            .dobble-card .symbol { transition: none; } 
            
            /* --- НОВЫЙ БЛОК CSS: Стили Легенды для Печати --- */
            #symbol-legend {
                display: block;             /* Показать блок при печати */
                page-break-before: always;  /* Начать с новой страницы (сделает ее последней) */
                padding-top: 10mm;
                width: 190mm; /* Ширина области печати A4 (210мм - 20мм полей) */
                margin: 0 auto;
            }
            #symbol-legend h2 {
                text-align: center;
                font-size: 16pt;
                margin-bottom: 5mm;
                border-bottom: 2px solid #000;
            }
            .legend-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9pt; /* Мелкий шрифт, чтобы все влезло */
            }
            .legend-table th, .legend-table td {
                border: 1px solid #aaa;
                padding: 2mm;
                text-align: left;
                vertical-align: middle;
                page-break-inside: avoid; /* Не разрывать строку таблицы */
            }
            .legend-table th {
                background-color: #eee;
                font-size: 11pt;
            }
            .legend-table img {
                width: 40px;   /* Фиксированный маленький размер */
                height: 40px;  /* Фиксированный маленький размер */
                object-fit: contain;
                vertical-align: middle;
            }
            .legend-table td:first-child { /* Ячейка с картинкой */
                width: 45px;
                text-align: center;
            }
            /* --- КОНЕЦ НОВЫХ CSS-СТИЛЕЙ ПЕЧАТИ --- */
        }
    </style>
</head>
</head>
<body>

    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
    
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button no-print">Déconnexion</button>
        </form>
        
        <?php if (!empty($all_cards_indices)): // N'afficher le bouton d'impression que si des cartes sont générées ?>
            <button onclick="window.print()" class="print-button no-print">🖨️ Imprimer / Exporter en PDF</button>
        <?php endif; ?>

        <h1>Générateur de Cartes Dopplegen</h1>

        <div class="generator-form no-print">
            <h2>Paramètres de génération</h2>
            <form action="" method="GET">
                <div class="form-group">
                    <label for="category">Choisir une catégorie de symboles :</label>
                    <select id="category" name="category">
                        <option value="">-- Toutes les catégories --</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($cat['category'] == $selected_category_get) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate">Générer / Régénérer</button>
            </form>
        </div>

        
        <?php if (!empty($generation_error)): ?>
            <p class="error"><?= $generation_error ?></p>
        
        <?php elseif (!empty($generation_message)): ?>
             <p class="info"><?= $generation_message ?></p>
        <?php endif; ?>


<?php if (!empty($all_cards_indices)): ?>
            
            <div class="cards-container">
                </div> <?php endif; ?> <?php if (!empty($symbols_to_use)): // Мы используем $symbols_to_use, так как он содержит все 57 (или 31, 21...) символов ?>
            <div id="symbol-legend">
                <h2>Symboles (Légende)</h2>
                <table class="legend-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom du Symbole</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($symbols_to_use as $symbol): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($uploadDir . $symbol['image_name']) ?>" 
                                         alt="<?= htmlspecialchars($symbol['name']) ?>">
                                </td>
                                <td><?= htmlspecialchars($symbol['name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

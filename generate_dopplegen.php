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
            position: relative; /* ОБЯЗАТЕЛЬНО для position: absolute у дочерних символов */
            overflow: hidden; 
            padding: 10px; 
            box-sizing: border-box;
            /* display: grid; БОЛЬШЕ НЕ НУЖЕН */
        }
        .card-header {
            position: absolute; top: 10px; left: 20px; font-size: 0.8rem; color: #aaa; z-index: 1;
        }


        /* * CSS ДЛЯ МАКЕТОВ (GRID LAYOUTS .layout-k...) БЫЛИ УДАЛЕНЫ.
         * Они больше не нужны, так как PHP теперь генерирует 
         * position: absolute с рассчитанными координатами.
        */

        /* Стиль для самого символа (теперь с position: absolute) */
        .dobble-card .symbol {
            max-width: 100%; 
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
            position: absolute; /* PHP УПРАВЛЯЕТ ЭТИМ */
            /* PHP применяет к нему width, height, top, left, transform */
        }
        .dobble-card .symbol:hover {
            transform: scale(1.2) !important;
            z-index: 10;
        }
        
        /* Стили печати остаются БЕЗ ИЗМЕНЕНИЙ */
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print, .logout-button, .print-button, h1, .info, .error, .generator-form {
                display: none !important; 
            }
            .cards-container {
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
            /* --- Styles pour l'impression de la Légende --- */
        .symbol-legend-container {
            page-break-before: always; /* ФОРСИРУЕТ ЛЕГЕНДУ НА НОВУЮ (ПОСЛЕДНЮЮ) СТРАНИЦУ */
            margin-top: 0;
            padding: 0;
            box-shadow: none;
            border: none;
        }
        .legend-table {
            width: 100%;
        }
        .legend-img {
            width: 40px; /* Чуть меньше для печати */
            height: 40px;
        }
        }
        /* --- Styles pour la Légende des Symboles --- */
        .symbol-legend-container {
            margin-top: 40px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .legend-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .legend-table th, .legend-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .legend-table th {
            background-color: #f9f9f9;
        }
        .legend-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            background: #fdfdfd;
            border-radius: 4px;
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
            
            <?php
            /**
             * Функция-хелпер "Слотов Макет"
             * Возвращает массив предопределенных, безопасных координат для каждого 'k' (количества символов).
             * Каждый слот - это [ 'size' => %, 'top' => %, 'left' => % ]
             * Эти координаты рассчитаны так, чтобы имитировать вид Dobble (1 большой, N маленьких)
             * и гарантированно не перекрываться и помещаться в круг.
             */
            function getSymbolLayoutSlots($k) {
                switch ($k) {
                    case 8: // 1 Большой (Центр) + 7 Маленьких (Орбита)
                        return [
                            ['size' => 40, 'top' => 30, 'left' => 30], // Центр
                            ['size' => 25, 'top' => 5,  'left' => 37], // Верх
                            ['size' => 25, 'top' => 15, 'left' => 10], // Верх-Лево
                            ['size' => 25, 'top' => 15, 'left' => 65], // Верх-Право
                            ['size' => 25, 'top' => 40, 'left' => 5],  // Середина-Лево
                            ['size' => 25, 'top' => 40, 'left' => 70], // Середина-Право
                            ['size' => 25, 'top' => 65, 'left' => 20], // Низ-Лево
                            ['size' => 25, 'top' => 65, 'left' => 55], // Низ-Право
                        ];
                    case 6: // 1 Большой (Центр) + 5 Маленьких (Пятиугольник)
                        return [
                            ['size' => 45, 'top' => 27.5,'left' => 27.5], // Центр
                            ['size' => 30, 'top' => 5,  'left' => 20], // Верх-Лево
                            ['size' => 30, 'top' => 5,  'left' => 50], // Верх-Право
                            ['size' => 30, 'top' => 35, 'left' => 65], // Середина-Право
                            ['size' => 30, 'top' => 60, 'left' => 45], // Низ-Право
                            ['size' => 30, 'top' => 60, 'left' => 15], // Низ-Лево
                        ];
                    case 5: // 1 Большой (Центр) + 4 Маленьких (Углы) - "Кости"
                        return [
                            ['size' => 45, 'top' => 27.5, 'left' => 27.5], // Центр
                            ['size' => 30, 'top' => 10, 'left' => 10], // Верх-Лево
                            ['size' => 30, 'top' => 10, 'left' => 60], // Верх-Право
                            ['size' => 30, 'top' => 60, 'left' => 10], // Низ-Лево
                            ['size' => 30, 'top' => 60, 'left' => 60], // Низ-Право
                        ];
                    case 4: // 1 Большой (Центр) + 3 Маленьких (Треугольник)
                        return [
                            ['size' => 50, 'top' => 25, 'left' => 25], // Центр
                            ['size' => 35, 'top' => 10, 'left' => 45], // Верх-Право
                            ['size' => 35, 'top' => 55, 'left' => 10], // Низ-Лево
                            ['size' => 35, 'top' => 55, 'left' => 55], // Низ-Право
                        ];
                    case 3: // 1 Большой + 2 Средних
                    default:
                        return [
                            ['size' => 50, 'top' => 25, 'left' => 25], // Центр
                            ['size' => 40, 'top' => 10, 'left' => 5],  // Верх-Лево
                            ['size' => 40, 'top' => 50, 'left' => 55], // Низ-Право
                        ];
                }
            }
            ?>

            <div class="cards-container">
                <?php foreach ($all_cards_indices as $card_index => $symbol_indices_array): ?>
                    
                    <?php
                    $k = count($symbol_indices_array);
                    // 1. Получаем массив предопределенных макетов (слотов)
                    $layout_slots = getSymbolLayoutSlots($k);
                    
                    // 2. КЛЮЧЕВОЙ МОМЕНТ: Мы перемешиваем массив слотов.
                    // (Первый слот в массиве всегда "Большой", остальные "Маленькие").
                    // Перемешивая их, мы гарантируем, что "Большой" слот достанется случайному символу.
                    shuffle($layout_slots);
                    ?>

                    <div class="dobble-card"> 
                        <div class="card-header no-print">Carte <?= $card_index + 1 ?></div>
                        
                        <?php 
                        // Мы перебираем символы как $key => $symbol_db_index
                        // $key будет 0, 1, 2... (до $k-1)
                        foreach ($symbol_indices_array as $key => $symbol_db_index): 
                        ?>
                            <?php 
                            $symbol_data = $symbols_to_use[$symbol_db_index];
                            
                            // 3. Получаем соответствующий (теперь перемешанный) слот для этого ключа
                            $slot = $layout_slots[$key];
                            
                            // 4. Добавляем случайный поворот
                            $rotation = rand(-180, 180);      
                            
                            // 5. Собираем стиль: position: absolute с ДЕТЕРМИНИРОВАННЫМИ координатами из слота.
                            $style = "position: absolute; " .
                                     "width: {$slot['size']}%; " .
                                     "height: {$slot['size']}%; " . // Гарантируем квадратность
                                     "top: {$slot['top']}%; " .
                                     "left: {$slot['left']}%; " .
                                     "transform: rotate({$rotation}deg);";
                            ?>
                            
                            <img src="<?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?>" 
                                 alt="<?= htmlspecialchars($symbol_data['name']) ?>" 
                                 title="<?= htmlspecialchars($symbol_data['name']) ?>"
                                 class="symbol" 
                                 style="<?= $style ?>">
                        <?php endforeach; // Конец цикла по символам ?>
                    </div>
                <?php endforeach; // Конец цикла по картам ?>

            </div> <div class="symbol-legend-container">
                <h2>Symboles utilisés (Légende)</h2>
                <p>Liste de tous les symboles uniques (<?= count($symbols_to_use) ?>) utilisés dans ce jeu.</p>
                <table class="legend-table">
                    <thead>
                        <tr>
                            <th>Image (mini)</th>
                            <th>Nom du Symbole</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($symbols_to_use as $symbol_data): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?>" 
                                         alt="<?= htmlspecialchars($symbol_data['name']) ?>" 
                                         class="legend-img">
                                </td>
                                <td><?= htmlspecialchars($symbol_data['name']) ?></td>
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

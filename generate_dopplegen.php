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

    // --- ПОЛУЧАЕМ ЗНАЧЕНИЯ СЛАЙДЕРОВ (или значения по умолчанию) ---
    $min_variance_mod = isset($_GET['min_var']) ? (int)$_GET['min_var'] : 60;
    $max_variance_mod = isset($_GET['max_var']) ? (int)$_GET['max_var'] : 100; // По умолчанию 100 (безопасно)


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
            $sql .= " ORDER BY RAND()"; // Mélanger les symboles для разной игры
            
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
        /* --- СТИЛИ ДЛЯ СЛАЙДЕРОВ --- */
        input[type="range"] { width: 100%; margin-top: 5px; }
        .slider-label { font-size: 0.9rem; color: #333; }
        .slider-value { font-weight: bold; color: #007bff; background-color: #e9ecef; padding: 2px 6px; border-radius: 4px; display: inline-block; min-width: 25px; text-align: center; }
        .slider-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        
        button {
            background-color: #007bff; color: white; padding: 10px 18px; border: none; border-radius: 5px;
            font-size: 1rem; cursor: pointer; transition: background-color 0.3s;
        }
        .logout-button { background-color: #6c757d; position: absolute; top: 20px; right: 20px; }
        .print-button { background-color: #28a745; position: fixed; bottom: 20px; right: 20px; z-index: 100; }
        
        .generator-form {
            background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        /* --- STYLES DE CARTE --- */
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
            padding: 0; 
            box-sizing: border-box;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card-header {
            position: absolute; top: 10px; left: 20px; font-size: 0.8rem; color: #aaa; z-index: 10;
        }

        /* Общие стили для символов */
        .dobble-card .symbol {
            position: absolute; 
            object-fit: contain; 
            transition: transform 0.3s ease;
            max-width: none; 
            max-height: none;
            z-index: 5;
        }
        .dobble-card .symbol:hover {
            transform: scale(1.1) !important; 
            z-index: 10;
        }
        
        /* Стили печати */
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .no-print, .logout-button, .print-button, h1, .info, .error, .generator-form {
                display: none !important; 
            }
            .cards-container {
                display: grid;
                grid-template-columns: 1fr 1fr; 
                grid-auto-rows: 100mm; 
                gap: 0;              
                padding: 0;           
                margin: 0 auto;       
                box-sizing: border-box;
            }
            .dobble-card {
                box-shadow: none;
                border: 2px solid #000;
                border-radius: 50%;
                page-break-inside: avoid;
                width: 100mm;  
                height: 100mm; 
                padding: 7px; 
                margin: 0;
                box-sizing: border-box;
            }
            .print-cards-section {
                page-break-after: always;
                
            }
            .card-header { display: none; }
            .dobble-card .symbol { transition: none; }
            /* --- Styles pour l'impression de la Légende --- */
            .symbol-legend-container {
                page-break-before: always; 
                margin-top: 0;
                padding: 0;
                box-shadow: none;
                border: none;
            }
            
            /* --- !!! НОВЫЕ СТИЛИ ЛЕГЕНДЫ ДЛЯ ПЕЧАТИ (6 КОЛОНОК) !!! --- */
            .legend-items-container {
                column-count: 6; /* 6 колонок при печати */
                column-gap: 15px;
            }
            .legend-item {
                gap: 5px;
                padding: 2px 0;
                border: none; /* Убираем границы при печати */
            }
            .legend-item .legend-name {
                font-size: 8pt; /* Мелкий шрифт для экономии места */
            }
            .legend-img { /* Существующий стиль */
                width: 40px; 
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
        .legend-img { /* Общий стиль для img легенды */
            width: 50px;
            height: 50px;
            object-fit: contain;
            background: #fdfdfd;
            border-radius: 4px;
            flex-shrink: 0; /* Предотвращает сжатие иконки */
        }
        
        /* --- !!! НОВЫЕ СТИЛИ ЛЕГЕНДЫ (ЗАМЕНА ТАБЛИЦЫ) !!! --- */
        /* 3 колонки для отображения на экране */
        .legend-items-container {
            column-count: 3;
            column-gap: 20px;
            border-top: 2px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }
        .legend-item {
            display: inline-flex; /* Используем flex для красивого выравнивания иконки и текста */
            align-items: center;
            gap: 10px;
            padding: 5px;
            width: 100%;
            box-sizing: border-box;
            break-inside: avoid; /* Предотвращает разрыв элемента между колонками */
            border-bottom: 1px solid #f0f0f0;
        }
        .legend-item:last-child {
            border-bottom: none;
        }
        .legend-item .legend-name {
            font-size: 0.9rem;
            word-break: break-word; /* Перенос длинных названий */
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

                <p class="slider-label">Ajuster la plage de taille aléatoire (% de la taille de sécurité maximale) :</p>
                <div class="slider-container">
                    <div class="form-group">
                        <label for="min_var">Taille Minimale: <span id="minValLabel" class="slider-value"><?= $min_variance_mod ?></span>%</label>
                        <input type="range" id="min_var" name="min_var" min="10" max="100" value="<?= $min_variance_mod ?>" 
                               oninput="document.getElementById('minValLabel').innerText = this.value">
                    </div>
                    <div class="form-group">
                        <label for="max_var">Taille Maximale: <span id="maxValLabel" class="slider-value"><?= $max_variance_mod ?></span>%</label>
                        <input type="range" id="max_var" name="max_var" min="10" max="130" value="<?= $max_variance_mod ?>"
                               oninput="document.getElementById('maxValLabel').innerText = this.value">
                    </div>
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
             * ФУНКЦИЯ-ПОМОЩНИК (Версия 3.0)
             * Принимает модификаторы min/max от слайдеров.
             */
            function build_slots($config_layers, $min_mod, $max_mod, $center_x = 50, $center_y = 50) {
                $slots = [];
                $layer_index = 0;

                // --- Проверка слайдеров ---
                if ($min_mod > $max_mod) {
                    $min_mod = $max_mod; 
                }

                foreach ($config_layers as $layer) {
                    $s = $layer['size'];    // Максимально БЕЗОПАСНЫЙ размер из рецепта
                    $c = $layer['count'];   // Количество
                    $r = $layer['radius'];  // Радиус
                    
                    if ($r == 0) {
                        // ЭТО ЦЕНТРАЛЬНЫЙ СЛОЙ: Используем фиксированный размер (не зависит от слайдеров).
                        for ($i = 0; $i < $c; $i++) { 
                            $slots[] = [
                                'size' => $s,
                                'top' => $center_y - ($s / 2),
                                'left' => $center_x - ($s / 2),
                                'z_index' => 10 - $layer_index
                            ];
                        }
                    } else {
                        // ЭТО ОРБИТАЛЬНЫЙ СЛОЙ: Применяем вариативность размера НА ОСНОВЕ СЛАЙДЕРОВ.
                        $angle_step = (M_PI * 2) / $c;
                        $angle_offset = (mt_rand() / mt_getrandmax()) * (M_PI * 2); 
                        
                        $recipe_max_size = $s; 
                        
                        // Рассчитываем фактический мин/макс размер на основе % от слайдеров
                        $actual_max_size = $recipe_max_size * ($max_mod / 100.0);
                        $actual_min_size = $recipe_max_size * ($min_mod / 100.0);

                        if ($actual_min_size < 0.1) $actual_min_size = 0.1;
                        if ($actual_max_size < $actual_min_size) $actual_max_size = $actual_min_size;


                        for ($i = 0; $i < $c; $i++) {
                            $angle = ($angle_step * $i) + $angle_offset;
                            $x = $center_x + $r * cos($angle);
                            $y = $center_y + $r * sin($angle);
                            
                            $current_size = mt_rand($actual_min_size * 100, $actual_max_size * 100) / 100;

                            $slots[] = [
                                'size' => $current_size, 
                                'top' => $y - ($current_size / 2),
                                'left' => $x - ($current_size / 2),
                                'z_index' => 5 - $layer_index
                            ];
                        }
                    }
                    $layer_index++;
                }
                return $slots;
            }

            /**
             * ОСНОВНАЯ ФУНКЦИЯ ГЕНЕРАЦИИ МАКЕТА (Версия 3.0)
             * Принимает $min_mod и $max_mod и передает их в build_slots.
             */
            function getSymbolLayoutSlots($k, $min_mod, $max_mod) {
                $all_recipes = []; 

                // --- K = 8 (Порядок 7, 8 слотов) ---
                $all_recipes[8] = [
                    [ ['size' => 27, 'count' => 1, 'radius' => 0], ['size' => 21, 'count' => 7, 'radius' => 34.4] ],
                    [ ['size' => 19, 'count' => 8, 'radius' => 35.5] ]
                ];
                
                // --- K = 6 (Порядок 5, 6 слотов) ---
                $all_recipes[6] = [
                    [ ['size' => 18, 'count' => 1, 'radius' => 0], ['size' => 25, 'count' => 5, 'radius' => 31] ],
                    [ ['size' => 23, 'count' => 6, 'radius' => 33] ]
                ];

                // --- K = 5 (Порядок 4, 5 слотов) ---
                $all_recipes[5] = [
                    [ ['size' => 12, 'count' => 1, 'radius' => 0], ['size' => 28, 'count' => 4, 'radius' => 29] ],
                    [ ['size' => 25, 'count' => 5, 'radius' => 31] ]
                ];
                
                // --- K = 4 (Порядок 3, 4 слота) ---
                $all_recipes[4] = [
                    [ ['size' => 28, 'count' => 4, 'radius' => 29] ],
                    [ ['size' => 27, 'count' => 4, 'radius' => 30] ]
                ];
                
                // --- K = 3 (Порядок 2, 3 слота) ---
                $all_recipes[3] = [
                    [ ['size' => 23, 'count' => 1, 'radius' => 0], ['size' => 23, 'count' => 2, 'radius' => 33] ],
                    [ ['size' => 32, 'count' => 3, 'radius' => 26.5] ]
                ];

                // 1. Получить список доступных рецептов для нашего $k
                $recipes_for_k = isset($all_recipes[$k]) ? $all_recipes[$k] : $all_recipes[3];
                
                // 2. Случайно выбрать ОДИН рецепт из списка
                $chosen_recipe_layers = $recipes_for_k[array_rand($recipes_for_k)];
                
                // 3. Построить и вернуть массив слотов, ПЕРЕДАВ МОДИФИКАТОРЫ СЛАЙДЕРОВ
                return build_slots($chosen_recipe_layers, $min_mod, $max_mod);
            }
            ?>
            <div class="print-cards-section">
            <div class="cards-container">
                <?php foreach ($all_cards_indices as $card_index => $symbol_indices_array): ?>
                    
                    <?php
                    $k = count($symbol_indices_array);
                    // Получаем массив слотов, ПЕРЕДАВАЯ значения слайдеров в генератор
                    $layout_slots = getSymbolLayoutSlots($k, $min_variance_mod, $max_variance_mod);
                    
                    // Перемешиваем массив слотов.
                    shuffle($layout_slots);
                    ?>

                    <div class="dobble-card"> 
                        <div class="card-header no-print">Carte <?= $card_index + 1 ?></div>
                        
                        <?php 
                        foreach ($symbol_indices_array as $key => $symbol_db_index): 
                            if (!isset($layout_slots[$key])) {
                                continue; 
                            }
                            $symbol_data = $symbols_to_use[$symbol_db_index];
                            $slot = $layout_slots[$key];
                            
                            $rotation = rand(-180, 180);      
                            
                            $style = "position: absolute; " .
                                     "width: {$slot['size']}%; " .
                                     "height: {$slot['size']}%; " .
                                     "top: {$slot['top']}%; " .
                                     "left: {$slot['left']}%; " .
                                     "transform: rotate({$rotation}deg); " .
                                     "z-index: {$slot['z_index']};";
                            ?>
                            
                            <img src="<?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?>" 
                                 alt="<?= htmlspecialchars($symbol_data['name']) ?>" 
                                 title="<?= htmlspecialchars($symbol_data['name']) ?>"
                                 class="symbol" 
                                 style="<?= $style ?>">
                        <?php endforeach; // Конец цикла по символам ?>
                    </div>
                <?php endforeach; // Конец цикла по картам ?>

            </div> 
                        </div> 
            <div class="symbol-legend-container">
                <h2>Symboles utilisés (Légende)</h2>
                <p>Liste de tous les symboles uniques (<?= count($symbols_to_use) ?>) utilisés dans ce jeu.</p>
                
                <div class="legend-items-container">
                    <?php foreach ($symbols_to_use as $symbol_data): ?>
                        <div class="legend-item">
                            <img src="<?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?>" 
                                 alt="<?= htmlspecialchars($symbol_data['name']) ?>" 
                                 class="legend-img">
                            <span class="legend-name"><?= htmlspecialchars($symbol_data['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

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

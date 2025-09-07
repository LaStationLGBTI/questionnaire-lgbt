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
            position: relative; /* Обязательно для позиционирования символов */
            overflow: hidden; /* Обрезает все, что выходит за круг */
            padding: 0; /* Небольшой отступ будет внутри символов */
            box-sizing: border-box;
            display: flex; /* Используем flexbox для центрирования содержимого */
            justify-content: center;
            align-items: center;
        }
        .card-header {
            position: absolute; top: 10px; left: 20px; font-size: 0.8rem; color: #aaa; z-index: 10;
        }

        /* Общие стили для символов */
        .dobble-card .symbol {
            position: absolute; /* Позиция управляется PHP */
            object-fit: contain; /* Сохраняет пропорции, вписывая в рамки */
            transition: transform 0.3s ease;
            max-width: none; /* Отменяем max-width, чтобы PHP-width работал */
            max-height: none; /* Отменяем max-height, чтобы PHP-height работал */
            z-index: 5;
        }
        .dobble-card .symbol:hover {
            transform: scale(1.1) !important; /* Уменьшаем scale, чтобы не сильно выходило за круг */
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
                padding: 7px; /* Слегка увеличим padding, чтобы символы не прилипали к краю */
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
             * Функция-хелпер "Слотов Макет" (ПЕРЕРАБОТАННАЯ ВЕРСИЯ)
             * Генерирует массив безопасных, математически выверенных координат для k символов.
             * Эта модель гарантирует отсутствие перекрытий и обрезки по краям.
             * Использует модель: 1 центральный слот + (k-1) слотов на одной орбите.
             * Размеры (size), радиус орбиты (radius) и отступы (top/left) рассчитаны математически.
             */
            function getSymbolLayoutSlots($k) {
                $slots = [];
                $center_x = 50; // Центр карты X (в %)
                $center_y = 50; // Центр карты Y (в %)

                // Параметры макета [center_size, orbit_size, orbit_radius]
                // Рассчитано на основе формул:
                // 1. (Безопасный радиус карты) = 48% (оставляем 2% зазора)
                // 2. (Радиус орбиты) + (Размер орбиты / 2) = 48
                // 3. (Размер орбиты) < 2 * (Радиус орбиты) * sin(PI / (k-1))  (чтобы символы на орбите не пересекались)
                // 4. (Размер центра) / 2 < (Радиус орбиты) - (Размер орбиты / 2) (чтобы центр не пересекался с орбитой)
                
                $layout_params = [];

                switch ($k) {
                    case 8: // Порядок 7 (1 центр, 7 на орбите)
                        // Max So < 29.04 -> Используем 28
                        // Max Sc < 40 -> Используем 38
                        // Ro = 48 - (28/2) = 34
                        $layout_params = ['center_size' => 38, 'orbit_size' => 28, 'orbit_radius' => 34];
                        break;
                    case 6: // Порядок 5 (1 центр, 5 на орбите)
                        // Max So < 35.53 -> Используем 34
                        // Max Sc < 28 -> Используем 26 (для зазора)
                        // Ro = 48 - (34/2) = 31
                        $layout_params = ['center_size' => 26, 'orbit_size' => 34, 'orbit_radius' => 31];
                        break;
                    case 5: // Порядок 4 (1 центр, 4 на орбите)
                        // Max So < 39.76 -> Используем 38
                        // Max Sc < 20 -> Используем 18 (для зазора)
                        // Ro = 48 - (38/2) = 29
                        $layout_params = ['center_size' => 18, 'orbit_size' => 38, 'orbit_radius' => 29];
                        break;
                    case 4: // Порядок 3 (1 центр, 3 на орбите)
                        // Max So < 44.55 -> Используем 42
                        // Max Sc < 12 -> Используем 10 (для зазора)
                        // Ro = 48 - (42/2) = 27
                        $layout_params = ['center_size' => 10, 'orbit_size' => 42, 'orbit_radius' => 27];
                        break;
                    case 3: // Порядок 2 (1 центр, 2 на орбите)
                    default:
                        // Сбалансированный макет
                        // S < 32. Используем 30 для всех.
                        // Ro должно быть > 30 и < 33. Используем 31.5
                        $layout_params = ['center_size' => 30, 'orbit_size' => 30, 'orbit_radius' => 31.5];
                        break;
                }

                // 1. Создать центральный слот
                $cs = $layout_params['center_size'];
                $slots[] = [
                    'size' => $cs,
                    'top' => $center_y - ($cs / 2),  // 50% - (половина размера) = верхний левый угол
                    'left' => $center_x - ($cs / 2), // 50% - (половина размера) = верхний левый угол
                    'z_index' => 10 // Центральный всегда выше
                ];

                // 2. Создать (k-1) орбитальных слотов
                $num_orbit = $k - 1;
                if ($num_orbit <= 0) {
                    return $slots; // На случай, если k=1 (хотя это невозможно по алгоритму игры)
                }

                $os = $layout_params['orbit_size'];
                $or = $layout_params['orbit_radius'];
                $angle_step = (M_PI * 2) / $num_orbit;
                $angle_offset = M_PI / $num_orbit; // Смещаем на половину шага, чтобы ни один символ не был ровно "в 3 часа"

                for ($i = 0; $i < $num_orbit; $i++) {
                    $angle = ($angle_step * $i) + $angle_offset;
                    
                    // Рассчитываем ЦЕНТР символа на орбите
                    $x = $center_x + $or * cos($angle);
                    $y = $center_y + $or * sin($angle);

                    // Рассчитываем ВЕРХНИЙ ЛЕВЫЙ УГОЛ (top, left) из центра и размера
                    $slots[] = [
                        'size' => $os,
                        'top' => $y - ($os / 2), // Центрируем символ относительно точки (x,y)
                        'left' => $x - ($os / 2),// Центрируем символ относительно точки (x,y)
                        'z_index' => 5
                    ];
                }
                
                return $slots;
            }
            ?>

            <div class="cards-container">
                <?php foreach ($all_cards_indices as $card_index => $symbol_indices_array): ?>
                    
                    <?php
                    $k = count($symbol_indices_array);
                    // Получаем массив предопределенных макетов (слотов)
                    $layout_slots = getSymbolLayoutSlots($k);
                    
                    // Перемешиваем массив слотов. Это гарантирует, что каждый символ 
                    // получит случайный слот (размер и позицию).
                    shuffle($layout_slots);
                    ?>

                    <div class="dobble-card"> 
                        <div class="card-header no-print">Carte <?= $card_index + 1 ?></div>
                        
                        <?php 
                        foreach ($symbol_indices_array as $key => $symbol_db_index): 
                            // Проверяем, что слот существует для данного ключа
                            if (!isset($layout_slots[$key])) {
                                // Это может случиться, если 'k' не соответствует количеству сгенерированных слотов
                                // В данном случае, просто пропустим символ или используем дефолтный слот
                                continue; 
                            }
                            $symbol_data = $symbols_to_use[$symbol_db_index];
                            $slot = $layout_slots[$key];
                            
                            // Добавляем случайный поворот
                            $rotation = rand(-180, 180);      
                            
                            // Собираем стиль: position: absolute с ДЕТЕРМИНИРОВАННЫМИ координатами из слота.
                            // Теперь размеры и координаты top/left рассчитываются так, чтобы символ 
                            // был центрирован относительно своей точки орбиты.
                            $style = "position: absolute; " .
                                     "width: {$slot['size']}%; " .
                                     "height: {$slot['size']}%; " . // Гарантируем квадратность
                                     "top: {$slot['top']}%; " .
                                     "left: {$slot['left']}%; " .
                                     "transform: rotate({$rotation}deg); " .
                                     "z-index: {$slot['z_index']};"; // Управляем z-index для порядка наложения
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
            <div class="symbol-legend-container">
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
                                <td><?= htmlspecialchars($uploadDir . $symbol_data['image_name']) ?></td>
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

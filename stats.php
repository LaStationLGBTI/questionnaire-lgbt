<?php
// Connexion admin (POST login) : on démarre la session via auth.php AVANT session_start(),
// pour que le cookie régénéré à la connexion ait les mêmes paramètres durcis que console.php
// (HttpOnly + SameSite=Strict). Les autres requêtes gardent le démarrage habituel.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    require_once __DIR__ . '/auth.php';
}
// Même durée de vie de session que index.php / game.php : sinon le GC par défaut (1440 s)
// peut supprimer une session hôte/questionnaire inactive.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', 31536000);
    session_start();
}
require_once 'conf.php';
// Accès au site par clé (access.php) : les statistiques exigent aussi une clé valide.
require_once __DIR__ . '/access.php';
// Mode admin : même session / même connexion que console.php (auth.php, CSRF).
require_once __DIR__ . '/auth.php';
$access_error = access_handle_post();
$admin_login_error = admin_handle_auth('stats.php', 'stats.php');
// Quitter le mode admin : retire seulement le drapeau admin (la clé visiteur reste en session).
// NB : c'est la même connexion que console.php, qui est donc aussi déconnectée.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stats_admin_exit'])) {
    admin_require_csrf();
    unset($_SESSION['is_logged_in'], $_SESSION['admin_last_seen']);
    header('Location: stats.php');
    exit();
}
$is_admin = admin_is_logged_in();
$want_admin = isset($_GET['admin']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']));
$visitor_ok = access_session_valid();
$stats_lang = isset($_SESSION['language']) && in_array($_SESSION['language'], ['fr', 'de', 'en'], true) ? $_SESSION['language'] : 'fr';
// Un admin connecté peut consulter sans clé de visiteur ; sinon gate (sauf demande de connexion admin).
if (!$visitor_ok && !$is_admin && !$want_admin) {
    access_render_gate($stats_lang, $access_error);
}

// Textes de l'interface (trilingue, même convention : $_SESSION['language'] fr/de/en).
$L_all = [
    'fr' => [
        'group_note' => 'Résultats du groupe : ', 'module' => 'Module :', 'total' => 'Total des réponses (Module {m}) : {n}',
        'from' => 'Du', 'to' => 'au', 'today' => "Aujourd'hui", 'd7' => '7 derniers jours', 'd30' => '30 derniers jours', 'all_time' => 'Tout',
        'respondents' => '{n} participant(e)s', 'range' => 'réponses du {a} au {b}', 'range_one' => 'réponses du {a}', 'no_dates' => 'dates non disponibles',
        'per_day' => 'Réponses par jour', 'admin' => 'Admin', 'admin_exit' => 'Quitter le mode admin', 'admin_group' => 'Groupe :',
        'all_keys' => 'Toutes les clés (global)', 'no_key' => 'Sans clé', 'deleted_key' => 'clé supprimée', 'rows' => 'rép.',
        'admin_mode' => 'Mode admin', 'login_title' => 'Connexion administrateur', 'login_user' => 'Identifiant', 'login_pass' => 'Mot de passe',
        'login_btn' => 'Se connecter', 'back' => 'Retour aux statistiques', 'err_access' => 'Accès refusé.', 'err_db' => 'Erreur base de données.',
        'date_unsupported' => 'Le filtre de dates n\'est pas disponible (colonne created_at absente).',
    ],
    'de' => [
        'group_note' => 'Ergebnisse der Gruppe: ', 'module' => 'Modul:', 'total' => 'Antworten insgesamt (Modul {m}): {n}',
        'from' => 'Von', 'to' => 'bis', 'today' => 'Heute', 'd7' => 'Letzte 7 Tage', 'd30' => 'Letzte 30 Tage', 'all_time' => 'Alle',
        'respondents' => '{n} Teilnehmende', 'range' => 'Antworten vom {a} bis {b}', 'range_one' => 'Antworten vom {a}', 'no_dates' => 'Daten nicht verfügbar',
        'per_day' => 'Antworten pro Tag', 'admin' => 'Admin', 'admin_exit' => 'Admin-Modus verlassen', 'admin_group' => 'Gruppe:',
        'all_keys' => 'Alle Schlüssel (global)', 'no_key' => 'Ohne Schlüssel', 'deleted_key' => 'Schlüssel gelöscht', 'rows' => 'Antw.',
        'admin_mode' => 'Admin-Modus', 'login_title' => 'Administrator-Anmeldung', 'login_user' => 'Benutzername', 'login_pass' => 'Passwort',
        'login_btn' => 'Anmelden', 'back' => 'Zurück zur Statistik', 'err_access' => 'Zugriff verweigert.', 'err_db' => 'Datenbankfehler.',
        'date_unsupported' => 'Der Datumsfilter ist nicht verfügbar (Spalte created_at fehlt).',
    ],
    'en' => [
        'group_note' => 'Results for group: ', 'module' => 'Module:', 'total' => 'Total responses (Module {m}): {n}',
        'from' => 'From', 'to' => 'to', 'today' => 'Today', 'd7' => 'Last 7 days', 'd30' => 'Last 30 days', 'all_time' => 'All time',
        'respondents' => '{n} respondent(s)', 'range' => 'responses from {a} to {b}', 'range_one' => 'responses on {a}', 'no_dates' => 'dates unavailable',
        'per_day' => 'Responses per day', 'admin' => 'Admin', 'admin_exit' => 'Leave admin mode', 'admin_group' => 'Group:',
        'all_keys' => 'All keys (global)', 'no_key' => 'Without key', 'deleted_key' => 'deleted key', 'rows' => 'resp.',
        'admin_mode' => 'Admin mode', 'login_title' => 'Administrator login', 'login_user' => 'Username', 'login_pass' => 'Password',
        'login_btn' => 'Log in', 'back' => 'Back to statistics', 'err_access' => 'Access denied.', 'err_db' => 'Database error.',
        'date_unsupported' => 'Date filter unavailable (created_at column missing).',
    ],
];
$L = $L_all[$stats_lang];

// Demande de connexion admin (non connecté) : petit formulaire, puis retour sur stats.php.
if ($want_admin && !$is_admin) {
    ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($stats_lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($L['login_title']) ?></title>
<style>
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f7f4fb; font-family: Arial, sans-serif; color: #333; }
    .box { background: #fff; border: 1px solid #e4dcf3; border-radius: 12px; padding: 24px 28px; width: 100%; max-width: 340px; box-shadow: 0 4px 14px rgba(138,123,244,0.15); }
    h1 { font-size: 20px; margin: 0 0 16px; color: #5b4fc4; }
    label { display: block; font-size: 14px; margin: 10px 0 4px; }
    input[type=text], input[type=password] { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; }
    button { margin-top: 16px; width: 100%; padding: 10px; border: 0; border-radius: 8px; background: #8a7bf4; color: #fff; font-size: 15px; cursor: pointer; }
    .err { background: #f3ebf2; border: 1px solid #e9c4ce; color: #8a3a52; border-radius: 8px; padding: 8px 10px; font-size: 14px; margin-bottom: 8px; }
    a { display: inline-block; margin-top: 14px; color: #5b4fc4; font-size: 14px; }
</style>
</head>
<body>
<form class="box" method="post" action="stats.php">
    <h1><?= htmlspecialchars($L['login_title']) ?></h1>
    <?php if ($admin_login_error): ?><div class="err"><?= htmlspecialchars($admin_login_error) ?></div><?php endif; ?>
    <?= csrf_input() ?>
    <label for="identifiant"><?= htmlspecialchars($L['login_user']) ?></label>
    <input type="text" id="identifiant" name="identifiant" autocomplete="username" required>
    <label for="mot_de_passe"><?= htmlspecialchars($L['login_pass']) ?></label>
    <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="current-password" required>
    <button type="submit" name="login" value="1"><?= htmlspecialchars($L['login_btn']) ?></button>
    <?php if ($visitor_ok): ?><a href="stats.php"><?= htmlspecialchars($L['back']) ?></a><?php endif; ?>
</form>
</body>
</html>
    <?php
    exit();
}

// Liste des modules (anciennement "niveaux") pour le sélecteur.
$modules = [];
$session_key = ($visitor_ok && isset($_SESSION['access_key'])) ? access_normalize_key($_SESSION['access_key']) : '';
$group_label = $session_key !== '' ? access_format_key($session_key) : ''; // repli : clé formatée XXXX-XXXX-XXXX
$admin_groups = []; // admin : valeur => libellé (clés normalisées, '__all__', '__none__')
$admin_selected = '__all__';
try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $levels = $pdo->query("SELECT DISTINCT level FROM GSDatabase ORDER BY level ASC")->fetchAll(PDO::FETCH_COLUMN);
    $titles = $pdo->query("SELECT level, titre FROM GSDatabaseT")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($levels as $lvl) {
        $modules[$lvl] = isset($titles[$lvl]) ? $titles[$lvl] : '';
    }
    // Catégorisation par groupe : libellé de la clé d'accès courante, si renseigné par l'admin.
    if ($session_key !== '') {
        $key_stmt = $pdo->prepare("SELECT label FROM access_keys WHERE access_key = ?");
        $key_stmt->execute([$session_key]);
        $key_label = $key_stmt->fetchColumn();
        if ($key_label !== false && $key_label !== '') {
            $group_label = $key_label;
        }
    }
    // Admin : liste des groupes (toutes les clés + clés présentes dans les réponses + sans clé).
    if ($is_admin) {
        $counts = [];
        $null_count = 0;
        if (access_ensure_responses_key_column($pdo)) {
            foreach ($pdo->query("SELECT access_key, COUNT(*) AS n FROM GSDatabaseR GROUP BY access_key")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['access_key'] === null || $r['access_key'] === '') { $null_count += (int) $r['n']; }
                else { $counts[$r['access_key']] = (int) $r['n']; }
            }
        }
        $admin_groups['__all__'] = $L['all_keys'] . ' (' . (array_sum($counts) + $null_count) . ' ' . $L['rows'] . ')';
        $admin_groups['__none__'] = $L['no_key'] . ' (' . $null_count . ' ' . $L['rows'] . ')';
        $known = [];
        try {
            $known = $pdo->query("SELECT access_key, label FROM access_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $known = [];
        }
        foreach ($known as $k => $lbl) {
            $k = access_normalize_key($k);
            if ($k === '') continue;
            $n = isset($counts[$k]) ? $counts[$k] : 0;
            $admin_groups[$k] = ($lbl !== null && $lbl !== '' ? $lbl . ' - ' : '') . access_format_key($k) . ' (' . $n . ' ' . $L['rows'] . ')';
        }
        foreach ($counts as $k => $n) {
            if (isset($admin_groups[$k])) continue;
            $admin_groups[$k] = access_format_key($k) . ' - ' . $L['deleted_key'] . ' (' . $n . ' ' . $L['rows'] . ')';
        }
    }
} catch (PDOException $e) {
    $modules = [];
}
if ($is_admin) {
    if (!isset($admin_groups['__all__'])) {
        $admin_groups = ['__all__' => $L['all_keys'], '__none__' => $L['no_key']] + $admin_groups;
    }
    if ($session_key !== '') {
        if (!isset($admin_groups[$session_key])) {
            $admin_groups[$session_key] = ($group_label !== '' ? $group_label : access_format_key($session_key));
        }
        $admin_selected = $session_key;
    }
}

$group_note = $is_admin ? $L['admin_mode'] : $L['group_note'] . $group_label;
// Module sélectionné par défaut : 2 s'il existe, sinon le premier disponible.
if (isset($modules[2])) {
    $selected_module = 2;
} elseif (count($modules)) {
    reset($modules);
    $selected_module = key($modules);
} else {
    $selected_module = 2;
}
?>
<!DOCTYPE html>
<html style="font-size: 16px;" lang="<?= htmlspecialchars($stats_lang) ?>">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>Statistiques</title>
    <link rel="stylesheet" href="nicepage.css" media="screen">
    <script src="js/chart.umd.min.js"></script>
    <link rel="stylesheet" href="Question.css" media="screen">
    <style>
        .chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            margin-top: 20px;
        }
        .chart-box {
            width: 90%;
            max-width: 80em;
            height: auto;
            min-height: 20em;
            margin-bottom: 2em;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            position: relative;
        }
        .chart-number {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 5px 10px;
            border-radius: 4px;
            z-index: 10;
        }
        .chart-box canvas {
            max-height: 20em !important;
            width: 100% !important;
        }
        .legend-container {
            margin-top: 15px;
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin: 3px 0;
            font-size: 14px;
            width: 100%;
            justify-content: flex-start;
        }
        .legend-label {
            flex-shrink: 1;
            word-wrap: break-word;
            white-space: normal;
            max-width: none;
            text-align: left;
        }
        .legend-color {
            width: 14px;
            height: 14px;
            margin-right: 8px;
            display: inline-block;
            flex-shrink: 0;
        }
        .count {
            margin-left: 8px;
            flex-shrink: 0;
            font-weight: bold;
        }
        .count-box {
            background-color: #f8f9fa;
            border: 2px solid #8a7bf4;
            border-radius: 10px;
            padding: 10px 20px;
            margin: 10px auto;
            width: fit-content;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        #totalCountText {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .stats-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 0.6em auto;
            font-size: 14px;
        }
        .stats-toolbar input[type=date], .stats-toolbar select {
            padding: 6px 8px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background: #fff;
        }
        .stats-btn {
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 999px;
            border: 1px solid #d6cff7;
            background: #f4eefb;
            color: #4a3fa8;
            cursor: pointer;
            text-decoration: none;
        }
        .stats-btn:hover, .stats-btn.active {
            background: #8a7bf4;
            border-color: #8a7bf4;
            color: #fff;
        }
        .admin-bar {
            background: #f4eefb;
            border: 1px solid #d6cff7;
            border-radius: 10px;
            padding: 8px 14px;
            width: fit-content;
            max-width: 95%;
        }
        .admin-bar select { max-width: 70vw; }
        #summaryText {
            font-size: 14px;
            color: #555;
            margin-top: 4px;
        }
        #dayChartBox {
            width: 90%;
            max-width: 80em;
            margin: 10px auto 0;
            border: 1px solid #e4dcf3;
            border-radius: 8px;
            padding: 10px 15px;
            box-sizing: border-box;
        }
        #dayChartBox canvas { max-height: 12em !important; width: 100% !important; }
        @media print {
            #footer-placeholder, .stats-toolbar, .admin-bar { display: none !important; }
            .count-box { box-shadow: none !important; }
            .chart-box { page-break-inside: avoid; box-shadow: none !important; border: 1px solid #ccc !important; width: 100% !important; }
            body, .chart-box, .legend-color { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            body, .u-group-1 .u-container-layout-1 { background-color: #fff !important; }
            section, .u-container-layout { min-height: auto !important; }
            .chart-number { color: #000 !important; background-color: #fff !important; }
        }
    </style>
</head>
<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="fr" style="height:100%">
    <section id="sec-089e">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">

                    <?php if ($is_admin): ?>
                    <div class="stats-toolbar admin-bar">
                        <label for="groupSelect" style="font-weight:bold;"><?= htmlspecialchars($L['admin_group']) ?></label>
                        <select id="groupSelect">
                            <?php foreach ($admin_groups as $gval => $gtext): ?>
                                <option value="<?= htmlspecialchars($gval) ?>" <?= ((string) $gval === $admin_selected) ? 'selected' : '' ?>><?= htmlspecialchars($gtext) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <form method="post" action="stats.php" style="display:inline; margin:0;">
                            <?= csrf_input() ?>
                            <button type="submit" name="stats_admin_exit" value="1" class="stats-btn"><?= htmlspecialchars($L['admin_exit']) ?></button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div style="text-align:right; max-width:80em; width:90%; margin:0.6em auto 0;">
                        <a href="stats.php?admin=1" class="stats-btn"><?= htmlspecialchars($L['admin']) ?></a>
                    </div>
                    <?php endif; ?>

                    <div style="margin: 1em auto;">
                        <label for="moduleSelect" style="font-weight:bold; margin-right:8px;"><?= htmlspecialchars($L['module']) ?></label>
                        <select id="moduleSelect" style="padding:8px 12px; font-size:16px; border-radius:8px; border:1px solid #ccc;">
                            <?php foreach ($modules as $lvl => $titre): ?>
                                <option value="<?= htmlspecialchars($lvl) ?>" <?= ($lvl == $selected_module) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars('Module ' . $lvl . ($titre !== '' ? ' : ' . $titre : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stats-toolbar">
                        <label for="dateFrom"><?= htmlspecialchars($L['from']) ?></label>
                        <input type="date" id="dateFrom">
                        <label for="dateTo"><?= htmlspecialchars($L['to']) ?></label>
                        <input type="date" id="dateTo">
                        <button type="button" class="stats-btn" data-preset="today"><?= htmlspecialchars($L['today']) ?></button>
                        <button type="button" class="stats-btn" data-preset="7"><?= htmlspecialchars($L['d7']) ?></button>
                        <button type="button" class="stats-btn" data-preset="30"><?= htmlspecialchars($L['d30']) ?></button>
                        <button type="button" class="stats-btn active" data-preset="all"><?= htmlspecialchars($L['all_time']) ?></button>
                    </div>

                    <div id="groupNote" style="margin:0 0 0.6em; font-size:14px; color:#555;">
                        <?= htmlspecialchars($group_note) ?>
                    </div>
                    <div id="totalCount" class="count-box">
                        <span id="totalCountText"><?= htmlspecialchars(str_replace(['{m}', '{n}'], [$selected_module, '0'], $L['total'])) ?></span>
                        <div id="summaryText"></div>
                    </div>
                    <div id="dayChartBox" style="display:none;">
                        <div style="font-weight:bold; font-size:14px; margin-bottom:6px;"><?= htmlspecialchars($L['per_day']) ?></div>
                        <canvas id="dayChart"></canvas>
                    </div>
                    <div id="chartsContainer" class="chart-container"></div>
                </div>
            </div>
        </div>
    </section>

    <script>
        const chartInstances = {};
        const L = <?= json_encode($L, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
        let dayChart = null;
        let requestSeq = 0; // ignore les réponses obsolètes (changements rapides de filtres)

        function fmt(tpl, vals) {
            return tpl.replace(/\{(\w+)\}/g, (m, k) => (vals[k] !== undefined ? vals[k] : m));
        }
        function ymd(d) {
            const p = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        }
        function frDate(s) {
            // AAAA-MM-JJ -> JJ.MM.AAAA (lisible en fr/de/en)
            const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
            return m ? `${m[3]}.${m[2]}.${m[1]}` : '';
        }
        function setPreset(preset) {
            const from = document.getElementById('dateFrom');
            const to = document.getElementById('dateTo');
            const today = new Date();
            if (preset === 'all') {
                from.value = ''; to.value = '';
            } else if (preset === 'today') {
                from.value = ymd(today); to.value = ymd(today);
            } else {
                const start = new Date(today);
                start.setDate(start.getDate() - (parseInt(preset, 10) - 1));
                from.value = ymd(start); to.value = ymd(today);
            }
            markPreset(preset);
            loadStats();
        }
        function markPreset(preset) {
            document.querySelectorAll('[data-preset]').forEach(b => b.classList.toggle('active', b.dataset.preset === preset));
        }
        function renderSummary(data, total) {
            const parts = [fmt(L.respondents, { n: total })];
            if (data.dateSupported === false) {
                parts.push(L.no_dates);
            } else if (data.firstDate && data.lastDate) {
                parts.push(data.firstDate === data.lastDate
                    ? fmt(L.range_one, { a: frDate(data.firstDate) })
                    : fmt(L.range, { a: frDate(data.firstDate), b: frDate(data.lastDate) }));
            }
            let txt = parts.join(' - ');
            if (data.dateSupported === false && (data.from || data.to || document.getElementById('dateFrom').value || document.getElementById('dateTo').value)) {
                txt += ' - ' + L.date_unsupported;
            }
            document.getElementById('summaryText').textContent = txt;

            const box = document.getElementById('dayChartBox');
            if (dayChart) { dayChart.destroy(); dayChart = null; }
            const perDay = data.perDay || {};
            const days = Object.keys(perDay).sort();
            if (days.length < 2) { box.style.display = 'none'; return; }
            box.style.display = '';
            dayChart = new Chart(document.getElementById('dayChart'), {
                type: 'bar',
                data: {
                    labels: days.map(frDate),
                    datasets: [{ label: L.per_day, data: days.map(d => perDay[d]), backgroundColor: '#8a7bf4', borderRadius: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        function loadStats() {
            const container = document.getElementById('chartsContainer');
            container.innerHTML = '';
            let chartCounter = 0; // Compteur pour numérotation
            Object.keys(chartInstances).forEach(key => {
                chartInstances[key].destroy();
                delete chartInstances[key];
            });

            const selectedModule = document.getElementById('moduleSelect').value;
            const params = new URLSearchParams({ level: selectedModule });
            const dFrom = document.getElementById('dateFrom').value;
            const dTo = document.getElementById('dateTo').value;
            if (dFrom) params.set('from', dFrom);
            if (dTo) params.set('to', dTo);
            // Le groupe n'est pris en compte côté serveur QUE pour un admin connecté.
            const groupSelect = document.getElementById('groupSelect');
            if (isAdmin && groupSelect) {
                params.set('group', groupSelect.value);
                const opt = groupSelect.options[groupSelect.selectedIndex];
                document.getElementById('groupNote').textContent = L.admin_mode + ' - ' + (opt ? opt.textContent : '');
            }
            const seq = ++requestSeq;
            fetch(`stats_getdata.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (seq !== requestSeq) return; // réponse obsolète
                    if (isAdmin && !data.error && (!data.scope || !data.scope.admin)) { location.reload(); return; } // session admin expirée
                    const totalResponses = data.totalResponses || 0;
                    document.getElementById('totalCountText').textContent = fmt(L.total, { m: selectedModule, n: totalResponses });
                    if (data.error) {
                        document.getElementById('summaryText').textContent = data.error === 'access_key_required' ? L.err_access : L.err_db;
                        document.getElementById('dayChartBox').style.display = 'none';
                        return;
                    }
                    renderSummary(data, totalResponses);

                    (data.formattedData || []).forEach(item => {
                        chartCounter++; // Augmenter le compteur pour chaque nouveau graphique
                        if (item.type === 'qcm' || item.type === 'echelle') {
                            createPieChart(item.question, item.responses, item.id, chartCounter);
                        } else if (item.type === 'mct') {
                            createStackedBarChart(item.sub_questions, item.responses, item.id, item.question, chartCounter);
                        } else if (item.type === 'lien') {
                            createStackedBarChart(item.sub_questions, item.sub_responses, item.id, item.question, chartCounter);
                        }
                    });

                    (data.answers || []).forEach(item => {
                        const questionId = parseInt(item.question);
                        const chart = chartInstances[questionId];
                        if (!chart) return;

                        if (item.response) {
                            const dataIndex = parseInt(item.response) - 1;
                            if (chart.data.datasets[0].data[dataIndex] !== undefined) {
                                chart.data.datasets[0].data[dataIndex]++;
                            }
                        } else if (item.subresponse && item.subquestion) {
                            const subResponses = item.subresponse.split(",").map(Number);
                            const subQuestions = item.subquestion.split(",").map(Number);

                            subQuestions.forEach((subQuestionIndex, i) => {
                                const responseIndex = subResponses[i] - 1;
                                const questionIndex = subQuestionIndex - 1;

                                if (chart.data.datasets[responseIndex] && chart.data.datasets[responseIndex].data[questionIndex] !== undefined) {
                                    chart.data.datasets[responseIndex].data[questionIndex]++;
                                }
                            });
                        }
                    });

                    Object.keys(chartInstances).forEach(chartId => {
                        const chart = chartInstances[chartId];
                        chart.update();

                        const legendContainer = document.querySelector(`#chart_${chartId}`).parentElement.querySelector('.legend-container');
                        if (!legendContainer) return;
                        const legendItems = legendContainer.querySelectorAll('.legend-item');

                        if (chart.config.type === 'pie') {
                            legendItems.forEach((legendItem, idx) => {
                                const countSpan = legendItem.querySelector(".count");
                                if (countSpan) {
                                    countSpan.textContent = `(${chart.data.datasets[0].data[idx]})`;
                                }
                            });
                        } else if (chart.config.type === 'bar') {
                            legendItems.forEach((legendItem, idx) => {
                                const countSpan = legendItem.querySelector(".count");
                                if (countSpan && chart.data.datasets[idx]) {
                                    const total = chart.data.datasets[idx].data.reduce((sum, val) => sum + val, 0);
                                    countSpan.textContent = `(${total})`;
                                }
                            });
                        }
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function createPieChart(question, responses, chartIndex, chartNumber) {
            const validResponses = responses.filter(response => response !== "null");
            let container = document.getElementById("chartsContainer");
            let div = document.createElement("div");
            div.className = "chart-box";
            let numberLabel = document.createElement("div");
            numberLabel.className = "chart-number";
            numberLabel.textContent = chartNumber; // Numéro du graphique
            div.appendChild(numberLabel);
            let questionLabel = document.createElement("div");
            questionLabel.innerHTML = `<b>Question: ${question}</b>`;
            questionLabel.style.textAlign = "center";
            questionLabel.style.marginBottom = "10px";
            div.appendChild(questionLabel);
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            div.appendChild(canvas);
            const backgroundColors = ["Blue", "#FF0080", "Yellow", "Orange", "Red", "Purple", "Green"];
            const chart = new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: validResponses,
                    datasets: [{
                        data: validResponses.map(() => 0),
                        backgroundColor: backgroundColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true }
                    }
                }
            });
            chartInstances[chartIndex] = chart;
            let legendContainer = document.createElement("div");
            legendContainer.className = "legend-container";
            validResponses.forEach((response, index) => {
                let legendItem = document.createElement("div");
                legendItem.className = "legend-item";
                let colorBox = document.createElement("span");
                colorBox.className = "legend-color";
                colorBox.style.backgroundColor = backgroundColors[index % backgroundColors.length];
                legendItem.appendChild(colorBox);
                let label = document.createElement("span");
                label.className = "legend-label";
                label.textContent = response;
                legendItem.appendChild(label);
                let countSpan = document.createElement("span");
                countSpan.className = "count";
                countSpan.textContent = `(0)`;
                legendItem.appendChild(countSpan);
                legendContainer.appendChild(legendItem);
            });
            div.appendChild(legendContainer);
            container.appendChild(div);
        }

        function createStackedBarChart(subQuestions, responses, chartIndex, question, chartNumber) {
            let container = document.getElementById("chartsContainer");
            let div = document.createElement("div");
            div.className = "chart-box";
            let numberLabel = document.createElement("div");
            numberLabel.className = "chart-number";
            numberLabel.textContent = chartNumber; // Numéro du graphique
            div.appendChild(numberLabel);
            let questionLabel = document.createElement("div");
            questionLabel.innerHTML = `<b>Question : ${question}</b>`;
            questionLabel.style.textAlign = "center";
            questionLabel.style.marginBottom = "10px";
            div.appendChild(questionLabel);
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            div.appendChild(canvas);
            container.appendChild(div);
            let datasets = responses.map((response, index) => ({
                label: response,
                data: subQuestions.map(() => 0),
                backgroundColor: `hsl(${index * 137.508}, 70%, 50%)`
            }));
            const chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: subQuestions,
                    datasets: datasets
                },
                options: {
                    scales: { x: { stacked: true }, y: { stacked: true } },
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
            chartInstances[chartIndex] = chart;
            let legendContainer = document.createElement("div");
            legendContainer.className = "legend-container";
            responses.forEach((response, index) => {
                let legendItem = document.createElement("div");
                legendItem.className = "legend-item";
                let colorBox = document.createElement("span");
                colorBox.className = "legend-color";
                colorBox.style.backgroundColor = datasets[index].backgroundColor;
                legendItem.appendChild(colorBox);
                let label = document.createElement("span");
                label.className = "legend-label";
                label.textContent = response;
                legendItem.appendChild(label);
                let countSpan = document.createElement("span");
                countSpan.className = "count";
                countSpan.textContent = `(0)`;
                legendItem.appendChild(countSpan);
                legendContainer.appendChild(legendItem);
            });
            div.appendChild(legendContainer);
        }

        // Recharger les statistiques quand on change de module
        document.getElementById('moduleSelect').addEventListener('change', loadStats);
        // Filtres de dates : saisie manuelle ou raccourcis
        ['dateFrom', 'dateTo'].forEach(id => document.getElementById(id).addEventListener('change', () => { markPreset(''); loadStats(); }));
        document.querySelectorAll('[data-preset]').forEach(b => b.addEventListener('click', () => setPreset(b.dataset.preset)));
        // Admin : choix du groupe (clé)
        if (document.getElementById('groupSelect')) {
            document.getElementById('groupSelect').addEventListener('change', loadStats);
        }

        loadStats(); // Chargement des statistiques à l'ouverture de la page
    </script>
    <script>
        setTimeout(() => {
            const section = document.querySelector('section');
            const newDiv = document.createElement('div');
            newDiv.id = "footer-placeholder";
            section.insertAdjacentElement('afterend', newDiv);
            fetch('pages/footer.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('footer-placeholder').innerHTML = data;
                });
        }, 100);
    </script>
</body>
</html>



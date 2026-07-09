<?php
/**
 * access.php — accès au site par clé (licence à durée limitée).
 *
 * Principe :
 *   - L'administrateur génère des clés dans manage_keys.php (durée de vie, prolongation, révocation).
 *   - Tout visiteur doit saisir une clé valide avant de voir / lancer les modules (index.php).
 *   - La clé est revérifiée périodiquement (AJAX access.php?action=status). Si elle expire en cours
 *     de route : le questionnaire EN COURS peut être terminé, mais aucun nouveau module ne peut
 *     être choisi (bloqué côté serveur ET côté client).
 *   - Les joueurs du Mode Jeu (play.php, rejoints par QR/PIN) ne saisissent JAMAIS de clé.
 *   - Anti-force-brute : 3 tentatives ratées par IP -> blocage temporaire (fichier, comme auth.php).
 *
 * Usage dans une page à protéger (après session_start) :
 *   require_once __DIR__ . '/access.php';
 *   $access_error = access_handle_post();          // traite la saisie de clé (POST)
 *   $access_valid = access_session_valid();        // clé en session encore valide ?
 *   if (!$access_valid && !isset($_SESSION['start'])) { access_render_gate($lang, $access_error); }
 *
 * Endpoint AJAX (appel direct du fichier) :
 *   access.php?action=status  ->  {"valid":true|false}   (revérification en base)
 */

require_once __DIR__ . '/conf.php';

// Session : démarrée par la page appelante en général ; on la démarre si accès direct (AJAX).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---------------------------------------------------------------------------
//  Base de données : table access_keys (créée automatiquement au besoin)
// ---------------------------------------------------------------------------

function access_pdo() {
    global $DB_HOSTNAME, $DB_NAME, $DB_USERNAME, $DB_PASSWORD;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

/** Crée la table si elle n'existe pas encore (déploiement sans migration manuelle). */
function access_ensure_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        access_key VARCHAR(32) NOT NULL UNIQUE,
        label VARCHAR(190) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        revoked TINYINT(1) NOT NULL DEFAULT 0,
        last_used_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
}

/** Crée la table du journal des connexions par clé si absente. */
function access_ensure_log_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        access_key VARCHAR(32) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        KEY idx_key (access_key),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
}

// Durées de conservation (politique de confidentialité, mentions.php) :
// journal IP 12 mois, réponses aux questionnaires 3 ans.
const ACCESS_LOG_RETENTION_MONTHS = 12;
const RESPONSES_RETENTION_YEARS   = 3;

/**
 * Journalise une entrée réussie par clé (IP + user-agent). Jamais bloquant.
 * NB : derrière un reverse-proxy, REMOTE_ADDR est l'IP du proxy ; on garde
 * REMOTE_ADDR car X-Forwarded-For est falsifiable par le client.
 */
function access_log_entry($key) {
    try {
        $pdo = access_pdo();
        access_ensure_log_table($pdo);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        $stmt = $pdo->prepare("INSERT INTO access_log (access_key, ip, user_agent, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([access_normalize_key($key), $ip, $ua]);
        access_retention_purge($pdo);
    } catch (PDOException $e) {
        error_log('[access] log error: ' . $e->getMessage());
    }
}

/**
 * Applique les durées de conservation RGPD (déclenchée à chaque entrée par clé ;
 * requêtes indexées sur created_at, chaque étape est non bloquante) :
 *  - journal IP : purge après ACCESS_LOG_RETENTION_MONTHS ;
 *  - réponses : purge après RESPONSES_RETENTION_YEARS (les lignes historiques sans
 *    created_at, antérieures à la colonne, sont conservées : date inconnue) ;
 *  - repmail : vidage des e-mails historiques (les nouveaux ne sont plus stockés).
 */
function access_retention_purge($pdo) {
    try {
        $pdo->exec("DELETE FROM access_log WHERE created_at < DATE_SUB(NOW(), INTERVAL " . ACCESS_LOG_RETENTION_MONTHS . " MONTH)");
    } catch (PDOException $e) {
        error_log('[access] purge access_log : ' . $e->getMessage());
    }
    try {
        // La colonne created_at n'existe qu'après sa migration (console d'admin, onglet Base de données)
        $pdo->exec("DELETE FROM GSDatabaseR WHERE created_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL " . RESPONSES_RETENTION_YEARS . " YEAR)");
    } catch (PDOException $e) {
        error_log('[access] purge GSDatabaseR : ' . $e->getMessage());
    }
    try {
        $pdo->exec("UPDATE GSDatabaseR SET repmail = '' WHERE repmail <> ''");
    } catch (PDOException $e) {
        error_log('[access] purge repmail : ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
//  Clés : normalisation, génération, vérification
// ---------------------------------------------------------------------------

/** Normalise la saisie utilisateur : majuscules, sans tirets/espaces. */
function access_normalize_key($raw) {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $raw));
}

/** Format d'affichage : XXXX-XXXX-XXXX. */
function access_format_key($key) {
    return trim(chunk_split($key, 4, '-'), '-');
}

/**
 * Génère une clé aléatoire de 12 caractères (alphabet sans caractères ambigus 0/O/1/I).
 * Renvoie la forme normalisée (sans tirets).
 */
function access_generate_key() {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $key = '';
    for ($i = 0; $i < 12; $i++) {
        if (function_exists('random_int')) {
            $n = random_int(0, strlen($alphabet) - 1);
        } else {
            $n = mt_rand(0, strlen($alphabet) - 1); // fallback vieux PHP (CLI local 5.6)
        }
        $key .= $alphabet[$n];
    }
    return $key;
}

/**
 * Statut d'une clé (vérification en base) :
 *   'ok' | 'expired' | 'revoked' | 'unknown' | 'error'
 */
function access_key_status($key) {
    $key = access_normalize_key($key);
    if ($key === '') return 'unknown';
    try {
        $pdo = access_pdo();
        access_ensure_table($pdo);
        $stmt = $pdo->prepare("SELECT revoked, expires_at FROM access_keys WHERE access_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[access] DB error: ' . $e->getMessage());
        return 'error';
    }
    if (!$row) return 'unknown';
    if (!empty($row['revoked'])) return 'revoked';
    if (strtotime($row['expires_at']) <= time()) return 'expired';
    // Trace de dernière utilisation (au mieux ; jamais bloquant).
    try {
        $pdo->prepare("UPDATE access_keys SET last_used_at = NOW() WHERE access_key = ?")->execute([$key]);
    } catch (PDOException $e) { /* non bloquant */ }
    return 'ok';
}

// ---------------------------------------------------------------------------
//  Anti-force-brute : 3 tentatives par IP, blocage 15 min (fichier temporaire)
// ---------------------------------------------------------------------------

const ACCESS_MAX_ATTEMPTS   = 3;    // tentatives de clé autorisées...
const ACCESS_WINDOW_SECONDS = 900;  // ...dans cette fenêtre glissante (15 min)
const ACCESS_LOCK_SECONDS   = 900;  // durée du blocage une fois le seuil atteint (15 min)

function access_throttle_file() {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgbt_access_throttle';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    return $dir . DIRECTORY_SEPARATOR . sha1($ip) . '.json';
}

function access_throttle_read() {
    $f = access_throttle_file();
    if (!is_file($f)) return ['count' => 0, 'first' => 0, 'locked_until' => 0];
    $raw = @file_get_contents($f);
    $d = $raw ? json_decode($raw, true) : null;
    if (!is_array($d)) return ['count' => 0, 'first' => 0, 'locked_until' => 0];
    return $d + ['count' => 0, 'first' => 0, 'locked_until' => 0];
}

/** ['blocked' => bool, 'remaining' => secondes de blocage restantes, 'attempts_left' => int]. */
function access_throttled() {
    $d = access_throttle_read();
    $now = time();
    if (!empty($d['locked_until']) && $d['locked_until'] > $now) {
        return ['blocked' => true, 'remaining' => $d['locked_until'] - $now, 'attempts_left' => 0];
    }
    $count = (!empty($d['first']) && ($now - $d['first']) <= ACCESS_WINDOW_SECONDS) ? (int) $d['count'] : 0;
    return ['blocked' => false, 'remaining' => 0, 'attempts_left' => max(0, ACCESS_MAX_ATTEMPTS - $count)];
}

function access_register_failure() {
    $d = access_throttle_read();
    $now = time();
    if (empty($d['first']) || ($now - $d['first']) > ACCESS_WINDOW_SECONDS) {
        $d['first'] = $now;
        $d['count'] = 0;
    }
    $d['count']++;
    if ($d['count'] >= ACCESS_MAX_ATTEMPTS) {
        $d['locked_until'] = $now + ACCESS_LOCK_SECONDS;
    }
    @file_put_contents(access_throttle_file(), json_encode($d), LOCK_EX);
}

function access_throttle_clear() {
    $f = access_throttle_file();
    if (is_file($f)) { @unlink($f); }
}

// ---------------------------------------------------------------------------
//  État de session
// ---------------------------------------------------------------------------

/** Intervalle (secondes) entre deux revérifications en base de la clé en session. */
const ACCESS_RECHECK_SECONDS = 180;

// « Se souvenir de la clé » : cookie fonctionnel optionnel (case cochée sur le gate).
// httponly + SameSite=Lax ; durée alignée sur mentions.php (politique de confidentialité).
const ACCESS_REMEMBER_COOKIE = 'station_access_key';
const ACCESS_REMEMBER_DAYS   = 30;

function access_remember_cookie_set($key) {
    setcookie(ACCESS_REMEMBER_COOKIE, access_normalize_key($key), [
        'expires'  => time() + ACCESS_REMEMBER_DAYS * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function access_remember_cookie_clear() {
    setcookie(ACCESS_REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Reconnexion silencieuse depuis le cookie « se souvenir » : si la session n'a pas
 * de clé mais que le cookie en contient une encore valide, on la remet en session.
 * Un cookie devenu invalide (clé expirée/révoquée/supprimée) est effacé.
 */
function access_try_cookie() {
    if (empty($_COOKIE[ACCESS_REMEMBER_COOKIE])) return false;
    $key = access_normalize_key($_COOKIE[ACCESS_REMEMBER_COOKIE]);
    $status = access_key_status($key);
    if ($status === 'ok') {
        access_grant($key);
        return true;
    }
    if ($status !== 'error') { // erreur DB ponctuelle : on garde le cookie, on retentera
        access_remember_cookie_clear();
    }
    return false;
}

/** Une clé a-t-elle déjà été acceptée dans cette session (sans garantir sa validité actuelle) ? */
function access_session_granted() {
    return !empty($_SESSION['access_key']);
}

/**
 * La clé mémorisée en session est-elle ENCORE valide ?
 * Revérifie en base au plus toutes les ACCESS_RECHECK_SECONDS ($force = true : toujours).
 * En cas d'erreur de base ponctuelle, on conserve le dernier verdict connu (pas de blocage injuste).
 */
function access_session_valid($force = false) {
    if (empty($_SESSION['access_key'])) {
        // Pas de clé en session : tentative de reconnexion via le cookie « se souvenir »
        return access_try_cookie();
    }
    $now = time();
    $checkedAt = isset($_SESSION['access_checked_at']) ? (int) $_SESSION['access_checked_at'] : 0;
    $lastOk    = !empty($_SESSION['access_checked_ok']);
    if (!$force && $checkedAt && ($now - $checkedAt) < ACCESS_RECHECK_SECONDS) {
        return $lastOk;
    }
    $status = access_key_status($_SESSION['access_key']);
    if ($status === 'error') {
        return $lastOk; // base injoignable : ne pas éjecter l'utilisateur pour autant
    }
    $_SESSION['access_checked_at'] = $now;
    $_SESSION['access_checked_ok'] = ($status === 'ok');
    return $status === 'ok';
}

/** Mémorise la clé validée dans la session. */
function access_grant($key) {
    $_SESSION['access_key']        = access_normalize_key($key);
    $_SESSION['access_checked_at'] = time();
    $_SESSION['access_checked_ok'] = true;
}

/**
 * Une partie « Mode Jeu » existe-t-elle pour ce PIN ?
 * Même convention que game.php : un fichier <pin>.json dans le dossier temporaire.
 */
function access_game_pin_exists($pin) {
    if (!preg_match('/^\d{6}$/', (string) $pin)) return false;
    return is_file(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgbt_kahoot' . DIRECTORY_SEPARATOR . $pin . '.json');
}

/**
 * Traite la soumission du formulaire de clé (POST access_key_submit)
 * ou du formulaire PIN « Mode Jeu » (POST game_pin_submit).
 * Renvoie un message d'erreur (string) ou null. Redirige (PRG) en cas de succès.
 */
function access_handle_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !(isset($_POST['access_key_submit']) || isset($_POST['game_pin_submit']))) {
        return null;
    }
    $lang = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
    $t = access_texts($lang);

    // --- Entrée par PIN de partie (joueurs du Mode Jeu, sans clé) ---
    // Volontairement hors anti-force-brute des clés : un PIN erroné ne doit pas
    // bloquer la saisie de clé, et rejoindre une partie n'ouvre aucun accès privilégié.
    if (isset($_POST['game_pin_submit'])) {
        $pin = preg_replace('/\D/', '', isset($_POST['game_pin']) ? $_POST['game_pin'] : '');
        if (access_game_pin_exists($pin)) {
            header('Location: play.php?pin=' . $pin);
            exit();
        }
        return $t['pin_invalid'];
    }

    $throttle = access_throttled();
    if ($throttle['blocked']) {
        return str_replace('{min}', (string) ceil($throttle['remaining'] / 60), $t['blocked']);
    }
    $key = access_normalize_key(isset($_POST['access_key']) ? $_POST['access_key'] : '');
    $status = access_key_status($key);
    if ($status === 'ok') {
        access_throttle_clear();
        session_regenerate_id(true);
        access_grant($key);
        if (!empty($_POST['remember_key'])) {
            access_remember_cookie_set($key); // « se souvenir sur cet appareil » (30 jours)
        }
        access_log_entry($key); // journal des connexions (IP), consultable dans le panneau d'admin
        header('Location: ' . basename($_SERVER['SCRIPT_NAME']));
        exit();
    }
    if ($status === 'error') {
        return $t['db_error'];
    }
    access_register_failure();
    $throttle = access_throttled();
    if ($throttle['blocked']) {
        return str_replace('{min}', (string) ceil($throttle['remaining'] / 60), $t['blocked']);
    }
    $msg = ($status === 'expired') ? $t['expired'] : (($status === 'revoked') ? $t['revoked'] : $t['invalid']);
    return $msg . ' ' . str_replace('{n}', (string) $throttle['attempts_left'], $t['attempts_left']);
}

// ---------------------------------------------------------------------------
//  Textes (fr / de / en)
// ---------------------------------------------------------------------------

function access_texts($lang) {
    $all = [
        'fr' => [
            'title'         => 'Accès protégé',
            'intro'         => 'L\'accès aux questionnaires nécessite une clé d\'accès. Saisissez la clé qui vous a été fournie.',
            'placeholder'   => 'XXXX-XXXX-XXXX',
            'submit'        => 'Valider la clé',
            'invalid'       => 'Clé inconnue.',
            'expired'       => 'Cette clé a expiré.',
            'revoked'       => 'Cette clé a été désactivée.',
            'attempts_left' => 'Il vous reste {n} tentative(s).',
            'blocked'       => 'Trop de tentatives. Réessayez dans environ {min} minute(s).',
            'db_error'      => 'Erreur technique, veuillez réessayer plus tard.',
            'note_players'  => 'Les joueurs qui rejoignent une partie avec un PIN / QR code n\'ont pas besoin de clé.',
            'banner_expired'=> 'Votre clé d\'accès a expiré : vous pouvez terminer le questionnaire en cours, mais pas en choisir un nouveau.',
            'pin_or'        => 'ou',
            'pin_title'     => 'Rejoindre une partie (Mode Jeu)',
            'pin_intro'     => 'Vous avez un code PIN affiché par l\'animateur·rice ? Entrez-le ici, sans clé d\'accès.',
            'pin_submit'    => 'Rejoindre la partie',
            'pin_invalid'   => 'Aucune partie en cours avec ce PIN.',
            'remember'      => 'Se souvenir de la clé sur cet appareil (30 jours)',
            'show_key'      => 'Afficher / masquer la clé',
        ],
        'de' => [
            'title'         => 'Geschützter Zugang',
            'intro'         => 'Der Zugang zu den Fragebögen erfordert einen Zugangsschlüssel. Geben Sie den Ihnen mitgeteilten Schlüssel ein.',
            'placeholder'   => 'XXXX-XXXX-XXXX',
            'submit'        => 'Schlüssel bestätigen',
            'invalid'       => 'Unbekannter Schlüssel.',
            'expired'       => 'Dieser Schlüssel ist abgelaufen.',
            'revoked'       => 'Dieser Schlüssel wurde deaktiviert.',
            'attempts_left' => 'Es verbleiben {n} Versuch(e).',
            'blocked'       => 'Zu viele Versuche. Versuchen Sie es in etwa {min} Minute(n) erneut.',
            'db_error'      => 'Technischer Fehler, bitte versuchen Sie es später erneut.',
            'note_players'  => 'Spieler, die mit PIN / QR-Code einem Spiel beitreten, brauchen keinen Schlüssel.',
            'banner_expired'=> 'Ihr Zugangsschlüssel ist abgelaufen: Sie können den laufenden Fragebogen beenden, aber keinen neuen wählen.',
            'pin_or'        => 'oder',
            'pin_title'     => 'Einem Spiel beitreten (Spielmodus)',
            'pin_intro'     => 'Sie haben eine PIN, die vom Spielleiter angezeigt wird? Geben Sie sie hier ein — ohne Zugangsschlüssel.',
            'pin_submit'    => 'Dem Spiel beitreten',
            'pin_invalid'   => 'Kein laufendes Spiel mit dieser PIN.',
            'remember'      => 'Schlüssel auf diesem Gerät merken (30 Tage)',
            'show_key'      => 'Schlüssel anzeigen / verbergen',
        ],
        'en' => [
            'title'         => 'Protected access',
            'intro'         => 'Access to the questionnaires requires an access key. Enter the key you were given.',
            'placeholder'   => 'XXXX-XXXX-XXXX',
            'submit'        => 'Validate the key',
            'invalid'       => 'Unknown key.',
            'expired'       => 'This key has expired.',
            'revoked'       => 'This key has been deactivated.',
            'attempts_left' => 'You have {n} attempt(s) left.',
            'blocked'       => 'Too many attempts. Try again in about {min} minute(s).',
            'db_error'      => 'Technical error, please try again later.',
            'note_players'  => 'Players joining a game with a PIN / QR code do not need a key.',
            'banner_expired'=> 'Your access key has expired: you can finish the current questionnaire, but not choose a new one.',
            'pin_or'        => 'or',
            'pin_title'     => 'Join a game (Game Mode)',
            'pin_intro'     => 'Got a PIN shown by the host? Enter it here — no access key needed.',
            'pin_submit'    => 'Join the game',
            'pin_invalid'   => 'No ongoing game with this PIN.',
            'remember'      => 'Remember the key on this device (30 days)',
            'show_key'      => 'Show / hide the key',
        ],
    ];
    return isset($all[$lang]) ? $all[$lang] : $all['fr'];
}

// ---------------------------------------------------------------------------
//  Écran de saisie de la clé (page autonome, puis exit)
// ---------------------------------------------------------------------------

function access_render_gate($lang, $error = null) {
    // index.php émet « <!DOCTYPE html> » avant le PHP : on vide le tampon de sortie
    // pour que l'écran de saisie soit une page propre (et que les en-têtes passent).
    if (ob_get_level() > 0 && ob_get_length()) { @ob_clean(); }
    $t = access_texts($lang);
    $throttle = access_throttled();
    if ($error === null && $throttle['blocked']) {
        $error = str_replace('{min}', (string) ceil($throttle['remaining'] / 60), $t['blocked']);
    }
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($t['title']); ?></title>
<style>
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
           font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
           background:#f7f4fb; color:#2b2b2b; }
    .gate { background:#fff; max-width:440px; width:calc(100% - 40px); padding:34px 30px 28px;
            border-radius:18px; border:1px solid #f0e3e6; box-shadow:0 10px 30px rgba(0,0,0,.10);
            text-align:center; position:relative; overflow:hidden; }
    .gate::before { content:""; position:absolute; top:0; left:0; right:0; height:8px;
            background:linear-gradient(90deg,#e40303,#ff8c00,#ffed00,#008026,#004dff,#750787); }
    .gate h1 { font-size:22px; margin:10px 0 8px; color:#4a3a86; }
    .gate .lock { font-size:38px; }
    .gate p.intro { font-size:14.5px; line-height:1.5; color:#555; margin:0 0 18px; }
    .gate input[type=text], .gate input[type=password] { width:100%; box-sizing:border-box; padding:13px; font-size:19px;
            letter-spacing:2px; text-align:center; text-transform:uppercase;
            border:2px solid #d8cff7; border-radius:10px; outline:none; }
    .gate input[type=text]:focus, .gate input[type=password]:focus { border-color:#8a7bf4; }
    .gate .key-wrap { position:relative; }
    .gate .key-wrap input { padding-right:48px; }
    .gate .key-wrap .eye { position:absolute; right:8px; top:50%; transform:translateY(-50%);
            background:none; border:none; font-size:20px; cursor:pointer; padding:4px; line-height:1; }
    .gate label.remember { display:flex; gap:8px; align-items:flex-start; margin-top:12px;
            font-size:13px; color:#666; text-align:left; cursor:pointer; }
    .gate label.remember input { margin-top:2px; }
    .gate button { margin-top:14px; width:100%; padding:13px; border:none; border-radius:10px;
            font-size:16px; font-weight:800; color:#fff; background:#8a7bf4; cursor:pointer; }
    .gate button:hover { background:#7867e6; }
    .gate button:disabled { background:#c9c2ea; cursor:not-allowed; }
    .gate .error { margin:0 0 14px; padding:10px 12px; border-radius:10px; font-size:14px;
            background:#fdecec; border:1px solid #f3b0b0; color:#a33; }
    .gate .note { margin-top:18px; font-size:12.5px; color:#8a7f9a; line-height:1.45; }
    .gate .sep { display:flex; align-items:center; gap:12px; margin:22px 0 16px;
            color:#8a7f9a; font-size:13px; text-transform:uppercase; letter-spacing:.08em; }
    .gate .sep::before, .gate .sep::after { content:""; flex:1; height:1px; background:#e5dcf3; }
    .gate h2.pin-title { font-size:16px; margin:0 0 6px; color:#4a3a86; }
    .gate p.pin-intro { font-size:13px; color:#666; margin:0 0 12px; line-height:1.45; }
    .gate input.pin { letter-spacing:6px; }
    .gate button.pin-btn { background:#5cb37a; }
    .gate button.pin-btn:hover { background:#4da26b; }
</style>
</head>
<body>
    <div class="gate">
        <div class="lock">🔐</div>
        <h1><?php echo htmlspecialchars($t['title']); ?></h1>
        <p class="intro"><?php echo htmlspecialchars($t['intro']); ?></p>
        <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" action="" autocomplete="on">
            <!-- Champ « identifiant » masqué : permet aux gestionnaires de mots de passe
                 d'enregistrer la clé (type=password + current-password) sous un nom stable. -->
            <input type="text" name="access_user" value="cle-questionnaire" autocomplete="username"
                   readonly tabindex="-1" aria-hidden="true" style="display:none;">
            <div class="key-wrap">
                <input type="password" id="access-key-input" name="access_key" maxlength="20"
                       autocomplete="current-password" placeholder="<?php echo htmlspecialchars($t['placeholder']); ?>"
                       <?php echo $throttle['blocked'] ? 'disabled' : 'required autofocus'; ?>>
                <button type="button" class="eye" title="<?php echo htmlspecialchars($t['show_key']); ?>"
                        onclick="var k=document.getElementById('access-key-input');k.type=(k.type==='password')?'text':'password';">👁</button>
            </div>
            <label class="remember">
                <input type="checkbox" name="remember_key" value="1" checked <?php echo $throttle['blocked'] ? 'disabled' : ''; ?>>
                <span><?php echo htmlspecialchars($t['remember']); ?></span>
            </label>
            <button type="submit" name="access_key_submit" value="1" <?php echo $throttle['blocked'] ? 'disabled' : ''; ?>>
                <?php echo htmlspecialchars($t['submit']); ?>
            </button>
        </form>
        <!-- Entrée par PIN de partie : les joueurs du Mode Jeu n'ont pas de clé.
             Disponible même pendant un blocage anti-force-brute des clés (flux distinct). -->
        <div class="sep"><?php echo htmlspecialchars($t['pin_or']); ?></div>
        <h2 class="pin-title">🎮 <?php echo htmlspecialchars($t['pin_title']); ?></h2>
        <p class="pin-intro"><?php echo htmlspecialchars($t['pin_intro']); ?></p>
        <form method="POST" action="" autocomplete="off">
            <input type="text" class="pin" name="game_pin" inputmode="numeric" pattern="\d{6}" maxlength="6"
                   placeholder="123456" required>
            <button type="submit" class="pin-btn" name="game_pin_submit" value="1">
                <?php echo htmlspecialchars($t['pin_submit']); ?>
            </button>
        </form>
    </div>
</body>
</html>
    <?php
    exit();
}

// ---------------------------------------------------------------------------
//  Endpoint AJAX (uniquement en accès direct : access.php?action=status)
// ---------------------------------------------------------------------------

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=UTF-8');
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if ($action === 'status') {
        // Revérification "live" en base (appelée toutes les ~3 min par le client).
        echo json_encode(['valid' => access_session_valid(true)]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'bad_action']);
    }
    exit();
}

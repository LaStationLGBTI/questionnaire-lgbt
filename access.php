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
    if (empty($_SESSION['access_key'])) return false;
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
 * Traite la soumission du formulaire de clé (POST access_key_submit).
 * Renvoie un message d'erreur (string) ou null. Redirige (PRG) en cas de succès.
 */
function access_handle_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['access_key_submit'])) {
        return null;
    }
    $lang = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
    $t = access_texts($lang);

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
    .gate input[type=text] { width:100%; box-sizing:border-box; padding:13px; font-size:19px;
            letter-spacing:2px; text-align:center; text-transform:uppercase;
            border:2px solid #d8cff7; border-radius:10px; outline:none; }
    .gate input[type=text]:focus { border-color:#8a7bf4; }
    .gate button { margin-top:14px; width:100%; padding:13px; border:none; border-radius:10px;
            font-size:16px; font-weight:800; color:#fff; background:#8a7bf4; cursor:pointer; }
    .gate button:hover { background:#7867e6; }
    .gate button:disabled { background:#c9c2ea; cursor:not-allowed; }
    .gate .error { margin:0 0 14px; padding:10px 12px; border-radius:10px; font-size:14px;
            background:#fdecec; border:1px solid #f3b0b0; color:#a33; }
    .gate .note { margin-top:18px; font-size:12.5px; color:#8a7f9a; line-height:1.45; }
</style>
</head>
<body>
    <div class="gate">
        <div class="lock">🔐</div>
        <h1><?php echo htmlspecialchars($t['title']); ?></h1>
        <p class="intro"><?php echo htmlspecialchars($t['intro']); ?></p>
        <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" action="" autocomplete="off">
            <input type="text" name="access_key" maxlength="20" placeholder="<?php echo htmlspecialchars($t['placeholder']); ?>"
                   <?php echo $throttle['blocked'] ? 'disabled' : 'required autofocus'; ?>>
            <button type="submit" name="access_key_submit" value="1" <?php echo $throttle['blocked'] ? 'disabled' : ''; ?>>
                <?php echo htmlspecialchars($t['submit']); ?>
            </button>
        </form>
        <p class="note">🎮 <?php echo htmlspecialchars($t['note_players']); ?></p>
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

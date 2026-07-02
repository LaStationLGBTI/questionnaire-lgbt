<?php
/**
 * auth.php — module d'authentification commun aux pages d'administration.
 *
 * Regroupe et corrige les problèmes de sécurité qui étaient dupliqués dans chaque
 * panneau admin :
 *   - Anti-force-brute robuste : compteur par IP stocké dans un FICHIER (pas dans la
 *     session), donc NON réinitialisable en supprimant le cookie / en se déconnectant.
 *   - Mots de passe hachés (password_hash / password_verify) avec migration
 *     transparente des anciens mots de passe stockés en clair.
 *   - Session durcie : cookie HttpOnly + SameSite=Strict (+ Secure en HTTPS),
 *     régénération de l'identifiant de session à la connexion (anti-fixation).
 *   - Jetons CSRF pour tous les formulaires qui modifient l'état.
 *   - Pas de fuite de détails d'erreur (display_errors off ; messages génériques).
 *
 * Usage dans une page admin :
 *   require_once 'auth.php';                 // démarre la session durcie, inclut conf.php
 *   $login_error = admin_handle_auth();      // gère login / logout, renvoie un message ou null
 *   if (admin_is_logged_in()) { ... }        // contenu protégé
 *   // Dans chaque formulaire : echo csrf_input();
 *   // En tête de chaque traitement POST d'action : admin_require_csrf();
 */

require_once __DIR__ . '/conf.php';

// --- Ne jamais exposer les erreurs PHP à l'utilisateur (les journaliser seulement) ---
// Mettre APP_DEBUG=1 dans l'environnement du serveur pour réactiver l'affichage en dev.
if (getenv('APP_DEBUG') === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);       // on garde le log complet, sans l'afficher
ini_set('log_errors', '1');

// --- Démarrage de session durci (les options cookie doivent précéder session_start) ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Strict',
        ]);
    } else {
        // PHP < 7.3 : SameSite passé via le paramètre "path" (astuce compatible).
        session_set_cookie_params(0, '/; samesite=Strict', '', $https, true);
    }
    session_start();
}

// ---------------------------------------------------------------------------
//  Anti-force-brute (stockage fichier, par adresse IP)
// ---------------------------------------------------------------------------

const ADMIN_MAX_ATTEMPTS   = 5;     // tentatives échouées autorisées...
const ADMIN_WINDOW_SECONDS = 900;   // ...dans cette fenêtre glissante (15 min)
const ADMIN_LOCK_SECONDS   = 900;   // durée du blocage une fois le seuil atteint (15 min)

function admin_throttle_dir() {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgbt_admin_throttle';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    return $dir;
}

function admin_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}

function admin_throttle_file() {
    // Clé = hash de l'IP (nom de fichier sûr, pas de fuite d'IP en clair).
    return admin_throttle_dir() . DIRECTORY_SEPARATOR . sha1(admin_client_ip()) . '.json';
}

function admin_throttle_read() {
    $f = admin_throttle_file();
    if (!is_file($f)) return ['count' => 0, 'first' => 0, 'locked_until' => 0];
    $raw = @file_get_contents($f);
    $d = $raw ? json_decode($raw, true) : null;
    if (!is_array($d)) return ['count' => 0, 'first' => 0, 'locked_until' => 0];
    return $d + ['count' => 0, 'first' => 0, 'locked_until' => 0];
}

function admin_throttle_write($data) {
    @file_put_contents(admin_throttle_file(), json_encode($data), LOCK_EX);
}

/** Renvoie ['blocked' => bool, 'remaining' => secondes restantes de blocage]. */
function admin_login_throttled() {
    $d = admin_throttle_read();
    $now = time();
    if (!empty($d['locked_until']) && $d['locked_until'] > $now) {
        return ['blocked' => true, 'remaining' => $d['locked_until'] - $now];
    }
    return ['blocked' => false, 'remaining' => 0];
}

/** Enregistre une tentative échouée et pose un verrou si le seuil est atteint. */
function admin_throttle_register_failure() {
    $d = admin_throttle_read();
    $now = time();
    // Réinitialise le compteur si la fenêtre est expirée.
    if (empty($d['first']) || ($now - $d['first']) > ADMIN_WINDOW_SECONDS) {
        $d['first'] = $now;
        $d['count'] = 0;
    }
    $d['count']++;
    if ($d['count'] >= ADMIN_MAX_ATTEMPTS) {
        $d['locked_until'] = $now + ADMIN_LOCK_SECONDS;
    }
    admin_throttle_write($d);
}

/** Efface le compteur (connexion réussie). */
function admin_throttle_clear() {
    $f = admin_throttle_file();
    if (is_file($f)) { @unlink($f); }
}

// ---------------------------------------------------------------------------
//  Vérification des identifiants (avec migration des mots de passe en clair)
// ---------------------------------------------------------------------------

/**
 * Vérifie login+mot de passe contre la table stationl1.
 * Compatibilité : si le mot de passe est encore stocké en clair, on l'accepte une
 * dernière fois puis on le re-hache automatiquement (password_hash).
 * Renvoie true/false. Ne divulgue jamais de détail (anti-énumération).
 */
function admin_check_credentials($login, $pass) {
    global $DB_HOSTNAME, $DB_NAME, $DB_USERNAME, $DB_PASSWORD;
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT passconn FROM stationl1 WHERE loginconn = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[auth] DB error: ' . $e->getMessage());
        return false;
    }
    if (!$user) {
        // Comparaison factice pour égaliser le temps de réponse (anti-timing/énumération).
        password_verify($pass, '$2y$10$usesomesillystringforsaltttttttttttttttttttttttttttttttu');
        return false;
    }

    $stored = (string) $user['passconn'];
    $isHash = (strncmp($stored, '$2y$', 4) === 0 || strncmp($stored, '$2a$', 4) === 0
               || strncmp($stored, '$argon2', 7) === 0);

    if ($isHash) {
        return password_verify($pass, $stored);
    }

    // Ancien format en clair : comparaison à temps constant puis re-hachage.
    if (hash_equals($stored, (string) $pass)) {
        try {
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE stationl1 SET passconn = ? WHERE loginconn = ?");
            $upd->execute([$newHash, $login]);
        } catch (PDOException $e) {
            error_log('[auth] rehash failed: ' . $e->getMessage());
        }
        return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
//  Flux de connexion / déconnexion
// ---------------------------------------------------------------------------

function admin_is_logged_in() {
    return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
}

function admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Gère les POST 'login' et 'logout'. À appeler juste après require_once 'auth.php'.
 * Renvoie un message d'erreur de connexion (string) ou null.
 * $redirectAfterLogin : URL vers laquelle rediriger après une connexion réussie
 * (pour éviter le renvoi du POST). null = pas de redirection.
 */
function admin_handle_auth($redirectAfterLogin = null, $redirectAfterLogout = null) {
    // Déconnexion (protégée par CSRF).
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
        admin_require_csrf();
        admin_logout();
        $dest = $redirectAfterLogout ?: (basename($_SERVER['SCRIPT_NAME']));
        header('Location: ' . $dest);
        exit();
    }

    // Connexion.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        admin_require_csrf();
        $throttle = admin_login_throttled();
        if ($throttle['blocked']) {
            return "Trop de tentatives. Réessayez dans environ " . ceil($throttle['remaining'] / 60) . " minute(s).";
        }
        $login = isset($_POST['identifiant']) ? (string) $_POST['identifiant'] : '';
        $pass  = isset($_POST['mot_de_passe']) ? (string) $_POST['mot_de_passe'] : '';
        if (admin_check_credentials($login, $pass)) {
            admin_throttle_clear();
            session_regenerate_id(true);          // anti-fixation de session
            $_SESSION['is_logged_in'] = true;
            if ($redirectAfterLogin !== null) {
                header('Location: ' . $redirectAfterLogin);
                exit();
            }
            return null;
        }
        admin_throttle_register_failure();
        return "Identifiant ou mot de passe incorrect.";
    }
    return null;
}

// ---------------------------------------------------------------------------
//  CSRF
// ---------------------------------------------------------------------------

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = function_exists('random_bytes')
            ? bin2hex(random_bytes(32))
            : bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Champ caché à insérer dans chaque formulaire. */
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Vérifie le jeton pour la requête POST courante. */
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $sent = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Coupe la requête si le jeton CSRF est absent/invalide. */
function admin_require_csrf() {
    if (!csrf_check()) {
        http_response_code(403);
        exit('Requête invalide (jeton CSRF manquant ou incorrect). Rechargez la page et réessayez.');
    }
}

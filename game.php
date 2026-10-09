<?php
/**
 * game.php — contrôleur d'état pour le « Mode Jeu » (style Kahoot) des questionnaires.
 *
 * Pas de base de données : l'état d'une partie est un fichier JSON dans le dossier
 * temporaire système (un fichier par PIN). Hôte (index.php) et joueurs (play.php)
 * communiquent par polling AJAX. La bonne réponse (correctIndex) reste côté serveur
 * et n'est renvoyée aux joueurs qu'au moment du « reveal ».
 *
 * Actions (param `action`) :
 *   create  (hôte)   : crée une partie depuis la session → renvoie {pin}
 *   setq    (hôte)   : pousse la question courante (lue dans la session) → status=question
 *   reveal  (hôte)   : status=reveal (les téléphones voient juste/faux)
 *   end     (hôte)   : status=ended (classement final)
 *   resume  (hôte)   : rechargement / reconnexion de l'hôte → même partie (même PIN)
 *   abort   (hôte)   : status=cancelled (annulation explicite ; une partie « ended » le reste)
 *   state   + host=1 : champs réservés à l'hôte (correctPlayers) seulement si host=1 ET host_auth
 *   join    (joueur) : pin + name → crée un joueur (ou reprend celui du même pseudo) → {pid}
 *   answer  (joueur) : pin + pid + choice → enregistre, +100 si correct
 *   state   (tous)   : renvoie l'état nettoyé (correctIndex masqué hors reveal/ended)
 *
 * Erreurs : 'not_found' = le fichier de la partie n'existe PLUS (seul cas où un joueur
 * oublie sa session) ; 'busy' = lecture momentanément impossible (à réessayer).
 *
 * Authentification de l'hôte : session_id() == hostSid OU cookie httponly
 * `lgbt_kahoot_host` = "<pin>.<hostKey>" (survit à une perte/régénération de session PHP,
 * p. ex. après une coupure réseau prolongée). Sur succès par cookie, hostSid est re-lié.
 */

// Même durée de vie que index.php : sinon le ramasse-miettes des sessions déclenché par
// les nombreux appels de polling (défaut 1440 s) supprime la session de l'hôte pendant
// une coupure réseau ou une longue explication => l'hôte perdait le contrôle de la partie.
// Mode bibliothèque : index.php inclut ce fichier (define('GAME_LIB_ONLY', true)) pour
// réutiliser les fonctions (annulation de l'ancienne partie) sans exécuter le contrôleur.
if (!defined('GAME_LIB_ONLY')) {
    ini_set('session.gc_maxlifetime', 31536000);
    session_start();
    header('Content-Type: application/json; charset=utf-8');
}

$GAME_DIR = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgbt_kahoot';
if (!defined('HOST_COOKIE')) define('HOST_COOKIE', 'lgbt_kahoot_host');

/** Pose le cookie hôte (12 h). En-tête brut : compatible avec toutes les versions de PHP. */
function host_cookie_set($pin, $key) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '; Secure' : '';
    header('Set-Cookie: ' . HOST_COOKIE . '=' . $pin . '.' . $key
        . '; Max-Age=43200; Path=/; HttpOnly; SameSite=Lax' . $secure, false);
}
function host_cookie_clear() {
    header('Set-Cookie: ' . HOST_COOKIE . '=deleted; Max-Age=0; Path=/; HttpOnly; SameSite=Lax', false);
}
/** Renvoie [pin, hostKey] lus dans le cookie hôte, ou null. */
function host_cookie_get() {
    if (empty($_COOKIE[HOST_COOKIE])) return null;
    if (!preg_match('/^(\d{6})\.([0-9a-f]{16,64})$/', (string)$_COOKIE[HOST_COOKIE], $m)) return null;
    return array($m[1], $m[2]);
}
/** L'appelant prouve-t-il être l'hôte de $game (session ou cookie) ? */
function host_auth($game) {
    if (isset($game['hostSid']) && $game['hostSid'] === session_id()) return true;
    $c = host_cookie_get();
    return $c && $c[0] === (string)$game['pin'] && !empty($game['hostKey'])
        && hash_equals((string)$game['hostKey'], $c[1]);
}

function jexit($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Aléatoire portable (random_int/random_bytes : PHP 7+ ; repli mt_rand pour PHP plus ancien).
function rand_int($min, $max) {
    return function_exists('random_int') ? random_int($min, $max) : mt_rand($min, $max);
}
function rand_hex($bytes) {
    if (function_exists('random_bytes')) return bin2hex(random_bytes($bytes));
    $s = '';
    for ($i = 0; $i < $bytes * 2; $i++) { $s .= dechex(mt_rand(0, 15)); }
    return $s;
}
function jerr($msg) { jexit(['ok' => false, 'error' => $msg]); }

function game_path($dir, $pin) {
    // PIN strictement numérique : pas de traversée de chemin possible.
    return $dir . DIRECTORY_SEPARATOR . $pin . '.json';
}

/**
 * Lit une partie sous verrou PARTAGÉ.
 * Retourne : le tableau de la partie ; null si la partie n'existe pas (fichier absent) ;
 * false si la lecture a échoué momentanément.
 * (Avant : lecture sans verrou => pendant l'écriture d'un autre joueur (ftruncate puis
 * fwrite) on lisait un fichier VIDE → 'no_game' → les téléphones croyaient la partie
 * annulée et effaçaient leur session.)
 */
function load_game($dir, $pin) {
    if (!preg_match('/^\d{6}$/', (string)$pin)) return null;
    $path = game_path($dir, $pin);
    if (!is_file($path)) return null;
    $fp = @fopen($path, 'r');
    if (!$fp) return is_file($path) ? false : null;
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($raw === false || $raw === '') return false;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : false;
}

function save_game($dir, $game) {
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    $path = game_path($dir, $game['pin']);
    $game['updatedAt'] = time();
    $fp = fopen($path, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($game, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/** Erreur « métier » levée dans une transaction pour annuler sans écrire. */
class GameError extends Exception {}

/**
 * Lecture-modification-écriture d'une partie sous verrou EXCLUSIF.
 * Évite les pertes de mises à jour quand plusieurs joueurs répondent en même temps
 * (sinon load_game + save_game séparés => le dernier écrivain écrase les autres).
 * $cb reçoit le tableau $game par référence ; il peut lever GameError pour annuler
 * sans écrire. Retourne le $game final, ou null si la partie n'existe pas.
 */
function mutate_game($dir, $pin, $cb) {
    if (!preg_match('/^\d{6}$/', (string)$pin)) return null;
    $path = game_path($dir, $pin);
    if (!is_file($path)) return null;
    $fp = @fopen($path, 'r+');
    if (!$fp) {
        if (!is_file($path)) return null;
        throw new GameError('busy'); // erreur passagère : le client réessaie
    }
    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $game = json_decode($raw, true);
    if (!is_array($game)) { flock($fp, LOCK_UN); fclose($fp); throw new GameError('busy'); }
    try {
        $cb($game);
    } catch (GameError $e) {
        flock($fp, LOCK_UN); fclose($fp);
        throw $e;
    }
    $game['updatedAt'] = time();
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($game, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $game;
}

/** Supprime les parties inactives depuis plus de 6 heures (ménage best-effort). */
function cleanup_old($dir) {
    if (!is_dir($dir)) return;
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') as $f) {
        if (@filemtime($f) < time() - 6 * 3600) { @unlink($f); }
    }
}

/**
 * Annule (status=cancelled) la partie $pin si l'appelant en est l'hôte. Une partie déjà
 * terminée (ended) reste « ended » (classement final conservé pour les téléphones).
 * 'busy' est réessayé brièvement côté serveur.
 * Retourne 'ok' | 'not_found' | 'not_host' | 'busy'.
 */
function game_cancel($dir, $pin) {
    for ($try = 0; $try < 5; $try++) {
        try {
            $g = mutate_game($dir, $pin, function (&$g) {
                if (!host_auth($g)) throw new GameError('not_host');
                if (isset($g['status']) && $g['status'] === 'ended') return;
                $g['status'] = 'cancelled';
            });
            return $g ? 'ok' : 'not_found';
        } catch (GameError $e) {
            if ($e->getMessage() !== 'busy') return $e->getMessage();
            usleep(200000);
        }
    }
    return 'busy';
}

/**
 * L'hôte quitte le module (index.php?back=1, autre module, nouveau lancement) : annule
 * la partie liée à sa session et/ou à son cookie hôte, et oublie le PIN de la session.
 * Sans cela, relancer le même module en Mode Jeu dans les 12 h reprenait l'ancienne partie.
 */
function game_cancel_host_game($dir) {
    $pins = array();
    if (!empty($_SESSION['game_pin'])) $pins[] = (string)$_SESSION['game_pin'];
    $c = host_cookie_get();
    if ($c && !in_array($c[0], $pins, true)) $pins[] = $c[0];
    foreach ($pins as $p) { game_cancel($dir, $p); }
    unset($_SESSION['game_pin']);
    // Le cookie ne peut être effacé qu'avant toute sortie (index.php : ?back / ?level). Sinon il
    // reste, mais pointe vers une partie annulée => « resume » est refusé et une partie neuve est créée.
    if ($c && !headers_sent()) host_cookie_clear();
    return count($pins) > 0;
}

/** Lit la question courante depuis la session de l'hôte (index.php). */
function current_question_from_session() {
    if (!isset($_SESSION['QuestionToUse'], $_SESSION['LastQuestion'])) return null;
    $idx   = (int)$_SESSION['LastQuestion'];
    $q     = explode('__', $_SESSION['QuestionToUse']);
    if (!isset($q[$idx])) return null;
    $rep   = [];
    for ($i = 1; $i <= 5; $i++) {
        $arr = explode('__', isset($_SESSION['Rep' . $i]) ? $_SESSION['Rep' . $i] : '');
        $rep[$i] = isset($arr[$idx]) ? $arr[$idx] : '';
    }
    $answerArr = explode('__', isset($_SESSION['answer']) ? $_SESSION['answer'] : '');
    $idArr     = explode('__', isset($_SESSION['IdInUse']) ? $_SESSION['IdInUse'] : '');
    $typeArr   = explode('__', isset($_SESSION['qtype']) ? $_SESSION['qtype'] : '');
    $expliqArr = explode('__', isset($_SESSION['expliqs']) ? $_SESSION['expliqs'] : '');

    $answers = [];
    for ($i = 1; $i <= 5; $i++) {
        $t = $rep[$i];
        if ($t === null || $t === 'null' || trim($t) === '') continue;
        $answers[] = ['n' => $i, 'text' => $t];
    }
    return [
        'text'         => $q[$idx],
        'answers'      => $answers,
        'correctIndex' => isset($answerArr[$idx]) ? (int)$answerArr[$idx] : 0, // numéro de slot 1..5
        'expliq'       => isset($expliqArr[$idx]) ? $expliqArr[$idx] : '',      // explication de la bonne réponse
        'qid'          => isset($idArr[$idx]) ? $idArr[$idx] : '',
        'qtype'        => isset($typeArr[$idx]) ? $typeArr[$idx] : 'qcm',
        'qNumber'      => $idx,
        'totalQ'       => isset($_SESSION['TotalQuestions']) ? (int)$_SESSION['TotalQuestions'] : 0,
    ];
}

/**
 * État renvoyé au client (correctIndex masqué hors reveal/ended).
 * $hostView : la requête vient de la page HÔTE (index.php : actions hôte, ou state avec
 * host=1). Un onglet play.php ouvert dans le navigateur de l'hôte est authentifié (cookie)
 * mais ne doit PAS recevoir les champs réservés à l'hôte (correctPlayers pendant la question).
 */
function public_state($game, $pid = null, $hostView = false) {
    $reveal = in_array($game['status'], ['reveal', 'ended'], true);
    $players = [];
    foreach ($game['players'] as $id => $p) {
        $players[] = [
            'pid'      => $id,
            'name'     => $p['name'],
            'score'    => (int)$p['score'],
            'answered' => !empty($p['answered']),
        ];
    }
    // Classement (par score décroissant) — utile pour lobby et fin.
    usort($players, function ($a, $b) { return $b['score'] - $a['score']; });

    $q = null;
    if (!empty($game['question'])) {
        $q = [
            'text'    => $game['question']['text'],
            'answers' => $game['question']['answers'],
            'qNumber' => isset($game['qNumber']) ? $game['qNumber'] : 0,
            'totalQ'  => isset($game['totalQ']) ? $game['totalQ'] : 0,
        ];
    }
    $out = [
        'ok'      => true,
        'pin'     => $game['pin'],
        'status'  => $game['status'],
        'lang'    => $game['lang'],
        'players' => $players,
        'count'   => count($game['players']),
        'answeredCount' => count(array_filter($game['players'], function ($p) { return !empty($p['answered']); })),
        'question' => $q,
    ];
    if ($reveal && isset($game['question']['correctIndex'])) {
        $ci = (int)$game['question']['correctIndex'];
        $out['correctIndex'] = $ci;
        // Texte de la bonne réponse + explication, pour la fenêtre d'info chez l'hôte.
        $out['expliq'] = isset($game['question']['expliq']) ? $game['question']['expliq'] : '';
        $out['correctText'] = '';
        if (!empty($game['question']['answers'])) {
            foreach ($game['question']['answers'] as $a) {
                if (isset($a['n']) && (int)$a['n'] === $ci) { $out['correctText'] = $a['text']; break; }
            }
        }
        // Hote ayant change de langue en cours de partie : la session est deja re-traduite
        // (i18n_relocalize_session), on y relit le texte et l'explication si c'est la meme question.
        $qn  = isset($game['question']['qNumber']) ? (int)$game['question']['qNumber'] : -1;
        $ids = explode('__', isset($_SESSION['IdInUse']) ? $_SESSION['IdInUse'] : '');
        if ($qn > 0 && isset($ids[$qn], $game['question']['qid']) && (string)$ids[$qn] === (string)$game['question']['qid']) {
            $se = explode('__', isset($_SESSION['expliqs']) ? $_SESSION['expliqs'] : '');
            if (isset($se[$qn])) $out['expliq'] = $se[$qn];
            $sr = explode('__', isset($_SESSION['Rep' . $ci]) ? $_SESSION['Rep' . $ci] : '');
            if ($ci >= 1 && $ci <= 5 && isset($sr[$qn]) && trim($sr[$qn]) !== '' && $sr[$qn] !== 'null') $out['correctText'] = $sr[$qn];
        }
    }
    // Panneau "bonnes réponses" — RÉSERVÉ À L'HÔTE (jamais envoyé aux joueurs).
    // Calculé côté serveur à partir du vrai correctIndex, y compris pendant la phase 'question'
    // (mise à jour en direct pour l'hôte), sans jamais révéler la bonne réponse aux joueurs.
    if ($hostView && host_auth($game)) {
        $ci = (isset($game['question']) && isset($game['question']['correctIndex']))
            ? (int)$game['question']['correctIndex'] : null;
        $correctCount = 0;
        $correctPlayers = [];
        if ($ci !== null) {
            foreach ($game['players'] as $p) {
                if (!empty($p['answered']) && isset($p['lastChoice']) && (int)$p['lastChoice'] === $ci) {
                    $correctCount++;
                    $correctPlayers[] = $p['name'];
                }
            }
        }
        $out['correctCount'] = $correctCount;
        $out['correctPlayers'] = $correctPlayers;
    }
    // Vue personnelle du joueur (son propre choix / score).
    if ($pid !== null && isset($game['players'][$pid])) {
        $me = $game['players'][$pid];
        $out['me'] = [
            'name'       => $me['name'],
            'score'      => (int)$me['score'],
            'answered'   => !empty($me['answered']),
            'lastChoice' => isset($me['lastChoice']) ? $me['lastChoice'] : null,
        ];
    }
    return $out;
}

if (defined('GAME_LIB_ONLY')) return; // inclus par index.php : fonctions seulement

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'create': {
        cleanup_old($GAME_DIR);
        if (!isset($_SESSION['level'])) jerr('no_level');
        // Seul l'hôte (qui a validé une clé d'accès via index.php) peut créer une partie.
        // Les joueurs (join/answer/state) n'ont jamais besoin de clé.
        if (empty($_SESSION['access_key'])) jerr('no_access');
        // Génère un PIN à 6 chiffres non utilisé.
        $pin = null;
        for ($try = 0; $try < 30; $try++) {
            $cand = str_pad((string)rand_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (!is_file(game_path($GAME_DIR, $cand))) { $pin = $cand; break; }
        }
        if ($pin === null) jerr('pin_alloc');
        $hostKey = rand_hex(16);
        $game = [
            'pin'     => $pin,
            'level'   => $_SESSION['level'],
            'lang'    => isset($_SESSION['language']) ? $_SESSION['language'] : 'fr',
            'status'  => 'lobby',
            'hostSid' => session_id(),
            'hostKey' => $hostKey, // secret de reprise hôte (cookie), jamais renvoyé en JSON
            'qNumber' => 0,
            'totalQ'  => isset($_SESSION['TotalQuestions']) ? (int)$_SESSION['TotalQuestions'] : 0,
            'question' => null,
            'players' => [],
            'createdAt' => time(),
        ];
        if (!save_game($GAME_DIR, $game)) jerr('busy');
        $_SESSION['game_pin'] = $pin;
        host_cookie_set($pin, $hostKey);
        jexit(['ok' => true, 'pin' => $pin]);
    }

    case 'resume': {
        // Rechargement / reconnexion de la page hôte : reprise de la MÊME partie (même PIN,
        // QR, joueurs, scores) au lieu d'en créer une nouvelle et vide. Le PIN vient de la
        // session, ou à défaut du cookie hôte (session PHP perdue ou régénérée).
        $cookie = host_cookie_get();
        $pin = !empty($_SESSION['game_pin']) ? $_SESSION['game_pin'] : ($cookie ? $cookie[0] : null);
        if (empty($pin)) jerr('no_game');
        $sid   = session_id();
        $level = isset($_SESSION['level']) ? (string)$_SESSION['level'] : null;
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($sid, $level) {
                if (!host_auth($g)) throw new GameError('not_host');
                $st = isset($g['status']) ? $g['status'] : '';
                if ($st === 'ended' || $st === 'cancelled') throw new GameError('ended');
                // Autre module choisi entre-temps : ne pas mélanger deux questionnaires.
                if ($level !== null && isset($g['level']) && (string)$g['level'] !== $level) throw new GameError('other_level');
                $g['hostSid'] = $sid; // re-lie la partie à la session courante
            });
        } catch (GameError $e) {
            if ($e->getMessage() !== 'busy') { unset($_SESSION['game_pin']); }
            jerr($e->getMessage());
        }
        if (!$game) { unset($_SESSION['game_pin']); host_cookie_clear(); jerr('no_game'); }
        $_SESSION['game_pin'] = $pin;
        if (!empty($game['hostKey'])) host_cookie_set($pin, $game['hostKey']);
        // Session hôte repartie de zéro (questionnaire relancé) alors que la partie était
        // plus loin : on recale la question courante si c'est bien la même question (même id).
        $gq = isset($game['qNumber']) ? (int)$game['qNumber'] : 0;
        if (!empty($_SESSION['start']) && in_array($game['status'], array('question', 'reveal'), true)
            && isset($_SESSION['LastQuestion']) && (int)$_SESSION['LastQuestion'] < $gq
            && !empty($game['question']['qid'])) {
            $idArr = explode('__', isset($_SESSION['IdInUse']) ? $_SESSION['IdInUse'] : '');
            if (isset($idArr[$gq]) && (string)$idArr[$gq] === (string)$game['question']['qid']) {
                $_SESSION['LastQuestion'] = (string)$gq;
            }
        }
        jexit(['ok' => true, 'pin' => $pin, 'status' => $game['status'], 'qNumber' => $gq]);
    }

    case 'setq': {
        $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : (isset($_SESSION['game_pin']) ? $_SESSION['game_pin'] : '');
        // La question courante est lue dans la session de l'hôte (hors verrou : pas d'accès fichier).
        $cq  = current_question_from_session();
        $sid = session_id();
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($cq, $sid) {
                if (!host_auth($g)) throw new GameError('not_host');
                // Partie terminée / annulée : on ne la rouvre jamais (requête rejouée, autre onglet…).
                if ($g['status'] === 'ended' || $g['status'] === 'cancelled') throw new GameError('ended');
                $g['hostSid'] = $sid;
                if (!$cq) throw new GameError('no_question');
                // Même question déjà en cours (hôte rechargé / reconnecté, ou setq ré-émis) :
                // on NE remet PAS à zéro les réponses déjà données ni l'état reveal.
                if (in_array($g['status'], array('question', 'reveal'), true) && !empty($g['question'])
                    && (int)$g['qNumber'] === (int)$cq['qNumber']
                    && (string)$g['question']['qid'] === (string)$cq['qid']) {
                    return;
                }
                $g['question'] = array(
                    'text'         => $cq['text'],
                    'answers'      => $cq['answers'],
                    'correctIndex' => $cq['correctIndex'],
                    'expliq'       => $cq['expliq'],
                    'qid'          => $cq['qid'],
                );
                $g['qNumber'] = $cq['qNumber'];
                $g['totalQ']  = $cq['totalQ'];
                $g['status']  = 'question';
                foreach ($g['players'] as $id => $p) {
                    $g['players'][$id]['answered']   = false;
                    $g['players'][$id]['lastChoice'] = null;
                }
            });
        } catch (GameError $e) { jerr($e->getMessage()); }
        if (!$game) jerr('not_found');
        jexit(public_state($game, null, true));
    }

    case 'reveal':
    case 'end': {
        $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : (isset($_SESSION['game_pin']) ? $_SESSION['game_pin'] : '');
        $newStatus = ($action === 'end') ? 'ended' : 'reveal';
        $sid = session_id();
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($sid, $newStatus) {
                if (!host_auth($g)) throw new GameError('not_host');
                $g['hostSid'] = $sid;
                if ($g['status'] === 'cancelled') throw new GameError('ended');
                // 'reveal' ne doit jamais rouvrir une partie terminée (requête rejouée après coupure).
                if ($newStatus === 'reveal' && $g['status'] === 'ended') return;
                $g['status'] = $newStatus;
            });
        } catch (GameError $e) { jerr($e->getMessage()); }
        if (!$game) jerr('not_found');
        if ($action === 'end') { host_cookie_clear(); }
        jexit(public_state($game, null, true));
    }

    case 'abort': {
        // Annulation EXPLICITE par l'hôte : status=cancelled (les téléphones affichent
        // « partie annulée » et oublient leur session). Le fichier est purgé par cleanup_old.
        // Une partie déjà terminée reste « ended » ; 'busy' persistant => erreur (le client réessaie).
        $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : (isset($_SESSION['game_pin']) ? $_SESSION['game_pin'] : '');
        $r = game_cancel($GAME_DIR, $pin);
        if ($r === 'busy') jerr('busy');
        unset($_SESSION['game_pin']);
        host_cookie_clear();
        jexit(['ok' => true, 'result' => $r]);
    }

    case 'join': {
        $pin  = isset($_REQUEST['pin']) ? trim($_REQUEST['pin']) : '';
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        if ($name === '') jerr('no_name');
        // Nettoyage / limite de longueur du pseudo.
        // On décode d'abord : le téléphone peut renvoyer le pseudo déjà échappé (celui reçu
        // du serveur, ex. « D&#039;Arc ») lors d'une reprise automatique → pas de double échappement.
        $plain = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $name  = mb_substr(htmlspecialchars($plain, ENT_QUOTES, 'UTF-8'), 0, 24);
        // Clé de comparaison = pseudo TRONQUÉ décodé (comme celui stocké), sinon un pseudo > 24 car. créerait un doublon.
        $key   = mb_strtolower(trim(html_entity_decode($name, ENT_QUOTES, 'UTF-8')), 'UTF-8');
        $pid  = rand_hex(8);
        $reconnected = false;
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use (&$pid, $name, $key, &$reconnected) {
                // Rejoindre / revenir est possible à TOUT moment (lobby, question, reveal)
                // tant que la partie n'est ni terminée ni annulée.
                if ($g['status'] === 'ended' || $g['status'] === 'cancelled') throw new GameError('ended');
                // Reconnexion : si un joueur porte déjà ce pseudo (sortie accidentelle / perte de
                // connexion), on le réutilise tel quel => score et progression conservés.
                foreach ($g['players'] as $existingPid => $p) {
                    if (isset($p['name']) && mb_strtolower(trim(html_entity_decode($p['name'], ENT_QUOTES, 'UTF-8')), 'UTF-8') === $key) {
                        $pid = $existingPid;
                        $reconnected = true;
                        return;
                    }
                }
                if (count($g['players']) >= 200) throw new GameError('full');
                $g['players'][$pid] = array(
                    'name'       => $name,
                    'score'      => 0,
                    'answered'   => false,
                    'lastChoice' => null,
                );
            });
        } catch (GameError $e) { jerr($e->getMessage()); }
        if (!$game) jerr('not_found');
        jexit(array(
            'ok'          => true,
            'pid'         => $pid,
            'name'        => $game['players'][$pid]['name'], // casse d'origine conservée
            'status'      => $game['status'],
            'reconnected' => $reconnected,
        ));
    }

    case 'answer': {
        $pin    = isset($_REQUEST['pin']) ? trim($_REQUEST['pin']) : '';
        $pid    = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : '';
        $choice = isset($_REQUEST['choice']) ? (int)$_REQUEST['choice'] : 0;
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($pid, $choice) {
                if (!isset($g['players'][$pid])) throw new GameError('no_player');
                if ($g['status'] !== 'question') throw new GameError('not_open');
                if (!empty($g['players'][$pid]['answered'])) return; // déjà répondu : on ignore
                $g['players'][$pid]['answered']   = true;
                $g['players'][$pid]['lastChoice'] = $choice;
                $correct = isset($g['question']['correctIndex']) ? (int)$g['question']['correctIndex'] : -1;
                if ($choice === $correct && $correct > 0) {
                    $g['players'][$pid]['score'] += 100;
                }
            });
        } catch (GameError $e) { jerr($e->getMessage()); }
        if (!$game) jerr('not_found');
        jexit(public_state($game, $pid));
    }

    case 'state': {
        $pin = isset($_REQUEST['pin']) ? trim($_REQUEST['pin']) : '';
        $pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : null;
        // Lecture seule : on libère tout de suite le verrou de session (polling fréquent).
        session_write_close();
        $game = load_game($GAME_DIR, $pin);
        if ($game === null) jerr('not_found'); // la partie n'existe plus (seul cas « définitif »)
        if ($game === false) jerr('busy');     // échec passager : le client réessaie
        // Champs hôte uniquement si la page hôte le demande explicitement (host=1) ET s'authentifie.
        $hostView = isset($_REQUEST['host']) && $_REQUEST['host'] === '1';
        jexit(public_state($game, $pid, $hostView));
    }

    default:
        jerr('unknown_action');
}

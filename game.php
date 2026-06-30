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
 *   abort   (hôte)   : supprime la partie
 *   join    (joueur) : pin + name → crée un joueur → renvoie {pid}
 *   answer  (joueur) : pin + pid + choice → enregistre, +100 si correct
 *   state   (tous)   : renvoie l'état nettoyé (correctIndex masqué hors reveal/ended)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$GAME_DIR = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lgbt_kahoot';

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

function load_game($dir, $pin) {
    if (!preg_match('/^\d{6}$/', (string)$pin)) return null;
    $path = game_path($dir, $pin);
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
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
    $fp = fopen($path, 'r+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $game = json_decode($raw, true);
    if (!is_array($game)) { flock($fp, LOCK_UN); fclose($fp); return null; }
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

/** Supprime les parties de plus de 3 heures (ménage best-effort). */
function cleanup_old($dir) {
    if (!is_dir($dir)) return;
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') as $f) {
        if (@filemtime($f) < time() - 3 * 3600) { @unlink($f); }
    }
}

/** L'appelant est-il l'hôte de cette partie ? */
function is_host($game) {
    return isset($game['hostSid']) && $game['hostSid'] === session_id();
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
        'qid'          => isset($idArr[$idx]) ? $idArr[$idx] : '',
        'qtype'        => isset($typeArr[$idx]) ? $typeArr[$idx] : 'qcm',
        'qNumber'      => $idx,
        'totalQ'       => isset($_SESSION['TotalQuestions']) ? (int)$_SESSION['TotalQuestions'] : 0,
    ];
}

/** État renvoyé au client (correctIndex masqué hors reveal/ended). */
function public_state($game, $pid = null) {
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
        $out['correctIndex'] = (int)$game['question']['correctIndex'];
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

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'create': {
        cleanup_old($GAME_DIR);
        if (!isset($_SESSION['level'])) jerr('no_level');
        // Génère un PIN à 6 chiffres non utilisé.
        $pin = null;
        for ($try = 0; $try < 30; $try++) {
            $cand = str_pad((string)rand_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (!is_file(game_path($GAME_DIR, $cand))) { $pin = $cand; break; }
        }
        if ($pin === null) jerr('pin_alloc');
        $game = [
            'pin'     => $pin,
            'level'   => $_SESSION['level'],
            'lang'    => isset($_SESSION['language']) ? $_SESSION['language'] : 'fr',
            'status'  => 'lobby',
            'hostSid' => session_id(),
            'qNumber' => 0,
            'totalQ'  => isset($_SESSION['TotalQuestions']) ? (int)$_SESSION['TotalQuestions'] : 0,
            'question' => null,
            'players' => [],
            'createdAt' => time(),
        ];
        save_game($GAME_DIR, $game);
        $_SESSION['game_pin'] = $pin;
        jexit(['ok' => true, 'pin' => $pin]);
    }

    case 'setq': {
        $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : (isset($_SESSION['game_pin']) ? $_SESSION['game_pin'] : '');
        // La question courante est lue dans la session de l'hôte (hors verrou : pas d'accès fichier).
        $cq  = current_question_from_session();
        $sid = session_id();
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($cq, $sid) {
                if (!isset($g['hostSid']) || $g['hostSid'] !== $sid) throw new GameError('not_host');
                if (!$cq) throw new GameError('no_question');
                $g['question'] = array(
                    'text'         => $cq['text'],
                    'answers'      => $cq['answers'],
                    'correctIndex' => $cq['correctIndex'],
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
        if (!$game) jerr('no_game');
        jexit(public_state($game));
    }

    case 'reveal':
    case 'end': {
        $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : (isset($_SESSION['game_pin']) ? $_SESSION['game_pin'] : '');
        $newStatus = ($action === 'end') ? 'ended' : 'reveal';
        $sid = session_id();
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($sid, $newStatus) {
                if (!isset($g['hostSid']) || $g['hostSid'] !== $sid) throw new GameError('not_host');
                $g['status'] = $newStatus;
            });
        } catch (GameError $e) { jerr($e->getMessage()); }
        if (!$game) jerr('no_game');
        jexit(public_state($game));
    }

    case 'abort': {
        $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : (isset($_SESSION['game_pin']) ? $_SESSION['game_pin'] : '');
        $game = load_game($GAME_DIR, $pin);
        if ($game && is_host($game)) { @unlink(game_path($GAME_DIR, $pin)); }
        unset($_SESSION['game_pin']);
        jexit(['ok' => true]);
    }

    case 'join': {
        $pin  = isset($_REQUEST['pin']) ? trim($_REQUEST['pin']) : '';
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        if ($name === '') jerr('no_name');
        // Nettoyage / limite de longueur du pseudo.
        $name = mb_substr(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), 0, 24);
        $pid  = rand_hex(8);
        try {
            $game = mutate_game($GAME_DIR, $pin, function (&$g) use ($pid, $name) {
                if ($g['status'] === 'ended') throw new GameError('ended');
                if (count($g['players']) >= 200) throw new GameError('full');
                $g['players'][$pid] = array(
                    'name'       => $name,
                    'score'      => 0,
                    'answered'   => false,
                    'lastChoice' => null,
                );
            });
        } catch (GameError $e) { jerr($e->getMessage()); }
        if (!$game) jerr('no_game');
        jexit(['ok' => true, 'pid' => $pid, 'name' => $name, 'status' => $game['status']]);
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
        if (!$game) jerr('no_game');
        jexit(public_state($game, $pid));
    }

    case 'state': {
        $pin = isset($_REQUEST['pin']) ? trim($_REQUEST['pin']) : '';
        $pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : null;
        $game = load_game($GAME_DIR, $pin);
        if (!$game) jerr('no_game');
        jexit(public_state($game, $pid));
    }

    default:
        jerr('unknown_action');
}

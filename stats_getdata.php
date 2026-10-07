<?php
require_once 'conf.php';
header('Content-Type: application/json');
// Accès au site par clé (access.php) : refuse les données si aucune clé valide en session.
// Même durée de vie de session que index.php / game.php (sinon le GC par défaut 1440 s
// peut supprimer une session hôte/questionnaire inactive).
ini_set('session.gc_maxlifetime', 31536000);
session_start();
require_once __DIR__ . '/access.php';
// auth.php : session déjà active -> ne la redémarre pas ; fournit admin_is_logged_in()
// et applique l'expiration d'inactivité admin (ADMIN_IDLE_TIMEOUT).
require_once __DIR__ . '/auth.php';

// BEGIN stats_scope
/**
 * Résout le périmètre (groupe) des statistiques. SÉCURITÉ : le paramètre demandé
 * ($requested, venant de $_GET['group']) n'est pris en compte QUE si $is_admin est vrai
 * (vérifié côté serveur via admin_is_logged_in()). Sinon : uniquement la clé du visiteur.
 * Renvoie ['mode' => 'key', 'key' => K] | ['mode' => 'all'] | ['mode' => 'none'] | ['mode' => 'deny'].
 */
function stats_resolve_scope($is_admin, $requested, $session_key) {
    $session_key = access_normalize_key($session_key === null ? '' : $session_key);
    if (!$is_admin) {
        // Visiteur : paramètre ignoré, toujours sa propre clé (échec fermé si absente).
        return $session_key !== '' ? ['mode' => 'key', 'key' => $session_key] : ['mode' => 'deny'];
    }
    $requested = is_string($requested) ? trim($requested) : '';
    if ($requested === '') {
        // Admin sans choix explicite : sa clé de visiteur si présente, sinon global.
        return $session_key !== '' ? ['mode' => 'key', 'key' => $session_key] : ['mode' => 'all'];
    }
    if ($requested === '__all__') return ['mode' => 'all'];
    if ($requested === '__none__') return ['mode' => 'none'];
    $key = access_normalize_key($requested);
    if ($key === '' || strlen($key) > 32) return ['mode' => 'deny'];
    return ['mode' => 'key', 'key' => $key];
}

/** Valide une date Y-m-d stricte ; renvoie la chaîne normalisée ou null. */
function stats_valid_date($s) {
    if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
// END stats_scope

$is_admin = admin_is_logged_in();
// Un admin connecté peut consulter les statistiques sans clé de visiteur (comme les autres
// pages d'administration) ; tout autre visiteur doit avoir une clé valide.
if (!$is_admin && !access_session_valid()) {
    http_response_code(403);
    echo json_encode(['error' => 'access_key_required']);
    exit();
}

$scope = stats_resolve_scope(
    $is_admin,
    isset($_GET['group']) ? $_GET['group'] : '',
    isset($_SESSION['access_key']) ? $_SESSION['access_key'] : ''
);

// Filtre de dates (inclusif) : from = début de journée, to = fin de journée (< lendemain 00:00).
$date_from = stats_valid_date(isset($_GET['from']) ? $_GET['from'] : '');
$date_to   = stats_valid_date(isset($_GET['to']) ? $_GET['to'] : '');
if ($date_from !== null && $date_to !== null && $date_from > $date_to) {
    $tmp = $date_from; $date_from = $date_to; $date_to = $tmp;
}

try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Module sélectionné (anciennement "niveau"). Par défaut module 2.
    $level = isset($_GET['level']) && $_GET['level'] !== '' ? (int) $_GET['level'] : 2;

    $level_stmt = $pdo->prepare("SELECT id FROM GSDatabase WHERE level = ?");
    $level_stmt->execute([$level]);
    $level2_question_ids = $level_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $scope_out = ['mode' => $scope['mode'], 'admin' => $is_admin];

    if (empty($level2_question_ids)) {
        echo json_encode(["formattedData" => [], "answers" => [], "totalResponses" => 0, "level" => $level,
            "perDay" => new stdClass(), "firstDate" => null, "lastDate" => null,
            "from" => $date_from, "to" => $date_to, "dateSupported" => true, "scope" => $scope_out]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM GSDatabase WHERE level = ?");
    $stmt->execute([$level]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // created_at peut manquer (migration faite par console.php, onglet database) : lecture seule ici.
    $has_created_at = false;
    try {
        $cols_r = $pdo->query("DESCRIBE `GSDatabaseR`")->fetchAll(PDO::FETCH_COLUMN);
        $has_created_at = in_array('created_at', $cols_r);
    } catch (PDOException $e) {
        $has_created_at = false;
    }

    // Catégorisation par groupe : par défaut, seules les réponses enregistrées avec la
    // clé d'accès du visiteur courant sont prises en compte (groupes non mélangés).
    // Échec FERMÉ : si la colonne access_key est absente (migration indisponible, p. ex.
    // droits DB insuffisants), on ne retombe JAMAIS sur un SELECT * non filtré pour un
    // périmètre « clé » ou « sans clé » - on renvoie une réponse vide, même forme JSON.
    // Le périmètre « all » (global) n'est atteignable que par un admin connecté.
    $where = [];
    $params = [];
    $run_query = true;
    if ($scope['mode'] === 'deny') {
        $run_query = false;
    } elseif ($scope['mode'] === 'key' || $scope['mode'] === 'none') {
        if (!access_ensure_responses_key_column($pdo)) {
            $run_query = false;
        } elseif ($scope['mode'] === 'key') {
            $where[] = 'access_key = ?';
            $params[] = $scope['key'];
        } else {
            $where[] = 'access_key IS NULL';
        }
    } elseif ($scope['mode'] !== 'all') {
        $run_query = false;
    }
    if ($has_created_at && $date_from !== null) {
        $where[] = 'created_at >= ?';
        $params[] = $date_from . ' 00:00:00';
    }
    if ($has_created_at && $date_to !== null) {
        $where[] = 'created_at < ?';
        $params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
    }

    if ($run_query) {
        $sql = "SELECT id, reponse" . ($has_created_at ? ", created_at" : "") . " FROM GSDatabaseR";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_reponses_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_reponses_db = [];
    }

    $QuestionsR = [];
    $submissions_with_level2_answers = [];
    $perDay = [];

    foreach ($all_reponses_db as $row) {
        $responseString = $row['reponse'];
        if (empty($responseString) || $responseString === 'null') {
            continue;
        }

        $submission_id = $row['id'];
        $contains_level2_answer = false;

        $responseString = str_replace('&amp;', '&', $responseString);
        $parts = explode('__', $responseString);

        foreach ($parts as $part) {
            if (empty($part)) continue;

            $current_question_id = null;

            if (strpos($part, '||') !== false) {
                $q_part = explode('||', $part)[0];
                $current_question_id = (int) substr($q_part, strpos($q_part, '@') + 1);
            } elseif (strpos($part, '&&') !== false) {
                 $main_q_part = explode('&&', $part)[0];
                 $current_question_id = (int) str_replace('Q@', '', $main_q_part);
            }

            if ($current_question_id && in_array($current_question_id, $level2_question_ids)) {
                $contains_level2_answer = true;

                if (strpos($part, '&&') !== false) {
                    $subParts = explode('&&', $part);
                    $subQuestions = []; $subResponses = [];
                    foreach ($subParts as $subPart) {
                        if (strpos($subPart, '|') !== false) {
                            list($question, $response) = explode('|', $subPart);
                            $subQuestions[] = substr($question, strpos($question, '@') + 1);
                            $subResponses[] = substr($response, strpos($response, '@') + 1);
                        }
                    }
                    $QuestionsR[] = ['question' => $current_question_id, 'response' => null, 'subquestion' => implode(',', $subQuestions), 'subresponse' => implode(',', $subResponses)];
                } else {
                    list($question, $response) = explode('||', $part);
                    $responseValue = (int) substr($response, strpos($response, '@') + 1);
                    $QuestionsR[] = ['question' => $current_question_id, 'response' => $responseValue];
                }
            }
        }

        if ($contains_level2_answer && !isset($submissions_with_level2_answers[$submission_id])) {
            $submissions_with_level2_answers[$submission_id] = true;
            // Répartition par jour (uniquement les soumissions comptées pour ce module).
            if ($has_created_at && !empty($row['created_at'])) {
                $day = substr((string) $row['created_at'], 0, 10);
                if (stats_valid_date($day) !== null) {
                    $perDay[$day] = isset($perDay[$day]) ? $perDay[$day] + 1 : 1;
                }
            }
        }
    }

    $totalResponses = count($submissions_with_level2_answers);
    ksort($perDay);
    $days = array_keys($perDay);


    $formattedData = [];
    foreach ($questions as $row) {
        $qtype = $row['qtype'];
        $qid = $row['id'];
        $questionText = $row['question'];
        if ($qtype === "qcm" || $qtype === "echelle") {
            $responses = [];
            for ($i = 1; $i <= 5; $i++) { if (!empty($row["rep$i"])) { $responses[] = $row["rep$i"]; } }
            $formattedData[] = [ 'id' => $qid, 'type' => 'qcm', 'question' => $questionText, 'responses' => $responses ];
        } elseif ($qtype === "mct") {
            $subQuestions = explode("--", $row['rep1']);
            $responses = [];
            for ($i = 2; $i <= 5; $i++) { if (!empty($row["rep$i"])) { $responses[] = $row["rep$i"]; } }
            $formattedData[] = [ 'id' => $qid, 'type' => 'mct', 'question' => $questionText, 'sub_questions' => $subQuestions, 'responses' => $responses ];
        } elseif ($qtype === "lien") {
            $subQuestions = explode("--", $row['rep1']);
            $subResponses = explode("--", $row['rep2']);
            $formattedData[] = [ 'id' => $qid, 'type' => 'lien', 'question' => $questionText, 'sub_questions' => $subQuestions, 'sub_responses' => $subResponses ];
        }
    }

    $response = [
        "formattedData" => $formattedData,
        "answers" => $QuestionsR,
        "totalResponses" => $totalResponses,
        "level" => $level,
        "perDay" => $perDay ? $perDay : new stdClass(),
        "firstDate" => $days ? $days[0] : null,
        "lastDate" => $days ? $days[count($days) - 1] : null,
        "from" => $date_from,
        "to" => $date_to,
        "dateSupported" => $has_created_at,
        "scope" => $scope_out
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    error_log('[stats_getdata] ' . $e->getMessage());
    echo json_encode(['error' => 'Erreur base de données', 'formattedData' => [], 'answers' => [], 'totalResponses' => 0]);
}
?>

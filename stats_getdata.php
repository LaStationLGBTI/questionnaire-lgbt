<?php
require_once 'conf.php'; 
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $level2_stmt = $pdo->query("SELECT id FROM GSDatabase WHERE level = 2");
    $level2_question_ids = $level2_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (empty($level2_question_ids)) {
        echo json_encode(["formattedData" => [], "answers" => [], "totalResponses" => 0]);
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM GSDatabase WHERE level = 2");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM GSDatabaseR");
    $all_reponses_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $QuestionsR = [];
    $submissions_with_level2_answers = [];

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

        if ($contains_level2_answer) {
            $submissions_with_level2_answers[$submission_id] = true;
        }
    }
    
    $totalResponses = count($submissions_with_level2_answers);


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
        "totalResponses" => $totalResponses
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage(), 'formattedData' => [], 'answers' => [], 'totalResponses' => 0]);
}
?>


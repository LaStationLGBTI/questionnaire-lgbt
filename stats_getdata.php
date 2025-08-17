<?php
require_once 'conf.php'; 
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ИЗМЕНЕНО: Запрос к GSDatabase с фильтром level = 2
    $stmt = $pdo->query("SELECT * FROM GSDatabase WHERE level = 2");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ИЗМЕНЕНО: Подсчет общего количества ответов из GSDatabaseR с фильтром level = 2
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM GSDatabaseR WHERE level = 2");
    $totalResponses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ИЗМЕНЕНО: Запрос всех ответов из GSDatabaseR с фильтром level = 2
    $stmt = $pdo->query("SELECT * FROM GSDatabaseR WHERE level = 2");
    $reponsesdb = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $QuestionsR = [];
    $formattedData = [];
    foreach ($reponsesdb as $row) {
        $responseString = $row['reponse'];
        if ($responseString === null || $responseString === 'null') continue;

        $responseString = str_replace('&amp;', '&', $responseString);
        $parts = explode('__', $responseString);
        foreach ($parts as $part) {
            if (empty($part)) continue;

            if (strpos($part, '&&') !== false) {
                $subParts = explode('&&', $part);
                $mainQuestion = '';
                $subQuestions = [];
                $subResponses = [];
                if (empty($mainQuestion) && isset($subParts[0]) && strpos($subParts[0], '@') !== false) {
                    $mainQuestion = explode('@', $subParts[0])[1];
                    $mainQuestion = (int) $mainQuestion;
                }
                foreach ($subParts as $subPart) {
                    $subPartArray = explode('|', $subPart);
                    if (count($subPartArray) === 2) {
                        list($question, $response) = $subPartArray;
                        $questionValue = substr($question, strpos($question, '@') + 1);
                        $responseValue = substr($response, strpos($response, '@') + 1);
                        $subQuestions[] = $questionValue;
                        $subResponses[] = $responseValue;
                    }
                }
                $QuestionsR[] = [
                    'question' => $mainQuestion,
                    'response' => null,
                    'subquestion' => implode(',', $subQuestions),
                    'subresponse' => implode(',', $subResponses)
                ];
            } else if (strpos($part, '||') !== false) {
                list($question, $response) = explode('||', $part);
                $questionValue = (int) substr($question, strpos($question, '@') + 1);
                $responseValue = (int) substr($response, strpos($response, '@') + 1);
                $QuestionsR[] = [
                    'question' => $questionValue,
                    'response' => $responseValue
                ];
            }
        }
    }

    foreach ($questions as $row) {
        $qtype = $row['qtype'];
        $qid = $row['id'];
        $questionText = $row['question'];
        if ($qtype === "qcm" || $qtype === "echelle") {
            $responses = [];
            for ($i = 1; $i <= 5; $i++) {
                if (!empty($row["rep$i"])) {
                    $responses[] = $row["rep$i"];
                }
            }
            $formattedData[] = [
                'id' => $qid,
                'type' => 'qcm',
                'question' => $questionText,
                'responses' => $responses,
            ];
        } elseif ($qtype === "mct") {
            $subQuestions = explode("--", $row['rep1']);
            $responses = [];
            for ($i = 2; $i <= 5; $i++) {
                if (!empty($row["rep$i"])) {
                    $responses[] = $row["rep$i"];
                }
            }
            $formattedData[] = [
                'id' => $qid,
                'type' => 'mct',
                'question' => $questionText,
                'sub_questions' => $subQuestions,
                'responses' => $responses,
            ];
        } elseif ($qtype === "lien") {
            $subQuestions = explode("--", $row['rep1']);
            $subResponses = explode("--", $row['rep2']);
            $formattedData[] = [
                'id' => $qid,
                'type' => 'lien',
                'question' => $questionText,
                'sub_questions' => $subQuestions,
                'sub_responses' => $subResponses,
            ];
        }
    }

    $response = [
        "formattedData" => $formattedData,
        "answers" => isset($QuestionsR) ? $QuestionsR : [],
        "totalResponses" => $totalResponses
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

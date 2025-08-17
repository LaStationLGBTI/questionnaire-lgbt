<?php
require_once 'conf.php'; 
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ШАГ 1: Получаем ID всех вопросов, которые относятся к уровню 2
    $level2_stmt = $pdo->query("SELECT id FROM GSDatabase WHERE level = 2");
    // PDO::FETCH_COLUMN создает простой массив из ID, например: [23, 24, 25, ...]
    $level2_question_ids = $level2_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Если вопросов уровня 2 нет, возвращаем пустой результат
    if (empty($level2_question_ids)) {
        echo json_encode(["formattedData" => [], "answers" => [], "totalResponses" => 0]);
        exit;
    }

    // Получаем полную информацию по вопросам уровня 2 для построения графиков
    $stmt = $pdo->query("SELECT * FROM GSDatabase WHERE level = 2");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ШАГ 2: Получаем ВСЕ ответы из таблицы GSDatabaseR без фильтра по уровню
    $stmt = $pdo->query("SELECT * FROM GSDatabaseR");
    $all_reponses_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $QuestionsR = [];
    $submissions_with_level2_answers = []; // Сюда будем записывать ID уникальных сессий

    // ШАГ 3: Фильтруем ответы в PHP
    foreach ($all_reponses_db as $row) {
        $responseString = $row['reponse'];
        if (empty($responseString) || $responseString === 'null') {
            continue;
        }

        $submission_id = $row['id']; // ID уникальной сессии ответа
        $contains_level2_answer = false;
        
        $responseString = str_replace('&amp;', '&', $responseString);
        $parts = explode('__', $responseString);
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            $current_question_id = null;

            // Определяем ID вопроса в текущем фрагменте ответа
            if (strpos($part, '||') !== false) { // Формат QCM: Q@23||R@1
                $q_part = explode('||', $part)[0];
                $current_question_id = (int) substr($q_part, strpos($q_part, '@') + 1);
            } elseif (strpos($part, '&&') !== false) { // Формат MCT/Lien: 23&&Q@1|R@2...
                 $main_q_part = explode('&&', $part)[0];
                 $current_question_id = (int) str_replace('Q@', '', $main_q_part);
            }

            // Если мы нашли ID вопроса и он относится к уровню 2
            if ($current_question_id && in_array($current_question_id, $level2_question_ids)) {
                $contains_level2_answer = true;
                // Добавляем этот фрагмент ответа в массив для обработки
                // (логика парсинга перенесена сюда)
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

        // Если в этой сессии был хотя бы один ответ на вопрос 2-го уровня, считаем ее
        if ($contains_level2_answer) {
            $submissions_with_level2_answers[$submission_id] = true;
        }
    }
    
    $totalResponses = count($submissions_with_level2_answers);

    // --- Дальнейший код для форматирования данных остается без изменений ---

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

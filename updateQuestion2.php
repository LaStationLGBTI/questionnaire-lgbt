<?php

header('Content-Type: application/json');
session_start();
// require_once 'conf.php'; // Конфигурация БД больше не нужна в этом файле

// Этот блок можно упростить или удалить, так как основная логика теперь на стороне index.php
if (isset($_SESSION["finish"]) && $_SESSION["finish"] == 1) {
    if ($_SESSION['LastQuestion'] > $_SESSION['TotalQuestions']) {
        $answers = explode("__", $_SESSION["answer"]);
        // Проверка на случай, если индекс окажется за пределами массива
        $lastAnswerIndex = $_SESSION['LastQuestion'] - 1;
        if (isset($answers[$lastAnswerIndex])) {
            $lastCorrectAnswer = $answers[$lastAnswerIndex];
            echo "fin__" . $lastCorrectAnswer;
        } else {
            echo "fin__"; // Отправляем fin, даже если ответ не найден
        }
        exit();
    }
}

if (isset($_SESSION['QuestionToUse'])) {
    if (isset($_SESSION['start'])) {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['choise'])) {
                if (isset($_SESSION['reponses'])) {
                    if (explode("__", $_SESSION["qtype"])[$_SESSION["LastQuestion"]] == "lien" || explode("__", $_SESSION["qtype"])[$_SESSION["LastQuestion"]] == "mct") {
                        $_SESSION['reponses'] .= "__Q@" . htmlspecialchars($_POST['choise']);
                    } else {
                        $_SESSION['reponses'] .= "__Q@" . explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]] . "||R@" . htmlspecialchars($_POST['choise']);
                    }
                } else {
                    $_SESSION['reponses'] = "Q@" . explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]] . "||R@" . htmlspecialchars($_POST['choise']);
                }
            }
        }
        if ($_SESSION["LastQuestion"] < $_SESSION["TotalQuestions"]) {
            $_SESSION["LastQuestion"] += 1;

            $prochaineQ = explode("__", $_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]]; //0
            $prochaineQ .= "__" . explode("__", $_SESSION["Rep1"])[$_SESSION["LastQuestion"]]; //1
            $prochaineQ .= "__" . explode("__", $_SESSION["Rep2"])[$_SESSION["LastQuestion"]]; //2
            $prochaineQ .= "__" . explode("__", $_SESSION["Rep3"])[$_SESSION["LastQuestion"]]; //3
            $prochaineQ .= "__" . explode("__", $_SESSION["Rep4"])[$_SESSION["LastQuestion"]]; //4
            $prochaineQ .= "__" . explode("__", $_SESSION["Rep5"])[$_SESSION["LastQuestion"]]; //5
            $prochaineQ .= "__" . $_SESSION["LastQuestion"]; //6
            $prochaineQ .= "__" . explode("__", $_SESSION["answer"])[$_SESSION["LastQuestion"] - 1]; //7
            $prochaineQ .= "__" . explode("__", $_SESSION["qtype"])[$_SESSION["LastQuestion"]]; //8
            $prochaineQ .= "__" . explode("__", $_SESSION["qtype"])[$_SESSION["LastQuestion"] - 1]; //9
            $prochaineQ .= "__" . explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]]; //10

        } else {
            // --- ИЗМЕНЕНИЕ ---
            // Просто сообщаем, что анкета закончена. Вся запись в БД перенесена в index.php
            $prochaineQ = "fin";
            $prochaineQ .= "__" . explode("__", $_SESSION["answer"])[$_SESSION["LastQuestion"]];
            $_SESSION["LastQuestion"] += 1;
            $_SESSION["finish"] = 1;
        }
    } else {
        $questionId = isset($_SESSION["IdInUse"]) ? explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]] : 'N/A';
        echo "Erreur code 2 lors de la sélection de la question, veuillez contacter 'La station', id de la question est " . $questionId;
        exit();
    }
} else {
    $questionId = isset($_SESSION["IdInUse"]) ? explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]] : 'N/A';
    echo "Erreur code 3 lors de la sélection de la question, veuillez contacter 'La station', id de la question est " . $questionId;
    exit();
}
echo $prochaineQ;
?>

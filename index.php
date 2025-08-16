<?php
session_start();
// Язык теперь установлен по умолчанию, так как выбор убран.
$lang = 'fr';
$_SESSION['language'] = $lang;

// Этот блок инициализирует анкету при первом заходе
if (!isset($_SESSION["start"])) {
    // Если уровень не передан в URL, возвращаем на страницу выбора
    if (!isset($_GET['level']) || !is_numeric($_GET['level'])) {
        header('Location: start.php');
        exit();
    }
    
    $selectedLevel = (int)$_GET['level'];
    $_SESSION['level'] = $selectedLevel;

    require_once 'conf.php';
    try {
        $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Ошибка подключения к БД: " . $e->getMessage());
    }
    
    // Загружаем вопросы из GSDatabase в случайном порядке
    $table = 'GSDatabase';
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE level = ? ORDER BY RAND()");
    $stmt->execute([$selectedLevel]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        die("Для выбранного уровня ($selectedLevel) вопросы не найдены. <a href='start.php'>Вернуться к выбору</a>.");
    } else {
        // Очищаем старые сессии и заполняем новыми данными
        $_SESSION["QuestionToUse"] = $_SESSION["Rep1"] = $_SESSION["Rep2"] = $_SESSION["Rep3"] = $_SESSION["Rep4"] = $_SESSION["Rep5"] = $_SESSION["answer"] = $_SESSION["qtype"] = $_SESSION["IdInUse"] = "";
        
        foreach ($results as $row) {
            $_SESSION["QuestionToUse"] .= $row["question"] . "__";
            $_SESSION["Rep1"] .= $row["rep1"] . "__";
            $_SESSION["Rep2"] .= $row["rep2"] . "__";
            $_SESSION["Rep3"] .= $row["rep3"] . "__";
            $_SESSION["Rep4"] .= $row["rep4"] . "__";
            $_SESSION["Rep5"] .= $row["rep5"] . "__";
            $_SESSION["answer"] .= $row["answer"] . "__";
            $_SESSION["qtype"] .= $row["qtype"] . "__";
            $_SESSION["IdInUse"] .= $row["id"] . "__";
        }

        // Убираем лишние "__" в конце строк
        $_SESSION["QuestionToUse"] = substr($_SESSION["QuestionToUse"], 0, -2);
        $_SESSION["Rep1"] = substr($_SESSION["Rep1"], 0, -2);
        $_SESSION["Rep2"] = substr($_SESSION["Rep2"], 0, -2);
        $_SESSION["Rep3"] = substr($_SESSION["Rep3"], 0, -2);
        $_SESSION["Rep4"] = substr($_SESSION["Rep4"], 0, -2);
        $_SESSION["Rep5"] = substr($_SESSION["Rep5"], 0, -2);
        $_SESSION["answer"] = substr($_SESSION["answer"], 0, -2);
        $_SESSION["qtype"] = substr($_SESSION["qtype"], 0, -2);
        $_SESSION["IdInUse"] = substr($_SESSION["IdInUse"], 0, -2);
        
        // Устанавливаем стартовые параметры сессии
        $_SESSION["start"] = 1;
        $_SESSION["TotalQuestions"] = count($results) -1;
        $_SESSION["LastQuestion"] = -1;
    }
}
?>
<!DOCTYPE html>
<html style="font-size: 16px;" lang="fr">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="utf-8">
    <title>Анкета</title>
    <link rel="stylesheet" href="nicepage.css" media="screen">
    <link rel="stylesheet" href="Question.css" media="screen">
    <script class="u-script" type="text/javascript" src="jquery.js" defer=""></script>
    <script class="u-script" type="text/javascript" src="nicepage.js" defer=""></script>
</head>
<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="fr">
    <section class="u-clearfix u-section-1" id="sec-089e">
        <div class="u-clearfix u-sheet u-sheet-1">
            <div id="question-container" class="u-container-style u-grey-10 u-group u-group-1">
                <div class="u-container-layout u-container-layout-1">
                    <h2 id="question" class="u-text u-text-default u-text-1">Chargement...</h2>
                    <div id="reponses" class="u-form u-form-1">
                        <div class="reponse-grid">
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <script>
    function Suivant(choise) {
        let formData = new FormData();
        formData.append('choise', choise);

        fetch('updateQuestion2.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.startsWith("fin")) {
                document.getElementById("question-container").innerHTML = `<h2 style='text-align:center; padding-top: 50px;'>Merci d'avoir participé !</h2><p style='text-align:center;'>Vous pouvez fermer cette page.</p>`;
                return;
            }
            if (data.includes("Erreur")) {
                document.getElementById("question").innerText = data;
                return;
            }
            displayQuestion(data.split("__"));
        });
    }

    function displayQuestion(parts) {
        const questionText = parts[0];
        const answers = [parts[1], parts[2], parts[3], parts[4], parts[5]].filter(rep => rep && rep !== 'null');
        const qType = parts[8];
        const reponsesContainer = document.querySelector("#reponses .reponse-grid");

        document.getElementById("question").innerText = questionText;
        reponsesContainer.innerHTML = ''; // Очищаем старые ответы

        if (qType === 'echelle' || qType === 'qcm') {
            answers.forEach((answer, index) => {
                const answerDiv = document.createElement('div');
                answerDiv.className = 'u-border-none u-btn u-btn-round u-button-style u-custom-color-1 u-hover-palette-1-light-1 u-radius-50 u-btn-1';
                answerDiv.innerText = answer;
                answerDiv.onclick = () => Suivant(index + 1);
                reponsesContainer.appendChild(answerDiv);
            });
        }
    }

    // Эта функция запускается сразу при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        fetch('StartQuestions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes("Erreur") || data.includes("err, no start")) {
                document.getElementById("question").innerText = "Erreur de chargement de la question. Veuillez réessayer.";
                return;
            }
            displayQuestion(data.split("__"));
        });
    });
    </script>
</body>
</html>

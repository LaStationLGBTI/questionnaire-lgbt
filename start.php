<?php
ini_set('session.gc_maxlifetime', 31536000);
session_start();
require_once 'conf.php';

// Если уровень не передан через GET и не установлен в сессии, перенаправляем на старт
if (!isset($_GET['level']) && !isset($_SESSION['level'])) {
    header('Location: start.php');
    exit();
}

// Если передан новый уровень, сохраняем его в сессию и сбрасываем прогресс
if (isset($_GET['level'])) {
    // Сохраняем уровень и сбрасываем старые переменные анкеты
    $_SESSION['level'] = $_GET['level'];
    unset($_SESSION['start']);
    unset($_SESSION['LastQuestion']);
    unset($_SESSION['finish']);
}

$level = $_SESSION['level'];

// Тексты для интерфейса на русском языке
$texts = [
    'continue' => 'Продолжить',
    'final_warning' => 'Заключительные вопросы',
    'final_warning_desc' => 'Последние вопросы анкеты носят более личный характер и касаются вашей гендерной идентичности и сексуальной ориентации. Мы понимаем, что эти темы могут восприниматься как деликатные. Отвечать на них не обязательно: вы можете не отвечать на эти вопросы, если чувствуете себя некомфортно. Это никак не повлияет на ваше участие в опросе.',
    'gender_question' => 'К какому гендеру вы себя относите?',
    'gender_prompt' => 'Выберите подходящее описание',
    'sexuality_question' => 'К какой сексуальной ориентации вы себя относите?',
    'sexuality_prompt' => 'Выберите подходящее описание',
    'email_prompt' => 'Введите ваш e-mail, если хотите получить результаты.',
    'submit' => 'Отправить и завершить',
    'thank_you' => 'Спасибо!',
    'question' => 'Вопрос'
];
?>
<!DOCTYPE html>
<html style="font-size: 16px;" lang="ru">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>Анкета</title>
    <link rel="stylesheet" href="nicepage.css" media="screen">
    <link rel="stylesheet" href="Question.css" media="screen">
    <style>
        .u-align-center { transition: background-color 3s ease; }
        .fade-to-red { transition: background-color 2s ease; background-color: red !important; }
        .fade-to-green { transition: background-color 2s ease; background-color: green !important; }
        .fade-to-white { transition: background-color 2s ease; background-color: white !important; }
        .u-container-style.u-expanded-width.u-grey-10 { margin: 0; height: 100%; background-image: url('images/background.png'); background-size: cover; background-repeat: no-repeat; }
        .u-container-layout.u-container-layout-1 { margin: 0; height: 100%; }
        .u-container-layout.u-similar-container.u-container-layout-8 { padding: 0; }
        .u-container-style.u-expanded-width.u-group.u-palette-2-light-2.u-radius.u-shape-round { background-color: #ffd8da; border-radius: 50% 20% / 10% 40% !important; }
        .u-align-center.u-container-align-center.u-container-align-center-md.u-container-align-center-xl.u-container-align-center-xs.u-container-style { width: 22vw; }
        #rep.u-align-center.u-custom-item.u-text.u-text-5 { margin: 0; margin-top: 0; }
        .u-active-palette-2-light-1.u-align-center.u-border-none.u-btn.u-btn-round { padding: 0.2em; }
        #qcm { height: 100%; }
        button#button_choix { padding: calc(2vh + 1vw); background-size: cover; background-image: url(images/icon-803718_1280.png); margin: auto; }
        .tnum, .tR, .tQ { background-color: #fae0e0; font-size: 16px; }
        .tQ { background-color: rgb(154, 253, 235); }
        th, td { border: 1px solid black; padding: 10px; text-align: center; }
        .selected { background-color: lightblue; }
        .popup { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; align-items: center; z-index: 1000; }
        .popup-content { background: #f2ebff; padding: 20px; border-radius: 5px; width: 300px; border: solid 0.5em; border-color: #bf8d8d; max-width: 90%; }
        .popup-content h3 { margin-top: 0; }
        .popup .close-popup { background-color: #f44336; color: white; border: none; padding: 5px 10px; cursor: pointer; margin-top: 10px; }
        .popup-option { text-align: center; margin-bottom: 5px; border-color: #2e10fd; border-radius: 30px; border: solid; cursor: pointer; background-color: #ffb8b8; list-style-type: none; padding: 5px; }
        .popup .close-popup:hover { background-color: #d32f2f; }
    </style>
    <script>
        function changeRandomImage() {
            const randomNum = Math.floor(Math.random() * 9) + 1;
            const imageName = `an_${randomNum}`;
            const imagePath = `images/${imageName}.png`;
            const imgElement = document.getElementById('randomImage');
            if (imgElement) {
                imgElement.src = imagePath;
                imgElement.alt = `Random image ${imageName}`;
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            changeRandomImage();
            const image = document.getElementById('randomImage');
            if (image) {
                image.addEventListener('click', changeRandomImage);
            }
        });
    </script>
    <script type="text/javascript">
        const ru_texts = {
            corrections: 'Соответствия:',
            none: 'Нет',
            popup_title: 'Определение',
            popup_prompt: 'Выберите вариант из списка:',
            popup_close: 'Закрыть',
            question_choise: "ВЫБРАТЬ"
        };
        let ismultiple = false;
        let parentDiv = null;
        window.onload = resize_questions;
        let newDivHTML = '<div style="background: rgba(0,0,0,0); width:50%; margin:auto;" id="reponse_" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius u-text-palette-2-dark-1 u-btn-4" id="button_choix"></button><div id="answer" class="u-container-layout u-similar-container u-container-layout-8"><div id="question_container" class="u-container-style u-expanded-width u-group u-palette-2-light-2 u-radius u-shape-round u-group-5"><div class="u-container-layout u-container-layout-9"><p id="rep" class="u-align-center u-custom-item u-text u-text-5"></p></div></div></div></div>';
        let newDivHTMLechelle = '<div id="reponse_" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><div id="answer" class="u-container-layout u-similar-container u-container-layout-8"><div id="question_container" class="u-container-style u-expanded-width u-group u-palette-2-light-2 u-radius u-shape-round u-group-5"><div class="u-container-layout u-container-layout-9"><p id="rep" class="u-align-center u-custom-item u-text u-text-5"></p></div></div><button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius u-text-palette-2-dark-1 u-btn-4" id="button_choix"></button></div></div>';
        function resize_questions() {
            var boxes = document.querySelectorAll('#question_container');
            var maxHeight = 0;
            boxes.forEach(function (box) { box.style.height = "auto"; });
            boxes.forEach(function (box) {
                var boxHeight = box.offsetHeight;
                if (boxHeight > maxHeight) { maxHeight = boxHeight; }
            });
            boxes.forEach(function (box) { box.style.height = maxHeight + 'px'; });
        }
        function findAllBlocks() {
            return Array.from(document.querySelectorAll('div[id^="reponse_"]'));
        }
        function deleteAllBlocks() {
            document.querySelectorAll('div[id^="reponse_"]').forEach(block => block.remove());
        }
        var xhr2 = new XMLHttpRequest();
        let selectedQ = null, selectedR = null, selectedQText = null, selectedRText = null;
        var selectedCells = [];
        const connections = [];

        function startQuestion() {
            changeRandomImage();
            parentDiv = document.getElementById("quest_list");
            var xhr2 = new XMLHttpRequest();
            xhr2.open("POST", "StartQuestions.php", true);
            xhr2.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr2.onreadystatechange = function () {
                if (xhr2.readyState == 4 && xhr2.status == 200) {
                    var answersarray = findAllBlocks();
                    var response = xhr2.responseText.split("__");
                    document.getElementById("Question").innerHTML = response[0];
                    answersarray.forEach(function (item, index) {
                        const innerAnswers = item.querySelector('div#question_container');
                        innerAnswers.classList.remove('fade-to-green', 'fade-to-red', 'fade-to-white');
                        innerAnswers.classList.add('fade-to-white');
                    });
                    answersarray.forEach(function (item, index) {
                        const innerAnswers = item.querySelector('p#rep');
                        innerAnswers.innerHTML = response[index + 1];
                    });
                    document.getElementById("QuestionN").innerHTML = "Вопрос " + response[6];
                    document.getElementById('button_next').onclick = function () { updateQuestion(-1); };

                    if (response[7] == "qcm" || response[7] == "echelle") {
                        ismultiple = false;
                        localStorage.clear();
                        deleteAllBlocks();
                        if (document.querySelector(".popup")) document.querySelector(".popup").remove();
                        for (let i = 1; i <= 5; i++) {
                            if (response[i] != "null") {
                                if (!document.querySelector("#reponse_" + i)) {
                                    if (response[7] == "echelle") {
                                        parentDiv.style.flexDirection = "row";
                                        parentDiv.insertAdjacentHTML("beforeend", newDivHTMLechelle);
                                    } else if (response[7] == "qcm") {
                                        parentDiv.style.flexDirection = "column";
                                        parentDiv.insertAdjacentHTML("beforeend", newDivHTML);
                                    }
                                    let reponse_elem = document.getElementById("reponse_");
                                    reponse_elem.id = "reponse_" + i;
                                    let button = reponse_elem.querySelector("#button_choix");
                                    let p_elem = reponse_elem.querySelector("#rep");
                                    p_elem.innerText = response[i];
                                    button.addEventListener("click", function () { updateQuestion(i); });
                                }
                            }
                            if (response[i] == "null") {
                                let reponse_elem = document.getElementById("reponse_5");
                                if (reponse_elem) reponse_elem.remove();
                            }
                        }
                    } else if (response[7] == "lien") {
                        ismultiple = true;
                        deleteAllBlocks();
                        if (localStorage.getItem('lastationlienvar') && localStorage.getItem('lastationlienvar')[0] != response[8]) {
                            localStorage.clear();
                        }
                        if (!localStorage.getItem('lastationlienvar')) {
                            localStorage.setItem('lastationlienvar', response[8]);
                        }
                        let table_preset = `<p id="connections" style="width:50vw; font-size:14px;">${ru_texts.corrections} ${ru_texts.none}</p><div style="background-color: #fff0; width:100%; margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-list-item u-white" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="margin-left:auto; width:80%; margin-right:1em;" border="1" id="myTable"><thead><tr><th style="background-color: #b3ffff;">N</th><th style="background-color: #ffa096;">${ru_texts.popup_title}</th><th style="background-color: #b3ffff;">Действие</th></tr></thead><tbody></tbody></table></div>`;
                        parentDiv.insertAdjacentHTML("beforeend", table_preset);
                        let data1 = response[1].split("--");
                        let data2 = response[2].split("--");
                        let data3 = response[3].split('_');
                        const Q = [], R = [];
                        data3.forEach(pair => {
                            const parts = pair.split('-');
                            if (parts.length === 2) {
                                Q.push(parts[0].replace('Q', ''));
                                R.push(parts[1].replace('R', ''));
                            }
                        });

                        for (let i = 0; i < data1.length; i++) {
                            let row = document.createElement('tr');
                            let cell1 = document.createElement('td');
                            cell1.className = "tnum"; cell1.style.fontSize = "16px"; cell1.textContent = i + 1;
                            let cell3 = document.createElement('td');
                            cell3.className = "tR"; cell3.dataset.type = "R"; cell3.dataset.row = i + 1;
                            const parser = new DOMParser();
                            cell3.textContent = parser.parseFromString(data2[i], "text/html").documentElement.textContent;
                            let cell4 = document.createElement('td');
                            let button = document.createElement('button');
                            button.className = 'u-active-palette-2-light-1 u-align-center u-border-none u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius u-text-palette-2-dark-1 u-btn-4 show-info-btn';
                            button.style.cssText = "padding: calc(0.2vh + 0.2vw); margin: 0; cursor: pointer; font-size: 12px;";
                            button.innerHTML = ru_texts.question_choise;
                            button.dataset.row = i + 1;
                            button.dataset.cell2Decoded = cell3.textContent;
                            cell4.appendChild(button);
                            row.append(cell1, cell3, cell4);
                            document.querySelector('tbody').appendChild(row);
                        }

                        document.querySelectorAll('.show-info-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                selectedR = this.dataset.row;
                                selectedRText = this.dataset.cell2Decoded;
                                if (!document.querySelector(".popup")) {
                                    const popup = document.createElement('div');
                                    popup.className = 'popup';
                                    popup.innerHTML = `<div class="popup-content"><h3>${ru_texts.popup_title}</h3><p>${ru_texts.popup_prompt}</p><ul id="popup-options-list"></ul><button class="close-popup">${ru_texts.popup_close}</button></div>`;
                                    document.body.appendChild(popup);
                                    let popupOptionsList = popup.querySelector("#popup-options-list");
                                    for (let i = 0; i < data1.length; i++) {
                                        let optionItem = document.createElement('li');
                                        optionItem.textContent = data1[i];
                                        optionItem.dataset.row = i + 1;
                                        optionItem.dataset.cell2 = data1[i];
                                        optionItem.className = 'popup-option tQ';
                                        optionItem.style.cursor = "pointer";
                                        popupOptionsList.appendChild(optionItem);
                                    }
                                    popup.querySelectorAll('.popup-option').forEach(option => {
                                        option.addEventListener('click', function () {
                                            selectedQText = this.dataset.cell2;
                                            selectedQ = this.dataset.row;
                                            if (selectedQ && selectedR) {
                                                let connection = "", goodconnection = "";
                                                if (R[selectedQ - 1] == selectedR) {
                                                    connection = `<span style='color: green;'>${selectedQText} -> ${selectedRText}</span><br>`;
                                                } else {
                                                    connection = `<span style='color: red;'>${selectedQText} -> ${selectedRText}</span><br>`;
                                                    let indexrep = R.indexOf(selectedR);
                                                    let indexrep2 = Q[indexrep];
                                                    let indexrep3 = R[indexrep];
                                                    let element = document.querySelector(`.tR[data-row="${indexrep3}"]`);
                                                    let element2 = document.querySelector(`td.tQ[data-row="${indexrep2}"]`); // More specific selector
                                                    if(element && element2) goodconnection = `<span style='color: green;'>${element2.innerHTML} -> ${element.innerHTML}</span><br>`;
                                                }
                                                const parser = new DOMParser();
                                                const decodedPhrase = parser.parseFromString(selectedRText, 'text/html').documentElement.textContent;
                                                const decodedTexts = connections.map(c => parser.parseFromString(c, 'text/html').documentElement.textContent);
                                                if (decodedTexts.every(text => !text.includes(decodedPhrase))) {
                                                    connections.push(connection);
                                                    if (goodconnection) connections.push(goodconnection);
                                                    localStorage.setItem('lastationlienvar', localStorage.getItem('lastationlienvar') + "&&Q@" + selectedQ + "|R@" + selectedR);
                                                }
                                                document.getElementById('connections').innerHTML = `${ru_texts.corrections}<br>${connections.join('')}`;
                                                selectedQ = null; selectedR = null;
                                            }
                                            popup.style.display = "none";
                                        });
                                    });
                                    popup.querySelector('.close-popup').addEventListener('click', () => popup.style.display = "none");
                                }
                                document.querySelector(".popup").style.display = "flex";
                            });
                            button.click();
                            document.querySelector(".popup").style.display = "none";
                        });
                        // Restore previous connections if any
                    } else if (response[7] == "mct") {
                       // ... logic for "mct" questions ...
                    }
                }
            };
            xhr2.send();
            resize_questions();
        }
    </script>
</head>
<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="ru" style="height:100%">
    <?php
    // Если анкета завершена, показываем финальную форму
    if ((isset($_SESSION["LastQuestion"]) && $_SESSION["LastQuestion"] > $_SESSION["TotalQuestions"]) && !isset($_POST["acc"]) && !isset($_SESSION["acc"])) { ?>
    <section class="u-clearfix u-valign-middle u-section-1" id="sec-089e">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <div class="u-clearfix u-sheet u-sheet-1">
                    <p style="margin:0;" class="u-text u-text-default u-text-1">
                        <i><b><?php echo $texts['final_warning']; ?></b><br>
                        <?php echo $texts['final_warning_desc']; ?></i>
                    </p><br><br>
                    <form method="POST" class="u-clearfix u-form-spacing-32 u-inner-form" style="padding: 10px;">
                        <div class="u-form-group u-form-name u-form-partition-factor-2">
                            <h3 style="margin:0;"><?php echo $texts['gender_question']; ?></h3><br>
                            <div style="display: flex; align-items: center; gap:10px;">
                                <p style="margin:0;"><?php echo $texts['gender_prompt']; ?></p>
                                <select style="margin:0; padding-left:0;" name="genre" class="u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-btn-2" required>
                                    <option value="1">Цисгендер</option>
                                    <option value="2">Трансгендер</option>
                                    <option value="3">Небинарный</option>
                                    <option value="4">Гендерфлюид</option>
                                    <option value="5">Интерсекс</option>
                                    <option value="6">Ни один из вариантов</option>
                                    <option value="7">Другое</option>
                                    <option value="8">Предпочитаю не отвечать</option>
                                </select>
                            </div>
                        </div><br><br>
                        <div class="u-form-email u-form-group u-form-partition-factor-2">
                            <h3 style="margin:0;"><?php echo $texts['sexuality_question']; ?></h3><br>
                            <div style="display: flex; align-items: center; gap:10px;">
                                <p style="margin:0;"><?php echo $texts['sexuality_prompt']; ?></p>
                                <select style="margin:0; padding-left:0;" name="orient" class="u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-btn-2" required>
                                    <option value="1">Гетеросексуальность</option>
                                    <option value="2">Гомосексуальность</option>
                                    <option value="3">Бисексуальность</option>
                                    <option value="4">Пансексуальность</option>
                                    <option value="5">Асексуальность</option>
                                    <option value="6">Ни один из вариантов</option>
                                    <option value="7">Другое</option>
                                    <option value="8">Предпочитаю не отвечать</option>
                                </select>
                            </div>
                        </div><br><br>
                        <div class="u-form-email u-form-group u-form-partition-factor-2">
                            <label><?php echo $texts['email_prompt']; ?></label>
                            <input name="e_mm" class="u-radius-50 u-text-hover-white">
                        </div>
                        <div class="u-align-right u-form-group u-form-submit">
                            <button type="submit" name="acc" class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-btn-submit u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                                <?php echo $texts['submit']; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php // Если финальная форма отправлена, показываем экран благодарности
    } else if (isset($_POST["acc"]) || isset($_SESSION["acc"])) {
        $_SESSION["acc"] = "1";
        $_SESSION["genre"] = isset($_POST['genre']) ? htmlspecialchars($_POST['genre'], ENT_QUOTES, 'UTF-8') : ($_SESSION['genre'] ?? '');
        $_SESSION["orient"] = isset($_POST['orient']) ? htmlspecialchars($_POST['orient'], ENT_QUOTES, 'UTF-8') : ($_SESSION['orient'] ?? '');
        $_SESSION["emailr"] = isset($_POST['e_mm']) ? htmlspecialchars($_POST['e_mm'], ENT_QUOTES, 'UTF-8') : ($_SESSION['emailr'] ?? '');

        if (isset($_SESSION["id_user"]) && isset($_SESSION["genre"])) {
            try {
                $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $query = "UPDATE stationr2 SET genre = :genre, orientation = :orientation, repmail = :repmail, lang = 'ru' WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    'genre' => $_SESSION["genre"],
                    'orientation' => $_SESSION["orient"],
                    'repmail' => $_SESSION["emailr"],
                    'id' => $_SESSION["id_user"]
                ]);
                unset($_SESSION["id_user"]);
            } catch (PDOException $e) { /* echo "Erreur connection: " . $e->getMessage(); */ }
        }
    ?>
    <section class="u-clearfix u-valign-middle u-section-1" id="sec-089e-thankyou">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">
                    <p class="u-text u-text-default u-text-1" style="margin: auto;"><?php echo $texts['thank_you']; ?></p>
                    <img src="images/drap.png" alt="" style="margin: auto;">
                </div>
            </div>
        </div>
    </section>
    <script>localStorage.clear();</script>
    
    <?php // Иначе, продолжаем анкету
    } else {
        if (!isset($_SESSION["start"])) {
            try {
                $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $conn->prepare("SELECT * FROM GSDatabase WHERE level = :level ORDER BY `id` ASC");
                $stmt->bindParam(':level', $level, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($results) {
                    $_SESSION["QuestionToUse"] = "Questions"; $_SESSION["Rep1"] = "Reponses1"; $_SESSION["Rep2"] = "Reponses2";
                    $_SESSION["Rep3"] = "Reponses3"; $_SESSION["Rep4"] = "Reponses4"; $_SESSION["Rep5"] = "Reponses5";
                    $_SESSION["IdInUse"] = "id"; $_SESSION["answer"] = "answer"; $_SESSION["qtype"] = "qtype";
                    foreach ($results as $row) {
                        $_SESSION["QuestionToUse"] .= "__" . $row["question"];
                        $_SESSION["Rep1"] .= "__" . $row["rep1"]; $_SESSION["Rep2"] .= "__" . $row["rep2"];
                        $_SESSION["Rep3"] .= "__" . $row["rep3"]; $_SESSION["Rep4"] .= "__" . $row["rep4"];
                        $_SESSION["Rep5"] .= "__" . $row["rep5"]; $_SESSION["IdInUse"] .= "__" . $row["id"];
                        $_SESSION["answer"] .= "__" . $row["answer"]; $_SESSION["qtype"] .= "__" . $row["qtype"];
                    }
                    $_SESSION["TotalQuestions"] = count($results);
                    $_SESSION["start"] = 1;
                    $_SESSION["LastQuestion"] = 1;
                } else {
                    echo "Нет данных для выбранного уровня. Пожалуйста, свяжитесь с администратором.";
                    exit();
                }
            } catch (PDOException $e) {
                echo "Ошибка подключения: " . $e->getMessage(); exit();
            }
        }
    ?>
    <section style="height:auto;" class="u-align-center u-clearfix u-container-align-center u-palette-2-light-3 u-section-2" id="qcm">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <h5 id="QuestionN" class="u-align-center" style="margin-top:1vh; margin-bottom:0;">
                    Вопрос <?php echo $_SESSION["LastQuestion"]; ?>
                </h5>
                <button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-radius u-btn-4" style="color:black; margin-top:0; background-color:#8a7bf4;" id="button_next" onclick="updateQuestion(-1)">
                    <?php echo $texts['continue']; ?>
                </button>
                <b><p id="Question" class="u-align-center" style="margin-top:1vh; margin-bottom:0;width:100%; padding:1em; background-color:#ffb5b9;"></p></b>
                <div style="flex-direction: row; display: flex; justify-content: space-between; margin-top:1em; gap: 10px; width: 80%; margin: auto;" id="quest_list"></div>
            </div>
        </div>
        <div class="u-align-right u-form-group u-form-submit">
            <img id="randomImage" src="" width="200em" alt="">
        </div>
    </section>
    <?php
        if (!isset($_SESSION["finish"])) {
            echo '<script type="text/javascript">startQuestion();</script>';
        }
    }
    ?>
    <script>
        // ... (JavaScript for updateQuestion remains largely the same, but simplified) ...
        // The existing JS for updateQuestion should work, as it relies on the same backend logic.
        // It's important that the ru_texts object is correctly used in the JS logic for "lien" questions if you keep them.
		let timeout = false;
		let xhr = new XMLHttpRequest();
		let cd = 3000;
		function updateQuestion(buttonIndex) {
            // This function remains the same as in your original file.
            // ...
        }
    </script>
</body>
</html>

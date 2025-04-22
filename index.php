<!DOCTYPE html>
<?php 
ini_set('session.gc_maxlifetime', 31536000);
session_start();

if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'fr';
}

if (isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'] === 'de' ? 'de' : 'fr';
}

$texts = [
    'fr' => [
        'project_title' => 'Projet solidaire d’un élève du Collège international Vauban de Strasbourg, avec le soutien de la STATION, centre LGBTQIA+ de Strasbourg.',
        'project_desc' => 'Présentation du Projet Solidaire d’un élève du Collège international Vauban à Strasbourg, a lancé un projet remarquable visant à sensibiliser ses camarades de classe aux questions LGBTQIA+. À travers une enquête en ligne qu’il a soigneusement conçue, Luc invite ses pairs à réfléchir sur des thématiques essentielles telles que l’identité de genre, l’orientation sexuelle et le respect de la diversité.',
        'objectives' => 'Objectifs du projet :',
        'awareness' => 'Sensibilisation : Encourager les élèves à mieux comprendre les réalités et les défis auxquels font face les personnes LGBTQIA+.',
        'inclusion' => 'Inclusion : Promouvoir un climat scolaire respectueux, où chacun se sent accepté, peu importe son identité ou son orientation.',
        'engagement' => 'Engagement citoyen : Montrer comment des actions concrètes peuvent contribuer à faire évoluer les mentalités et renforcer les valeurs de respect et de solidarité.',
        'why_survey' => 'Pourquoi une enquête en ligne ?',
        'survey_reason' => 'Luc a choisi ce format interactif pour permettre à ses camarades de participer anonymement et de s’exprimer librement. Les résultats de l’enquête serviront de base à des discussions en classe, des ateliers de sensibilisation, ou encore des initiatives pour améliorer l’inclusion au sein de l’établissement.',
        'impact' => 'L’impact attendu :',
        'impact_desc' => 'En impliquant ses pairs dans ce processus participatif, Luc fait bien plus que sensibiliser : il agit comme un vecteur de changement en les encourageant à adopter des comportements inclusifs et à devenir eux-mêmes des ambassadeurs du respect.',
        'project_note' => 'Ce projet, à la fois éducatif et solidaire, s’inscrit pleinement dans les valeurs portées par le Collège international Vauban et témoigne de l’engagement d’un élève inspirant pour construire un monde plus juste et tolérant.',
        'warning_title' => 'Avertissement concernant le sondage',
        'anonymity' => 'Anonymat garanti : Toutes vos réponses sont recueillies de manière anonyme. Aucune information personnelle ne sera associée à vos réponses.',
        'voluntary' => 'Participation libre : La participation à ce sondage est entièrement facultative. Vous pouvez choisir de ne pas répondre à certaines questions si vous ne le souhaitez pas.',
        'results' => 'Résultats disponibles : Si vous souhaitez recevoir un résumé des résultats une fois l’enquête terminée, vous pouvez laisser votre adresse e-mail à la fin du sondage. Cette étape est totalement optionnelle et ne compromet pas l’anonymat de vos réponses.',
        'thanks' => 'Merci pour votre participation à ce projet qui contribue à sensibiliser et à promouvoir le respect et l’inclusion au sein de notre communauté scolaire.',
        'continue' => 'Continuer',
        'footer' => 'Conception de la page : R. (Hex) ; maître de stage : Gérald Schlemminger, (c) 2025 La STATION / Collège international Vauban.',
        'final_warning' => 'Avertissement concernant la question finale',
        'final_warning_desc' => 'Les dernières questions du sondage sont plus personnelles et portent sur ton identité de genre et ton orientation sexuelle. Nous comprenons que ces thématiques peuvent être perçues comme sensibles ou intrusives. Il n’y a aucune obligation de réponse : tu es libre de ne pas répondre à ces questions si tu ne te sens pas à l’aise. Cela n’affectera en rien ta participation au sondage.',
        'gender_question' => 'Te reconnais-tu dans l’un des genres suivants ?',
        'gender_prompt' => 'Sélectionne la description qui te convient',
        'sexuality_question' => 'Te reconnais-tu dans l’une des orientations sexuelles suivantes ?',
        'sexuality_prompt' => 'Sélectionne la description qui te convient',
        'email_prompt' => 'Entre ton adresse email si tu souhaites recevoir les résultats de l’enquête.',
        'submit' => 'Envoyer et terminer le questionnaire',
        'thank_you' => 'Merci !',
        'popup_title' => 'Définition',
        'popup_prompt' => 'Choisissez une option dans la liste :',
        'popup_close' => 'Close',
        'corrections' => 'Corrections :',
        'none' => 'Aucun'
    ],
    'de' => [
        'project_title' => 'Solidarisches Projekt eines Schülers des Collège international Vauban in Straßburg, unterstützt von der STATION, dem LGBTQIA+-Zentrum in Straßburg.',
        'project_desc' => 'Vorstellung des solidarischen Projekts: Ein Schüler des Collège international Vauban in Straßburg hat ein bemerkenswertes Projekt ins Leben gerufen, um seine Mitschüler für LGBTQIA+-Themen zu sensibilisieren. Mit einer sorgfältig gestalteten Online-Umfrage lädt Luc seine Altersgenossen ein, über wesentliche Themen wie Geschlechtsidentität, sexuelle Orientierung und Respekt vor Vielfalt nachzudenken.',
        'objectives' => 'Ziele des Projekts:',
        'awareness' => 'Sensibilisierung: Die Schüler dazu ermutigen, die Realitäten und Herausforderungen von LGBTQIA+-Personen besser zu verstehen.',
        'inclusion' => 'Inklusion: Förderung eines respektvollen Schulklima, in dem sich jede Person unabhängig von ihrer Identität oder Orientierung akzeptiert fühlt.',
        'engagement' => 'Bürgerschaftliches Engagement: Zeigen, wie konkrete Maßnahmen dazu beitragen können, Einstellungen zu verändern und Werte wie Respekt und Solidarität zu stärken.',
        'why_survey' => 'Warum eine Online-Umfrage?',
        'survey_reason' => 'Luc hat dieses interaktive Format gewählt, um seinen Mitschülern eine anonyme Teilnahme und freie Meinungsäußerung zu ermöglichen. Die Ergebnisse der Umfrage dienen als Grundlage für Klassendiskussionen, Sensibilisierungsworkshops oder Initiativen zur Verbesserung der Inklusion in der Schule.',
        'impact' => 'Erwartete Wirkung:',
        'impact_desc' => 'Durch die Einbindung seiner Mitschüler in diesen partizipativen Prozess geht Luc weit über Sensibilisierung hinaus: Er wirkt als Katalysator für Veränderung, indem er sie dazu ermutigt, inklusive Verhaltensweisen anzunehmen und selbst zu Botschaftern für Respekt zu werden.',
        'project_note' => 'Dieses sowohl pädagogische als auch solidarische Projekt steht ganz im Einklang mit den Werten des Collège international Vauban und zeigt das Engagement eines inspirierenden Schülers, eine gerechtere und tolerantere Welt zu schaffen.',
        'warning_title' => 'Hinweis zur Umfrage',
        'anonymity' => 'Garantierte Anonymität: Alle Ihre Antworten werden anonym erfasst. Es werden keine persönlichen Informationen mit Ihren Antworten verknüpft.',
        'voluntary' => 'Freiwillige Teilnahme: Die Teilnahme an dieser Umfrage ist völlig freiwillig. Sie können entscheiden, bestimmte Fragen nicht zu beantworten, wenn Sie dies nicht möchten.',
        'results' => 'Verfügbare Ergebnisse: Wenn Sie nach Abschluss der Umfrage eine Zusammenfassung der Ergebnisse erhalten möchten, können Sie am Ende der Umfrage Ihre E-Mail-Adresse hinterlassen. Dieser Schritt ist völlig optional und beeinträchtigt nicht die Anonymität Ihrer Antworten.',
        'thanks' => 'Vielen Dank für Ihre Teilnahme an diesem Projekt, das dazu beiträgt, das Bewusstsein zu schärfen und Respekt sowie Inklusion in unserer Schulgemeinschaft zu fördern.',
        'continue' => 'Weiter',
        'footer' => 'Seitengestaltung: R. (Hex); Praktikumsbetreuer: Gérald Schlemminger, (c) 2025 La STATION / Collège international Vauban.',
        'final_warning' => 'Hinweis zur abschließenden Frage',
        'final_warning_desc' => 'Die letzten Fragen der Umfrage sind persönlicher und betreffen deine Geschlechtsidentität und sexuelle Orientierung. Wir verstehen, dass diese Themen als sensibel oder aufdringlich empfunden werden können. Es besteht keine Verpflichtung zu antworten: Du kannst frei entscheiden, diese Fragen nicht zu beantworten, wenn du dich unwohl fühlst. Dies hat keinen Einfluss auf deine Teilnahme an der Umfrage.',
        'gender_question' => 'Erkennst du dich in einem der folgenden Geschlechter wieder?',
        'gender_prompt' => 'Wähle die Beschreibung, die zu dir passt',
        'sexuality_question' => 'Erkennst du dich in einer der folgenden sexuellen Orientierungen wieder?',
        'sexuality_prompt' => 'Wähle die Beschreibung, die zu dir passt',
        'email_prompt' => 'Gib deine E-Mail-Adresse ein, wenn du die Ergebnisse der Umfrage erhalten möchtest.',
        'submit' => 'Absenden und die Umfrage beenden',
        'thank_you' => 'Danke!',
        'popup_title' => 'Definition',
        'popup_prompt' => 'Wähle eine Option aus der Liste:',
        'popup_close' => 'Schließen',
        'corrections' => 'Korrekturen:',
        'none' => 'Keine'
    ]
];
$lang = $_SESSION['language'];
?>
<?php require_once 'conf.php'; ?>
<html style="font-size: 16px;" lang="<?php echo $lang; ?>">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>Question</title>
    <link rel="stylesheet" href="nicepage.css" media="screen">
    <link rel="stylesheet" href="Question.css" media="screen">
    <style>
        .u-align-center {
            transition: background-color 3s ease;
        }

        .fade-to-red {
            transition: background-color 2s ease;
            background-color: red !important;
        }

        .fade-to-green {
            transition: background-color 2s ease;
            background-color: green !important;
        }

        .fade-to-white {
            transition: background-color 2s ease;
            background-color: white !important;
        }

        .u-container-style.u-expanded-width.u-grey-10 {
            margin: 0;
            height: 100%;
            background-image: url('images/background.png');
            background-size: cover;
            background-repeat: no-repeat; 
        }

        .u-container-layout.u-container-layout-1 {
            margin: 0;
            height: 100%;
        }

        .u-container-layout.u-similar-container.u-container-layout-8 {
            padding: 0;
        }

        .u-container-style.u-expanded-width.u-group.u-palette-2-light-2.u-radius.u-shape-round {
            background-color: #ffd8da;
            border-radius: 50% 20% / 10% 40% !important;
        }

        .u-align-center.u-container-align-center.u-container-align-center-md.u-container-align-center-xl.u-container-align-center-xs.u-container-style {
            width: 22vw;
        }

        #rep.u-align-center.u-custom-item.u-text.u-text-5 {
            margin: 0;
            margin-top: 0;
        }

        .u-active-palette-2-light-1.u-align-center.u-border-none.u-btn.u-btn-round {
            padding: 0.2em;
        }

        #qcm {
            height: 100%;
        }

        button#button_choix {
            padding: calc(2vh + 1vw);
            background-size: cover;
            background-image: url(images/icon-803718_1280.png);
            margin: auto;
        }

        .tnum, .tR, .tQ {
            background-color: #fae0e0;
            font-size: 16px;
        }

        .tQ {
            background-color: rgb(154, 253, 235);
        }

        th, td {
            border: 1px solid black;
            padding: 10px;
            text-align: center;
        }

        .selected {
            background-color: lightblue;
        }

        .popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background: #f2ebff;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
            border: solid 0.5em;
            border-color: #bf8d8d;
            max-width: 90%;
        }

        .popup-content h3 {
            margin-top: 0;
        }

        .popup .close-popup {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            margin-top: 10px;
        }

        .popup-option {
            text-align: center;
            margin-bottom: 5px;
            border-color: #2e10fd;
            border-radius: 30px;
            border: solid;
            cursor: pointer;
            background-color: #ffb8b8;
            list-style-type: none;
            padding: 5px;
        }

        .popup .close-popup:hover {
            background-color: #d32f2f;
        }

        .language-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 1vh;
            justify-content: center;
        }

        .language-flag {
            width: 40px;
            height: 40px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }

        .language-flag:hover {
            border-color: #007bff;
        }

        .language-flag.selected {
            border-color: #007bff;
        }
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
        function changeToSecond() {
            var section2 = document.getElementById("sec-089e2");
            var section1 = document.getElementById("sec-089e");
            section2.style.display = "none";
            section1.style.display = "block";
        }
        let ismultiple = false;
        let parentDiv = null;
        window.onload = resize_questions;
        let newDivHTML = '<div style="background: rgba(0,0,0,0); width:50%; margin:auto;" id="reponse_" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius u-text-palette-2-dark-1 u-btn-4" id="button_choix"></button><div id="answer" class="u-container-layout u-similar-container u-container-layout-8"><div id="question_container" class="u-container-style u-expanded-width u-group u-palette-2-light-2 u-radius u-shape-round u-group-5"><div class="u-container-layout u-container-layout-9"><p id="rep" class="u-align-center u-custom-item u-text u-text-5"></p></div></div></div></div>';
        let newDivHTMLechelle = '<div id="reponse_" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><div id="answer" class="u-container-layout u-similar-container u-container-layout-8"><div id="question_container" class="u-container-style u-expanded-width u-group u-palette-2-light-2 u-radius u-shape-round u-group-5"><div class="u-container-layout u-container-layout-9"><p id="rep" class="u-align-center u-custom-item u-text u-text-5"></p></div></div><button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius u-text-palette-2-dark-1 u-btn-4" id="button_choix"></button></div></div>';
        function resize_questions() {
            var boxes = document.querySelectorAll('#question_container');
            var maxHeight = 0;
            boxes.forEach(function (box) {
                box.style.height = "auto";
            });
            boxes.forEach(function (box) {
                var boxHeight = box.offsetHeight;
                if (boxHeight > maxHeight) {
                    maxHeight = boxHeight;
                }
            });
            boxes.forEach(function (box) {
                box.style.height = maxHeight + 'px';
            });
        }
        function findAllBlocks() {
            const blocks = document.querySelectorAll('div[id^="reponse_"]');
            const answersArray = Array.from(blocks);
            return answersArray;
        }
        function deleteAllBlocks() {
            const blocks = document.querySelectorAll('div[id^="reponse_"]');
            blocks.forEach(block => block.remove());
        }
        var xhr2 = new XMLHttpRequest();
        let selectedQ = null;
        let selectedR = null;
        let selectedQText = null;
        let selectedRText = null;
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
                        innerAnswers.classList.remove('fade-to-green');
                        innerAnswers.classList.remove('fade-to-red');
                        innerAnswers.classList.remove('fade-to-white');
                        innerAnswers.classList.add('fade-to-white');
                    });
                    answersarray.forEach(function (item, index) {
                        const innerAnswers = item.querySelector('p#rep');
                        innerAnswers.innerHTML = response[index + 1];
                    });
                    document.getElementById("QuestionN").innerHTML = "Question " + response[6];
                    document.getElementById('button_next').onclick = function () {
                        updateQuestion(-1);
                    };
                    if (response[7] == "qcm" || response[7] == "echelle") {
                        ismultiple = false;
                        localStorage.clear();
                        deleteAllBlocks();
                        if (document.getElementsByClassName("popup")[0] != null)
                            document.getElementsByClassName("popup")[0].remove();
                        for (let i = 1; i <= 5; i++) {
                            if (response[i] != "null") {
                                let repo = document.querySelector("#reponse_" + i);
                                if (repo == null) {
                                    if (response[7] == "echelle") {
                                        parentDiv.style.flexDirection = "row";
                                        parentDiv.insertAdjacentHTML("beforeend", newDivHTMLechelle);
                                    } else if (response[7] == "qcm") {
                                        parentDiv.insertAdjacentHTML("beforeend", newDivHTML);
                                        parentDiv.style.flexDirection = "column";
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
                                if (reponse_elem != null) {
                                    reponse_elem.remove();
                                }
                            }
                        }
                    } else if (response[7] == "lien") {
                        ismultiple = true;
                        const blocks = document.querySelectorAll('div[id^="reponse_"]');
                        blocks.forEach(block => {
                            block.remove();
                        });
                        if (localStorage.getItem('lastationlienvar')) {
                            if (localStorage.getItem('lastationlienvar')[0] != response[8]) {
                                localStorage.clear();
                                localStorage.setItem('lastationlienvar', response[8]);
                            }
                        } else {
                            localStorage.setItem('lastationlienvar', response[8]);
                        }
                        let table_preset = `<p id="connections" style="width:50vw; font-size:14px;">${texts[lang].corrections} ${texts[lang].none}</p><div style="background-color: #fff0; width:100%; margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="margin-left:auto; width:80%; margin-right:1em;" border="1" id="myTable"><thead><tr><th style="background-color: #b3ffff;">N</th><th style="background-color: #ffa096;">${texts[lang].popup_title}</th><th style="background-color: #b3ffff;">Action</th></tr></thead><tbody></tbody></table></div>`;
                        parentDiv.insertAdjacentHTML("beforeend", table_preset);

                        let data1 = response[1].split("--");
                        let data2 = response[2].split("--");
                        let data3 = response[3].split('_');
                        const Q = [];
                        const R = [];
                        data3.forEach(pair => {
                            const parts = pair.split('-');
                            if (parts.length === 2) {
                                const [q, r] = parts;
                                Q.push(q.replace('Q', ''));
                                R.push(r.replace('R', ''));
                            }
                        });
                        for (let i = 0; i < data1.length; i++) {
                            let row = document.createElement('tr');

                            let cell1 = document.createElement('td');
                            cell1.classList.add("tnum");
                            cell1.style.fontSize = "16px";
                            cell1.textContent = i + 1;

                            let cell2 = document.createElement('td');
                            cell2.classList.add("tQ");
                            cell2.setAttribute("data-type", "Q");
                            cell2.setAttribute("data-row", i + 1);
                            cell2.textContent = data1[i];

                            let cell3 = document.createElement('td');
                            cell3.classList.add("tR");
                            cell3.setAttribute("data-type", "R");
                            cell3.setAttribute("data-row", i + 1);
                            const parser = new DOMParser();
                            const decodedString = parser.parseFromString(data2[i], "text/html").documentElement.textContent;
                            cell3.textContent = decodedString;

                            let cell4 = document.createElement('td');
                            let button = document.createElement('button');
                            button.classList.add('u-active-palette-2-light-1', 'u-align-center', 'u-border-none', 'u-btn-round', 'u-button-style', 'u-hover-palette-2-light-1', 'u-palette-2-light-2', 'u-radius', 'u-text-palette-2-dark-1', 'u-btn-4');
                            button.classList.add('show-info-btn');
                            button.style.padding = "calc(0.2vh + 0.2vw)";
                            button.style.margin = 0;
                            button.innerHTML = lang === 'de' ? 'AUSWÄHLEN' : 'CHOISIR';
                            button.style.cursor = "pointer";
                            button.style.fontSize = "12px";
                            button.setAttribute('data-row', i + 1);
                            button.setAttribute('data-cell2-decoded', decodedString);
                            cell4.appendChild(button);

                            row.appendChild(cell1);
                            row.appendChild(cell3);
                            row.appendChild(cell4);
                            document.querySelector('tbody').appendChild(row);
                        }

                        document.querySelectorAll('.show-info-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                const rowNum = button.getAttribute('data-row');
                                const decodedText = button.getAttribute('data-cell2-decoded');
                                selectedR = rowNum;
                                selectedRText = decodedText;
                                if (document.getElementsByClassName("popup")[0] == null) {
                                    const popup = document.createElement('div');
                                    popup.classList.add('popup');
                                    popup.innerHTML = `
                <div class="popup-content">
                    <h3>${texts[lang].popup_title}</h3>
                    <p>${texts[lang].popup_prompt}</p>
                    <ul id="popup-options-list"></ul>
                    <button class="close-popup">${texts[lang].popup_close}</button>
                </div>
            `;
                                    document.body.appendChild(popup);

                                    let popupOptionsList = popup.querySelector("#popup-options-list");
                                    for (let i = 0; i < data1.length; i++) {
                                        let optionItem = document.createElement('li');
                                        optionItem.textContent = data1[i];
                                        optionItem.setAttribute('data-row', i + 1);
                                        optionItem.setAttribute('data-cell2', data1[i]);
                                        optionItem.classList.add('popup-option', 'tQ');
                                        optionItem.style.cursor = "pointer";
                                        popupOptionsList.appendChild(optionItem);
                                    }
                                    popup.querySelectorAll('.popup-option').forEach(option => {
                                        option.addEventListener('click', function () {
                                            const selectedOption = option.getAttribute('data-cell2');
                                            const rowIndex = option.getAttribute('data-row');
                                            selectedQText = selectedOption;
                                            selectedQ = rowIndex;
                                            if (selectedQ && selectedR) {
                                                var connection = "";
                                                var goodconnection = "";
                                                if (R[selectedQ - 1] == (selectedR)) {
                                                    connection = `${"<span style='color: green;'>" + selectedQText} -> ${"</span><span style='color: green;'>" + selectedRText + "</span><br>"}`;
                                                } else {
                                                    connection = `${"<span style='color: red;'>" + selectedQText} -> ${"</span><span style='color: red;'>" + selectedRText + "</span><br>"}`;
                                                    var indexrep = R.indexOf(selectedR);
                                                    var indexrep2 = Q[indexrep];
                                                    var indexrep3 = R[indexrep];

                                                    var element = document.querySelector(`.tR[data-row="${indexrep3}"]`);
                                                    var element2 = document.querySelector(`.tQ[data-row="${indexrep2}"]`);
                                                    goodconnection = `${"<span style='color: green;'>" + element2.innerHTML} -> ${"</span><span style='color: green;'>" + element.innerHTML + "</span><br>"}`;
                                                }

                                                const index = connections.indexOf(connection);
                                                const decodeHTML = str => {
                                                    const parser = new DOMParser();
                                                    const dom = parser.parseFromString(str, 'text/html');
                                                    return dom.documentElement.textContent;
                                                };
                                                const decodedTexts = connections.map(decodeHTML);
                                                const decodedPhrase = decodeHTML(selectedRText);
                                                if (decodedTexts.every(text => !text.includes(decodedPhrase))) {
                                                    if (index !== -1) {
                                                    } else {
                                                        connections.push(connection);
                                                        if (goodconnection != "")
                                                            connections.push(goodconnection);
                                                        localStorage.setItem('lastationlienvar', localStorage.getItem('lastationlienvar') + "&&Q@" + selectedQ + "|R@" + selectedR);
                                                    }
                                                }

                                                document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections}`;
                                                selectedQ = null;
                                                selectedR = null;
                                            }
                                            popup.style.display = "none";
                                        });
                                    });
                                    popup.querySelector('.close-popup').addEventListener('click', function () {
                                        popup.style.display = "none";
                                    });
                                } else {
                                    document.getElementsByClassName("popup")[0].style.display = "flex";
                                }
                            });
                            button.click();
                            document.getElementsByClassName("popup")[0].style.display = "none";
                        });
                        const updatedString = localStorage.getItem('lastationlienvar').slice(1);
                        const parts = updatedString.split('&&');
                        const QQ = [];
                        const RR = [];
                        parts.forEach(part => {
                            const subParts = part.split('|');
                            subParts.forEach(subPart => {
                                if (subPart.startsWith('Q@')) {
                                    QQ.push(Number(subPart.slice(2)));
                                } else if (subPart.startsWith('R@')) {
                                    RR.push(Number(subPart.slice(2)));
                                }
                            });
                        });
                        if (QQ.length > 0) {
                            QQ.forEach((element, index) => {
                                const Q = [];
                                const R = [];
                                data3.forEach(pair => {
                                    const parts = pair.split('-');
                                    if (parts.length === 2) {
                                        const [q, r] = parts;
                                        Q.push(q.replace('Q', ''));
                                        R.push(r.replace('R', ''));
                                    }
                                });
                                var indexrep = R.indexOf(String(RR[index]));
                                var indexrep2 = Q[indexrep];
                                var indexrep3 = R[indexrep];
                                var element = document.querySelector(`.tR[data-row="${indexrep3}"]`);
                                var element2 = document.querySelector(`.tQ[data-row="${indexrep2}"]`);
                                goodconnection = `${"<span style='color: green;'>" + element2.innerHTML} -> ${"</span><span style='color: green;'>" + element.innerHTML + "</span><br>"}`;
                                connections.push(goodconnection);
                            });
                            document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections}`;
                        }
                    } else if (response[7] == "mct") {
                        ismultiple = true;
                        localStorage.clear();
                        localStorage.setItem('lastationlienvar', response[8]);
                        if (document.getElementsByClassName("popup")[0] != null)
                            document.getElementsByClassName("popup")[0].remove();
                        const blocks = document.querySelectorAll('div[id^="reponse_"]');
                        blocks.forEach(block => {
                            block.remove();
                        });
                        let table_preset = '<div style="background-color: #fff0; width:100%; margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="width:100%;" border="1" id="myTable"><thead></thead><tbody></tbody></table></div>';
                        parentDiv.insertAdjacentHTML("beforeend", table_preset);
                        let data1 = response[1].split("--");
                        let data2 = response[2];
                        let data3 = response[3];
                        let data4 = response[4];
                        let data5 = response[5];
                        for (let i = 0; i < data1.length; i++) {
                            let row = document.createElement('tr');
                            let cell1 = document.createElement('td');
                            cell1.classList.add("tnum");
                            cell1.style.fontSize = "16px";
                            cell1.textContent = data1[i];
                            row.appendChild(cell1);
                            if (data2 != "null") {
                                let cell2 = document.createElement('td');
                                cell2.classList.add("tQ");
                                cell2.setAttribute("data-type", "Q");
                                cell2.setAttribute("data-row", i + 1);
                                cell2.setAttribute("data-id", 1);
                                cell2.textContent = data2;
                                row.appendChild(cell2);
                            }
                            if (data3 != "null") {
                                let cell3 = document.createElement('td');
                                cell3.classList.add("tR");
                                cell3.setAttribute("data-type", "R");
                                cell3.setAttribute("data-row", i + 1);
                                cell3.setAttribute("data-id", 2);
                                cell3.textContent = data3;
                                row.appendChild(cell3);
                            }
                            if (data4 != "null") {
                                let cell4 = document.createElement('td');
                                cell4.classList.add("tQ");
                                cell4.setAttribute("data-type", "R");
                                cell4.setAttribute("data-row", i + 1);
                                cell4.setAttribute("data-id", 3);
                                cell4.textContent = data4;
                                row.appendChild(cell4);
                            }
                            if (data5 != "null") {
                                let cell5 = document.createElement('td');
                                cell5.classList.add("tR");
                                cell5.setAttribute("data-type", "R");
                                cell5.setAttribute("data-row", i + 1);
                                cell5.setAttribute("data-id", 4);
                                cell5.textContent = data5;
                                row.appendChild(cell5);
                            }
                            document.querySelector('tbody').appendChild(row);
                        }
                        document.querySelectorAll('td[data-type]').forEach(cell => {
                            cell.addEventListener('click', function () {
                                const cellType = cell.dataset.type;
                                const cellRow = cell.dataset.row;
                                if (cell.classList.contains('selected')) {
                                    cell.classList.remove('selected');
                                    if (cellType === 'Q') selectedQ = null;
                                    if (cellType === 'R') selectedR = null;
                                } else {
                                    const rowCells = document.querySelectorAll(`[data-row='${cellRow}']:not([data-type='first'])`);
                                    rowCells.forEach(rowCell => {
                                        if (rowCell !== cell) rowCell.classList.remove('selected');
                                    });
                                }
                                cell.classList.add('selected');
                                document.getElementById('button_next').onclick = function () {
                                    updateQuestion(cell);
                                };
                                const selectedElements = document.querySelectorAll('.selected');
                                let resultString = '';
                                selectedElements.forEach(element => {
                                    let dataRow = element.getAttribute('data-row');
                                    let dataId = element.getAttribute('data-id');
                                    resultString += `&&Q@${dataRow}|R@${dataId}`;
                                });
                                localStorage.setItem('lastationlienvar', response[8] + resultString);
                            });
                        });
                    }
                }
            };
            xhr2.send();
            resize_questions();
        }
    </script>

<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="<?php echo $lang; ?>" style="height:100%">

    <?php
    if (!isset($_POST["start"]) && !isset($_SESSION["start"])) { ?>
        <section class="u-clearfix u-valign-middle u-section-1" id="sec-089e2">
            <form method="POST" action="">
                <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
                    <div class="u-container-layout u-container-layout-1">
                        <div class="u-clearfix u-sheet u-sheet-1">
                            <p class="u-text u-text-default u-text-1" style="text-align:center;margin:auto; font-size:24px;">
                                <b><?php echo $texts[$lang]['project_title']; ?></b>
                            </p>
                            <p style="margin:0; font-size:16px;">
                                <?php echo $texts[$lang]['project_desc']; ?><br><br>

                                <b><?php echo $texts[$lang]['objectives']; ?></b><br>
                                <b><?php echo $texts[$lang]['awareness']; ?></b><br>
                                <b><?php echo $texts[$lang]['inclusion']; ?></b><br>
                                <b><?php echo $texts[$lang]['engagement']; ?></b><br>

                                <br><b><?php echo $texts[$lang]['why_survey']; ?></b><br>
                                <?php echo $texts[$lang]['survey_reason']; ?><br>

                                <br><b><?php echo $texts[$lang]['impact']; ?></b><br>
                                <?php echo $texts[$lang]['impact_desc']; ?><br>
                                <br><?php echo $texts[$lang]['project_note']; ?>
                            </p>
                            <p style="margin:1em; padding:1em;border:solid; font-size:14px; border-color:#1400ff;">
                                <b><?php echo $texts[$lang]['warning_title']; ?></b> <br><br>

                                <b><?php echo $texts[$lang]['anonymity']; ?></b><br>
                                <b><?php echo $texts[$lang]['voluntary']; ?></b><br>
                                <b><?php echo $texts[$lang]['results']; ?></b><br><br>

                                <i><?php echo $texts[$lang]['thanks']; ?></i>
                            </p>

                            <div class="language-selector">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="language" value="fr">
                                    <input type="image" src="images/france.svg" alt="Français" class="language-flag <?php echo $lang === 'fr' ? 'selected' : ''; ?>" style="width: 40px; height: 40px;">
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="language" value="de">
                                    <input type="image" src="images/germany.svg" alt="Deutsch" class="language-flag <?php echo $lang === 'de' ? 'selected' : ''; ?>" style="width: 40px; height: 40px;">
                                </form>
                            </div>

                            <div class="u-align-right u-form-group u-form-submit">
                                <button style="margin-top:1vh;" value="1" name="start" type="submit"
                                    class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-btn-submit u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                                    <?php echo $texts[$lang]['continue']; ?>
                                </button>
                                <p style="font-size:10px;"><?php echo $texts[$lang]['footer']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </section>

    <?php } else if ((isset($_POST["start"]) || isset($_SESSION["start"])) && (isset($_SESSION["LastQuestion"]) ? $_SESSION["LastQuestion"] : 0) <= (isset($_SESSION["TotalQuestions"]) ? $_SESSION["TotalQuestions"] : 1)) {
if (!isset($_SESSION["start"])) {
    try {
        $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "Erreur connection: " . $e->getMessage();
    }

    // Выбор таблицы в зависимости от языка
    $table = $lang === 'de' ? 'stationq2' : 'stationq1';
    $stmt = $conn->prepare("SELECT * FROM $table WHERE level = 101 ORDER BY `id` ASC");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $_SESSION["QuestionToUse"] = "Questions";
    $_SESSION["Rep1"] = "Reponses1";
    $_SESSION["Rep2"] = "Reponses2";
    $_SESSION["Rep3"] = "Reponses3";
    $_SESSION["Rep4"] = "Reponses4";
    $_SESSION["Rep5"] = "Reponses5";
    $_SESSION["IdInUse"] = "id";
    $_SESSION["answer"] = "answer";
    $_SESSION["qtype"] = "qtype";
    if ($results) {
        foreach ($results as $row) {
            $_SESSION["QuestionToUse"] .= "__" . $row["question"];
            $_SESSION["Rep1"] .= "__" . $row["rep1"];
            $_SESSION["Rep2"] .= "__" . $row["rep2"];
            $_SESSION["Rep3"] .= "__" . $row["rep3"];
            $_SESSION["Rep4"] .= "__" . $row["rep4"];
            $_SESSION["Rep5"] .= "__" . $row["rep5"];
            $_SESSION["IdInUse"] .= "__" . $row["id"];
            $_SESSION["answer"] .= "__" . $row["answer"];
            $_SESSION["qtype"] .= "__" . $row["qtype"];
        }
        $ids = explode("__", $_SESSION["IdInUse"]);
        $_SESSION["TotalQuestions"] = count($ids) - 1;
        $_SESSION["start"] = 1;
        $_SESSION["LastQuestion"] = "1";
    } else {
        echo $lang === 'de' ? "Fehlende Daten für die gewählte Stufe. Bitte kontaktieren Sie 'La STATION'" : "Manque des données pour le niveau choisi. Veuillez contacter 'La STATION'";
        exit();
    }
}
        if (isset(explode("__", $_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]])) {
            $currentQuestion = explode("__", $_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]];
            $currentRep1 = explode("__", $_SESSION["Rep1"])[$_SESSION["LastQuestion"]];
            $currentRep2 = explode("__", $_SESSION["Rep2"])[$_SESSION["LastQuestion"]];
            $currentRep3 = explode("__", $_SESSION["Rep3"])[$_SESSION["LastQuestion"]];
            $currentRep4 = explode("__", $_SESSION["Rep4"])[$_SESSION["LastQuestion"]];
            $currentRep5 = explode("__", $_SESSION["Rep5"])[$_SESSION["LastQuestion"]];
            $qtype = explode("__", $_SESSION["qtype"])[$_SESSION["LastQuestion"]];
        } else {
            echo $lang === 'de' ? "Fehler bei der Auswahl der Frage, bitte kontaktieren Sie 'La STATION'" : "Erreur lors de la sélection de la question, veuillez contacter 'La STATION'";
        }
        ?>
        <section style="height:auto;" class="u-align-center u-clearfix u-container-align-center u-palette-2-light-3 u-section-2" id="qcm">
            <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
                <div class="u-container-layout u-container-layout-1">
                    <h5 id="QuestionN" class="u-align-center" style="margin-top:1vh; margin-bottom:0;">
                        Question <?php echo $_SESSION["LastQuestion"]; ?>
                    </h5>
                    <button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-radius u-btn-4" style="color:black; margin-top:0; background-color:#8a7bf4;" id="button_next">
                        <?php echo $texts[$lang]['continue']; ?>
                    </button>
                    <b>
                        <p id="Question" class="u-align-center" style="margin-top:1vh; margin-bottom:0;width:100%; padding:1em; background-color:#ffb5b9;">
                            <?php echo $currentQuestion; ?>
                        </p>
                    </b>
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
    } else if (((isset($_SESSION["LastQuestion"]) ? $_SESSION["LastQuestion"] : 0) >= (isset($_SESSION["TotalQuestions"]) ? $_SESSION["TotalQuestions"] : 1)) && !(isset($_POST["acc"]) || isset($_SESSION["acc"]))) { ?>
        <section class="u-clearfix u-valign-middle u-section-1" id="sec-089e">
            <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
                <div class="u-container-layout u-container-layout-1">
                    <div class="u-clearfix u-sheet u-sheet-1">
                        <p style="margin:0;" class="u-text u-text-default u-text-1">
                            <i><b><?php echo $texts[$lang]['final_warning']; ?></b><br>
                            <?php echo $texts[$lang]['final_warning_desc']; ?></i>
                        </p><br><br>
                        <form method="POST" class="u-clearfix u-form-spacing-32 u-inner-form" style="padding: 10px;">
                            <div class="u-form-group u-form-name u-form-partition-factor-2">
                                <h3 style="margin:0;"><?php echo $texts[$lang]['gender_question']; ?></h3><br>
                                <div style="display: flex; align-items: center; gap:10px;">
                                    <p style="margin:0;"><?php echo $texts[$lang]['gender_prompt']; ?></p>
                                    <select style="margin:0; padding-left:0;" id="name-bb9b" name="genre" class="u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-btn-2" required>
                                        <option value="1"><?php echo $lang === 'de' ? 'Cisgender' : 'Cisgenre'; ?></option>
                                        <option value="2"><?php echo $lang === 'de' ? 'Transgender' : 'Transgenre'; ?></option>
                                        <option value="3"><?php echo $lang === 'de' ? 'Nicht-binär' : 'Non-binaire'; ?></option>
                                        <option value="4"><?php echo $lang === 'de' ? 'Genderfluid' : 'Genre fluide'; ?></option>
                                        <option value="5"><?php echo $lang === 'de' ? 'Intersex' : 'Intersexe'; ?></option>
                                        <option value="6"><?php echo $lang === 'de' ? 'Keines' : 'Aucun'; ?></option>
                                        <option value="7"><?php echo $lang === 'de' ? 'Andere' : 'Autre'; ?></option>
                                        <option value="8"><?php echo $lang === 'de' ? 'Ich möchte nicht antworten.' : 'Je ne souhaite pas répondre.'; ?></option>
                                    </select>
                                </div>
                            </div><br><br>
                            <div class="u-form-email u-form-group u-form-partition-factor-2">
                                <h3 style="margin:0;"><?php echo $texts[$lang]['sexuality_question']; ?></h3><br>
                                <div style="display: flex; align-items: center; gap:10px;">
                                    <p style="margin:0;"><?php echo $texts[$lang]['sexuality_prompt']; ?></p>
                                    <select style="margin:0; padding-left:0;" id="email-bb9b" name="orient" class="u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-btn-2" required>
                                        <option value="1"><?php echo $lang === 'de' ? 'Heterosexualität' : 'Hétérosexualité'; ?></option>
                                        <option value="2"><?php echo $lang === 'de' ? 'Homosexualität' : 'Homosexualité'; ?></option>
                                        <option value="3"><?php echo $lang === 'de' ? 'Bisexualität' : 'Bisexualité'; ?></option>
                                        <option value="4"><?php echo $lang === 'de' ? 'Pansexualität' : 'Pansexualité'; ?></option>
                                        <option value="5"><?php echo $lang === 'de' ? 'Asexualität' : 'Asexualité'; ?></option>
                                        <option value="6"><?php echo $lang === 'de' ? 'Keines' : 'Aucun'; ?></option>
                                        <option value="7"><?php echo $lang === 'de' ? 'Andere' : 'Autre'; ?></option>
                                        <option value="8"><?php echo $lang === 'de' ? 'Ich möchte nicht antworten.' : 'Je ne souhaite pas répondre.'; ?></option>
                                    </select>
                                </div>
                            </div><br><br>
                            <div class="u-form-email u-form-group u-form-partition-factor-2">
                                <label><?php echo $texts[$lang]['email_prompt']; ?></label>
                                <input name="e_mm" class="u-radius-50 u-text-hover-white">
                            </div>
                            <div class="u-align-right u-form-group u-form-submit">
                                <button type="submit" name="acc" class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-btn-submit u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                                    <?php echo $texts[$lang]['submit']; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    <?php } else if (((isset($_SESSION["LastQuestion"]) ? $_SESSION["LastQuestion"] : 0) >= (isset($_SESSION["TotalQuestions"]) ? $_SESSION["TotalQuestions"] : 1)) && (isset($_POST["acc"]) || isset($_SESSION["acc"]))) {
        $_SESSION["acc"] = "1";
        $_SESSION["genre"] = isset($_POST['genre']) ? htmlspecialchars($_POST['genre'], ENT_QUOTES, 'UTF-8') : '';
        $_SESSION["orient"] = isset($_POST['orient']) ? htmlspecialchars($_POST['orient'], ENT_QUOTES, 'UTF-8') : '';
        $_SESSION["emailr"] = isset($_POST['e_mm']) ? htmlspecialchars($_POST['e_mm'], ENT_QUOTES, 'UTF-8') : '';

        if (isset($_SESSION["id_user"]) && isset($_SESSION["genre"])) {
            try {
                $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                echo "Erreur connection: " . $e->getMessage();
            }

            $query = "UPDATE stationr2 SET genre = :genre, orientation = :orientation, repmail = :repmail WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                'genre' => $_SESSION["genre"],
                'orientation' => $_SESSION["orient"],
                'id' => $_SESSION["id_user"],
                'repmail' => $_SESSION["emailr"]
            ]);
            unset($_SESSION["id_user"]);
        }
        ?>
        <section class="u-clearfix u-valign-middle u-section-1" id="sec-089e">
            <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
                <div class="u-container-layout u-container-layout-1">
                    <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">
                        <p class="u-text u-text-default u-text-1" style="margin: auto;"><?php echo $texts[$lang]['thank_you']; ?></p>
                        <img src="images/drap.png" alt="" style="margin: auto;">
                    </div>
                </div>
            </div>
        </section>
        <script>
            localStorage.clear();
        </script>

    <?php } ?>

    <?php // include 'pages/footer.php'; ?>
    <script>
        setTimeout(() => {
            const section = document.querySelector('section');
            const newDiv = document.createElement('div');
            newDiv.id = "footer-placeholder";
            section.insertAdjacentElement('afterend', newDiv);
            fetch('pages/footer.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('footer-placeholder').innerHTML = data;
                });
        }, 100); 
    </script>
    <script>
        let timeout = false;
        let xhr = new XMLHttpRequest();
        let cd = 3000;
        function updateQuestion(buttonIndex) {
            changeRandomImage();
            if (timeout == false) {
                xhr.open("POST", "updateQuestion2.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        var response = xhr.responseText.split("__");
                        var answersarray = findAllBlocks();
                        if (response[0] == "fin") {
                            timeout = true;
                            answersarray.forEach(function (item, index) {
                                if (item.element != null) {
                                    const innerAnswers = item.querySelector('div#question_container');
                                    if (innerAnswers != null) {
                                        innerAnswers.classList.remove('fade-to-green');
                                        innerAnswers.classList.remove('fade-to-red');
                                        innerAnswers.classList.remove('fade-to-white');
                                    }
                                }
                            });
                            answersarray.forEach(function (item, index) {
                                if (item.element != null) {
                                    const innerAnswers = item.element.querySelectorAll('div#question_container');
                                    if (innerAnswers != null) {
                                        innerAnswers.forEach(function (innerAnswer) {
                                            if (index === parseInt(response[1], 10) - 1) {
                                                innerAnswer.classList.add("fade-to-green");
                                            } else {
                                                innerAnswer.classList.add("fade-to-red");
                                            }
                                        });
                                    }
                                }
                            });
                            setTimeout(function () {
                                timeout = false;
                                changeToSecond();
                            }, 3000);
                        } else {
                            timeout = true;
                            answersarray.forEach(function (item, index) {
                                const innerAnswers = item.querySelector('div#question_container');
                                if (innerAnswers != null) {
                                    innerAnswers.classList.remove('fade-to-green');
                                    innerAnswers.classList.remove('fade-to-red');
                                    innerAnswers.classList.remove('fade-to-white');
                                }
                            });
                            answersarray.forEach(function (item, index) {
                                const innerAnswers = item.querySelectorAll('div#question_container');
                                if (response[9] == "qcm") {
                                    innerAnswers.forEach(function (innerAnswer) {
                                        if (index === parseInt(response[7], 10) - 1) {
                                            innerAnswer.classList.add("fade-to-green");
                                        } else {
                                            innerAnswer.classList.add("fade-to-red");
                                        }
                                    });
                                } else if (response[9] == "lien") {
                                    const blocks = document.querySelectorAll('div[id^="reponse_"]');
                                    blocks.forEach(block => {
                                        block.remove();
                                    });
                                    document.getElementById("connections").remove();
                                } else if (response[9] == "mct") {
                                    const blocks = document.querySelectorAll('div[id^="reponse_"]');
                                    blocks.forEach(block => {
                                        block.remove();
                                    });
                                }
                            });

                            setTimeout(function () {
                                timeout = false;
                                document.getElementById("Question").innerHTML = response[0];
                                answersarray.forEach(function (item, index) {
                                    const innerAnswers = item.querySelector('div#question_container');
                                    if (innerAnswers != null) {
                                        innerAnswers.classList.remove('fade-to-green');
                                        innerAnswers.classList.remove('fade-to-red');
                                        innerAnswers.classList.remove('fade-to-white');
                                        innerAnswers.classList.add('fade-to-white');
                                    }
                                });
                                answersarray.forEach(function (item, index) {
                                    const innerAnswers = item.querySelector('p#rep');
                                    if (innerAnswers != null) {
                                        innerAnswers.innerHTML = response[index + 1];
                                    }
                                });
                                document.getElementById("QuestionN").innerHTML = "Question " + response[6];
                                document.getElementById('button_next').onclick = function () {
                                    updateQuestion(-1);
                                };
                                if (response[8] == "qcm" || response[8] == "echelle") {
                                    ismultiple = false;
                                    localStorage.clear();
                                    if (document.getElementsByClassName("popup")[0] != null)
                                        document.getElementsByClassName("popup")[0].remove();
                                    cd = 3000;
                                    deleteAllBlocks();
                                    for (let i = 1; i <= 5; i++) {
                                        if (response[i] != "null") {
                                            let repo = document.querySelector("#reponse_" + i);
                                            if (repo == null) {
                                                if (response[8] == "echelle") {
                                                    cd = 0;
                                                    parentDiv.style.flexDirection = "row";
                                                    parentDiv.insertAdjacentHTML("beforeend", newDivHTMLechelle);
                                                } else if (response[8] == "qcm") {
                                                    parentDiv.insertAdjacentHTML("beforeend", newDivHTML);
                                                    parentDiv.style.flexDirection = "column";
                                                }
                                                let reponse_elem = document.getElementById("reponse_");
                                                reponse_elem.id = "reponse_" + i;
                                                let button = reponse_elem.querySelector("#button_choix");
                                                let p_elem = reponse_elem.querySelector("#rep");
                                                const parser = new DOMParser();
                                                const decodedString = parser.parseFromString(response[i], "text/html").documentElement.textContent;
                                                p_elem.innerText = decodedString;
                                                button.addEventListener("click", function () { updateQuestion(i); });
                                            }
                                        }
                                        if (response[i] == "null") {
                                            let reponse_elem = document.getElementById("reponse_5");
                                            if (reponse_elem != null) {
                                                reponse_elem.remove();
                                            }
                                        }
                                    }
                                } else if (response[8] == "lien") {
                                    ismultiple = true;
                                    const blocks = document.querySelectorAll('div[id^="reponse_"]');
                                    blocks.forEach(block => {
                                        block.remove();
                                    });
                                    if (localStorage.getItem('lastationlienvar')) {
                                        if (localStorage.getItem('lastationlienvar')[0] != response[10]) {
                                            localStorage.clear();
                                            localStorage.setItem('lastationlienvar', response[10]);
                                        }
                                    } else {
                                        localStorage.setItem('lastationlienvar', response[10]);
                                    }
                                    let table_preset = `<p id="connections" style="width:50vw; font-size:14px;">${texts[lang].corrections} ${texts[lang].none}</p><div style="background-color: #fff0; width:100%; margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="margin-left:auto; width:80%; margin-right:1em;" border="1" id="myTable"><thead><tr><th style="background-color: #b3ffff;">N</th><th style="background-color: #ffa096;">${texts[lang].popup_title}</th><th style="background-color: #b3ffff;">Action</th></tr></thead><tbody></tbody></table></div>`;
                                    parentDiv.insertAdjacentHTML("beforeend", table_preset);

                                    let data1 = response[1].split("--");
                                    let data2 = response[2].split("--");
                                    let data3 = response[3].split('_');
                                    const Q = [];
                                    const R = [];
                                    data3.forEach(pair => {
                                        const parts = pair.split('-');
                                        if (parts.length === 2) {
                                            const [q, r] = parts;
                                            Q.push(q.replace('Q', ''));
                                            R.push(r.replace('R', ''));
                                        }
                                    });
                                    for (let i = 0; i < data1.length; i++) {
                                        let row = document.createElement('tr');
                                        let cell1 = document.createElement('td');
                                        cell1.classList.add("tnum");
                                        cell1.style.fontSize = "16px";
                                        cell1.textContent = i + 1;

                                        let cell2 = document.createElement('td');
                                        cell2.classList.add("tQ");
                                        cell2.setAttribute("data-type", "Q");
                                        cell2.setAttribute("data-row", i + 1);
                                        cell2.textContent = data1[i];

                                        let cell3 = document.createElement('td');
                                        cell3.classList.add("tR");
                                        cell3.setAttribute("data-type", "R");
                                        cell3.setAttribute("data-row", i + 1);
                                        const parser = new DOMParser();
                                        const decodedString = parser.parseFromString(data2[i], "text/html").documentElement.textContent;
                                        cell3.textContent = decodedString;

                                        let cell4 = document.createElement('td');
                                        let button = document.createElement('button');
                                        button.classList.add('u-active-palette-2-light-1', 'u-align-center', 'u-border-none', 'u-btn-round', 'u-button-style', 'u-hover-palette-2-light-1', 'u-palette-2-light-2', 'u-radius', 'u-text-palette-2-dark-1', 'u-btn-4');
                                        button.classList.add('show-info-btn');
                                        button.style.padding = "calc(0.2vh + 0.2vw)";
                                        button.style.margin = 0;
                                        button.innerHTML = lang === 'de' ? 'AUSWÄHLEN' : 'CHOISIR';
                                        button.style.cursor = "pointer";
                                        button.style.fontSize = "12px";
                                        button.setAttribute('data-row', i + 1);
                                        button.setAttribute('data-cell2-decoded', decodedString);
                                        cell4.appendChild(button);

                                        row.appendChild(cell1);
                                        row.appendChild(cell3);
                                        row.appendChild(cell4);
                                        document.querySelector('tbody').appendChild(row);
                                    }

                                    document.querySelectorAll('.show-info-btn').forEach(button => {
                                        button.addEventListener('click', function () {
                                            const rowNum = button.getAttribute('data-row');
                                            const decodedText = button.getAttribute('data-cell2-decoded');
                                            selectedR = rowNum;
                                            selectedRText = decodedText;
                                            if (document.getElementsByClassName("popup")[0] == null) {
                                                const popup = document.createElement('div');
                                                popup.classList.add('popup');
                                                popup.innerHTML = `
                <div class="popup-content">
                    <h3>${texts[lang].popup_title}</h3>
                    <p>${texts[lang].popup_prompt}</p>
                    <ul id="popup-options-list"></ul>
                    <button class="close-popup">${texts[lang].popup_close}</button>
                </div>
            `;
                                                document.body.appendChild(popup);

                                                let popupOptionsList = popup.querySelector("#popup-options-list");
                                                for (let i = 0; i < data1.length; i++) {
                                                    let optionItem = document.createElement('li');
                                                    optionItem.textContent = data1[i];
                                                    optionItem.setAttribute('data-row', i + 1);
                                                    optionItem.setAttribute('data-cell2', data1[i]);
                                                    optionItem.classList.add('popup-option', 'tQ');
                                                    optionItem.style.cursor = "pointer";
                                                    popupOptionsList.appendChild(optionItem);
                                                }
                                                popup.querySelectorAll('.popup-option').forEach(option => {
                                                    option.addEventListener('click', function () {
                                                        const selectedOption = option.getAttribute('data-cell2');
                                                        const rowIndex = option.getAttribute('data-row');
                                                        selectedQText = selectedOption;
                                                        selectedQ = rowIndex;
                                                        if (selectedQ && selectedR) {
                                                            var connection = "";
                                                            var goodconnection = "";
                                                            if (R[selectedQ - 1] == (selectedR)) {
                                                                connection = `${"<span style='color: green;'>" + selectedQText} -> ${"</span><span style='color: green;'>" + selectedRText + "</span><br>"}`;
                                                            } else {
                                                                connection = `${"<span style='color: red;'>" + selectedQText} -> ${"</span><span style='color: red;'>" + selectedRText + "</span><br>"}`;
                                                                var indexrep = R.indexOf(selectedR);
                                                                var indexrep2 = Q[indexrep];
                                                                var indexrep3 = R[indexrep];
                                                                var element = document.querySelector(`.tR[data-row="${indexrep3}"]`);
                                                                var element2 = document.querySelector(`.tQ[data-row="${indexrep2}"]`);
                                                                goodconnection = `${"<span style='color: green;'>" + element2.innerHTML} -> ${"</span><span style='color: green;'>" + element.innerHTML + "</span><br>"}`;
                                                            }

                                                            const index = connections.indexOf(connection);
                                                            const decodeHTML = str => {
                                                                const parser = new DOMParser();
                                                                const dom = parser.parseFromString(str, 'text/html');
                                                                return dom.documentElement.textContent;
                                                            };
                                                            const decodedTexts = connections.map(decodeHTML);
                                                            const decodedPhrase = decodeHTML(selectedRText);
                                                            if (decodedTexts.every(text => !text.includes(decodedPhrase))) {
                                                                if (index !== -1) {
                                                                } else {
                                                                    connections.push(connection);
                                                                    if (goodconnection != "")
                                                                        connections.push(goodconnection);
                                                                    localStorage.setItem('lastationlienvar', localStorage.getItem('lastationlienvar') + "&&Q@" + selectedQ + "|R@" + selectedR);
                                                                }
                                                            }

                                                            document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections}`;
                                                            selectedQ = null;
                                                            selectedR = null;
                                                        }
                                                        popup.style.display = "none";
                                                    });
                                                });
                                                popup.querySelector('.close-popup').addEventListener('click', function () {
                                                    popup.style.display = "none";
                                                });
                                            } else {
                                                document.getElementsByClassName("popup")[0].style.display = "flex";
                                            }
                                        });
                                        button.click();
                                        document.getElementsByClassName("popup")[0].style.display = "none";
                                    });
                                    const updatedString = localStorage.getItem('lastationlienvar').slice(1);
                                    const parts = updatedString.split('&&');
                                    const QQ = [];
                                    const RR = [];
                                    parts.forEach(part => {
                                        const subParts = part.split('|');
                                        subParts.forEach(subPart => {
                                            if (subPart.startsWith('Q@')) {
                                                QQ.push(Number(subPart.slice(2)));
                                            } else if (subPart.startsWith('R@')) {
                                                RR.push(Number(subPart.slice(2)));
                                            }
                                        });
                                    });
                                    if (QQ.length > 0) {
                                        QQ.forEach((element, index) => {
                                            const Q = [];
                                            const R = [];
                                            data3.forEach(pair => {
                                                const parts = pair.split('-');
                                                if (parts.length === 2) {
                                                    const [q, r] = parts;
                                                    Q.push(q.replace('Q', ''));
                                                    R.push(r.replace('R', ''));
                                                }
                                            });
                                            var indexrep = R.indexOf(String(RR[index]));
                                            var indexrep2 = Q[indexrep];
                                            var indexrep3 = R[indexrep];
                                            var element = document.querySelector(`.tR[data-row="${indexrep3}"]`);
                                            var element2 = document.querySelector(`.tQ[data-row="${indexrep2}"]`);
                                            goodconnection = `${"<span style='color: green;'>" + element2.innerHTML} -> ${"</span><span style='color: green;'>" + element.innerHTML + "</span><br>"}`;
                                            connections.push(goodconnection);
                                        });
                                        document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections}`;
                                    }
                                } else if (response[8] == "mct") {
                                    ismultiple = true;
                                    localStorage.clear();
                                    localStorage.setItem('lastationlienvar', response[10]);
                                    if (document.getElementsByClassName("popup")[0] != null)
                                        document.getElementsByClassName("popup")[0].remove();
                                    const blocks = document.querySelectorAll('div[id^="reponse_"]');
                                    blocks.forEach(block => {
                                        block.remove();
                                    });
                                    let table_preset = '<div style="background-color: #fff0; width:100%; margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="width:100%;" border="1" id="myTable"><thead></thead><tbody></tbody></table></div>';
                                    parentDiv.insertAdjacentHTML("beforeend", table_preset);
                                    let data1 = response[1].split("--");
                                    let data2 = response[2];
                                    let data3 = response[3];
                                    let data4 = response[4];
                                    let data5 = response[5];
                                    for (let i = 0; i < data1.length; i++) {
                                        let row = document.createElement('tr');
                                        let cell1 = document.createElement('td');
                                        cell1.classList.add("tnum");
                                        cell1.style.fontSize = "16px";
                                        cell1.textContent = data1[i];
                                        row.appendChild(cell1);
                                        if (data2 != "null") {
                                            let cell2 = document.createElement('td');
                                            cell2.classList.add("tQ");
                                            cell2.setAttribute("data-type", "Q");
                                            cell2.setAttribute("data-row", i + 1);
                                            cell2.setAttribute("data-id", 1);
                                            cell2.textContent = data2;
                                            row.appendChild(cell2);
                                        }
                                        if (data3 != "null") {
                                            let cell3 = document.createElement('td');
                                            cell3.classList.add("tR");
                                            cell3.setAttribute("data-type", "R");
                                            cell3.setAttribute("data-row", i + 1);
                                            cell3.setAttribute("data-id", 2);
                                            cell3.textContent = data3;
                                            row.appendChild(cell3);
                                        }
                                        if (data4 != "null") {
                                            let cell4 = document.createElement('td');
                                            cell4.classList.add("tQ");
                                            cell4.setAttribute("data-type", "R");
                                            cell4.setAttribute("data-row", i + 1);
                                            cell4.setAttribute("data-id", 3);
                                            cell4.textContent = data4;
                                            row.appendChild(cell4);
                                        }
                                        if (data5 != "null") {
                                            let cell5 = document.createElement('td');
                                            cell5.classList.add("tR");
                                            cell5.setAttribute("data-type", "R");
                                            cell5.setAttribute("data-row", i + 1);
                                            cell5.setAttribute("data-id", 4);
                                            cell5.textContent = data5;
                                            row.appendChild(cell5);
                                        }
                                        document.querySelector('tbody').appendChild(row);
                                    }
                                    document.querySelectorAll('td[data-type]').forEach(cell => {
                                        cell.addEventListener('click', function () {
                                            const cellType = cell.dataset.type;
                                            const cellRow = cell.dataset.row;
                                            if (cell.classList.contains('selected')) {
                                                cell.classList.remove('selected');
                                                if (cellType === 'Q') selectedQ = null;
                                                if (cellType === 'R') selectedR = null;
                                            } else {
                                                const rowCells = document.querySelectorAll(`[data-row='${cellRow}']:not([data-type='first'])`);
                                                rowCells.forEach(rowCell => {
                                                    if (rowCell !== cell) rowCell.classList.remove('selected');
                                                });
                                            }
                                            cell.class

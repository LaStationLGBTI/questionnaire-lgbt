<!DOCTYPE html>
<?php
ini_set('session.gc_maxlifetime', 31536000);
session_start();
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3) {
	echo '
            <h1>Accès Bloqué</h1>
            <p class="error" name="session_bloquee">Votre accès est bloqué. Veuillez contacter l`administrateur.</p>';
	exit();
}
if (isset($_POST['reset_session'])) {
    session_unset();
    session_destroy();
}
if (isset($_GET['level']) && !isset($_SESSION['level'])) {
    $new_level = $_GET['level'];
    $lang_to_preserve = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
    session_unset();
    $_SESSION['level'] = $new_level;
    $_SESSION['language'] = $lang_to_preserve;
    header('Location: index.php');
    exit();
}
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = isset($_POST['language']) && in_array($_POST['language'], ['de', 'fr']) ? $_POST['language'] : 'fr';
}
if (isset($_GET['level'])) {
    $_SESSION['level'] = $_GET['level'];
}
if (isset($_POST['language']) && in_array($_POST['language'], ['de', 'fr'])) {
    $_SESSION['language'] = $_POST['language'];
}
$lang = $_SESSION['language'];

$texts = [
    'fr' => [
		'choose_questionnaire' => 'Choisissez un questionnaire',
        'questionnaire_level' => 'L\'enquête : Module `{level}`',
        'no_questionnaires_available' => 'Aucun questionnaire disponible.',
        'project_title' => 'Sensibilisation aux violences sexuelles et sexistes et autres discriminations (Formation en ligne et en auto-évaluation)',
        'project_desc' => '',
        'objectives' => 'Objectifs du projet :',
        'awareness' => 'Sensibilisation : Encourager les élèves à mieux comprendre les réalités et les défis auxquels font face les personnes LGBTQIA+.',
        'inclusion' => 'Inclusion : Promouvoir un climat scolaire respectueux, où chacun se sent accepté, peu importe son identité ou son orientation.',
        'engagement' => 'Engagement citoyen : Montrer comment des actions concrètes peuvent contribuer à faire évoluer les mentalités et renforcer les valeurs de respect et de solidarité.',
        'why_survey' => 'Pourquoi une enquête en ligne ?',
        'survey_reason' => 'L’élève a choisi ce format interactif pour permettre à ses camarades de participer anonymement et de s’exprimer librement.',
        'impact' => 'L’impact attendu :',
        'impact_desc' => 'En impliquant ses pairs dans ce processus participatif, l’élève fait bien plus que sensibiliser : il agit comme un vecteur de changement en les encourageant à adopter des comportements inclusifs et à devenir eux-mêmes des ambassadeurs du respect.',
        'project_note' => 'Ce projet, à la fois éducatif et solidaire, s’inscrit pleinement dans les valeurs portées par le Collège international Vauban et témoigne de l’engagement d’un élève inspirant pour construire un monde plus juste et tolérant.',
        'warning_title' => 'Avertissement concernant le sondage',
        'anonymity' => 'Anonymat garanti : Toutes vos réponses sont recueillies de manière anonyme. Aucune information personnelle ne sera associée à vos réponses.',
        'voluntary' => 'Participation libre : La participation à ce sondage est entièrement facultative. Vous pouvez choisir de ne pas répondre à certaines questions si vous ne le souhaitez pas.',
        'results' => 'Résultats disponibles : Si vous souhaitez recevoir un résumé des résultats une fois l’enquête terminée, vous pouvez laisser votre adresse e-mail à la fin du sondage. Cette étape est totalement optionnelle et ne compromet pas l’anonymat de vos réponses.',
        'thanks' => 'Merci pour votre participation à ce projet qui contribue à sensibiliser et à promouvoir le respect et l’inclusion au sein de notre communauté scolaire.',
        'continue' => 'Continuer',
        'footer' => 'Conception de la page : R. (Hex) ; maître de stage : Gérald Schlemminger, (c) 2025 La STATION',
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
        'none' => 'Aucun',
		'question_choise' => "CHOISIR",
        'return_to_start' => 'Retour à l\'accueil'
    ],
    'de' => [
	    'choose_questionnaire' => 'Wählen Sie einen Fragebogen',
        'questionnaire_level' => 'Umfrage starten (Stufe {level})',
        'no_questionnaires_available' => 'Keine Fragebögen verfügbar.',
        'project_title' => '',
        'project_desc' => '',
        'objectives' => 'Ziele des Projekts:',
        'awareness' => 'Sensibilisierung: Die Schüler/innen dazu ermutigen, die Realitäten und Herausforderungen von LGBTQIA+-Personen besser zu verstehen.',
        'inclusion' => 'Inklusion: Förderung eines respektvollen Schulklima, in dem sich jede Person unabhängig von ihrer Identität oder Orientierung akzeptiert fühlt.',
        'engagement' => 'Bürgerschaftliches Engagement: Zeigen, wie konkrete Maßnahmen dazu beitragen können, Einstellungen zu verändern und Werte wie Respekt und Solidarität zu stärken.',
        'why_survey' => 'Warum eine Online-Umfrage?',
        'survey_reason' => 'Luc hat dieses interaktive Format gewählt, um seinen Mitschüler/innen eine anonyme Teilnahme und freie Meinungsäußerung zu ermöglichen. Die Ergebnisse der Umfrage dienen als Grundlage für Klassendiskussionen, Sensibilisierungsworkshops oder Initiativen zur Verbesserung der Inklusion in der Schule.',
        'impact' => 'Erwartete Wirkung:',
        'impact_desc' => 'Durch die Einbindung seiner Mitschüler/innen in diesen partizipativen Prozess geht Luc weit über Sensibilisierung hinaus: Er wirkt als Katalysator für Veränderung, indem er sie dazu ermutigt, inklusive Verhaltensweisen anzunehmen und selbst zu Botschaftern für Respekt zu werden.',
        'project_note' => 'Dieses sowohl pädagogische als auch solidarische Projekt steht ganz im Einklang mit den Werten des Collège international Vauban und zeigt das Engagement eines inspirierenden Schülers, eine gerechtere und tolerantere Welt zu schaffen.',
        'warning_title' => 'Hinweis zur Umfrage',
        'anonymity' => 'Garantierte Anonymität: Alle Ihre Antworten werden anonym erfasst. Es werden keine persönlichen Informationen mit Ihren Antworten verknüpft.',
        'voluntary' => 'Freiwillige Teilnahme: Die Teilnahme an dieser Umfrage ist völlig freiwillig. Du kannst entscheiden, bestimmte Fragen nicht zu beantworten, wenn Sie Dudies nicht möchten.',
        'results' => 'Verfügbare Ergebnisse: Wenn Sie du nach Abschluss der Umfrage eine Zusammenfassung der Ergebnisse erhalten möchtest, kannst du am Ende der Umfrage Ihre E-Mail-Adresse hinterlassen. Dieser Schritt ist völlig optional und beeinträchtigt nicht die Anonymität deiner Antworten.',
        'thanks' => 'Vielen Dank für deine Teilnahme an diesem Projekt, das dazu beiträgt, das Bewusstsein zu schärfen und Respekt sowie Inklusion in unserer Schulgemeinschaft zu fördern.',
        'continue' => 'Weiter',
        'footer' => 'Seitengestaltung: R. (Hex); Praktikumsbetreuer: Gérald Schlemminger, (c) 2025 La STATION',
        'final_warning' => 'Hinweis zur abschließenden Fragen',
        'final_warning_desc' => 'Die letzten Fragen der Umfrage sind persönlicher Art und betreffen deine Geschlechtsidentität und deine sexuelle Orientierung. Wir verstehen, dass diese Themen als sensibel oder aufdringlich empfunden werden können. Es besteht keine Verpflichtung zu antworten: Du kannst frei entscheiden, diese Fragen nicht zu beantworten, wenn du dich unwohl fühlst. Dies hat keinen Einfluss auf deine Teilnahme an der Umfrage.',
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
        'none' => 'Keine',
		'question_choise' => "AUSWÄHLEN",
        'return_to_start' => 'Zurück zum Start'
    ]
];
$lang = $_SESSION['language'];
?>
<?php require_once 'conf.php'; ?>
<html style="font-size: 16px;" lang="<?php echo $lang; ?>">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title><?php echo $lang === 'de' ? 'Frage' : 'Question'; ?></title>
    <link rel="stylesheet" href="nicepage.css" media="screen">
    <link rel="stylesheet" href="Question.css" media="screen">
    <style>
        .error { color: #dc3545; }
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
		.results-table .question-column { width: 30%; font-weight: bold; }
    	.results-table .expliq-column {
        width: 35%;
        font-style: italic;
        color: #555;
        background-color: #fdfdfd;
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
	let texts = <?php echo json_encode($texts); ?>;
	let lang = "<?php echo $lang; ?>";
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
	 document.getElementById("QuestionN").innerHTML = (lang === 'de' ? "Frage " : "Question ") + response[6];
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
		    button.innerHTML = texts[lang].question_choise;
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

					document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections.join('')}`;
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
		document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections.join('')}`;
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
if (!isset($_SESSION['level'])) {


    session_unset();
    $levels = [];
    $error_message = '';
	$level_titles = [];
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT DISTINCT level FROM GSDatabase ORDER BY level ASC");
        $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$stmt_titles = $pdo->query("SELECT level, titre FROM GSDatabaseT");
        $all_titles = $stmt_titles->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        $error_message = "Erreur de connexion à la base de données : " . $e->getMessage();
    }
?>
    <section class="u-clearfix u-valign-middle u-section-1" id="sec-level-selection">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">

                    <h2 class="u-align-center u-text u-text-default u-text-1">
                        <b><?php echo $texts[$lang]['choose_questionnaire']; ?></b>
                    </h2>

                    <?php if ($error_message): ?>
                        <p class="error u-text"><?= htmlspecialchars($error_message) ?></p>
                    <?php elseif (empty($levels)): ?>
                        <p class="u-text"><?= $texts[$lang]['no_questionnaires_available'] ?></p>
                    <?php else: ?>
                        <div style="margin-top: 2em; padding-bottom: 2em;">
                            <?php foreach ($levels as $level): ?>
                                <b><a href="index.php?level=<?= htmlspecialchars($level) ?>"

                                   style="color:black; display: block; width: 100%; max-width: 400px; margin: 15px auto;">

                                   <?php  ?>
                                   <?= str_replace('{level}', htmlspecialchars($level), $texts[$lang]['questionnaire_level']);

                                   if (isset($all_titles[$level])) {

                                       echo ': ' . htmlspecialchars($all_titles[$level]);
                                   }
                                   ?>
                                   <?php ?>

                                </a></b>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
<?php
    } else if (!isset($_POST["start"]) && !isset($_SESSION["start"])) {

	    $level_titre = '';
    $level_text = ''; // Variable par défaut

    try {
        // Connexion à la base de données
        $pdo_desc = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo_desc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Préparons une requête pour obtenir le texte actuel $_SESSION['level']
        $stmt_desc = $pdo_desc->prepare("SELECT titre, text FROM GSDatabaseT WHERE level = ?");
        $stmt_desc->execute([$_SESSION['level']]);
        $level_data = $stmt_desc->fetch(PDO::FETCH_ASSOC);

        // Si les données pour ce niveau sont trouvées, nous les utilisons
        if ($level_data) {
            $level_titre = $level_data['titre'];
            $level_text = $level_data['text'];
        } else {
            // Si le tableau ne contient aucune description pour ce niveau, utilisez le texte par défaut
            $level_text = $texts[$lang]['project_desc'];
        }
    } catch (PDOException $e) {
        // En cas d'erreur de connexion à la base de données, afficher le message suivant
        $level_text = "Erreur de chargement de la description.";
    }

	?>
<section class="u-clearfix u-valign-middle u-section-1" id="sec-089e2">
    <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
        <div class="u-container-layout u-container-layout-1">
            <div class="u-clearfix u-sheet u-sheet-1">
                <p class="u-text u-text-default u-text-1" style="text-align:center;margin:auto; font-size:24px;">
                    <b><?php echo $texts[$lang]['project_title']; ?></b>
                </p>

                <?php // --- LIGNE DE SORTIE MODIFIÉE --- ?>
                <div style="margin:1em 0; font-size:16px; text-align: justify; padding: 0 1em;">
                    <?php
                        // Nous extrayons le titre de la base de données, s'il existe
                        if (!empty($level_titre)) {
                            echo '<i><b><h3 style="text-align: center;">' . htmlspecialchars($level_titre) . '</h3></i></b>';
                        }
                        // Nous affichons le texte principal (il contient déjà du HTML, donc sans htmlspecialchars)
                        echo $level_text;
                    ?>
                </div>
                <?php // --- FIN DES MODIFICATIONS --- ?>

                <p style="margin:1em; padding:1em;border:solid; font-size:14px; border-color:#1400ff;">
                    <b><?php echo $texts[$lang]['warning_title']; ?></b> <br><br>

                    <b><?php echo $texts[$lang]['anonymity']; ?></b><br>
                    <b><?php echo $texts[$lang]['voluntary']; ?></b><br>
                    <b><?php echo $texts[$lang]['results']; ?></b><br><br>

                    <i><?php echo $texts[$lang]['thanks']; ?></i>
                </p>

                <div class="language-selector">
                    <span style="align-self: center;">Français</span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="language" value="fr">
                        <input type="image" src="images/france.svg" alt="Français" class="language-flag <?php echo $lang === 'fr' ? 'selected' : ''; ?>" style="width: 40px; height: 40px;">
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="language" value="de">
                        <input type="image" src="images/germany.svg" alt="Deutsch" class="language-flag <?php echo $lang === 'de' ? 'selected' : ''; ?>" style="width: 40px; height: 40px;">
                    </form>
                    <span style="align-self: center;">Deutsch</span>
                </div>

                <form method="POST" action="">
                    <div class="u-align-right u-form-group u-form-submit">
                        <button style="margin-top:1vh;" value="1" name="start" type="submit"
                            class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-btn-submit u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                            <?php echo $texts[$lang]['continue']; ?>
                        </button>
                        <p style="font-size:10px;"><?php echo $texts[$lang]['footer']; ?></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

    <?php } else if ((isset($_POST["start"]) || isset($_SESSION["start"])) && (isset($_SESSION["LastQuestion"]) ? $_SESSION["LastQuestion"] : 0) <= (isset($_SESSION["TotalQuestions"]) ? $_SESSION["TotalQuestions"] : 1)) {
if (!isset($_SESSION["start"])) {
	    if (!isset($_SESSION['level'])) {
        echo "Error: Questionnaire level not selected. Please go back and choose a questionnaire.";
        exit();
    }
    try {
        $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "Erreur connection: " . $e->getMessage();
    }
    $table = $lang === 'de' ? 'GSDatabase' : 'GSDatabase';
    $stmt = $conn->prepare("SELECT * FROM $table WHERE level = ? ORDER BY `id` ASC");
    $stmt->execute([$_SESSION['level']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($_SESSION['level'] != 1 && count($results) > 20) {
    shuffle($results);
    $results = array_slice($results, 0, 20);
	}
    $_SESSION["QuestionToUse"] = "Questions";
    $_SESSION["Rep1"] = "Reponses1";
    $_SESSION["Rep2"] = "Reponses2";
    $_SESSION["Rep3"] = "Reponses3";
    $_SESSION["Rep4"] = "Reponses4";
    $_SESSION["Rep5"] = "Reponses5";
    $_SESSION["IdInUse"] = "id";
    $_SESSION["answer"] = "answer";
    $_SESSION["qtype"] = "qtype";
	$_SESSION["expliqs"] = "expliqs";
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
			$_SESSION["expliqs"] .= "__" . $row["expliq"];
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
            <button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-radius u-btn-4" style="color:black; margin-top:0; background-color:#8a7bf4;" id="button_next" onclick="updateQuestion(-1)">
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

    $query = "UPDATE GSDatabaseR SET genre = :genre, orientation = :orientation, repmail = :repmail, lang = :lang WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'genre' => $_SESSION["genre"],
        'orientation' => $_SESSION["orient"],
        'repmail' => $_SESSION["emailr"],
        'lang' => $_SESSION["language"],
        'id' => $_SESSION["id_user"]
    ]);
    unset($_SESSION["id_user"]);
}
$questions = explode('__', $_SESSION["QuestionToUse"]);
$ids_in_use = explode('__', $_SESSION["IdInUse"]);
$rep1s = explode('__', $_SESSION["Rep1"]);
$rep2s = explode('__', $_SESSION["Rep2"]);
$rep3s = explode('__', $_SESSION["Rep3"]);
$rep4s = explode('__', $_SESSION["Rep4"]);
$rep5s = explode('__', $_SESSION["Rep5"]);
$correct_answers = explode('__', $_SESSION["answer"]);
$qtypes = explode('__', $_SESSION["qtype"]);

// Nous analysons les réponses de l'utilisateur dans un tableau pratique [id_question => réponse]
$user_answers = [];
if(isset($_SESSION['reponses'])){
    $user_reponses_raw = explode('__Q@', $_SESSION['reponses']);
    foreach ($user_reponses_raw as $rep) {
        if (strpos($rep, '||R@') !== false) {
            list($qid_part, $rans_part) = explode('||R@', $rep);
            $qid = str_replace('Q@', '', $qid_part);
            $user_answers[$qid] = $rans_part;
        }
    }
}
// -- FIN DU BLOC DE PRÉPARATION DES DONNÉES --
?>

<style>
    .results-table {
        width: 90%; margin: 1em auto; border-collapse: collapse;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1); background-color: white;
    }
    .results-table th, .results-table td {
        padding: 12px 15px; border: 1px solid #ddd; text-align: left;
    }
    .results-table th {
        background-color: #4f6d7a; color: white; font-weight: bold;
    }
    .results-table tr:nth-of-type(even) { background-color: #f8f8f8; }
    .results-table .question-column { width: 35%; font-weight: bold; }

    .legend { text-align: left; width: 90%; margin: 1em auto; padding: 10px; background-color: #f0f0f0; border-radius: 8px; font-size: 0.9em; }
    .legend-item { display: inline-flex; align-items: center; margin-right: 20px; }
    .legend-color-box { width: 20px; height: 20px; border: 1px solid #ccc; margin-right: 8px; }

    .user-answer-color { background-color: #a0c4ff; }
    .correct-answer-color { background-color: #90ee90; }
    .user-correct-answer-color { background: linear-gradient(135deg, #90ee90 50%, #a0c4ff 50%); }

    .user-answer { background-color: #a0c4ff; /* Bleu */}
    .correct-answer { background-color: #90ee90; /* Vert */}
    /* Couleur mixte, si la cellule appartient aux DEUX classes */
    .user-answer.correct-answer {
        background: linear-gradient(135deg, #90ee90 50%, #a0c4ff 50%);
    }
    .score-display { font-size: 1.2em; font-weight: bold; margin-top: 2em; }
</style>

<section class="u-clearfix u-valign-middle u-section-1" id="sec-089e">
    <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
        <div class="u-container-layout u-container-layout-1">
            <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">
                <p class="u-text u-text-default u-text-1" style="margin: auto;"><?php echo $texts[$lang]['thank_you']; ?></p>

                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color-box user-answer-color"></div>
                        <span><?php echo $lang === 'de' ? 'Ihre Antwort' : 'Votre Réponse'; ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color-box correct-answer-color"></div>
                        <span><?php echo $lang === 'de' ? 'Richtige Antwort' : 'Réponse correcte'; ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color-box user-correct-answer-color"></div>
                        <span><?php echo $lang === 'de' ? 'Ihre richtige Antwort' : 'Votre réponse est correcte'; ?></span>
                    </div>
                </div>

<table class="results-table">
    <thead>
        <tr>
            <th class="question-column">Question</th>
            <th colspan="5">Réponses</th>
			<th class="expliq-column">Explication</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $total_questions_in_summary = 0;
        $correct_answers_count = 0;

        // --- NEW: Load expliqs from session ---
        $expliqs = explode('__', $_SESSION["expliqs"]);

        for ($i = 1; $i <= $_SESSION["TotalQuestions"]; $i++) {
            $q_type = $qtypes[$i] ?? 'qcm';
            if ($q_type != 'qcm' && $q_type != 'echelle') {
                continue;
            }
            $total_questions_in_summary++;

            $current_id = $ids_in_use[$i];
            $user_choice = $user_answers[$current_id] ?? null;
            $correct_answer = $correct_answers[$i];

            if ($user_choice !== null && $user_choice == $correct_answer) {
                $correct_answers_count++;
            }

            echo '<tr>';
            echo '<td class="question-column">' . htmlspecialchars($questions[$i]) . '</td>';




            $possible_answers = [$rep1s[$i], $rep2s[$i], $rep3s[$i], $rep4s[$i], $rep5s[$i]];

            $answers_count = 0;
            foreach ($possible_answers as $j => $answer_text) {
                if ($answer_text !== 'null' && $answer_text !== '') {
                    $answers_count++;
                    $answer_num = $j + 1;
                    $classes = [];

                    if ($answer_num == $user_choice) { $classes[] = 'user-answer'; }
                    if ($answer_num == $correct_answer) { $classes[] = 'correct-answer'; }

                    echo '<td class="' . implode(' ', $classes) . '">' . htmlspecialchars($answer_text) . '</td>';
                }
            }


            for ($k = $answers_count; $k < 5; $k++) {
                echo '<td></td>';
            }
			            $explanation = isset($expliqs[$i]) ? htmlspecialchars($expliqs[$i]) : '';
            echo '<td class="expliq-column">' . $explanation . '</td>';
            echo '</tr>';
        }
        ?>
    </tbody>
</table>

                <p class="score-display">
                    <?php
                    if ($lang === 'de') {
                        echo "Sie haben {$correct_answers_count} von {$total_questions_in_summary} Fragen richtig beantwortet.";
                    } else {
                        echo "Vous avez répondu correctement à {$correct_answers_count} questions sur  {$total_questions_in_summary}";
                    }
                    ?>
                </p>

                <form method="POST" action="index.php" style="margin-top: 1em;">
                    <button type="submit" name="reset_session" class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                        <?php echo $texts[$lang]['return_to_start']; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
        <script>
            localStorage.clear();
        </script>

    <?php } ?>

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
		let cd = 0;
		function updateQuestion(buttonIndex) {
			changeRandomImage();
			if (timeout == false) {
				timeout = true;
				xhr.open("POST", "updateQuestion2.php", true);
				xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
				xhr.onreadystatechange = function () {
					if (xhr.readyState == 4 && xhr.status == 200) {

						var response = xhr.responseText.split("__");
						var answersarray = findAllBlocks();
						if (response[0] == "fin") {
							timeout = true;
							// Tout d'abord, supprimons toutes les classes précédentes pour plus de clarté
							answersarray.forEach(function (item, index) {
								const innerAnswers = item.querySelector('div#question_container');
								if (innerAnswers != null) {
									innerAnswers.classList.remove('fade-to-green');
									innerAnswers.classList.remove('fade-to-red');
									innerAnswers.classList.remove('fade-to-white');
								}
							});
							// Ensuite, nous montrons la bonne/mauvaise réponse
							answersarray.forEach(function (item, index) {
								const innerAnswers = item.querySelectorAll('div#question_container');
								if (innerAnswers.length > 0) {
									innerAnswers.forEach(function (innerAnswer) {
										if (index === parseInt(response[1], 10) - 1) {
											innerAnswer.classList.add("fade-to-green");
										} else {
											innerAnswer.classList.add("fade-to-red");
										}
									});
								}
							});
							setTimeout(function () {
								timeout = false;
								window.location.href = window.location.href;
							}, 3000);
						}

						else {
							timeout = true;
							if (response[9] === "qcm") {
								cd = 3000;
							}
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
								}
								else if (response[9] == "lien") {
									const blocks = document.querySelectorAll('div[id^="reponse_"]');
									blocks.forEach(block => {
										block.remove();
									});
									document.getElementById("connections").remove();
								}
								else if (response[9] == "mct") {
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
								document.getElementById("QuestionN").innerHTML = (lang === 'de' ? "Frage " : "Question ") + response[6];
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

												}
												else if (response[8] == "qcm") {
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
								}
								else if (response[8] == "lien") {
									ismultiple = true;

									const blocks = document.querySelectorAll('div[id^="reponse_"]');
									blocks.forEach(block => {
										block.remove();
									});
									if (localStorage.getItem('lastationlienvar')) {
										if (localStorage.getItem('lastationlienvar')[0] != response[10]) {
											console.log("not same");
											localStorage.clear();
											localStorage.setItem('lastationlienvar', response[10]);
										}
									}
									else {
										localStorage.setItem('lastationlienvar', response[10]);
									}
									let table_preset = `<p id="connections" style="width:50vw; font-size:14px;">${texts[lang].corrections} ${texts[lang].none}</p><div style="background-color: #fff0; width:100%; margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="margin-left:auto; width:80%; margin-right:1em;" border="1" id="myTable"><thead><tr><th style="background-color: #b3ffff;">N</th><th style="background-color: #ffa096;">${texts[lang].popup_title}</th><th style="background-color: #b3ffff;">Action</th></tr></thead><tbody></tbody></table></div>`;									parentDiv.insertAdjacentHTML("beforeend", table_preset);

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
										} else {
											console.error(`Malformed pair: "${pair}"`);
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
										button.classList.add('u-active-palette-2-light-1', 'u-align-center', 'u-border-none', 'u-btn-round', 'u-button-style', 'u-hover-palette-2-light-1', 'u-palette-2-light-2', 'u-radius', 'u-text-palette-2-dark-1', 'u-btn-4'); button.classList.add('show-info-btn');
										button.style.padding = "calc(0.2vh + 0.2vw)";
										button.style.margin = 0;
										button.innerHTML = texts[lang].question_choise;
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
					<h3>Définition</h3>
					<p>Choisissez une option dans la liste :</p>
					<ul id="popup-options-list"></ul>
					<button class="close-popup">Close</button>
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
															if (R[selectedQ - 1] == (selectedR))
															{
																connection = `${"<span style='color: green;'>" + selectedQText} -> ${"</span><span style='color: green;'>" + selectedRText + "</span><br>"}`;
															}
															else {
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

															document.getElementById('connections').innerHTML = `Corrections:<br>${connections}`;
															selectedQ = null;
															selectedR = null;
														}
														popup.style.display = "none";
													});
												});
												popup.querySelector('.close-popup').addEventListener('click', function () {
													popup.style.display = "none";
												});
											}
											else {
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
												} else {
													console.error(`Malformed pair: "${pair}"`);
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
										document.getElementById('connections').innerHTML = `Corrections:<br>${connections}`;
									}
								}
								else if (response[8] == "mct") {
									ismultiple = true;

									console.log(localStorage.getItem('lastationlienvar'));
									localStorage.clear();
									localStorage.setItem('lastationlienvar', response[10]);

									if (document.getElementsByClassName("popup")[0] != null)
										document.getElementsByClassName("popup")[0].remove();
									const blocks = document.querySelectorAll('div[id^="reponse_"]');
									blocks.forEach(block => {
										block.remove();
									});
									let table_preset = '<div style="background-color: #fff0; width:100%;  margin:auto; margin-top:0;" id="reponse_1" class="u-align-center u-container-align-center u-container-align-center-md u-container-align-center-xl u-container-align-center-xs u-container-style u-custom-border u-list-item u-radius u-repeater-item u-shape-round u-white u-list-item-4" data-animation-name="customAnimationIn" data-animation-duration="1750" data-animation-delay="500"><table style="width:100%;" border="1" id="myTable"><thead></thead><tbody></tbody></table></div>';
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
											if (cellType === 'Q') selectedQ = cell;
											if (cellType === 'R') selectedR = cell;
											const selectedElements = document.querySelectorAll('.selected');
											let resultString = '';
											selectedElements.forEach(element => {
												let dataRow = element.getAttribute('data-row');
												let dataId = element.getAttribute('data-id');
												resultString += `&&Q@${dataRow}|R@${dataId}`;
											});
											localStorage.setItem('lastationlienvar', response[10] + resultString);
											console.log(localStorage.getItem('lastationlienvar'));
										});
									});
								}
								resize_questions();
							}, cd);

						}
					}
					else if (xhr.readyState == 4) {
						// Si la requête s'est terminée par une erreur, nous autorisons à nouveau les clics
						timeout = false;
						alert('Une erreur s'est produite. Veuillez réessayer.');
					}
				};
				if (ismultiple == true) {
					let temptext = String(localStorage.getItem('lastationlienvar'));
					xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
					console.log(temptext);
					xhr.send("choise=" + encodeURIComponent(temptext));
				}
				else
					xhr.send("choise=" + buttonIndex);
			}

		}
	</script>
</body>
</html>













































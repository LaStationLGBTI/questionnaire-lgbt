<!DOCTYPE html>
<?php
// --- Mode test CLI : tester l'envoi SMTP sans passer par le questionnaire ---
// Usage :  php index.php destinataire@exemple.com
// Dans le conteneur : docker exec <conteneur> php /var/www/html/index.php destinataire@exemple.com
// Affiche la raison exacte (lignes [mail]) en cas d'echec. Aucun mot de passe n'est logue.
if (PHP_SAPI === 'cli') {
    $to = isset($argv[1]) ? trim($argv[1]) : '';
    if ($to === '' || $to === '--help' || $to === '-h') {
        fwrite(STDERR, "Usage: php index.php destinataire@exemple.com\n");
        exit(1);
    }
    require_once __DIR__ . '/conf.php';
    require_once __DIR__ . '/mailer.php';
    fwrite(STDOUT, "Test d'envoi SMTP vers : $to\n");
    $ok = send_results_email(
        $to,
        'Test SMTP - La Station LGBTQIA+',
        '<html><head><meta charset="UTF-8"></head><body>'
            . '<p>Ceci est un message de test envoye depuis index.php (CLI).</p>'
            . '</body></html>'
    );
    fwrite(STDOUT, $ok
        ? "Resultat : ENVOYE (verifiez la boite de reception, y compris les spams)\n"
        : "Resultat : ECHEC -> voir les lignes [mail] ci-dessus pour la raison exacte\n");
    exit($ok ? 0 : 1);
}

// --- Auto-test SMTP par URL (TEMPORAIRE : a retirer apres le test) ---
// Permet de tester l'envoi depuis le navigateur, sans acces au serveur.

// Avant de pousser : remplacez le jeton ci-dessous par une chaine aleatoire a vous.
if (isset($_GET['selftest'])) {
    $SELFTEST_TOKEN = 'AowGKhu4zgf2QxkZVC5tJNyU'; // votre jeton aleatoire
    header('Content-Type: text/plain; charset=UTF-8');
    if ($SELFTEST_TOKEN === '' || (string) $_GET['selftest'] !== $SELFTEST_TOKEN) {
        http_response_code(403);
        echo "Acces refuse : jeton invalide.\n";
        exit;
    }
    $to = isset($_GET['to']) ? trim($_GET['to']) : '';
    require_once __DIR__ . '/conf.php';
    require_once __DIR__ . '/mailer.php';
    echo "== Auto-test SMTP ==\n";
    echo 'SMTP_HOST : ' . (!empty($SMTP_HOST) ? 'defini' : 'VIDE') . "\n";
    echo 'SMTP_USER : ' . (!empty($SMTP_USER) ? 'defini' : 'VIDE') . "\n";
    echo 'SMTP_PASS : ' . (!empty($SMTP_PASS) ? 'defini' : 'VIDE') . "\n";
    echo 'Destinataire : ' . $to . "\n\n";
    $ok = send_results_email(
        $to,
        'Test SMTP - La Station LGBTQIA+',
        '<html><head><meta charset="UTF-8"></head><body><p>Message de test (auto-test web).</p></body></html>'
    );
    echo $ok
        ? "Resultat : ENVOYE (verifiez la boite de reception, y compris les spams)\n"
        : "Resultat : ECHEC\n";
    if (!$ok && !empty($GLOBALS['mail_last_error'])) {
        echo 'Raison : ' . $GLOBALS['mail_last_error'] . "\n";
    }
    if (!empty($GLOBALS['mail_debug'])) {
        echo "\n--- Trace SMTP ---\n" . $GLOBALS['mail_debug'];
    }
    exit;
}
ini_set('session.gc_maxlifetime', 31536000);
session_start();
// Ne pas exposer les erreurs PHP au public (mettre APP_DEBUG=1 côté serveur pour le dev).
if (getenv('APP_DEBUG') === '1') {
    ini_set('display_errors', 1); ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0); ini_set('display_startup_errors', 0);
}
error_reporting(E_ALL); ini_set('log_errors', '1');
// NB : le verrouillage anti-force-brute de l'admin est désormais géré par fichier (auth.php),
// indépendamment de cette session publique. Le questionnaire n'est plus jamais bloqué par ce biais.
if (isset($_POST['reset_session'])) {
    session_unset();
    session_destroy();
}
// Retour au choix du module : on efface uniquement la sélection et l'état du
// questionnaire, en conservant la langue (pas de session_destroy / unset global).
if (isset($_GET['back'])) {
    foreach (['level', 'start', 'LastQuestion', 'TotalQuestions', 'QuestionToUse',
              'Rep1', 'Rep2', 'Rep3', 'Rep4', 'Rep5', 'IdInUse', 'answer', 'qtype',
              'expliqs', 'reponses', 'id_user', 'finish', 'acc', 'genre', 'orient', 'emailr',
              'mail_sent', 'game_mode', 'game_pin'] as $k) {
        unset($_SESSION[$k]);
    }
    header('Location: index.php');
    exit();
}
if (isset($_GET['level']) && !isset($_SESSION['level'])) {
    $new_level = $_GET['level'];
    $lang_to_preserve = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
    // session_unset() effacerait aussi la clé d'accès (access.php) : on la préserve comme la langue.
    $access_to_preserve = array_intersect_key($_SESSION, array_flip(['access_key', 'access_checked_at', 'access_checked_ok']));
    session_unset();
    $_SESSION['level'] = $new_level;
    $_SESSION['language'] = $lang_to_preserve;
    foreach ($access_to_preserve as $ak => $av) { $_SESSION[$ak] = $av; }
    header('Location: index.php');
    exit();
}
// Seules les locales RÉELLEMENT installées (lang/{code}.php) sont acceptées ici (sans DB).
// Une revalidation « langue activée » est faite plus bas dès qu'un $pdo est disponible.
$lang_installed = static function ($code) {
    return is_string($code)
        && preg_match('/^[a-z]{2,5}$/', $code)
        && file_exists(__DIR__ . "/lang/{$code}.php");
};
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = (isset($_POST['language']) && $lang_installed($_POST['language'])) ? $_POST['language'] : 'fr';
}
if (isset($_GET['level'])) {
    $_SESSION['level'] = $_GET['level'];
}
if (isset($_POST['language']) && $lang_installed($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
}
$lang = $_SESSION['language'];
require_once __DIR__ . '/i18n.php';

require_once __DIR__ . '/conf.php';
require_once __DIR__ . '/i18n.php';
try {
    $pdo_boot = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo_boot->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    i18n_boot($pdo_boot);
    $lang = i18n_valid_lang($pdo_boot, $lang, 'fr');
    $_SESSION['language'] = $lang;
} catch (PDOException $e) {
    error_log('[index i18n boot] ' . $e->getMessage());
    $pdo_boot = null;
    // fallback: keep file_exists-validated $lang
}
i18n_use($lang); // charge la locale (aucune DB requise)

// --- Accès au site par clé (access.php) -------------------------------------------------
// Une clé valide (générée dans manage_keys.php) est exigée pour voir / lancer les modules.
// Exception : un questionnaire DÉJÀ COMMENCÉ peut être terminé même si la clé vient
// d'expirer ; seul le choix d'un nouveau module est alors bloqué (bannière + refus serveur).
// Les joueurs du Mode Jeu passent par play.php / game.php : jamais de clé pour eux.
require_once __DIR__ . '/access.php';
$access_error = access_handle_post();
// Vérification "live" (sans cache) au moment critique : choix / démarrage d'un module.
$access_valid = access_session_valid(isset($_GET['level']) || isset($_POST['start']));
if (!$access_valid && (!access_session_granted() || !isset($_SESSION['start']))) {
    access_render_gate($lang, $access_error); // affiche l'écran de saisie puis exit()
}

// Mode Jeu (style Kahoot) : actif si déjà mémorisé en session OU si la case a été cochée
// sur l'écran d'intro (POST). Cf. game.php / play.php pour la mécanique temps réel.
$game_mode = (!empty($_SESSION['game_mode']) || isset($_POST['game_mode']));

$lang = $_SESSION['language'];
?>
<?php require_once 'conf.php'; ?>
<html style="font-size: 16px;" lang="<?php echo $lang; ?>" dir="<?php echo i18n_dir($pdo_boot ?? null, $lang); ?>">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title><?php echo t('page_title_question'); ?></title>
    <link rel="stylesheet" href="nicepage.css" media="screen">
    <link rel="stylesheet" href="Question.css" media="screen">
    <style>
        .error { color: #dc3545; }
        .u-align-center {
            transition: background-color 3s ease;
        }

        .fade-to-red {
            transition: background-color 2s ease;
            background-color: #f3b0b0 !important; /* rouge pastel, doux pour les yeux */
            color: #4a1f1f !important;
        }

        .fade-to-green {
            transition: background-color 2s ease;
            background-color: #aedcb0 !important; /* vert pastel, lisible */
            color: #1f3d22 !important;
        }

        .fade-to-white {
            transition: background-color 2s ease;
            background-color: white !important;
        }

        .u-container-style.u-expanded-width.u-grey-10 {
            margin: 0;
            height: 100%;
            background-image: url('images/background.png');
            /* 100% en largeur -> les bandes colorées gardent leur ~9% de chaque côté ;
               'auto' en hauteur -> pas d'étirement vertical (les rayures gardent leurs proportions) ;
               repeat-y -> le motif se répète proprement sur les pages longues. */
            background-size: 100% auto;
            background-repeat: repeat-y;
            background-position: top center;
        }

        .u-container-layout.u-container-layout-1 {
            margin: 0;
            height: 100%;
            /* garde le contenu dans la zone grise centrale du fond */
            box-sizing: border-box;
            padding-left: 10%;
            padding-right: 10%;
        }

        /* Nicepage fixe la largeur du .u-sheet en px par palier ;
           entre deux paliers le texte débordait sur les bandes colorées */
        .u-container-layout-1 .u-sheet {
            max-width: 100% !important;
            margin-left: auto;
            margin-right: auto;
        }

        /* u-text-default rend le bloc "fit-content" et l'aligne à gauche ;
           on le recentre (titres : choix du questionnaire, titre du projet, merci) */
        .u-text-default.u-text-1 {
            margin-left: auto;
            margin-right: auto;
        }

        .u-container-layout.u-similar-container.u-container-layout-8 {
            padding: 0;
        }

        .u-container-style.u-expanded-width.u-group.u-palette-2-light-2.u-radius.u-shape-round {
            background-color: #f3ebf2;
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
            background: #f4eefb;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
            border: solid 0.5em;
            border-color: #c7aecb;
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
            background-color: #f0dde4;
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
	let texts = { "<?php echo $lang; ?>": <?php echo json_encode([
		'corrections'     => t('corrections'),
		'none'            => t('none'),
		'popup_title'     => t('popup_title'),
		'popup_prompt'    => t('popup_prompt'),
		'popup_close'     => t('popup_close'),
		'question_choise' => t('question_choise'),
		'js_question_label' => t('js_question_label'),
		'js_thanks_answer' => t('js_thanks_answer'),
		'js_correct'      => t('js_correct'),
		'js_wrong'        => t('js_wrong'),
		'js_correct_answer' => t('js_correct_answer'),
		'js_see_answer'   => t('js_see_answer'),
		'js_error_alert'  => t('js_error_alert'),
		'continue'        => t('continue'),
	], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?> };
	let lang = "<?php echo $lang; ?>";
	// --- Mode Jeu (Kahoot) ---
	var GAME_MODE = <?php echo $game_mode ? 'true' : 'false'; ?>;
	var GAME_PIN = "";
	// Palette Kahoot (couleur + symbole), identique à play.php, indexée par position de réponse.
	var GAME_PALETTE = [
		{ c: "#e21b3c", s: "▲" }, // rouge  ▲
		{ c: "#1368ce", s: "◆" }, // bleu   ◆
		{ c: "#d89e00", s: "●" }, // jaune  ●
		{ c: "#26890c", s: "■" }, // vert   ■
		{ c: "#7a1fa2", s: "★" }  // violet ★
	];
	// Colore les blocs de réponse façon Kahoot (uniquement en Mode Jeu).
	function applyGameColors() {
		if (!GAME_MODE) return;
		var blocks = document.querySelectorAll('div[id^="reponse_"]');
		Array.prototype.forEach.call(blocks, function (block, i) {
			var pal = GAME_PALETTE[i % GAME_PALETTE.length];
			// L'hôte ne choisit pas la bonne réponse : on retire le bouton de sélection.
			var choix = block.querySelector('#button_choix');
			if (choix) choix.remove();
			var qc = block.querySelector('#question_container');
			if (qc) {
				qc.classList.remove('u-palette-2-light-2');
				qc.style.backgroundColor = pal.c;
				var rep = qc.querySelector('#rep');
				if (rep) {
					rep.style.color = "#fff";
					if (!rep.getAttribute('data-symed')) {
						rep.innerHTML = '<span style="font-size:1.3em; margin-right:.4em;">' + pal.s + '</span>' + rep.innerHTML;
						rep.setAttribute('data-symed', '1');
					}
				}
			}
		});
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
	 document.getElementById("QuestionN").innerHTML = texts[lang]['js_question_label'] + response[6];
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
                    // une réponse "null" OU vide (chaîne vide/espaces) = pas de bouton
                    let isEmpty = response[i] == null || response[i] == "null" || response[i].trim() === "";
                    if (!isEmpty) {
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
                            // En Mode Jeu, l'écran hôte n'est pas cliquable : ce sont les téléphones qui répondent.
                            if (!GAME_MODE) button.addEventListener("click", function () { updateQuestion(i); });
                        }
                    } else {
                        let reponse_elem = document.getElementById("reponse_" + i);
                        if (reponse_elem != null) {
                            reponse_elem.remove();
                        }
                    }
                }
                if (GAME_MODE) { applyGameColors(); if (typeof gameAfterRender === 'function') gameAfterRender(); }
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


    // session_unset() efface la langue ET la clé d'accès : on les restaure pour les conserver.
    $access_to_preserve = array_intersect_key($_SESSION, array_flip(['access_key', 'access_checked_at', 'access_checked_ok']));
    session_unset();
    $_SESSION['language'] = $lang;
    foreach ($access_to_preserve as $ak => $av) { $_SESSION[$ak] = $av; }
    $levels = [];
    $error_message = '';
	$level_titles = [];
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        i18n_boot($pdo);
        // Revalidation : une langue désactivée retombe sur le français.
        $lang = i18n_valid_lang($pdo, $lang, 'fr');
        i18n_use($lang);
        if ($lang === 'fr') {
            $stmt = $pdo->query("SELECT DISTINCT level FROM GSDatabase ORDER BY level ASC");
            $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt_titles = $pdo->query("SELECT level, titre FROM GSDatabaseT");
            $all_titles = $stmt_titles->fetchAll(PDO::FETCH_KEY_PAIR);
        } else {
            // Modules traduits : niveaux FR possédant une traduction dans la langue courante.
            $stmt = $pdo->prepare("SELECT DISTINCT f.level FROM GSDatabase f
                                   JOIN GSDatabase_i18n i ON i.fr_id = f.id AND i.lang = ?
                                   ORDER BY f.level ASC");
            $stmt->execute([$lang]);
            $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
            // Titres : base française puis surcharge par la traduction (i18n prioritaire).
            $all_titles = $pdo->query("SELECT level, titre FROM GSDatabaseT")->fetchAll(PDO::FETCH_KEY_PAIR);
            $stmt_titles = $pdo->prepare("SELECT level, titre FROM GSDatabaseT_i18n WHERE lang = ?");
            $stmt_titles->execute([$lang]);
            foreach ($stmt_titles->fetchAll(PDO::FETCH_KEY_PAIR) as $lv => $tt) {
                if ($tt !== null && $tt !== '') { $all_titles[$lv] = $tt; }
            }
        }
    } catch (PDOException $e) {
        error_log('[index] ' . $e->getMessage());
        $error_message = "Erreur de connexion à la base de données.";
    }
?>
    <section class="u-clearfix u-valign-middle u-section-1" id="sec-level-selection">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">

                    <h2 class="u-align-center u-text u-text-default u-text-1">
                        <b><?php echo t('choose_questionnaire'); ?></b>
                    </h2>

                    <!-- Rejoindre une partie en cours (Mode Jeu) avec un PIN -->
                    <div style="max-width:420px; margin:0 auto 1.4em; padding:14px 16px; border:2px solid #8a7bf4; border-radius:16px; background:#f4eefb;">
                        <div style="font-weight:800; color:#4a3a86; margin-bottom:8px;">🎮 <?php echo t('join_game'); ?></div>
                        <form onsubmit="var p=this.pin.value.replace(/\D/g,''); if(/^\d{6}$/.test(p)){window.location.href='play.php?pin='+p;} return false;" style="display:flex; gap:8px; justify-content:center;">
                            <input name="pin" inputmode="numeric" maxlength="6" placeholder="PIN" style="flex:1; max-width:170px; padding:11px; font-size:18px; text-align:center; border:2px solid #d8cff7; border-radius:10px;">
                            <button type="submit" style="border:none; border-radius:10px; padding:0 20px; font-weight:800; color:#fff; background:#8a7bf4; cursor:pointer;">OK</button>
                        </form>
                    </div>

                    <div class="language-selector">
                        <?php foreach ((isset($pdo) && $pdo instanceof PDO) ? i18n_languages($pdo) : [] as $L): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="language" value="<?php echo htmlspecialchars($L['code']); ?>">
                            <input type="image" src="images/<?php echo htmlspecialchars($L['flag_file']); ?>" alt="<?php echo htmlspecialchars($L['label']); ?>" title="<?php echo htmlspecialchars($L['label']); ?>" class="language-flag <?php echo $lang === $L['code'] ? 'selected' : ''; ?>">
                        </form>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($error_message): ?>
                        <p class="error u-text"><?= htmlspecialchars($error_message) ?></p>
                    <?php elseif (empty($levels)): ?>
                        <p class="u-text"><?= t('no_questionnaires_available') ?></p>
                    <?php else: ?>
                        <style>
                            .module-grid {
                                display: grid;
                                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                                gap: 22px;
                                max-width: 940px;
                                margin: 2em auto;
                                padding: 0 1em 2em;
                            }
                            .module-card {
                                position: relative;
                                display: flex;
                                flex-direction: column;
                                align-items: flex-start;
                                background: #fff;
                                border-radius: 18px;
                                padding: 28px 22px 20px;
                                text-decoration: none;
                                color: #2b2b2b;
                                border: 1px solid #f0e3e6;
                                box-shadow: 0 4px 14px rgba(0,0,0,.08);
                                overflow: hidden;
                                transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
                            }
                            .module-card:hover, .module-card:focus-visible {
                                transform: translateY(-6px);
                                box-shadow: 0 12px 26px rgba(0,0,0,.16);
                                border-color: var(--accent);
                                outline: none;
                            }
                            .module-card__stripe {
                                position: absolute; top: 0; left: 0; right: 0; height: 8px;
                                background: linear-gradient(90deg,#e40303,#ff8c00,#ffed00,#008026,#004dff,#750787);
                            }
                            .module-card__badge {
                                display: inline-flex; align-items: center; justify-content: center;
                                width: 54px; height: 54px; border-radius: 50%;
                                background: var(--accent); color: #fff;
                                font-size: 26px; font-weight: 800; line-height: 1;
                                box-shadow: 0 2px 6px rgba(0,0,0,.18);
                                margin-bottom: 14px;
                            }
                            .module-card__label {
                                font-size: 12px; text-transform: uppercase; letter-spacing: .09em;
                                font-weight: 700; color: var(--accent);
                            }
                            .module-card__title {
                                font-size: 17px; font-weight: 700; line-height: 1.3;
                                margin-top: 4px; color: #2b2b2b;
                            }
                            .module-card__cta {
                                margin-top: auto; padding-top: 18px;
                                font-size: 14px; font-weight: 700; color: var(--accent);
                                display: inline-flex; align-items: center; gap: 6px;
                            }
                            .module-card__cta .arrow { transition: transform .18s ease; }
                            .module-card:hover .module-card__cta .arrow { transform: translateX(5px); }
                        </style>
                        <div class="module-grid">
                            <?php
                            // Palette d'accents façon drapeau (réutilisée en boucle si plus de modules)
                            $accents = ['#e40303','#d2660b','#0a8a3f','#1846d8','#7a1fa2','#c81d77','#0c8d8d','#b1121f'];
                            $module_word = t('module_word');
                            $i_card = 0;
                            foreach ($levels as $level):
                                $accent = $accents[$i_card % count($accents)];
                                $i_card++;
                                $title = isset($all_titles[$level]) ? $all_titles[$level] : '';
                            ?>
                            <a class="module-card" style="--accent: <?= htmlspecialchars($accent) ?>;" href="index.php?level=<?= htmlspecialchars($level) ?>" aria-label="<?= htmlspecialchars($module_word . ' ' . $level . ($title !== '' ? ' : ' . $title : '')) ?>">
                                <span class="module-card__stripe"></span>
                                <span class="module-card__badge"><?= htmlspecialchars($level) ?></span>
                                <span class="module-card__label"><?= htmlspecialchars($module_word . ' ' . $level) ?></span>
                                <?php if ($title !== ''): ?>
                                <span class="module-card__title"><?= htmlspecialchars($title) ?></span>
                                <?php endif; ?>
                                <span class="module-card__cta"><?= htmlspecialchars(t('continue')) ?> <span class="arrow">→</span></span>
                            </a>
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
        i18n_boot($pdo_desc);

        // Préparons une requête pour obtenir le texte actuel $_SESSION['level'].
        // Langue traduite : titre/text depuis GSDatabaseT_i18n, repli sur GSDatabaseT (FR).
        if ($lang === 'fr') {
            $stmt_desc = $pdo_desc->prepare("SELECT titre, text FROM GSDatabaseT WHERE level = ?");
            $stmt_desc->execute([$_SESSION['level']]);
            $level_data = $stmt_desc->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt_desc = $pdo_desc->prepare("SELECT titre, text FROM GSDatabaseT_i18n WHERE lang = ? AND level = ?");
            $stmt_desc->execute([$lang, $_SESSION['level']]);
            $level_data = $stmt_desc->fetch(PDO::FETCH_ASSOC);
            if (!$level_data) {
                $stmt_desc = $pdo_desc->prepare("SELECT titre, text FROM GSDatabaseT WHERE level = ?");
                $stmt_desc->execute([$_SESSION['level']]);
                $level_data = $stmt_desc->fetch(PDO::FETCH_ASSOC);
            }
        }

        // Si les données pour ce niveau sont trouvées, nous les utilisons
        if ($level_data) {
            $level_titre = $level_data['titre'];
            $level_text = $level_data['text'];
        } else {
            // Si le tableau ne contient aucune description pour ce niveau, utilisez le texte par défaut
            $level_text = t('project_desc');
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
                    <b><?php echo t('project_title'); ?></b>
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
                    <b><?php echo t('warning_title'); ?></b> <br><br>

                    <b><?php echo t('anonymity'); ?></b><br>
                    <b><?php echo t('voluntary'); ?></b><br>
                    <b><?php echo t('results'); ?></b><br><br>

                    <i><?php echo t('thanks'); ?></i>
                </p>

                <div class="language-selector">
                    <?php foreach ((isset($pdo_desc) && $pdo_desc instanceof PDO) ? i18n_languages($pdo_desc) : [] as $L): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="language" value="<?php echo htmlspecialchars($L['code']); ?>">
                        <input type="image" src="images/<?php echo htmlspecialchars($L['flag_file']); ?>" alt="<?php echo htmlspecialchars($L['label']); ?>" title="<?php echo htmlspecialchars($L['label']); ?>" class="language-flag <?php echo $lang === $L['code'] ? 'selected' : ''; ?>" style="width: 40px; height: 40px;">
                    </form>
                    <?php endforeach; ?>
                </div>

                <form method="POST" action="">
                    <label style="display:inline-flex; align-items:center; gap:10px; margin:0.5em auto 0; padding:0.7em 1.1em; border:2px solid #8a7bf4; border-radius:14px; background:#f4eefb; color:#4a3a86; font-weight:700; font-size:15px; cursor:pointer;">
                        <input type="checkbox" name="game_mode" value="1" style="width:20px; height:20px; accent-color:#8a7bf4;">
                        🎮 <?php echo t('launch_game_mode'); ?>
                    </label>
                    <div class="u-align-right u-form-group u-form-submit">
                        <a href="index.php?back=1"
                           style="display:inline-block; margin-top:1vh; margin-right:12px; padding:0.55em 1.3em; border-radius:50px; border:2px solid #9c5a86; color:#9c5a86; text-decoration:none; font-weight:700; font-size:14px;">
                            &larr; <?php echo t('back_to_module_selection'); ?>
                        </a>
                        <button style="margin-top:1vh;" value="1" name="start" type="submit"
                            class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-btn-submit u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                            <?php echo t('continue'); ?>
                        </button>
                        <p style="font-size:10px;"><?php echo t('footer'); ?></p>
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
        i18n_boot($conn);
    } catch (PDOException $e) {
        error_log('[index] ' . $e->getMessage()); echo "Erreur de connexion à la base de données.";
    }
    // Langue traduite : questions issues de GSDatabase (base FR) + surcouche de traduction
    // GSDatabase_i18n (COALESCE => repli FR si une chaîne manque). L'identifiant logique reste
    // TOUJOURS l'id français (GSDatabase.id) afin que GSDatabaseR / les statistiques regroupent
    // les réponses indépendamment de la langue.
    if ($lang === 'fr') {
        $stmt = $conn->prepare("SELECT * FROM GSDatabase WHERE level = ? ORDER BY `id` ASC");
        $stmt->execute([$_SESSION['level']]);
    } else {
        $stmt = $conn->prepare("SELECT f.id, f.level, f.answer, f.qtype,
                COALESCE(NULLIF(i.question, ''), f.question) AS question,
                COALESCE(NULLIF(i.rep1, ''), f.rep1) AS rep1, COALESCE(NULLIF(i.rep2, ''), f.rep2) AS rep2,
                COALESCE(NULLIF(i.rep3, ''), f.rep3) AS rep3, COALESCE(NULLIF(i.rep4, ''), f.rep4) AS rep4,
                COALESCE(NULLIF(i.rep5, ''), f.rep5) AS rep5, COALESCE(NULLIF(i.expliq, ''), f.expliq) AS expliq
            FROM GSDatabase f
            LEFT JOIN GSDatabase_i18n i ON i.fr_id = f.id AND i.lang = ?
            WHERE f.level = ? ORDER BY f.id ASC");
        $stmt->execute([$lang, $_SESSION['level']]);
    }
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
            // L'id logique est TOUJOURS l'id français (GSDatabase.id), y compris pour les
            // langues traduites (la requête ci-dessus renvoie f.id) : GSDatabaseR et les
            // statistiques regroupent ainsi les réponses toutes langues confondues.
            $logical_id = $row["id"];
            $_SESSION["IdInUse"] .= "__" . $logical_id;
            $_SESSION["answer"] .= "__" . $row["answer"];
            $_SESSION["qtype"] .= "__" . $row["qtype"];
			$_SESSION["expliqs"] .= "__" . $row["expliq"];
        }
        $ids = explode("__", $_SESSION["IdInUse"]);
        $_SESSION["TotalQuestions"] = count($ids) - 1;
        $_SESSION["start"] = 1;
        // Mode Jeu : mémorisé pour toute la durée de la partie (case cochée sur l'écran d'intro).
        $_SESSION["game_mode"] = isset($_POST["game_mode"]) ? 1 : 0;
        $_SESSION["LastQuestion"] = "1";
    } else {
        echo t('missing_level_data');
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
            echo t('question_select_error');
        }

        // --- Rappel : on récupère le texte du module et on garde la partie à partir de "Informations" ---
        $rappel_text = '';
        try {
            $pdo_rappel = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo_rappel->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            i18n_boot($pdo_rappel);
            // Langue traduite : text depuis GSDatabaseT_i18n, repli sur GSDatabaseT (FR).
            if ($lang === 'fr') {
                $stmt_rappel = $pdo_rappel->prepare("SELECT text FROM GSDatabaseT WHERE level = ?");
                $stmt_rappel->execute([$_SESSION['level']]);
                $row_rappel = $stmt_rappel->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt_rappel = $pdo_rappel->prepare("SELECT text FROM GSDatabaseT_i18n WHERE lang = ? AND level = ?");
                $stmt_rappel->execute([$lang, $_SESSION['level']]);
                $row_rappel = $stmt_rappel->fetch(PDO::FETCH_ASSOC);
                if (!$row_rappel || empty($row_rappel['text'])) {
                    $stmt_rappel = $pdo_rappel->prepare("SELECT text FROM GSDatabaseT WHERE level = ?");
                    $stmt_rappel->execute([$_SESSION['level']]);
                    $row_rappel = $stmt_rappel->fetch(PDO::FETCH_ASSOC);
                }
            }
            if ($row_rappel && !empty($row_rappel['text'])) {
                // Mot-clé de coupe du texte (sous-chaîne, stripos) : "Informations"/"Information".
                $rappel_keyword = t('reminder_info_keyword');
                $pos = stripos($row_rappel['text'], $rappel_keyword);
                if ($pos !== false) {
                    $rappel_text = substr($row_rappel['text'], $pos);
                }
            }
        } catch (PDOException $e) {
            $rappel_text = '';
        }
        ?>
<section style="height:auto;" class="u-align-center u-clearfix u-container-align-center u-palette-2-light-3 u-section-2" id="qcm">
    <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
        <div class="u-container-layout u-container-layout-1">
            <h5 id="QuestionN" class="u-align-center" style="margin-top:1vh; margin-bottom:0;">
                Question <?php echo $_SESSION["LastQuestion"]; ?>
            </h5>
            <button class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-radius u-btn-4" style="color:black; margin-top:0; background-color:#8a7bf4;" id="button_next" onclick="updateQuestion(-1)">
                <?php echo t('continue_without_answering'); ?>
            </button>
            <?php if (!empty($rappel_text)): ?>
            <button type="button" id="rappel-btn" onclick="document.getElementById('rappel-popup').style.display='block'"
                class="u-active-palette-2-light-1 u-align-center u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-radius u-btn-4"
                style="color:black; margin-top:0; margin-left:10px; background-color:#e8c06b;">
                📌 <?php echo t('reminder'); ?>
            </button>
            <?php endif; ?>
            <a href="index.php?back=1" id="quit-to-modules"
               onclick='return confirm(<?php echo htmlspecialchars(json_encode(t("leave_confirm")), ENT_QUOTES); ?>)'
               style="display:inline-block; margin-top:0; margin-left:10px; padding:0.5em 1em; border-radius:50px; border:2px solid #b5564a; color:#b5564a; text-decoration:none; font-weight:700; font-size:14px;">
                &larr; <?php echo t('leave_change_module'); ?>
            </a>
            <b>
                <p id="Question" class="u-align-center" style="margin-top:1vh; margin-bottom:0;width:100%; padding:1em; background:linear-gradient(135deg,#e9d9f2 0%,#dcd4f3 50%,#cfe3f2 100%); border-left:6px solid #8a7bf4;">
                    <?php echo $currentQuestion; ?>
                </p>
            </b>
            <div style="flex-direction: row; display: flex; justify-content: space-between; margin-top:1em; gap: 10px; width: 80%; margin: auto;" id="quest_list"></div>
        </div>
    </div>
    <div class="u-align-right u-form-group u-form-submit">
        <img id="randomImage" src="" width="200em" alt="">
    </div>
    <?php if (!empty($rappel_text)): ?>
    <!-- Popup "Rappel" : fenêtre flottante déplaçable (sans assombrir le fond), comme le popup de réponse -->
    <div id="rappel-popup"
        style="display:none; position:fixed; top:90px; left:50%; transform:translateX(-50%); background:#f4eefb; border-radius:10px; width:560px; max-width:92%; border:solid 0.3em #c7aecb; box-shadow:0 6px 24px rgba(0,0,0,0.25); z-index:10001;">
        <div id="rappel-popup-header"
            style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; cursor:move; background:#ffe9a8; border-radius:7px 7px 0 0; user-select:none;">
            <span style="font-weight:700;">📌 <?php echo t('reminder'); ?></span>
            <span onclick="document.getElementById('rappel-popup').style.display='none'"
                style="cursor:pointer; font-size:18px; line-height:1; padding:0 4px; color:#555;">&#10005;</span>
        </div>
        <div style="padding:16px; text-align:left; max-height:70vh; overflow:auto;">
            <?php echo $rappel_text; ?>
            <div style="text-align:center;">
                <button type="button" onclick="document.getElementById('rappel-popup').style.display='none'"
                    style="margin-top:16px; background:#8a7bf4; color:#fff; border:none; padding:8px 18px; border-radius:20px; cursor:pointer; font-size:14px;">
                    <?php echo t('reminder_close'); ?>
                </button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var box = document.getElementById('rappel-popup');
        var handle = document.getElementById('rappel-popup-header');
        if (!box || !handle) return;
        var dragging = false, offX = 0, offY = 0;
        function start(x, y) {
            var r = box.getBoundingClientRect();
            box.style.left = r.left + 'px';
            box.style.top = r.top + 'px';
            box.style.transform = 'none';
            offX = x - r.left; offY = y - r.top; dragging = true;
        }
        function move(x, y) {
            if (!dragging) return;
            box.style.left = (x - offX) + 'px';
            box.style.top = (y - offY) + 'px';
        }
        handle.addEventListener('mousedown', function (e) { start(e.clientX, e.clientY); e.preventDefault(); });
        document.addEventListener('mousemove', function (e) { move(e.clientX, e.clientY); });
        document.addEventListener('mouseup', function () { dragging = false; });
        handle.addEventListener('touchstart', function (e) { var t = e.touches[0]; start(t.clientX, t.clientY); });
        document.addEventListener('touchmove', function (e) { if (dragging) { var t = e.touches[0]; move(t.clientX, t.clientY); e.preventDefault(); } }, { passive: false });
        document.addEventListener('touchend', function () { dragging = false; });
    })();
    </script>
    <?php endif; ?>
</section>

<?php if ($game_mode): ?>
<?php // Libellés localisés pour l'UI du Mode Jeu (hôte) : servis par t() / le catalogue i18n. ?>
<script src="js/qrcode.min.js"></script>
<style>
    .kh-overlay { position: fixed; inset: 0; z-index: 9000; display: flex;
        align-items: center; justify-content: center; padding: 18px;
        background: linear-gradient(160deg,#6a5cf0 0%,#8a7bf4 45%,#b06fd8 100%); color:#fff; }
    .kh-overlay.kh-hidden { display: none; }
    .kh-card { background:#fff; color:#2b2b2b; border-radius:20px; padding:26px 24px;
        max-width:760px; width:100%; text-align:center; box-shadow:0 14px 40px rgba(0,0,0,.3); }
    .kh-pin { font-size:46px; font-weight:900; letter-spacing:6px; color:#6a5cf0; margin:6px 0; }
    .kh-pin-label { text-transform:uppercase; letter-spacing:.12em; font-size:13px; font-weight:800; color:#8a7bf4; }
    #kh-qr { display:inline-block; margin:14px auto; padding:10px; background:#fff; border-radius:12px; }
    .kh-url { font-size:13px; color:#666; word-break:break-all; }
    .kh-players { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin:16px 0; }
    .kh-chip { background:#f0ecfb; color:#5a3a86; font-weight:700; padding:7px 14px; border-radius:30px; font-size:15px; }
    .kh-chip.ans { background:#cdeccd; color:#1c6b1c; }
    .kh-btn { border:none; border-radius:40px; padding:14px 30px; font-size:18px; font-weight:800;
        color:#fff; background:#5cb37a; cursor:pointer; }
    .kh-btn:active { transform:translateY(1px); }
    .kh-btn.alt { background:#6a5cf0; }
    .kh-btn.danger { background:#d23; }
    .kh-btn.ghost { background:transparent; color:#d23; border:2px solid #d23; padding:11px 22px; }
    .kh-bar { position:fixed; left:0; right:0; bottom:0; z-index:8000; display:none;
        align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
        padding:12px 18px; background:#2b2b3a; color:#fff; box-shadow:0 -4px 14px rgba(0,0,0,.2); }
    .kh-bar.show { display:flex; }
    .kh-bar .info { font-size:18px; font-weight:800; }
    /* Panneau "bonnes réponses" côté hôte (visible seulement pour l'hôte). */
    #kh-correct-panel { position:fixed; left:12px; top:120px; z-index:8000; display:none;
        width:230px; max-height:70vh; overflow:auto; padding:14px 16px;
        background:rgba(43,43,58,.92); color:#fff; border-radius:16px;
        box-shadow:0 10px 30px rgba(0,0,0,.35); font-weight:700; }
    #kh-correct-panel.show { display:block; }
    #kh-correct-panel .kh-cp-title { font-size:15px; letter-spacing:.04em; text-transform:uppercase;
        color:#a9e5bd; margin-bottom:4px; }
    #kh-correct-panel .kh-cp-count { font-size:26px; font-weight:900; margin-bottom:10px; }
    #kh-correct-toggle { border:none; border-radius:30px; padding:7px 14px; font-size:13px;
        font-weight:800; color:#fff; background:#6a5cf0; cursor:pointer; margin-bottom:10px; }
    #kh-correct-list { list-style:none; margin:0; padding:0; }
    #kh-correct-list li { background:#cdeccd; color:#1c6b1c; border-radius:20px;
        padding:5px 12px; margin:5px 0; font-size:15px; word-break:break-word; }
    .kh-lead-row { display:flex; justify-content:space-between; align-items:center;
        padding:12px 18px; border-radius:12px; margin-top:10px; font-weight:800; font-size:18px;
        background:#f0ecfb; color:#3a2a66; }
    .kh-lead-row.top { background:linear-gradient(90deg,#ffd54a,#ffb300); color:#5a3a00; }
    .kh-correct-host {
        position:relative; z-index:2; border-radius:14px;
        outline:6px solid #26ff7a; outline-offset:4px;
        box-shadow:0 0 0 4px #0a8a3f, 0 0 26px 8px rgba(38,255,122,.85);
        animation:khPulse 1s ease-in-out infinite;
    }
    .kh-correct-host::after {
        content:"✓"; position:absolute; top:-16px; right:-16px; z-index:3;
        width:40px; height:40px; border-radius:50%; background:#0a8a3f; color:#fff;
        font-size:24px; font-weight:900; line-height:40px; text-align:center;
        box-shadow:0 2px 8px rgba(0,0,0,.35); animation:khPop .35s ease-out;
    }
    @keyframes khPulse {
        0%,100% { box-shadow:0 0 0 4px #0a8a3f, 0 0 20px 6px rgba(38,255,122,.65); transform:scale(1); }
        50%     { box-shadow:0 0 0 5px #0a8a3f, 0 0 34px 12px rgba(38,255,122,1);  transform:scale(1.04); }
    }
    @keyframes khPop { 0% { transform:scale(0); } 70% { transform:scale(1.25); } 100% { transform:scale(1); } }
    .kh-dim { opacity:.35; filter:grayscale(.4); transition:opacity .3s ease, filter .3s ease; }
</style>

<!-- Lobby hôte -->
<div id="kh-lobby" class="kh-overlay kh-hidden">
    <div class="kh-card">
        <h2 style="margin:0 0 4px;">🎮 <?php echo t('game_mode_title'); ?></h2>
        <div class="kh-pin-label"><?php echo t('join_with_pin'); ?></div>
        <div class="kh-pin" id="kh-pin">······</div>
        <div id="kh-qr"></div>
        <div class="kh-url" id="kh-url"></div>
        <div class="kh-players" id="kh-players"></div>
        <p style="font-weight:700;"><span id="kh-count">0</span> <?php echo t('players_count'); ?></p>
        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:4px;">
            <button class="kh-btn" id="kh-start"><?php echo t('start_game'); ?> →</button>
            <button class="kh-btn ghost kh-cancel" type="button"><?php echo t('cancel_game'); ?></button>
        </div>
        <div id="kh-lobby-err" style="color:#d23; margin-top:10px; min-height:18px;"></div>
    </div>
</div>

<!-- Panneau "bonnes réponses" (hôte uniquement) : compteur + noms en direct -->
<div id="kh-correct-panel">
    <div class="kh-cp-title">Bonnes réponses</div>
    <div class="kh-cp-count"><span id="kh-correct-count">0</span> / <span id="kh-correct-total">0</span></div>
    <button type="button" id="kh-correct-toggle">Masquer la liste</button>
    <ul id="kh-correct-list"></ul>
</div>

<!-- Barre de contrôle hôte (pendant les questions) -->
<div id="kh-bar" class="kh-bar">
    <span class="info"><span id="kh-answered">0</span> / <span id="kh-total">0</span> <?php echo t('answered_count'); ?></span>
    <span style="display:flex; gap:10px;">
        <button class="kh-btn danger kh-cancel" type="button"><?php echo t('cancel'); ?></button>
        <button class="kh-btn alt" id="kh-action"><?php echo t('reveal_answers'); ?></button>
    </span>
</div>

<!-- Classement final -->
<div id="kh-leader" class="kh-overlay kh-hidden">
    <div class="kh-card">
        <h2 style="margin:0 0 12px;">🏆 <?php echo t('final_leaderboard'); ?></h2>
        <div id="kh-leader-list"></div>
        <button class="kh-btn" style="margin-top:18px;" onclick="window.location.href='index.php?back=1'"><?php echo t('finish'); ?></button>
    </div>
</div>

<script>
(function () {
    "use strict";
    var KH = {
        reveal: "<?php echo t('reveal_answers'); ?>",
        next:   "<?php echo t('next_question'); ?> →",
        finish: "<?php echo t('show_leaderboard'); ?> →",
        err:    "<?php echo t('game_error'); ?>",
        pts:    "<?php echo t('pts'); ?>"
    };
    var lobbyTimer = null, ansTimer = null, phase = "question", revealed = false;

    function el(id) { return document.getElementById(id); }
    function gameApi(params) {
        var body = Object.keys(params).map(function (k) {
            return encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
        }).join("&");
        return fetch("game.php", { method:"POST",
            headers:{ "Content-Type":"application/x-www-form-urlencoded" }, body: body
        }).then(function (r) { return r.json(); });
    }
    function playUrl(pin) {
        var base = location.href.split("?")[0].replace(/[^\/]*$/, "");
        return base + "play.php?pin=" + pin;
    }

    // --- Lobby ---
    window.initHostGame = function () {
        // Masque l'écran de question tant qu'on est dans le lobby.
        var qcm = document.getElementById("qcm");
        if (qcm) qcm.style.display = "none";
        var bn = document.getElementById("button_next"); if (bn) bn.style.display = "none";
        var quit = document.getElementById("quit-to-modules"); if (quit) quit.style.display = "none";
        el("kh-lobby").classList.remove("kh-hidden");
        gameApi({ action: "create" }).then(function (res) {
            if (!res.ok) { el("kh-lobby-err").textContent = KH.err; return; }
            GAME_PIN = res.pin;
            el("kh-pin").textContent = res.pin;
            var url = playUrl(res.pin);
            el("kh-url").textContent = url;
            el("kh-qr").innerHTML = "";
            try { new QRCode(el("kh-qr"), { text: url, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M }); } catch (e) {}
            lobbyTimer = setInterval(lobbyPoll, 1500);
            lobbyPoll();
        });
    };
    function lobbyPoll() {
        gameApi({ action: "state", pin: GAME_PIN }).then(function (res) {
            if (!res.ok) return;
            el("kh-count").textContent = res.count;
            var box = el("kh-players"); box.innerHTML = "";
            res.players.forEach(function (p) {
                var c = document.createElement("span"); c.className = "kh-chip"; c.textContent = p.name;
                box.appendChild(c);
            });
        }).catch(function () {});
    }
    el("kh-start").addEventListener("click", function () {
        if (!GAME_PIN) { return; } // la partie n'est pas encore créée (PIN en attente)
        if (lobbyTimer) { clearInterval(lobbyTimer); lobbyTimer = null; }
        el("kh-lobby").classList.add("kh-hidden");
        var qcm = document.getElementById("qcm");
        if (qcm) qcm.style.display = "";
        el("kh-bar").classList.add("show");
        startQuestion(); // rend la 1re question ; gameAfterRender() est appelé ensuite
    });

    // Retire la fenêtre d'info (bonne réponse) si elle est encore ouverte.
    function khRemovePopup() {
        var p = document.getElementById("answer-info-popup"); if (p) p.remove();
        var r = document.getElementById("answer-reopen-btn"); if (r) r.remove();
    }

    // --- Après le rendu de chaque question ---
    window.gameAfterRender = function () {
        revealed = false; phase = "question";
        khRemovePopup();
        el("kh-action").textContent = KH.reveal;
        el("kh-action").disabled = false;
        clearHostHighlight();
        khResetCorrectPanel();
        khShowCorrectPanel();
        gameApi({ action: "setq", pin: GAME_PIN }).then(function (res) {
            if (res.ok && res.question) { el("kh-total").textContent = res.count; }
        });
        if (ansTimer) clearInterval(ansTimer);
        ansTimer = setInterval(pollAnswered, 1500);
        pollAnswered();
    };
    function pollAnswered() {
        gameApi({ action: "state", pin: GAME_PIN }).then(function (res) {
            if (!res.ok) return;
            el("kh-answered").textContent = res.answeredCount;
            el("kh-total").textContent = res.count;
            khUpdateCorrectPanel(res);
        }).catch(function () {});
    }

    // --- Panneau "bonnes réponses" (hôte) : compteur + noms, mis à jour en direct ---
    // Les clés correctCount / correctPlayers ne sont renvoyées qu'à l'hôte par game.php.
    function khUpdateCorrectPanel(res) {
        if (typeof res.correctCount === "undefined") return; // pas hôte / pas de données
        el("kh-correct-count").textContent = res.correctCount;
        el("kh-correct-total").textContent = res.count;
        var list = el("kh-correct-list");
        list.innerHTML = "";
        var names = res.correctPlayers || [];
        for (var i = 0; i < names.length; i++) {
            var li = document.createElement("li");
            li.textContent = names[i];
            list.appendChild(li);
        }
    }
    function khShowCorrectPanel() { el("kh-correct-panel").classList.add("show"); }
    function khHideCorrectPanel() { el("kh-correct-panel").classList.remove("show"); }
    function khResetCorrectPanel() {
        el("kh-correct-count").textContent = "0";
        el("kh-correct-list").innerHTML = "";
    }
    // Bascule : masque/affiche UNIQUEMENT la liste des noms, le compteur reste visible.
    el("kh-correct-toggle").addEventListener("click", function () {
        var list = el("kh-correct-list");
        if (list.style.display === "none") {
            list.style.display = "";
            this.textContent = "Masquer la liste";
        } else {
            list.style.display = "none";
            this.textContent = "Afficher la liste";
        }
    });

    function clearHostHighlight() {
        var blocks = document.querySelectorAll('div[id^="reponse_"]');
        Array.prototype.forEach.call(blocks, function (b) { b.classList.remove("kh-correct-host"); b.classList.remove("kh-dim"); });
    }
    function highlightCorrectHost(ci) {
        clearHostHighlight();
        var blocks = document.querySelectorAll('div[id^="reponse_"]');
        var elc = document.getElementById("reponse_" + ci);
        // On atténue les autres réponses pour faire ressortir la bonne.
        Array.prototype.forEach.call(blocks, function (b) { if (b !== elc) b.classList.add("kh-dim"); });
        if (elc) elc.classList.add("kh-correct-host");
    }

    // --- Bouton d'action de la barre : Révéler → Suivant ---
    el("kh-action").addEventListener("click", function () {
        if (phase === "question") {
            // Révéler : on fige les réponses, on montre la bonne sur l'écran et sur les téléphones.
            el("kh-action").disabled = true;
            gameApi({ action: "reveal", pin: GAME_PIN }).then(function (res) {
                if (res.ok && res.correctIndex) highlightCorrectHost(res.correctIndex);
                if (res.ok) khUpdateCorrectPanel(res); // fige le compteur/liste final au reveal
                // Même fenêtre d'info que le mode normal : bonne réponse + explication.
                if (res.ok && typeof showAnswerPopup === "function") {
                    showAnswerPopup(res.correctText || "", res.expliq || "", true, false);
                }
                revealed = true; phase = "reveal";
                el("kh-action").textContent = KH.next;
                el("kh-action").disabled = false;
                if (ansTimer) { clearInterval(ansTimer); ansTimer = null; }
            }).catch(function () { el("kh-action").disabled = false; }); // ne pas bloquer en cas d'échec réseau
        } else {
            // Question suivante (ou fin) : updateQuestion(-1) enchaîne via le flux existant.
            el("kh-action").disabled = true;
            updateQuestion(-1);
        }
    });

    // --- Annuler la partie (lobby ou en cours) : supprime la partie et déconnecte les joueurs ---
    function khAbort() {
        var msg = <?php echo json_encode(t('cancel_game_confirm')); ?>;
        if (!window.confirm(msg)) return;
        if (lobbyTimer) { clearInterval(lobbyTimer); lobbyTimer = null; }
        if (ansTimer) { clearInterval(ansTimer); ansTimer = null; }
        var done = function () { window.location.href = "index.php?back=1"; };
        gameApi({ action: "abort", pin: GAME_PIN }).then(done).catch(done);
    }
    Array.prototype.forEach.call(document.querySelectorAll(".kh-cancel"), function (b) {
        b.addEventListener("click", khAbort);
    });

    // --- Fin de partie : classement ---
    window.gameEnd = function () {
        if (ansTimer) { clearInterval(ansTimer); ansTimer = null; }
        khRemovePopup();
        khHideCorrectPanel();
        el("kh-bar").classList.remove("show");
        gameApi({ action: "end", pin: GAME_PIN }).then(function (res) {
            renderLeaderboard(res.ok ? res.players : []);
            el("kh-leader").classList.remove("kh-hidden");
        });
    };
    function renderLeaderboard(players) {
        var list = el("kh-leader-list"); list.innerHTML = "";
        if (!players.length) {
            list.innerHTML = "<p><?php echo t('no_players'); ?></p>"; return;
        }
        players.forEach(function (p, i) {
            var row = document.createElement("div");
            row.className = "kh-lead-row" + (i === 0 ? " top" : "");
            var medal = i === 0 ? "🥇 " : (i === 1 ? "🥈 " : (i === 2 ? "🥉 " : (i + 1) + ". "));
            row.innerHTML = "<span>" + medal + p.name + "</span><span>" + p.score + " " + KH.pts + "</span>";
            list.appendChild(row);
        });
    }
})();
</script>
<?php endif; ?>

        <?php
        if (!isset($_SESSION["finish"])) {
            if ($game_mode) {
                // Mode Jeu : on n'enchaîne pas tout de suite — on affiche le lobby (PIN + QR).
                echo '<script type="text/javascript">window.addEventListener("load", function(){ if (typeof initHostGame === "function") initHostGame(); });</script>';
            } else {
                echo '<script type="text/javascript">startQuestion();</script>';
            }
        }
    } else if (((isset($_SESSION["LastQuestion"]) ? $_SESSION["LastQuestion"] : 0) >= (isset($_SESSION["TotalQuestions"]) ? $_SESSION["TotalQuestions"] : 1)) && !((isset($_POST["acc"]) && isset($_POST["consent_rgpd"])) || isset($_SESSION["acc"]))) { ?>
        <section class="u-clearfix u-valign-middle u-section-1" id="sec-089e">
            <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
                <div class="u-container-layout u-container-layout-1">
                    <div class="u-clearfix u-sheet u-sheet-1">
                        <p style="margin:0;" class="u-text u-text-default u-text-1">
                            <i><b><?php echo t('final_warning'); ?></b><br>
                            <?php echo t('final_warning_desc'); ?></i>
                        </p><br><br>
                        <form method="POST" class="u-clearfix u-form-spacing-32 u-inner-form" style="padding: 10px;">
                            <div class="u-form-group u-form-name u-form-partition-factor-2">
                                <h3 style="margin:0;"><?php echo t('gender_question'); ?></h3><br>
                                <div style="display: flex; align-items: center; gap:10px;">
                                    <p style="margin:0;"><?php echo t('gender_prompt'); ?></p>
                                    <select style="margin:0; padding-left:0;" id="name-bb9b" name="genre" class="u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-btn-2" required>
                                        <!-- RGPD : pas de valeur présélectionnée pour une donnée sensible ; le choix doit être actif -->
                                        <option value="" selected disabled><?php echo t('choose_dash'); ?></option>
                                        <option value="1"><?php echo t('gender_opt_1'); ?></option>
                                        <option value="2"><?php echo t('gender_opt_2'); ?></option>
                                        <option value="3"><?php echo t('gender_opt_3'); ?></option>
                                        <option value="4"><?php echo t('gender_opt_4'); ?></option>
                                        <option value="5"><?php echo t('gender_opt_5'); ?></option>
                                        <option value="6"><?php echo t('gender_opt_6'); ?></option>
                                        <option value="7"><?php echo t('gender_opt_7'); ?></option>
                                        <option value="8"><?php echo t('gender_opt_8'); ?></option>
                                    </select>
                                </div>
                            </div><br><br>
                            <div class="u-form-email u-form-group u-form-partition-factor-2">
                                <h3 style="margin:0;"><?php echo t('sexuality_question'); ?></h3><br>
                                <div style="display: flex; align-items: center; gap:10px;">
                                    <p style="margin:0;"><?php echo t('sexuality_prompt'); ?></p>
                                    <select style="margin:0; padding-left:0;" id="email-bb9b" name="orient" class="u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-btn-2" required>
                                        <!-- RGPD : pas de valeur présélectionnée pour une donnée sensible ; le choix doit être actif -->
                                        <option value="" selected disabled><?php echo t('choose_dash'); ?></option>
                                        <option value="1"><?php echo t('sexuality_opt_1'); ?></option>
                                        <option value="2"><?php echo t('sexuality_opt_2'); ?></option>
                                        <option value="3"><?php echo t('sexuality_opt_3'); ?></option>
                                        <option value="4"><?php echo t('sexuality_opt_4'); ?></option>
                                        <option value="5"><?php echo t('sexuality_opt_5'); ?></option>
                                        <option value="6"><?php echo t('sexuality_opt_6'); ?></option>
                                        <option value="7"><?php echo t('sexuality_opt_7'); ?></option>
                                        <option value="8"><?php echo t('sexuality_opt_8'); ?></option>
                                    </select>
                                </div>
                            </div><br><br>
                            <div class="u-form-email u-form-group u-form-partition-factor-2">
                                <label><?php echo t('email_prompt'); ?></label>
                                <input name="e_mm" class="u-radius-50 u-text-hover-white">
                            </div><br>
                            <!-- Consentement explicite (art. 9 RGPD) : obligatoire pour enregistrer les réponses -->
                            <div class="u-form-group" style="text-align:left;">
                                <label style="font-weight:normal; font-size:14px; display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                                    <input type="checkbox" name="consent_rgpd" value="1" required style="margin-top:4px; width:auto; flex-shrink:0;">
                                    <span>
                                        <?php echo t('consent_intro'); ?><a href="mentions.php" target="_blank" rel="noopener"><?php echo t('consent_privacy_link'); ?></a>.
                                    </span>
                                </label>
                            </div>
                            <div class="u-align-right u-form-group u-form-submit">
                                <button type="submit" name="acc" class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-btn-submit u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                                    <?php echo t('submit'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    <?php } else if (((isset($_SESSION["LastQuestion"]) ? $_SESSION["LastQuestion"] : 0) >= (isset($_SESSION["TotalQuestions"]) ? $_SESSION["TotalQuestions"] : 1)) && ((isset($_POST["acc"]) && isset($_POST["consent_rgpd"])) || isset($_SESSION["acc"]))) {
        $_SESSION["acc"] = "1";
        $_SESSION["genre"] = isset($_POST['genre']) ? htmlspecialchars($_POST['genre'], ENT_QUOTES, 'UTF-8') : '';
        $_SESSION["orient"] = isset($_POST['orient']) ? htmlspecialchars($_POST['orient'], ENT_QUOTES, 'UTF-8') : '';
        $_SESSION["emailr"] = isset($_POST['e_mm']) ? htmlspecialchars($_POST['e_mm'], ENT_QUOTES, 'UTF-8') : '';

if (isset($_SESSION["id_user"]) && isset($_SESSION["genre"])) {
    try {
        $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        i18n_boot($conn);
    } catch (PDOException $e) {
        error_log('[index] ' . $e->getMessage()); echo "Erreur de connexion à la base de données.";
    }

    // Minimisation RGPD : l'e-mail n'est jamais enregistré en base — l'envoi des résultats
    // se fait depuis la session ($_SESSION["emailr"]). repmail est vidé (l'INSERT initial y met 'null').
    $query = "UPDATE GSDatabaseR SET genre = :genre, orientation = :orientation, repmail = '', lang = :lang WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'genre' => $_SESSION["genre"],
        'orientation' => $_SESSION["orient"],
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
                <p class="u-text u-text-default u-text-1" style="margin: auto;"><?php echo t('thank_you'); ?></p>

                <?php ob_start(); // on capture légende + tableau + score pour les réutiliser dans l'e-mail ?>
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color-box user-answer-color"></div>
                        <span><?php echo t('your_answer'); ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color-box correct-answer-color"></div>
                        <span><?php echo t('correct_answer'); ?></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color-box user-correct-answer-color"></div>
                        <span><?php echo t('your_correct_answer'); ?></span>
                    </div>
                </div>

<table class="results-table">
    <thead>
        <tr>
            <th class="question-column"><?php echo t('col_question'); ?></th>
            <th colspan="5"><?php echo t('col_answers'); ?></th>
			<th class="expliq-column"><?php echo t('col_explanation'); ?></th>
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
                    echo t('score_summary', ['correct' => $correct_answers_count, 'total' => $total_questions_in_summary]);
                    ?>
                </p>
                <?php
                // --- Résultats capturés : on les affiche sur la page ET on les envoie par e-mail ---
                $results_capture = ob_get_clean();
                echo $results_capture;

                // Envoi unique, seulement si une adresse valide a été saisie
                if (empty($_SESSION["mail_sent"]) && !empty($_SESSION["emailr"]) && filter_var($_SESSION["emailr"], FILTER_VALIDATE_EMAIL)) {
                    $email_styles = '<style>'
                        . 'table{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;}'
                        . 'th,td{padding:8px 10px;border:1px solid #ddd;text-align:left;}'
                        . 'th{background:#4f6d7a;color:#fff;}'
                        . '.user-answer{background:#a0c4ff;}'
                        . '.correct-answer{background:#90ee90;}'
                        . '.user-answer.correct-answer{background:#bfe6a0;}'
                        . '.score-display{font-size:1.1em;font-weight:bold;margin-top:1em;}'
                        . '.legend-item{display:inline-block;margin-right:16px;}'
                        . '.legend-color-box{display:inline-block;width:14px;height:14px;border:1px solid #ccc;vertical-align:middle;margin-right:5px;}'
                        . '.user-answer-color{background:#a0c4ff;}.correct-answer-color{background:#90ee90;}.user-correct-answer-color{background:#bfe6a0;}'
                        . '</style>';
                    $greeting = t('email_greeting');
                    $email_body = '<html><head><meta charset="UTF-8">' . $email_styles . '</head><body>'
                        . $greeting . $results_capture . '</body></html>';
                    $email_subject = t('email_subject');
                    require_once __DIR__ . '/mailer.php';
                    error_log('[mail] Bloc envoi atteint pour ' . $_SESSION["emailr"]);
                    if (send_results_email($_SESSION["emailr"], $email_subject, $email_body)) {
                        $_SESSION["mail_sent"] = 1;
                        error_log('[mail] Resultat : ENVOYE');
                    } else {
                        error_log('[mail] Resultat : ECHEC (voir lignes [mail] ci-dessus)');
                    }
                } else {
                    // Pourquoi le bloc d'envoi n'est meme pas entre
                    error_log('[mail] Bloc envoi IGNORE : '
                        . (!empty($_SESSION["mail_sent"]) ? 'deja envoye (mail_sent=1) ' : '')
                        . (empty($_SESSION["emailr"]) ? 'emailr vide ' : '')
                        . (!empty($_SESSION["emailr"]) && !filter_var($_SESSION["emailr"], FILTER_VALIDATE_EMAIL) ? "emailr invalide ('".$_SESSION["emailr"]."') " : ''));
                }
                ?>

                <form method="POST" action="index.php" style="margin-top: 1em;">
                    <button type="submit" name="reset_session" class="u-active-palette-2-light-1 u-border-none u-btn u-btn-round u-button-style u-hover-palette-2-light-1 u-palette-2-light-2 u-radius-50 u-text-active-white u-text-hover-white u-text-palette-2-dark-2 u-btn-1">
                        <?php echo t('return_to_start'); ?>
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
            fetch('pages/footer.php?lang=<?php echo $lang; ?>')
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

		// Popup flottant (sans assombrir le fond), déplaçable, refermable et ré-ouvrable.
		function showAnswerPopup(correctText, explanation, isCorrect, isNeutral) {
			let oldBox = document.getElementById('answer-info-popup');
			if (oldBox) oldBox.remove();
			let oldReopen = document.getElementById('answer-reopen-btn');
			if (oldReopen) oldReopen.remove();

			const status = isNeutral
				? texts[lang]['js_thanks_answer']
				: (isCorrect
					? texts[lang]['js_correct']
					: texts[lang]['js_wrong']);
			const goodLabel = texts[lang]['js_correct_answer'];
			const reopenLabel = texts[lang]['js_see_answer'];
			// échelle : pas de bonne/mauvaise réponse, on n'affiche pas le texte "bonne réponse"
			if (isNeutral) correctText = '';

			const box = document.createElement('div');
			box.id = 'answer-info-popup';
			box.style.cssText = 'position:fixed;top:90px;left:50%;transform:translateX(-50%);background:#f4eefb;border-radius:10px;width:420px;max-width:92%;border:solid 0.3em #c7aecb;box-shadow:0 6px 24px rgba(0,0,0,0.25);z-index:10000;';

			const header = document.createElement('div');
			header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:10px 14px;cursor:move;background:' + (isNeutral ? '#e9d9f2' : (isCorrect ? '#d6f5d6' : '#f7d6d6')) + ';border-radius:7px 7px 0 0;user-select:none;';
			const title = document.createElement('span');
			title.style.cssText = 'font-weight:700;color:' + (isNeutral ? '#5b4a8a' : (isCorrect ? '#2e7d32' : '#b5564a')) + ';';
			title.textContent = status;
			const closeX = document.createElement('span');
			closeX.textContent = '✕';
			closeX.style.cssText = 'cursor:pointer;font-size:18px;line-height:1;padding:0 4px;color:#555;';
			header.appendChild(title);
			header.appendChild(closeX);
			box.appendChild(header);

			const body = document.createElement('div');
			body.style.cssText = 'padding:16px;text-align:center;';
			if (correctText && correctText.trim() !== '') {
				const p1 = document.createElement('p');
				p1.style.margin = '8px 0';
				const b = document.createElement('b');
				b.textContent = goodLabel + ' : ';
				p1.appendChild(b);
				p1.appendChild(document.createTextNode(correctText));
				body.appendChild(p1);
			}
			if (explanation && explanation.trim() !== '' && explanation !== 'null') {
				const p2 = document.createElement('p');
				p2.style.cssText = 'margin:8px 0;font-style:italic;color:#444;';
				p2.textContent = explanation;
				body.appendChild(p2);
			}
			box.appendChild(body);
			document.body.appendChild(box);

			const reopen = document.createElement('button');
			reopen.id = 'answer-reopen-btn';
			reopen.type = 'button';
			reopen.textContent = reopenLabel;
			reopen.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:10000;background:#8a7bf4;color:#fff;border:none;padding:10px 16px;border-radius:20px;cursor:pointer;font-size:14px;box-shadow:0 3px 10px rgba(0,0,0,0.25);display:none;';
			document.body.appendChild(reopen);

			closeX.addEventListener('click', function () {
				box.style.display = 'none';
				reopen.style.display = 'block';
			});
			reopen.addEventListener('click', function () {
				box.style.display = 'block';
				reopen.style.display = 'none';
			});

			let dragging = false, offX = 0, offY = 0;
			function startDrag(clientX, clientY) {
				const rect = box.getBoundingClientRect();
				box.style.left = rect.left + 'px';
				box.style.top = rect.top + 'px';
				box.style.transform = 'none';
				offX = clientX - rect.left;
				offY = clientY - rect.top;
				dragging = true;
			}
			function moveDrag(clientX, clientY) {
				if (!dragging) return;
				box.style.left = (clientX - offX) + 'px';
				box.style.top = (clientY - offY) + 'px';
			}
			header.addEventListener('mousedown', function (e) { startDrag(e.clientX, e.clientY); e.preventDefault(); });
			document.addEventListener('mousemove', function (e) { moveDrag(e.clientX, e.clientY); });
			document.addEventListener('mouseup', function () { dragging = false; });
			header.addEventListener('touchstart', function (e) { const t = e.touches[0]; startDrag(t.clientX, t.clientY); });
			document.addEventListener('touchmove', function (e) { if (dragging) { const t = e.touches[0]; moveDrag(t.clientX, t.clientY); e.preventDefault(); } }, { passive: false });
			document.addEventListener('touchend', function () { dragging = false; });
		}

		// Bouton "Continuer" : on n'avance plus automatiquement, l'utilisateur clique pour la suite.
		function showContinueButton(onContinue) {
			let old = document.getElementById('continue-next-btn');
			if (old) old.remove();
			let skip = document.getElementById('button_next');
			if (skip) skip.style.display = 'none';
			const btn = document.createElement('button');
			btn.id = 'continue-next-btn';
			btn.type = 'button';
			btn.textContent = texts[lang]['continue'] + ' →';
			btn.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:10001;background:#5cb37a;color:#fff;border:none;padding:12px 28px;border-radius:24px;cursor:pointer;font-size:16px;font-weight:700;box-shadow:0 4px 14px rgba(0,0,0,0.3);';
			btn.addEventListener('click', function () {
				btn.remove();
				let p = document.getElementById('answer-info-popup'); if (p) p.remove();
				let r = document.getElementById('answer-reopen-btn'); if (r) r.remove();
				if (skip) skip.style.display = '';
				if (typeof onContinue === 'function') onContinue();
			});
			document.body.appendChild(btn);
		}

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
							// Mode Jeu : pas de page finale solo → on clôt la partie et on affiche le classement.
							if (GAME_MODE) { if (typeof gameEnd === 'function') gameEnd(); return; }
							// Tout d'abord, supprimons toutes les classes précédentes pour plus de clarté
							answersarray.forEach(function (item, index) {
								const innerAnswers = item.querySelector('div#question_container');
								if (innerAnswers != null) {
									innerAnswers.classList.remove('fade-to-green');
									innerAnswers.classList.remove('fade-to-red');
									innerAnswers.classList.remove('fade-to-white');
								}
							});
							// Ensuite, nous montrons la bonne/mauvaise réponse (pas pour échelle : pas de bonne/mauvaise réponse)
							if (response[2] == "qcm") {
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
							}
							// Popup (qcm / echelle), puis bouton "Continuer" vers la page finale
							if (response[2] == "qcm" || response[2] == "echelle") {
								let ci = parseInt(response[1], 10);
								let correctText = "";
								if (answersarray[ci - 1]) {
									let rep = answersarray[ci - 1].querySelector('p#rep');
									if (rep) correctText = rep.innerText;
								}
								let explanation = response.slice(3).join("__");
								let isEchelle = response[2] == "echelle";
								let userCorrect = parseInt(buttonIndex, 10) === ci;
								showAnswerPopup(correctText, explanation, userCorrect, isEchelle);
							}
							showContinueButton(function () {
								window.location.href = window.location.href;
							});
						}

						else {
							timeout = true;
							if (response[9] === "qcm") {
								cd = 2000;
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

							// Popup avec la bonne réponse + explication (qcm / echelle) ; on attend le bouton "Continuer"
							if (!GAME_MODE && (response[9] == "qcm" || response[9] == "echelle")) {
								let ci = parseInt(response[7], 10);
								let correctText = "";
								if (answersarray[ci - 1]) {
									let rep = answersarray[ci - 1].querySelector('p#rep');
									if (rep) correctText = rep.innerText;
								}
								let explanation = response.slice(11).join("__");
								let isEchelle = response[9] == "echelle";
								let userCorrect = parseInt(buttonIndex, 10) === ci;
								showAnswerPopup(correctText, explanation, userCorrect, isEchelle);
							}

							var pendingNext = function () {
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
								document.getElementById("QuestionN").innerHTML = texts[lang]['js_question_label'] + response[6];
								document.getElementById('button_next').onclick = function () {
									updateQuestion(-1);
								};
								if (response[8] == "qcm" || response[8] == "echelle") {

									ismultiple = false;
									localStorage.clear();
									if (document.getElementsByClassName("popup")[0] != null)
										document.getElementsByClassName("popup")[0].remove();
									cd = 2000;

									deleteAllBlocks();
									for (let i = 1; i <= 5; i++) {
										// décoder d'abord pour détecter aussi les réponses vides (espaces, &nbsp;, etc.)
										const parser = new DOMParser();
										let decodedString = (response[i] == null || response[i] == "null")
											? ""
											: parser.parseFromString(response[i], "text/html").documentElement.textContent;
										let isEmpty = decodedString.trim() === "";
										if (!isEmpty) {

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
												p_elem.innerText = decodedString;
												if (!GAME_MODE) button.addEventListener("click", function () { updateQuestion(i); });
											}
										}
										else {
											let reponse_elem = document.getElementById("reponse_" + i);
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
										document.getElementById('connections').innerHTML = `${texts[lang].corrections}<br>${connections}`;
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
							};
							// Mode Jeu : pas de popup solo ni de bouton « Continuer » → on enchaîne
							// directement vers la question suivante (l'hôte pilote via sa barre de contrôle).
							if (GAME_MODE) {
								pendingNext();
								applyGameColors();
								if (typeof gameAfterRender === 'function') gameAfterRender();
							} else {
								showContinueButton(pendingNext);
							}

						}
					}
					else if (xhr.readyState == 4) {
						// Si la requête s'est terminée par une erreur, nous autorisons à nouveau les clics
						timeout = false;
						alert(texts[lang]['js_error_alert']);
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
	<script type="text/javascript">
		// --- Surveillance de la clé d'accès (access.php) --------------------------------
		// Toutes les 3 minutes, on revérifie la validité de la clé côté serveur. Si elle a
		// expiré / été révoquée : bannière + désactivation du choix d'un nouveau module.
		// Le questionnaire EN COURS reste jouable jusqu'au bout (le serveur applique la
		// même règle : access.php bloque ?level= / start, pas updateQuestion2.php).
		(function () {
			var accessValid = <?php echo !empty($access_valid) ? 'true' : 'false'; ?>;
			var ACCESS_EXPIRED_MSG = <?php echo json_encode(t('access_banner_expired')); ?>;
			function applyAccessExpired() {
				if (document.getElementById('access-expired-banner')) return;
				var b = document.createElement('div');
				b.id = 'access-expired-banner';
				b.textContent = '🔒 ' + ACCESS_EXPIRED_MSG;
				b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;background:#fff3cd;color:#856404;'
					+ 'border-bottom:2px solid #e8c06b;padding:10px 16px;text-align:center;font-weight:700;font-size:14px;';
				document.body.appendChild(b);
				// Cartes de modules (écran de sélection) : plus cliquables.
				var cards = document.querySelectorAll('.module-card');
				for (var i = 0; i < cards.length; i++) {
					cards[i].style.pointerEvents = 'none';
					cards[i].style.opacity = '0.45';
				}
			}
			function checkAccess() {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', 'access.php?action=status', true);
				xhr.onreadystatechange = function () {
					if (xhr.readyState !== 4 || xhr.status !== 200) return;
					try {
						var d = JSON.parse(xhr.responseText);
						if (d && d.valid === false) { accessValid = false; applyAccessExpired(); }
					} catch (e) { /* réponse inattendue : on ne change rien */ }
				};
				xhr.send();
			}
			if (!accessValid) applyAccessExpired();
			setInterval(checkAccess, 180000); // 3 minutes
		})();
	</script>
</body>
</html>

<?php
// play.php — interface joueur (téléphone) du « Mode Jeu ».
// Page autonome et légère (pas de nicepage). Toute la logique passe par game.php.
require_once __DIR__ . '/conf.php';
require_once __DIR__ . '/i18n.php';

$prefillPin = isset($_GET['pin']) && preg_match('/^\d{6}$/', $_GET['pin']) ? $_GET['pin'] : '';

// --- Dictionnaire JS "T" construit depuis le catalogue lang/*.php pour CHAQUE ---
// langue activée (fr/en/de aujourd'hui, toute langue ajoutée plus tard sans retoucher
// ce fichier). En cas d'échec DB, on retombe sur fr/en/de en dur (dégradation gracieuse).
$playKeyMap = [
    'pinSub'    => 'play_pin_sub',
    'nameH1'    => 'play_name_h1',
    'nameSub'   => 'play_name_sub',
    'lobbyH1'   => 'play_lobby_h1',
    'lobbySub'  => 'play_lobby_sub',
    'waitTitle' => 'play_wait_title',
    'waitSub'   => 'play_wait_sub',
    'correct'   => 'play_correct',
    'wrong'     => 'play_wrong',
    'noAnswer'  => 'play_no_answer',
    'endH1'     => 'play_end_h1',
    'rank'      => 'play_rank',
    'first'     => 'play_first',
    'score'     => 'play_score',
    'badPin'    => 'play_bad_pin',
    'emptyName' => 'play_empty_name',
    'ended'     => 'play_ended',
    'cancelled' => 'play_cancelled',
    'nameSubmit'   => 'play_name_submit',
    'questionWord' => 'play_question_word',
];

$play_languages = [];
try {
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    i18n_boot($pdo);
    $play_languages = i18n_enabled_codes($pdo);
} catch (Throwable $e) {
    error_log('play.php: i18n DB init failed, falling back to fr/en/de: ' . $e->getMessage());
    $play_languages = ['fr', 'en', 'de'];
}
if (empty($play_languages)) {
    $play_languages = ['fr'];
}

$play_T = [];
foreach ($play_languages as $code) {
    i18n_use($code);
    $dict = [];
    foreach ($playKeyMap as $jsKey => $catKey) {
        $dict[$jsKey] = t($catKey);
    }
    $play_T[$code] = $dict;
}
i18n_use('fr');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>La Station — Mode Jeu</title>
<style>
    * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
    html, body { margin: 0; height: 100%; }
    body {
        font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        background: linear-gradient(160deg, #6a5cf0 0%, #8a7bf4 45%, #b06fd8 100%);
        color: #fff; overflow: hidden;
    }
    /* Plein écran, sans défilement : la page tient toujours dans l'écran du téléphone. */
    .wrap { height: 100vh; height: 100dvh; display: flex; flex-direction: column;
        align-items: center; justify-content: center; padding: 12px; text-align: center; gap: 12px; }
    .card { background: #fff; color: #2b2b2b; border-radius: 18px; padding: 22px;
        width: 100%; max-width: 420px; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .sub { font-size: 14px; opacity: .9; margin: 0; }
    input {
        width: 100%; padding: 15px; font-size: 20px; text-align: center;
        border: 2px solid #d8cff7; border-radius: 12px; margin-top: 14px; outline: none;
    }
    input:focus { border-color: #8a7bf4; }
    button.main {
        width: 100%; padding: 15px; font-size: 18px; font-weight: 800; color: #fff;
        background: #5cb37a; border: none; border-radius: 12px; margin-top: 14px;
        cursor: pointer;
    }
    button.main:active { transform: translateY(1px); }
    .err { color: #d23; font-size: 14px; margin-top: 10px; min-height: 18px; }
    /* Écran question : panneau de boutons qui remplit l'espace disponible (2 colonnes). */
    #screen-question { flex: 1; width: 100%; max-width: 560px; display: flex;
        flex-direction: column; gap: 10px; padding: 2px 0; }
    .q-head { font-size: 15px; font-weight: 800; opacity: .95; flex: 0 0 auto;
        background: rgba(255,255,255,.18); padding: 8px 14px; border-radius: 30px; align-self: center; }
    .answers { flex: 1 1 auto; display: grid; grid-template-columns: 1fr 1fr;
        grid-auto-rows: 1fr; gap: 10px; width: 100%; min-height: 0; }
    .answers.single { grid-template-columns: 1fr; }
    .ans {
        border: none; border-radius: 16px; color: #fff; font-weight: 800;
        font-size: clamp(15px, 4.4vw, 24px); padding: 10px 12px; min-height: 0;
        cursor: pointer; display: flex; gap: 10px; align-items: center; justify-content: center;
        line-height: 1.15; box-shadow: 0 5px 0 rgba(0,0,0,.18); text-shadow: 0 1px 2px rgba(0,0,0,.25);
        overflow: hidden; word-break: break-word;
    }
    .ans:active { transform: translateY(3px); box-shadow: 0 2px 0 rgba(0,0,0,.18); }
    .ans .sym { font-size: 1.6em; flex: 0 0 auto; }
    .big { font-size: 26px; font-weight: 900; margin: 6px 0; }
    .score-pill { display: inline-block; background: rgba(255,255,255,.22);
        padding: 8px 16px; border-radius: 40px; font-weight: 800; font-size: 18px; }
    .lobby-name { font-size: 20px; font-weight: 800; margin-top: 6px; }
    .dots::after { content: ''; animation: dots 1.4s steps(4,end) infinite; }
    @keyframes dots { 0%{content:''} 25%{content:'.'} 50%{content:'..'} 75%{content:'...'} }
    .hidden { display: none !important; }
    .lead { width: 100%; max-width: 420px; text-align: left; }
    .lead .row { display: flex; justify-content: space-between; padding: 8px 12px;
        background: #fff; color: #2b2b2b; border-radius: 10px; margin-top: 8px; font-weight: 700; }
    .lead .row.me { outline: 3px solid #ffd54a; }
</style>
</head>
<body>
<div class="wrap">

    <!-- 1. Saisie du PIN -->
    <div id="screen-pin" class="card hidden">
        <h1>🎮 Mode Jeu</h1>
        <p class="sub" id="t-pin-sub">Entre le code PIN affiché à l'écran</p>
        <input id="pin-input" inputmode="numeric" pattern="\d*" maxlength="6" placeholder="123456" value="<?php echo htmlspecialchars($prefillPin); ?>">
        <div class="err" id="pin-err"></div>
        <button class="main" id="pin-go">OK</button>
    </div>

    <!-- 2. Saisie du pseudo -->
    <div id="screen-name" class="card hidden">
        <h1 id="t-name-h1">Ton pseudo</h1>
        <p class="sub" id="t-name-sub">Comment veux-tu apparaître ?</p>
        <input id="name-input" maxlength="24" placeholder="Alex">
        <div class="err" id="name-err"></div>
        <button class="main" id="name-go"></button>
    </div>

    <!-- 3. Lobby (en attente) -->
    <div id="screen-lobby" class="card hidden">
        <h1 id="t-lobby-h1">Tu es dans la partie !</h1>
        <p class="lobby-name" id="lobby-name"></p>
        <p class="sub dots" id="t-lobby-sub">En attente du démarrage</p>
    </div>

    <!-- 4. Question : boutons de réponse -->
    <div id="screen-question" class="hidden">
        <div class="q-head" id="q-progress"></div>
        <div class="answers" id="answers"></div>
    </div>

    <!-- 5. En attente / résultat de la question -->
    <div id="screen-wait" class="card hidden">
        <h1 id="wait-title">Réponse envoyée</h1>
        <p class="sub dots" id="wait-sub">En attente des autres</p>
        <p class="big" id="wait-result"></p>
        <span class="score-pill" id="wait-score"></span>
    </div>

    <!-- 6. Fin : classement -->
    <div id="screen-end" class="card hidden">
        <h1 id="t-end-h1">Partie terminée !</h1>
        <p class="big" id="end-rank"></p>
        <span class="score-pill" id="end-score"></span>
    </div>

</div>

<script>
(function () {
    "use strict";
    // Palette Kahoot (couleur + symbole), indexée par position de réponse.
    var PALETTE = [
        { c: "#e21b3c", s: "▲" }, // rouge  ▲
        { c: "#1368ce", s: "◆" }, // bleu   ◆
        { c: "#d89e00", s: "●" }, // jaune  ●
        { c: "#26890c", s: "■" }, // vert   ■
        { c: "#7a1fa2", s: "★" }  // violet ★ (5e réponse éventuelle)
    ];

    // Dictionnaire émis par PHP depuis le catalogue lang/*.php (une entrée par langue
    // activée). Voir i18n.php / lang/fr.php,en.php,de.php pour les clés "play_*".
    var T = <?php echo json_encode($play_T, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    var DEFAULT_LANG = T.fr ? "fr" : Object.keys(T)[0];

    var lang = DEFAULT_LANG, t = T[DEFAULT_LANG];
    var pin = "", pid = "", myName = "";
    var pollTimer = null, lastStatus = "", lastQNumber = -1, answeredThisQ = false;

    function $(id) { return document.getElementById(id); }
    function show(id) {
        ["screen-pin","screen-name","screen-lobby","screen-question","screen-wait","screen-end"]
            .forEach(function (s) { $(s).classList.toggle("hidden", s !== id); });
    }
    function applyLang(l) {
        if (l && T[l]) { lang = l; t = T[l]; }
        // Code inconnu/absent : on garde la langue courante (comportement d'origine).
        $("t-pin-sub").textContent = t.pinSub;
        $("t-name-h1").textContent = t.nameH1; $("t-name-sub").textContent = t.nameSub;
        $("t-lobby-h1").textContent = t.lobbyH1; $("t-lobby-sub").textContent = t.lobbySub;
        $("wait-title").textContent = t.waitTitle; $("wait-sub").textContent = t.waitSub;
        $("t-end-h1").textContent = t.endH1;
        $("name-go").textContent = t.nameSubmit;
    }

    function api(params) {
        var body = Object.keys(params).map(function (k) {
            return encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
        }).join("&");
        return fetch("game.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body
        }).then(function (r) { return r.json(); });
    }

    // --- Mémorisation de la session (reconnexion après refresh / sortie accidentelle) ---
    var SKEY = "lgbt_kahoot_session";
    function saveSession() {
        try { localStorage.setItem(SKEY, JSON.stringify({ pin: pin, pid: pid, name: myName })); } catch (e) {}
    }
    function loadSession() {
        try { return JSON.parse(localStorage.getItem(SKEY) || "null"); } catch (e) { return null; }
    }
    function clearSession() { try { localStorage.removeItem(SKEY); } catch (e) {} }

    // --- Écran PIN ---
    $("pin-go").addEventListener("click", function () {
        var v = $("pin-input").value.replace(/\D/g, "");
        if (!/^\d{6}$/.test(v)) { $("pin-err").textContent = "PIN = 6 chiffres"; return; }
        // Vérifie que la partie existe.
        api({ action: "state", pin: v }).then(function (res) {
            if (!res.ok) { $("pin-err").textContent = t.badPin; return; }
            pin = v; applyLang(res.lang);
            show("screen-name"); $("name-input").focus();
        });
    });

    // --- Écran pseudo ---
    $("name-go").addEventListener("click", function () {
        var nm = $("name-input").value.trim();
        if (!nm) { $("name-err").textContent = t.emptyName; return; }
        api({ action: "join", pin: pin, name: nm }).then(function (res) {
            if (!res.ok) { $("name-err").textContent = res.error === "ended" ? t.ended : t.badPin; return; }
            pid = res.pid; myName = res.name;
            saveSession(); // pour pouvoir revenir au même joueur après un refresh
            $("lobby-name").textContent = myName;
            show("screen-lobby");
            startPolling();
        });
    });

    // --- Rendu de la question ---
    function renderQuestion(q) {
        var box = $("answers");
        box.innerHTML = "";
        $("q-progress").textContent = t.questionWord + " " + q.qNumber + (q.totalQ ? " / " + q.totalQ : "");
        q.answers.forEach(function (a, i) {
            var pal = PALETTE[i % PALETTE.length];
            var btn = document.createElement("button");
            btn.className = "ans";
            btn.style.background = pal.c;
            btn.innerHTML = '<span class="sym">' + pal.s + '</span><span>' + a.text + '</span>';
            btn.addEventListener("click", function () { sendAnswer(a.n); });
            box.appendChild(btn);
        });
        box.classList.toggle("single", q.answers.length === 1);
    }

    function sendAnswer(choice) {
        if (answeredThisQ) return;
        answeredThisQ = true;
        $("wait-result").textContent = "";
        $("wait-score").textContent = "";
        show("screen-wait");
        api({ action: "answer", pin: pin, pid: pid, choice: choice }).then(function (res) {
            if (res.ok && res.me) { $("wait-score").textContent = res.me.score + " " + t.score; }
        });
    }

    // --- Polling de l'état ---
    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        poll();
        pollTimer = setInterval(poll, 1500);
    }
    function poll() {
        api({ action: "state", pin: pin, pid: pid }).then(handleState).catch(function () {});
    }
    function showCancelled() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        clearSession();
        $("wait-title").textContent = t.cancelled;
        $("wait-sub").textContent = "";
        $("wait-result").textContent = "✕";
        $("wait-result").style.color = "#e21b3c";
        $("wait-score").textContent = "";
        show("screen-wait");
    }
    function handleState(res) {
        if (!res.ok) {
            // La partie a été annulée/supprimée par l'hôte → on prévient le joueur.
            if (res.error === "no_game" && pid) { showCancelled(); }
            return;
        }
        var me = res.me || { score: 0 };

        if (res.status === "lobby") {
            lastQNumber = -1;
            show("screen-lobby");
            return;
        }
        if (res.status === "question" && res.question) {
            // Nouvelle question → réinitialise et affiche les boutons.
            if (res.question.qNumber !== lastQNumber) {
                lastQNumber = res.question.qNumber;
                answeredThisQ = !!me.answered;
            }
            if (answeredThisQ || me.answered) {
                $("wait-result").textContent = "";
                $("wait-score").textContent = me.score + " " + t.score;
                show("screen-wait");
            } else {
                renderQuestion(res.question);
                show("screen-question");
            }
            return;
        }
        if (res.status === "reveal") {
            var msg, color;
            if (me.lastChoice == null) { msg = t.noAnswer; color = "#bbb"; }
            else if (res.correctIndex && me.lastChoice === res.correctIndex) { msg = t.correct; color = "#26890c"; }
            else { msg = t.wrong; color = "#e21b3c"; }
            $("wait-result").textContent = msg;
            $("wait-result").style.color = color;
            $("wait-sub").textContent = "";
            $("wait-score").textContent = me.score + " " + t.score;
            show("screen-wait");
            return;
        }
        if (res.status === "ended") {
            if (pollTimer) clearInterval(pollTimer);
            clearSession();
            var rank = 1;
            for (var i = 0; i < res.players.length; i++) {
                if (res.players[i].pid === pid) { rank = i + 1; break; }
            }
            $("end-rank").textContent = rank === 1 ? t.first : (rank + t.rank);
            $("end-score").textContent = me.score + " " + t.score;
            show("screen-end");
            return;
        }
    }

    // Init : reprise de session si possible, sinon PIN (ou pseudo si PIN pré-rempli via QR).
    applyLang(DEFAULT_LANG);
    var pre = $("pin-input").value.replace(/\D/g, "");
    var saved = loadSession();

    function gotoPinOrName() {
        if (/^\d{6}$/.test(pre)) {
            api({ action: "state", pin: pre }).then(function (res) {
                if (res.ok) {
                    pin = pre; applyLang(res.lang);
                    if (saved && saved.name) $("name-input").value = saved.name; // pré-remplit l'ancien pseudo
                    show("screen-name"); $("name-input").focus();
                } else { show("screen-pin"); }
            }).catch(function () { show("screen-pin"); });
        } else {
            if (saved && saved.name) $("name-input").value = saved.name;
            show("screen-pin");
        }
    }

    // Si une session est mémorisée et qu'on ne vient pas d'un QR vers une AUTRE partie → on tente la reprise.
    if (saved && /^\d{6}$/.test(String(saved.pin)) && (!/^\d{6}$/.test(pre) || pre === String(saved.pin))) {
        api({ action: "state", pin: saved.pin, pid: saved.pid }).then(function (res) {
            if (res.ok && res.me && res.status !== "ended") {
                // Le joueur existe encore : on le replace directement dans la partie.
                pin = saved.pin; pid = saved.pid; myName = res.me.name || saved.name;
                applyLang(res.lang);
                $("lobby-name").textContent = myName;
                show("screen-lobby"); // placeholder le temps du 1er polling
                startPolling(); // handleState affichera le bon écran (lobby / question / reveal)
            } else {
                clearSession();
                gotoPinOrName();
            }
        }).catch(function () { clearSession(); gotoPinOrName(); });
    } else {
        gotoPinOrName();
    }
})();
</script>
</body>
</html>

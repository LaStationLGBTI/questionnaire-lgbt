<?php

header('Content-Type: application/json');
// Même durée de vie de session que index.php / game.php : sinon le ramasse-miettes déclenché
// ici (défaut 1440 s) peut supprimer la session d'un hôte resté inactif (longue explication).
ini_set('session.gc_maxlifetime', 31536000);
session_start();
require_once 'conf.php';
require_once __DIR__ . '/access.php';
// Accès par clé (access.php) : une clé doit avoir été validée dans cette session.
// Pas de revérification "live" ici : un questionnaire déjà commencé peut être terminé.
if (empty($_SESSION['access_key'])) {
	echo "Erreur : accès non autorisé (clé d'accès requise).";
	exit();
}
// Rejeu sûr après une réponse perdue (coupure réseau) : le client envoie « expect » = numéro
// de la question qu'il affiche. Si le serveur a DÉJÀ avancé au-delà, on renvoie la question
// courante (même format) sans réenregistrer la réponse ni ré-incrémenter LastQuestion
// (sinon une question serait sautée). Sans « expect » : comportement historique inchangé.
$uq_expect = (isset($_POST['expect']) && ctype_digit((string)$_POST['expect'])) ? (int)$_POST['expect'] : null;

/** Réponse « question suivante » (format __ : voir indices ci-dessous) pour LastQuestion courant. */
function uq_next_payload() {
	$L = $_SESSION["LastQuestion"];
	$p = explode("__",$_SESSION["QuestionToUse"])[$L]; //0
	$p .= "__". explode("__",$_SESSION["Rep1"])[$L]; //1
	$p .= "__". explode("__",$_SESSION["Rep2"])[$L]; //2
	$p .= "__". explode("__",$_SESSION["Rep3"])[$L]; //3
	$p .= "__". explode("__",$_SESSION["Rep4"])[$L]; //4
	$p .= "__". explode("__",$_SESSION["Rep5"])[$L]; //5
	$p  .= "__" . $L; //6
	$p  .= "__" . explode("__",$_SESSION["answer"])[$L - 1]; //7
	$p  .= "__" . explode("__",$_SESSION["qtype"])[$L]; //8
	$p  .= "__" . explode("__",$_SESSION["qtype"])[$L - 1]; //9
	$p  .= "__" . explode("__",$_SESSION["IdInUse"])[$L]; //10
	// explication de la question à laquelle on vient de répondre (placée en dernier : peut contenir des espaces)
	$p  .= "__" . (isset($_SESSION["expliqs"]) ? explode("__",$_SESSION["expliqs"])[$L - 1] : ""); //11
	return $p;
}

if(isset($_SESSION["finish"]))
if($_SESSION["finish"] == 1)
{
	// Rejeu de la requête de la DERNIÈRE question (réponse « fin » perdue) : même charge utile
	// que la branche « fin » (bonne réponse / type / explication de la dernière question).
	$fq = isset($_SESSION["LastQuestion"]) ? (int)$_SESSION["LastQuestion"] - 1 : -1;
	if ($uq_expect !== null && $fq >= 1 && $uq_expect === $fq && isset($_SESSION["answer"], $_SESSION["qtype"])) {
		$fa = explode("__",$_SESSION["answer"]);
		$ft = explode("__",$_SESSION["qtype"]);
		$fe = isset($_SESSION["expliqs"]) ? explode("__",$_SESSION["expliqs"]) : array();
		echo "fin__" . (isset($fa[$fq]) ? $fa[$fq] : "") . "__" . (isset($ft[$fq]) ? $ft[$fq] : "") . "__" . (isset($fe[$fq]) ? $fe[$fq] : "");
		exit();
	}
	echo "fin";
	exit();
}
if (isset($_SESSION['QuestionToUse'])) {
if (isset($_SESSION['start'])) {
	$uq_L = isset($_SESSION["LastQuestion"]) ? (int)$_SESSION["LastQuestion"] : 0;
	if ($uq_expect !== null && $uq_expect < $uq_L && $uq_L >= 2 && $uq_L <= (int)$_SESSION["TotalQuestions"]
		&& isset(explode("__",$_SESSION["QuestionToUse"])[$uq_L])) {
		// Déjà avancé (requête précédente traitée, réponse perdue) : on renvoie la question courante.
		$prochaineQ = uq_next_payload();
	} else {
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		if(isset($_POST['choise']))
		{
		if(isset($_SESSION['reponses']))
		{  
			if(explode("__",$_SESSION["qtype"])[$_SESSION["LastQuestion"]] == "lien" || explode("__",$_SESSION["qtype"])[$_SESSION["LastQuestion"]] == "mct"){
			$_SESSION['reponses'] .= "__Q@".htmlspecialchars($_POST['choise']);
			}
			else
			$_SESSION['reponses'] .= "__Q@".explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]]."||R@".htmlspecialchars($_POST['choise']);
		}
		else
		{
			$_SESSION['reponses']  = "Q@".explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]]."||R@".htmlspecialchars($_POST['choise']);
		}
		}
	}
	if($_SESSION["LastQuestion"] < $_SESSION["TotalQuestions"]){
		if(isset(explode("__",$_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]]))
	{
			$_SESSION["LastQuestion"] += 1;

	$prochaineQ = uq_next_payload(); // indices 0..11 (cf. uq_next_payload)

	}
		else
	{
		echo "Erreur code 1 lors de la sélection de la question, veuillez contacter 'La station', id de la question est " . $ids = explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]];
		exit();
	}
	}
	else
	{
		$prochaineQ = "fin";
		$prochaineQ  .= "__" . explode("__",$_SESSION["answer"])[$_SESSION["LastQuestion"]]; //1
		$prochaineQ  .= "__" . explode("__",$_SESSION["qtype"])[$_SESSION["LastQuestion"]]; //2 type de la dernière question
		// explication de la dernière question (placée en dernier : peut contenir des espaces)
		$prochaineQ  .= "__" . (isset($_SESSION["expliqs"]) ? explode("__",$_SESSION["expliqs"])[$_SESSION["LastQuestion"]] : ""); //3
// Mode Jeu (Kahoot) : aucune écriture en base, aucun e-mail. Le classement se fait
// côté game.php (fichier de partie). On se contente de marquer la fin.
if (empty($_SESSION['game_mode'])) {
$servername = "localhost";
$username = "root";
$password = "";
$database = "lastation";

try {
    $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('[updateQuestion2] ' . $e->getMessage());
    echo "Erreur de connexion à la base de données.";
}
if(!isset($_SESSION["genre"]))
{
	$_SESSION["genre"] = 0;
}
if(!isset($_SESSION["orient"]))
{
	$_SESSION["orient"] = 0;
}
if(!isset($_SESSION["reponses"]))
{
	$_SESSION["reponses"] = "null";
}
if(!isset($_SESSION["emailr"]))
{
	$_SESSION["emailr"] = "null";
}
// Minimisation RGPD : ni l'IP du répondant ni son e-mail ne sont stockés avec les réponses
// (l'IP de sécurité est déjà journalisée à l'entrée par clé dans access_log ; l'e-mail ne sert
// qu'à l'envoi des résultats depuis la session).
// Catégorisation par groupe : on enregistre la clé d'accès utilisée (colonne ajoutée
// à la volée si absente) pour pouvoir séparer les statistiques par groupe de répondants.
// Si la migration échoue pour une raison quelconque (droits DB, etc.), on insère quand
// même la réponse, sans la colonne access_key : l'insertion ne doit jamais être bloquée.
access_ensure_responses_key_column($conn);
try {
	$conn->prepare("INSERT INTO GSDatabaseR (ip, genre, orientation, reponse, repmail, lang, access_key) VALUES (?,?,?,?,?,?,?)")->execute(['', $_SESSION["genre"], $_SESSION["orient"], $_SESSION['reponses'], '', $_SESSION["language"], $_SESSION['access_key']]);
} catch (PDOException $e) {
	error_log('[updateQuestion2] insert sans access_key (migration indisponible) : ' . $e->getMessage());
	$conn->prepare("INSERT INTO GSDatabaseR (ip, genre, orientation, reponse, repmail, lang) VALUES (?,?,?,?,?,?)")->execute(['', $_SESSION["genre"], $_SESSION["orient"], $_SESSION['reponses'], '', $_SESSION["language"]]);
}
	$_SESSION["id_user"] = $conn->lastInsertId();
}
	$_SESSION["LastQuestion"] += 1;
	$_SESSION["finish"] = 1;
	}
	} // fin du traitement normal (hors rejeu)
}
	else
	{
		echo "Erreur code 2 lors de la sélection de la question, veuillez contacter 'La station', id de la question est " . $ids = explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]];
		exit();
	}
}
	else
	{
		echo "Erreur code 3 lors de la sélection de la question, veuillez contacter 'La station', id de la question est " . $ids = explode("__", $_SESSION["IdInUse"])[$_SESSION["LastQuestion"]];
		exit();
	}
echo $prochaineQ;
?>

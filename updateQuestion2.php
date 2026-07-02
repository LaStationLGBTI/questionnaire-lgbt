<?php

header('Content-Type: application/json');
session_start();
require_once 'conf.php';
// Accès par clé (access.php) : une clé doit avoir été validée dans cette session.
// Pas de revérification "live" ici : un questionnaire déjà commencé peut être terminé.
if (empty($_SESSION['access_key'])) {
	echo "Erreur : accès non autorisé (clé d'accès requise).";
	exit();
}
if(isset($_SESSION["finish"]))
if($_SESSION["finish"] == 1)
{
	echo "fin";
	exit();
}
if (isset($_SESSION['QuestionToUse'])) {
if (isset($_SESSION['start'])) {
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

	$prochaineQ = explode("__",$_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]]; //0
	$prochaineQ .= "__". explode("__",$_SESSION["Rep1"])[$_SESSION["LastQuestion"]]; //1
	$prochaineQ .= "__". explode("__",$_SESSION["Rep2"])[$_SESSION["LastQuestion"]]; //2
	$prochaineQ .= "__". explode("__",$_SESSION["Rep3"])[$_SESSION["LastQuestion"]]; //3
	$prochaineQ .= "__". explode("__",$_SESSION["Rep4"])[$_SESSION["LastQuestion"]]; //4
	$prochaineQ .= "__". explode("__",$_SESSION["Rep5"])[$_SESSION["LastQuestion"]]; //5
	$prochaineQ  .= "__" . $_SESSION["LastQuestion"]; //6
	$prochaineQ  .= "__" . explode("__",$_SESSION["answer"])[$_SESSION["LastQuestion"] - 1]; //7
	$prochaineQ  .= "__" . explode("__",$_SESSION["qtype"])[$_SESSION["LastQuestion"]]; //8
	$prochaineQ  .= "__" . explode("__",$_SESSION["qtype"])[$_SESSION["LastQuestion"] - 1]; //9
	$prochaineQ  .= "__" . explode("__",$_SESSION["IdInUse"])[$_SESSION["LastQuestion"]]; //10
	// explication de la question à laquelle on vient de répondre (placée en dernier : peut contenir des espaces)
	$prochaineQ  .= "__" . (isset($_SESSION["expliqs"]) ? explode("__",$_SESSION["expliqs"])[$_SESSION["LastQuestion"] - 1] : ""); //11

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
$conn->prepare("INSERT INTO GSDatabaseR (ip, genre, orientation, reponse, repmail, lang) VALUES (?,?,?,?,?,?)")->execute([$_SERVER['REMOTE_ADDR'], $_SESSION["genre"], $_SESSION["orient"], $_SESSION['reponses'], $_SESSION["emailr"], $_SESSION["language"]]);
	$_SESSION["id_user"] = $conn->lastInsertId();
}
	$_SESSION["LastQuestion"] += 1;
	$_SESSION["finish"] = 1;
	}
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

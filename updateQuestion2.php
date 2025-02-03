<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();
require_once 'conf.php';
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
		$prochaineQ  .= "__" . explode("__",$_SESSION["answer"])[$_SESSION["LastQuestion"]];
$servername = "localhost";  
$username = "root";
$password = "";
$database = "lastation";

try {
    $conn = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erreur connection: " . $e->getMessage();
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
$conn->prepare("INSERT INTO stationr2 (ip, genre, orientation,reponse) VALUES (?,?,?,?)")->execute([$_SERVER['REMOTE_ADDR'], $_SESSION["genre"],$_SESSION["orient"],$_SESSION['reponses']]);
			$_SESSION["LastQuestion"] += 1;
	$_SESSION["id_user"] = $conn->lastInsertId();
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

<?php
header('Content-Type: application/json');
session_start();
if(isset($_SESSION["finish"]))
if($_SESSION["finish"] == 1)
{
	echo "fin";
	exit();
}

if (isset($_SESSION['QuestionToUse'])) {
if (isset($_SESSION['start'])) {
	if($_SESSION["LastQuestion"] - 1 < $_SESSION["TotalQuestions"]){
		if(isset(explode("__",$_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]]))
	{
	$prochaineQ = explode("__",$_SESSION["QuestionToUse"])[$_SESSION["LastQuestion"]]; //0
	$prochaineQ .= "__". explode("__",$_SESSION["Rep1"])[$_SESSION["LastQuestion"]]; //1
	$prochaineQ .= "__". explode("__",$_SESSION["Rep2"])[$_SESSION["LastQuestion"]]; //2
	$prochaineQ .= "__". explode("__",$_SESSION["Rep3"])[$_SESSION["LastQuestion"]]; //3
	$prochaineQ .= "__". explode("__",$_SESSION["Rep4"])[$_SESSION["LastQuestion"]]; //4
	$prochaineQ .= "__". explode("__",$_SESSION["Rep5"])[$_SESSION["LastQuestion"]]; //5
	$prochaineQ  .= "__" . $_SESSION["LastQuestion"]; //6
	$prochaineQ  .= "__" . explode("__",$_SESSION["qtype"])[$_SESSION["LastQuestion"]]; //7
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
<?php

include 'utils.php';
include 'dbclasses.php';
include 'cppclasses.php';

$pTable = isset($_POST["table"]) ? $_POST["table"] : "media";
$pDatabase = isset($_POST["db"]) ? $_POST["db"] :"tryba";
$pHost = isset($_POST["host"]) ? $_POST["host"] :"localhost";
$pUser = isset($_POST["user"]) ? $_POST["user"] :"cgarrido";
$pPass = isset($_POST["pass"]) ? $_POST["pass"] :"Har0303456";


$connDetails = new ConnexionDetails($pHost,$pUser,$pPass,$pDatabase);

print_r($dbInfo->getAllTablesNames());

// Connect to database db1
$dbInfo = new DatebaseInformation($connDetails);


$infoArr = $dbInfo->getInfo($pTable);

$generator = new CppQtOdbClass($pTable, $infoArr);
echo $generator->generate(true);
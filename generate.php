<?php

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

include 'includes/utils.php';
include 'includes/dbclasses.php';
include 'includes/cppclasses.php';


$pass = file_get_contents("pass");

if (isset($_GET["debug"])) {
    list($pDatabase,$pHost,$pUser,$pPass) = array("tryba", "localhost", "cgarrido", $pass);
}
else if (!isset($_POST["db"]) || !isset($_POST["host"]) || !isset($_POST["user"]) || !$pass) {
    echo "[]";
    exit;
}
else {
    $pDatabase = trim($_POST["db"]);
    $pHost = trim($_POST["host"]);
    $pUser = trim($_POST["user"]);
    $pPass = isset($_POST["pass"]) && $_POST["pass"] !== "" ? trim($_POST["pass"]) : $pass;
}

$connDetails = new ConnexionDetails($pHost,$pUser,$pPass,$pDatabase);
$dbInfo = new DatebaseInformation($connDetails);

$tables =  $dbInfo->getAllTablesNames();
$files = array();
foreach ($tables as $table) {
    $attrArr = $dbInfo->getAttrs($table);
    $relOneArr = $dbInfo->getRelationsOne($table);
    $relManyArr = $dbInfo->getRelationsMany($table);
    //echo "<pre>";print_r($infoArr); echo "</pre>";
    $generator = new CppQtOdbClass($table, $attrArr, $relOneArr, $relManyArr);
    //echo "<pre>";print_r($generator); echo "</pre>";
    $files[] = (object)[
        "name" => snakeToCamelUC($table),
        "code" => $generator->generate(false),
        "header" => $generator->generate(true),
    ];
}
if(isset($_GET["download"])){
    $zip = new ZipArchive();
    $zipname = tempnam('.',$pDatabase."_").".zip";

    if ($zip->open($zipname, ZipArchive::CREATE)!==TRUE) {
        echo ("Impossible d'ouvrir le fichier <$zipname>\n");
    }

    foreach ($files as $file) {
        //$zip->addFromString($file->name.".cxx", $file->code);
        $zip->addFromString($file->name.".hxx", $file->header);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename='.$zipname);
    header('Content-Length: ' . filesize($zipname));
    readfile($zipname);
    unlink($zipname);
    exit;
}

echo json_encode($files);
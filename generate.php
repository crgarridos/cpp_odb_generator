<?php

include 'utils.php';
include 'dbclasses.php';
include 'cppclasses.php';


if (isset($_GET["debug"])) {
    list($pDatabase,$pHost,$pUser,$pPass) = array("tryba", "localhost", "cgarrido", "Har0303456");
}
else if (!isset($_POST["db"]) || !isset($_POST["host"]) || !isset($_POST["user"]) || !isset($_POST["pass"])) {
    echo "[]";
    exit;
}
else {
    $pDatabase = trim($_POST["db"]);
    $pHost = trim($_POST["host"]);
    $pUser = trim($_POST["user"]);
    $pPass = trim($_POST["pass"]);
}

$connDetails = new ConnexionDetails($pHost,$pUser,$pPass,$pDatabase);
$dbInfo = new DatebaseInformation($connDetails);

$tables =  $dbInfo->getAllTablesNames();
$files = array();
foreach ($tables as $table){
    $infoArr = $dbInfo->getInfo($table);
    $generator = new CppQtOdbClass($table, $infoArr);
    $files[] = (object)[
        "name" => snakeToCamelUC($table),
        "code" => $generator->generate(false),
        "header" => $generator->generate(true),
    ];
}
if(isset($_GET["download"])){
    $zip = new ZipArchive();
    $zipname = tempnam('.',$pDatabase).".zip";

    if ($zip->open($zipname, ZipArchive::CREATE)!==TRUE) {
        echo ("Impossible d'ouvrir le fichier <$zipname>\n");
    }

    foreach ($files as $file) {
        $zip->addFromString($file->name.".cpp", $file->code);
        $zip->addFromString($file->name.".hpp", $file->header);
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
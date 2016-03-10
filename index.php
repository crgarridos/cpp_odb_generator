<?php

//header('Content-Type: charset=ISO-8859-1');
//header("Content-Disposition: attachment; filename=savethis.txt");
//header("Content-Type: application/force-download");

//ini_set('default_charset','iso-8859-1');
include "utils.php";
include 'dbclasses.php';
include 'cppclasses.php';

/*error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
*/
//echo '$_POST: ';print_r($_POST);

if (isset($_POST["generate"])){
		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => http_build_query($_POST)
		    )
		);
		$urlbase = 'http://localhost'.$_SERVER['REQUEST_URI'].'generate.php?';
		$json = file_get_contents($urlbase, false, stream_context_create($opts));
		$zip = file_get_contents($urlbase."download", false, stream_context_create($opts));
		if($json === FALSE)
			echo "<p style='color:red'>The given infos are invalides to recover the db's tables</p>";
		$files = json_decode($json);
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Generate Odb c++ classes from MySQL tables</title>
	<link rel="stylesheet" href="magula.min.css"/>
	<link rel="stylesheet" href="jquery-ui.min.css"/>
	<script src="highlight.min.js"></script>
	<script src="jquery.min.js"></script>
	<script>

		hljs.initHighlightingOnLoad();
		$(function() {
	    	$( "#tabs" ).tabs();
    	})
    </script>
	<script src="jquery-ui.min.js"></script>
	<style type="text/css">
	pre{
		background: #E5E5E5!important;
		line-height: .7;
		tab-size: 4
	}
	h2{color: red;margin-right: 150px}
	</style>
</head>
<body>
<h2>Not recommented to use in this site, http doesn't provide a secure way to send your password across internet, fork the github project and execute it in a secure enviroment</h2>
<a href="https://github.com/crgarridos/cpp_odb_generator"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/e7bbb0521b397edbd5fe43e7f760759336b5e05f/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f677265656e5f3030373230302e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_green_007200.png"></a>
	<form method="post">
		<p>Database: <input type="text" name="db" value="tryba	"/></p>
		<p>Host: <input type="text" name="host" value="localhost"/></p>
		<p>User: <input type="text" name="user" value="cgarrido"/></p>
		<p>Password: <input type="password" name="pass" /></p>
		<input type="hidden" name="generate" value="generate" />
		<input type="submit" value="Submit" />
	</form>
	<?php if (isset($_POST["generate"])): ?>
		<form action="generate.php?download" method="post" style="text-align: center;padding: 3em">
			<input type="hidden" name="db" value="<?= $_POST['db']?>"/>
			<input type="hidden" name="host" value="<?= $_POST['host']?>"/>
			<input type="hidden" name="user" value="<?= $_POST['user']?>"/>
			<input type="hidden" name="pass" value="<?= $_POST['pass']?>"/>
			<input type="submit" id="download" value="Download .zip" style="font-size: 32px;padding: .3em" />
		</form>
		<div id="tabs">
		  	<ul>
		  		<?php 
		  		for ($i = 0 ; $i < count($files); $i++) { 
		  			echo "<li><a href='#tabs-".($i+1)."'>".$files[$i]->name.".cpp</a></li>";
		  		}?>
			</ul>
			<?php 
	  		for ($i=0; $i < count($files); $i++) { 
	  			echo "<pre id='tabs-".($i+1)."'><code class='cpp'>".nl2br(htmlentities($files[$i]->code))."</code></pre>";
	  		}?>
		</div>
	<?php endif ?>
</body>
</html>

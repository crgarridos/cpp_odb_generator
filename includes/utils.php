<?php

function snakeToCamel($snakeText){
    $snakeText = str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeText)));  
    $snakeText = strtolower(substr($snakeText,0,1)).substr($snakeText,1);  
    return $snakeText;  
}
function snakeToCamelUC($snakeText){
    return ucfirst(snakeToCamel($snakeText)); 
}  


function postfixFirstSnake($snakeText, $glue){
    $ex = explode("_", $snakeText);
    if (count($ex) > 1) {
        return join($glue."_", $ex);
    }
    return $snakeText.$glue;
}  

function camelToSnake($camelText) {  
    return preg_replace_callback(
    	'/[A-Z]/',  
		create_function('$match', 'return "_" . strtolower($match[0]);'),  
    	$camelText
    );  
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}
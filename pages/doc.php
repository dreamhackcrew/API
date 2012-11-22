<?php

if ( !isset($_GET['file']) ) 
    die('<div class"alert alert-error">No file defined</div>');

$file = str_replace(array('..','\\'),'',$_GET['file']);
$file = 'api/'.$file;


if ( !is_file($file) ) 
    die('<div class="alert alert-error">This is not the file you are looking for!</div>');

passthru('LANG=en_US.utf8 pandoc '.escapeshellarg($file) ); // .' 2>&1');

?>

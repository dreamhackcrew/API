<?php

$headers = apache_request_headers();
if ( !isset($headers['Authorization']) )
    return false;

if ( substr($headers['Authorization'],0,5) != 'OAuth' )
    return false;

require_once('1.0a.php');

$o = new oauth_provider();
return $o->checkAccess();

?>

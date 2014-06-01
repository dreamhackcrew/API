<?php

$headers = apache_request_headers();
if ( !isset($headers['Authorization']) )
    return false;

if ( substr($headers['Authorization'],0,5) != 'Basic' )
    return false;

if ( !isset($_SERVER['HTTP_X_URL_SCHEME']) || $_SERVER['HTTP_X_URL_SCHEME'] != "https") {
    response(array(
        'error' => 'Access denied to restricted service. ',
        'desc' => 'HTTP Basic autorization is not allowed withot SSL!',
        'no' => E_USER_ERROR,
    ));

}

require_once('http.php');

$o = new http_provider();
return $o->checkAccess();

?>

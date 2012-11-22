<?php

if ( !isset($request[0]) )
    $request[0] = '';

include_once("api/$apiversion/1.0a.php");

$s = new oauth_provider();

$command = '_'.$request[0];
array_shift($request);


function filter_methods($method) {
    if ( substr($method,0,1)=='_' && substr($method,1,1)!='_' )
        return substr($method,1);
    else
        return null;
}

if ( !is_callable(array($s,$command)) )
    response(array(
        'error'=>'Method not found!',
        'available'=>array_filter(array_map('filter_methods',get_class_methods($s)))
    ));

response(call_user_func_array(array($s,$command),$request));

?>

<?php

function __autoload($class){
    if( is_file('lib/'.$class.'.php') )
        return require_once('lib/'.$class.'.php');
}

// Connect to the database
db::getInstance(true)->connect(config::dbServer, config::dbUser, config::dbPasswd,config::dbDatabase);
new session();

set_error_handler('errorHandler');
function errorHandler($errno, $errstr, $errfile, $errline) {
    if ( !($errno & E_WARNING||$errno & E_ERROR || $errno & E_CORE_ERROR || $errno & E_COMPILE_ERROR || $errno & E_USER_ERROR || $errno & E_USER_WARNING || $errno & E_USER_NOTICE) )
        return true;

    header('HTTP/1.0 500 Server error');

    response(array(
        'error'=>$errstr,
        'no' => $errno
    ));
}

function response($data) {
    global $ext;

    file_put_contents('log', print_r(apache_request_headers(),true).print_r($_GET,true).print_r($_POST,true)."\n".(json_encode($data))."\n-----------------------------------\n\n" ,FILE_APPEND);

    switch($ext) {
        case 'xml':
            die(xml::toXml($data));
        case 'url':
            die(http_build_query($data));
        case 'json':
        default:
            header('Content-type: application/json');
            die(json_encode($data));
    }

    die();
}

?>

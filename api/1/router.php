<?php

if ( !isset($request[0]) )
    $request[0] = '';

$service = $request[0];
array_shift($request);

include("api/$apiversion/service.php");

if ( isset($_SESSION['id']) && $_SESSION['id'] && is_file("api/$apiversion/restricted/$service.php")) {
    include("api/$apiversion/restricted/$service.php");
} elseif ( is_file("api/$apiversion/public/$service.php") ) {
    include("api/$apiversion/public/$service.php");
} elseif (is_file("api/$apiversion/restricted/$service.php")) {
    header('HTTP/1.0 401 Unauthorized');

    if ( isset($_SERVER['HTTP_X_URL_SCHEME']) && $_SERVER['HTTP_X_URL_SCHEME'] == "https") 
        header('WWW-Authenticate: Basic realm="Sign in with our Crew Corner account"');

    response(array(
        'error' => 'Access denied to restricted service "'.$service.'", please authoize first',
        'desc' => 'You are trying to access a restricted service without autoization.',
        'no' => E_USER_ERROR,
    ));
} else {
    $services = scandir("api/$apiversion/public");

    foreach($services as $key => $line) {
        if ( substr($line,0,1) == '.' )
            unset($services[$key]);

            if ( substr($line,-4) != '.php' )
            unset($services[$key]);

            if ( isset($services[$key]) )
                $services[$key] = substr($line,0,-4);
    }

    sort($services);

    header('HTTP/1.0 404 Not Found');

    response(array(
        'error' => 'The requested service "'.$service.'" does not exist',
        'desc' => 'Available services is: '.implode($services,', '),
        'available' => $services,
        'no' => E_USER_ERROR,
    ));
}

$s = new $service($request);

?>

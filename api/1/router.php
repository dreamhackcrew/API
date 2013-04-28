<?php

if ( !isset($request[0]) )
    $request[0] = '';

// get the requested service and shift it out of the request
$service = $request[0];
array_shift($request);

// include the service class
include_once("api/$apiversion/service.php");

// If the user is signed in and is requesting a restricted service
if ( isset($_SESSION['id']) && $_SESSION['id'] && is_file("api/$apiversion/restricted/$service.php")) {
    include("api/$apiversion/restricted/$service.php");
// The user is not signed in, use the public version
} elseif ( is_file("api/$apiversion/public/$service.php") ) {
    include("api/$apiversion/public/$service.php");
// The user is not signed in and there are no public version, send the user to the signin
} elseif (is_file("api/$apiversion/restricted/$service.php")) {
    header('HTTP/1.0 401 Unauthorized');

    if ( isset($_SERVER['HTTP_X_URL_SCHEME']) && $_SERVER['HTTP_X_URL_SCHEME'] == "https") 
        header('WWW-Authenticate: Basic realm="Sign in with our Crew Corner account"');

    response(array(
        'error' => 'Access denied to restricted service "'.$service.'", please authorize first',
        'desc' => 'You are trying to access a restricted service without authorization.',
        'no' => E_USER_ERROR,
    ));
} else {
// Make a list of public services and return that
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

// Create a instance of the service and call the method
$s = new $service($request);

?>

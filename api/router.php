<?php

if ( !isset($request[0]) )
    response(array(
        'error' => 'No or invalid API version provided',
        'desc' => 'Should be the first path of the url. ex http://api.crew.dreamhack.se/1 for version 1',
        'no' => E_USER_ERROR,
    ));


$apiversion = $request[0];
array_shift($request);
$request = array_filter($request);

// Find all api versions
$versions = scandir('api/');
foreach($versions as $key => $line) {
    if ( substr($line,0,1) == '.' )
        unset($versions[$key]);

    if ( !is_file("api/$line/router.php") )
        unset($versions[$key]);
}

sort($versions);

// Check auths, if trying to access a api
if ( is_numeric($apiversion) ) {
    foreach($versions as $key => $line ) {
        if ( is_numeric($line) )
            continue;

        if ( !is_file("api/$line/check.php") )
            continue;

        if ( $uid = include("api/$line/check.php") ) {
            $_SESSION['id'] = $uid;
            break;
        }
    }
}

// Check if the api version exists
if ( !is_dir('api/'.$apiversion) || !is_file("api/$apiversion/router.php") ) {

    header('HTTP/1.0 501 Not Implemented');

    response(array(
        'error' => 'The API version "'.$apiversion.'" does not exist',
        'desc' => 'Available versions is: '.implode($versions,', '),
        'available' => $versions,
        'no' => E_USER_ERROR,
    ));
}

require( "api/$apiversion/router.php" );

// Throw an error message if the router does not work
response(array(
    'error' => 'API request failed',
    'no' => E_USER_ERROR,
));

?>

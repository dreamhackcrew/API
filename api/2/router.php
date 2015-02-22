<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


//max: set timezone
//
date_default_timezone_set('Europe/Stockholm');

try {
	// Start EPI
	include('src/Epi.php');
	Epi::init('api','database');
	//include('config.php');
} catch(Exception $err) {
    // Make sure we always change the respose code to something else than 200
    http_response_code(500);

    $err = array(
		'error' => $err->getMessage(),
		'file'=>$err->getFile(),
		'line'=>$err->getLine()
    );
	
	response($err);
}


function p($data) {
    echo "<pre>".print_r($data,true)."</pre>";
}


getRoute()->get('/', 'home', 'insecure');
//user
getApi()->get('/users', 				array('users','getAll'), 			'secure', EpiApi::external);
getApi()->get('/users/self', 			array('users','getSelf'), 			'secure', EpiApi::external);
getApi()->get('/users/(\d+)', 			array('users','get'), 				'secure', EpiApi::external);
getApi()->get('/users/relations',		array('users','relations'),			'secure', EpiApi::external);
getApi()->get('/users/relationsng',		array('users','relationsng'),		'secure', EpiApi::external);
getApi()->get('/users/phone/(\w+)',		array('users','phonenumber'),		'secure', EpiApi::external);
//group

//event
getApi()->get('/events', 					array('events','getng'),	'secure', EpiApi::external);
getApi()->get('/events/all', 				array('events','getAll'), 		'secure', EpiApi::external);
getApi()->get('/events/(\d+)', 				array('events','getng'), 			'secure', EpiApi::external);
getApi()->get('/events/shortname/(\w+)', 	array('events','getShort'),		'secure', EpiApi::external);


require_once("src/Epi.php");


try {
	response(getRoute()->run('/'.implode($request,'/')));
} catch(Exception $err) {
    // Make sure we always change the respose code to something else than 200
    if ( http_response_code() == 200 )
        http_response_code(500);

    $err = array(
		'error' => $err->getMessage(),
		'file'=>$err->getFile(),
		'line'=>$err->getLine()
		//'backtrace'=>$err->getTrace()
    );

	response($err);
}

die();

<?php

ob_start();
header('X-XRDS-Location: http://' . $_SERVER['SERVER_NAME'] . '/services.xrds.php');

require('lib/start.php');

$request = $_GET['command'];

if ($ext = pathinfo($request, PATHINFO_EXTENSION) )
    $request = substr($request,0,-strlen($ext)-1);

$request = explode('/',$request);

if ( isset($request[0]) && trim($request[0]) && $ext != 'md' ) 
    return require('api/router.php');


if ( isset($_POST['username']) && isset($_POST['password']) ) {
    if ($u = db()->fetchOne("SELECT uid FROM users WHERE username='%s' AND password='%s' AND NOT level='disabled'",$_POST['username'],sha1($_POST['password']))) {
        $_SESSION['id'] = $u;
    }
}

if ( isset($_GET['exit']) ) {
    $_SESSION = array();
    session_destroy();
}

include('layout/header.php');
if ( isset($request[0]) && trim($request[0]) && $ext == 'md' ) {
    $_GET['file'] = $_GET['command'];
    include('pages/doc.php');
} else {
    include('pages/request.php');
}
include('layout/footer.php');

?>

<?php

class http_provider {
    function checkAccess() {
        if ( isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) ) {
            if ($u = db()->fetchOne("SELECT uid FROM users WHERE username='%s' AND password='%s' AND NOT level='disabled'",$_SERVER['PHP_AUTH_USER'],sha1($_SERVER['PHP_AUTH_PW']))) {
                return $u;
            } else {
                header('HTTP/1.0 401 Unauthorized');
                response(array('error'=>'Not authorized','http_problem'=>'Invalid username or password'));
            }
        }
        return false;
    }
}

?>

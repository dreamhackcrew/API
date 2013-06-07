<?php

class http_provider {
    function checkAccess() {
        if ( isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) ) {
            if ($u = db()->fetchOne("SELECT owner FROM api_customers WHERE customer_key='%s' AND customer_secret='%s' AND state='active' AND allow_https",$_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW'])) {
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

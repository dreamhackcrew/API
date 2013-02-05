<?php

function checkAccess() {
    $o = new oauth_provider();
    return $o->checkAccess();
}

class oauth_provider {
    private $request = array();

    function __construct() {/*{{{*/
        // Add the auth header
        $headers = apache_request_headers();
        if ( isset($headers['Authorization']) ) {
            $headers = $headers['Authorization'];
            $headers = explode(',',substr($headers,6));

            foreach($headers as $key => $line) {
                $line = explode('=',$line);
                $line[0] = trim($line[0]);
                $line[1] = trim($line[1],'"');

                $this->request[$line[0]] = urldecode($line[1]);
            }
        }

        // Add all get variables
            foreach( $_GET as $key => $line ) {
                if ( $key == 'command' ) 
                    continue;

                $this->request[$key] = urldecode($line);
            }

        // Add all post variables
            foreach( $_POST as $key => $line ) {
                $this->request[$key] = urldecode($line);
            }

        // Needs to be key sorted beacuse of the base_string
            ksort($this->request);

        // Check version
            if ( isset($this->request['oauth_version']) && $this->request['oauth_version'] != "1.0" )
                response(array('oauth_problem'=>'version_rejected'));

        // Check signature method
            if ( isset($this->request['oauth_signature_method']) && $this->request['oauth_signature_method'] != "HMAC-SHA1" )
                response(array('oauth_problem'=>'signature_method_rejected'));

        // http://wiki.oauth.net/w/page/12238543/ProblemReporting
    }/*}}}*/


    function _request_token() {/*{{{*/
        // Check all required fields 
        $required = array('oauth_consumer_key','oauth_signature_method','oauth_signature','oauth_timestamp','oauth_nonce');
        foreach($required as $line)
            if (!isset($this->request[$line]))
                response(array('oauth_problem'=>'parameter_absent','oauth_parameters_absent'=>$line));

        // Check that the timestamp isnt to old, max 5 min
            if ( abs(time() - $this->request['oauth_timestamp']) > 3600 ) {
                response(array('oauth_problem'=>'timestamp_refused'));
            }

        // Find the customer
            if ( !$customer = db()->fetchSingle("SELECT * FROM api_customers WHERE customer_key='%s'",$this->request['oauth_consumer_key']) ) {
                response(array('oauth_problem'=>'consumer_key_unknown'));
            }

        // Verify that the customer key is active
            if ( $customer['state'] == 'disabled' ) {
                response(array('oauth_problem'=>'consumer_key_refused'));
            }
            if ( $customer['state'] == 'revoked' ) {
                response(array('oauth_problem'=>'consumer_key_rejected'));
            }

        // Verify signature
            $this->verify( $customer['customer_secret'], '' );

        // Success, create and return token
            $token = sha1(md5(time()));
            $token_secret = sha1(md5(rand(-32676,32767)));

            db()->insert(array(
                'token' => $token,
                'secret' => $token_secret,
                'expire' => time()+3600,
                'callback' => urldecode($this->request['oauth_callback']),
            ),'api_auth_token');

            response(array('oauth_token'=>$token,'oauth_token_secret'=>$token_secret,'oauth_callback_confirmed'=>($this->request['oauth_callback']?'true':'false')));
    }
    function _OAuthGetRequestToken() {
        return $this->request_token();
    }/*}}}*/
    function _authorize() {/*{{{*/

        if ( !$_GET['test'] ) {
        // Check all required fields 
            $required = array('oauth_token');
            foreach($required as $line)
                if (!isset($this->request[$line])) { 
                    $error = "Parameter absent: ".$line;
                    $desc = "One of the mandatory parameters is missing!";
                    include('layout/signin_error.php');
                    die();
                }


        // Check auth_token
            if ( !$token = db()->fetchSingle("SELECT * FROM api_auth_token WHERE token='%s'",$this->request['oauth_token']) ) {
                $error = "Token rejected";
                $desc = "The provided auth token whas not found, the most probable cause for this error is that you are trying to use an old token. Please request a new auth token.";
                include('layout/signin_error.php');
                die();
            }

            db()->query("DELETE FROM api_auth_token WHERE expire < %d",time());

            if ( $token['state'] == 'used' ) {
                $error = "Token used";
                $desc = "The provided auth token is already used. Please request a new auth token and try again.";
                include('layout/signin_error.php');
                die();
            }
            if ( $token['expire'] < time() ) {
                $error = "Token expired";
                $desc = "The provided auth token has expired. An auth token is valid for 5 minutes. Please request a new auth token and try again.";
                include('layout/signin_error.php');
                die();
            }


            if ( !isset($_SESSION['id']) or true ) {
                if ( isset($_POST['username']) && isset($_POST['password']) ) {
                    if ($u = db()->fetchOne("SELECT uid FROM users WHERE username='%s' AND password='%s' AND NOT level='disabled'",$_POST['username'],sha1($_POST['password']))) {
                        $verifier = sha1(md5(rand(-32676,32767)));
                        db()->query("UPDATE api_auth_token SET verifier='%s',state='used',uid=%d WHERE token='%s'",$verifier,$u,$token['token']);

                        if ( !isset($this->request['oauth_callback']) )
                            $this->request['oauth_callback'] = $token['callback'];

                        if ( !$this->request['oauth_callback'] ) {
                            include('layout/callbackurl.php');
                            die();
                        }

                        $_SESSION['id'] = $u;

                        header( "Location: ".$this->request['oauth_callback']."?oauth_token={$token['token']}&oauth_verifier=$verifier" );
                        die();
                    }
                }
            } else {
                $verifier = sha1(md5(rand(-32676,32767)));
                db()->query("UPDATE api_auth_token SET verifier='%s',state='used',uid=%d WHERE token='%s'",$verifier,$_SESSION['id'],$token['token']);

                if ( !isset($this->request['oauth_callback']) )
                    $this->request['oauth_callback'] = $token['callback'];

                if ( !$this->request['oauth_callback'] ) {
                    include('layout/callbackurl.php');
                    die();
                }

                header( "Location: ".$this->request['oauth_callback']."?oauth_token={$token['token']}&oauth_verifier=$verifier" );
                die();
            }
        }

        include('layout/signin.php');
        die();
    }
    function _OAuthAuthorizeToken() {
        $this->authorize();
    }/*}}}*/
    function _access_token() {/*{{{*/
        // Check all required fields 
        $required = array('oauth_consumer_key','oauth_token','oauth_signature_method','oauth_signature','oauth_timestamp','oauth_nonce','oauth_verifier');
        foreach($required as $line)
            if (!isset($this->request[$line]))
                response(array('oauth_problem'=>'parameter_absent','oauth_parameters_absent'=>$line));

        // Check that the timestamp isnt to old, max 5 min
            if ( abs(time() - $this->request['oauth_timestamp']) > 3600 ) {
                response(array('oauth_problem'=>'timestamp_refused'));
            }

        // Find the customer
            if ( !$customer = db()->fetchSingle("SELECT * FROM api_customers WHERE customer_key='%s'",$this->request['oauth_consumer_key']) ) {
                response(array('oauth_problem'=>'consumer_key_unknown'));
            }

        // Check auth_token
            if ( !$token = db()->fetchSingle("SELECT * FROM api_auth_token WHERE token='%s'",$this->request['oauth_token']) ) {
                response(array('oauth_problem'=>'token_rejected'));
            }

            db()->query("DELETE FROM api_auth_token WHERE expire < %d",time());

            if ( $token['state'] != 'used' ) {
                response(array('oauth_problem'=>'token_used'));
            }
            if ( $token['expire'] < time() ) {
                response(array('oauth_problem'=>'token_expired'));
            }

        // Verify signature
            $this->verify( $customer['customer_secret'], $token['secret'] );

            db()->query("DELETE FROM api_auth_token WHERE token='%s'",$token['token']);

            $access_token = sha1(md5(time()));
            $access_token_secret = sha1(md5(rand(-32676,32767)));

            db()->insert(array(
                'token' => $access_token,
                'secret' => $access_token_secret,
                'customer' => $customer['customer_key'],
                'uid' => $token['uid']
            ),'api_access_token');

            response(array('oauth_token'=>$access_token,'oauth_token_secret'=>$access_token_secret));
    }
    function _OAuthGetAccessToken() {
        $this->access_token();
    }/*}}}*/

    function checkAccess(){/*{{{*/
        // Check all required fields 
        $required = array('oauth_consumer_key','oauth_token','oauth_signature_method','oauth_signature','oauth_timestamp','oauth_nonce');
        foreach($required as $line)
            if (!isset($this->request[$line])) {
                //header('HTTP/1.0 401 Unauthorized');
                response(array('error'=>'Not authorized','oauth_problem'=>'parameter_absent','oauth_parameters_absent'=>$line));
            }

        // Check that the timestamp isnt to old, max 5 min
            if ( abs(time() - $this->request['oauth_timestamp']) > 3600 ) {
                //header('HTTP/1.0 401 Unauthorized');
                response(array('error'=>'Not authorized','oauth_problem'=>'timestamp_refused'));
            }

        // Find the customer
            if ( !$customer = db()->fetchSingle("SELECT * FROM api_customers WHERE customer_key='%s'",$this->request['oauth_consumer_key']) ) {
                //header('HTTP/1.0 401 Unauthorized');
                response(array('error'=>'Not authorized','oauth_problem'=>'consumer_key_unknown'));
            }

        // Check auth_token
            if ( !$token = db()->fetchSingle("SELECT * FROM api_access_token WHERE token='%s'",$this->request['oauth_token']) ) {

                if ( !$token = db()->fetchSingle("SELECT * FROM api_auth_token WHERE token='%s'",$this->request['oauth_token']) ) {
                    response(array('error'=>'Not authorized','oauth_problem'=>'token_rejected'));
                } else {
                    // Check if token have expired
                    if ( $token['expire'] < time() ) {
                        // Delete expired keys
                        db()->query("DELETE FROM api_auth_token WHERE expire < %d",time());

                        response(array('error'=>'Not authorized','oauth_problem'=>'token_expired'));
                    } else {
                        // Update expire to +1 hour
                        db()->query('UPDATE api_auth_token SET expire=%d WHERE token="%s"',time()+3600,$token['token']);
                    }
                }
            } else {
                // Update seen
                db()->query("UPDATE api_access_token SET seen=NOW() WHERE token='%s' AND secret='%s'",$token['token'],$token['secret']);
            }

        // Verify signature
            $this->verify( $customer['customer_secret'], $token['secret'] );

        $_SESSION['id'] = $token['uid'];

        return $token['uid'];
    }/*}}}*/

// Help functions
    function verify($consumer_secret, $token_secret ) {/*{{{*/
        $out = '';
        $req = $this->request;
        unset($req['oauth_signature']);
        foreach($req as $key => $line) {
            $out .= '&'.$key.'='.urlencode($line);
        }

        $base_string = $_SERVER['REQUEST_METHOD'].'&'.urlencode($_SERVER['SCRIPT_URI']).'&'.urlencode(ltrim($out,'&'));

        /*
        $parameters = array();

        foreach($this->request as $key => $line) {
            if ( $key != 'oauth_signature' )
                $parameters[$key] = urlencode($line);
        }
        ksort($parameters);

        $base_string = $_SERVER['REQUEST_METHOD'].'&'.urlencode($_SERVER['SCRIPT_URI']).'&'.urlencode(http_build_query($parameters));
        */

        $signature = $this->sign( $base_string, $consumer_secret, $token_secret );

        $a = base64_decode(urldecode($signature));
        $b = base64_decode($this->request['oauth_signature']);

        // Bin compare
        if ( rawurlencode($a) != rawurlencode($b) ) {
            response(array('oauth_problem'=>'signature_invalid','base'=>$base_string,  ));
        }

        return true;
    }/*}}}*/
    function sign ( $base_string, $consumer_secret, $token_secret )/*{{{*/
    {
        $key = urlencode($consumer_secret).'&'.urlencode($token_secret);
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $base_string, $key, true));
        } else {
            $blocksize  = 64;
            $hashfunc   = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key     = str_pad($key,$blocksize,chr(0x00));
            $ipad    = str_repeat(chr(0x36),$blocksize);
            $opad    = str_repeat(chr(0x5c),$blocksize);
            $hmac     = pack(
                        'H*',$hashfunc(
                            ($key^$opad).pack(
                                'H*',$hashfunc(
                                    ($key^$ipad).$base_string
                                )
                            )
                        )
                    );
            $signature = base64_encode($hmac);
        }
        return urlencode($signature);
    }/*}}}*/
}

?>

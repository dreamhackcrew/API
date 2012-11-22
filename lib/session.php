<?php
/* vim: set expandtab tabstop=3 shiftwidth=3: */

/**
 *
 * Set_save_session_handler functions 
 * 
 * PHP Version 5
 *
 * @category   sessions 
 * @copyright  2008 the zion group
 * @link       http://honeydew.se/
 * @author     Joel Hansson <joel@everlast.se>
 * @author     Jonathan Svensson-Köhler <stamp@stamp.se>
 * @author     Jonas Falck <jonaz@jonaz.net>
 **/

require_once 'config.php';
require_once 'db.php';

class session {
    const version = '1.3.1';
    const sess_life = 3600; // One hour.
    const usefile = 1;
    function __construct() {// {{{
        // we should alread have made the connection to the database
        if ( !db() ) 
            return !trigger_error('No db-object were present',E_USER_WARNING);
         
        session_set_save_handler(
           array($this,'open'),
           array($this,'close'),
           array($this,'read'),
           array($this,'write'),
           array($this,'destroy'),
           array($this,'gc')
        );

        // Fire it up!
        session_start();
        //register_shutdown_function('session_write_close');
    }
    // }}}
    function __destruct(){
        //session_write_close();
    }
    function __sleep(){
        //session_write_close();
    }
    static function open( $save_path, $session_name ) {// {{{
        return true;
    }
    // }}}
    static function close() {// {{{
        return true;
    }
    // }}}
    static function read($key){// {{{
        $expiry = time();
        if(session::usefile == 1){

            $sql = 'SELECT sesskey FROM sessions '.
            'WHERE sesskey = "'.$key.'" '.
            'AND ip = "'.$_SERVER['REMOTE_ADDR'].'" '.
            'AND expiry > "'.$expiry.'" '.
            'LIMIT 1';

            if ($result = db()->fetchOne($sql)) {
                if ( !is_dir(config::storage) ) {
                    mkdir(config::storage);
                }
                if ( !is_dir(config::storage."/sessions/") ) {
                    mkdir(config::storage."/sessions");
                }
                if(is_file(config::storage."/sessions/".$result)){
                    $timeout = time()+2;
                    while(($content = @file_get_contents(config::storage."/sessions/".$result)) === false || $content == NULL || $content ==''){
                        if(time() > $timeout)
                            return "timeout";
                        error()->note('session read failed, trying again! content:'.$content);
                    }
                    return $content;
                }else
                    return;
            }

            self::destroy($key);

        }
        else {

            $sql = 'SELECT value FROM sessions '.
            'WHERE sesskey = "'.$key.'" '.
            'AND ip = "'.$_SERVER['REMOTE_ADDR'].'" '.
            'AND expiry > "'.$expiry.'" '.
            'LIMIT 1';

            if ($result = db::getInstance()->fetchOne($sql)) {
                return $result;
            }

            self::destroy($key);
        }

        return false;
    }
    // }}}
    static function write($key, $val){// {{{
        $expiry = time() + session::sess_life;
        $fileval = NULL;
        if(session::usefile == 1){
            $value = "";
            $fileval = $val;
        }
        else
            $value  = addslashes($val);

       /**
        * is there already a row with the correct sesskey and ip?
        * if so, we should do update, if not, lets do insert
        */
       
        $sql_chk_key = 'SELECT sesskey, expiry,ip '.
          'FROM sessions '.
          'WHERE sesskey="'.$key.'" '.
          'AND ip="'.$_SERVER['REMOTE_ADDR'].'" '.
          'LIMIT 1';

        $result = db()->fetchSingle($sql_chk_key);

        $user = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
      
        // destroy expired sessions;
        if ( isset($result['sesskey']) && ( ($result['expiry'] < time()) || $result['ip']!=$_SERVER['REMOTE_ADDR']) ) {
            self::destroy($result['sesskey']);
            return false;

        } elseif ( isset($result['sesskey']) ) {
            $sql = 'UPDATE sessions SET '.
                'expiry = '.$expiry.', '.
                'uid = '.$user.' '.
                //'value = "'.$value.'" '.
                'WHERE sesskey = "'.$key.'" '.
                'AND ip = "'.$_SERVER['REMOTE_ADDR'].'"';
            if($fileval !=null)
                file_put_contents(config::storage."/sessions/".$result['sesskey'],$fileval);
        } else {
            $sql = 'INSERT INTO sessions SET '.
                'sesskey="'.$key.'", '.
                'expiry='.$expiry.', '.
                'uid='.$user.', '.
                //'value="'.$value.'", '.
                'ip ="'.$_SERVER['REMOTE_ADDR'].'"';
            if($fileval !=null)
                file_put_contents(config::storage."/sessions/".$key,$fileval);
        }

        $result = db()->query($sql);
        if ( date('s') == '42' )
            session::gc(123);

        if($result)
            return true;
        return false;
    }
    // }}}
    static function destroy($key){// {{{
    
        $sql = 'DELETE FROM sessions WHERE sesskey = "'.$key.'"';   
        if(db()->query($sql)){
            if(is_file(config::storage."/sessions/".$key))
                unlink(config::storage."/sessions/".$key);
            return true;
        }
        return false;
    }
    // }}}
    static function gc($sess_life) {// {{{
        $time = time();
        if(session::usefile == 1){
            if ( $sessions = db()->fetchAllOne("SELECT sesskey FROM sessions WHERE expiry <".$time) )
                foreach($sessions AS $sesskey)
                    if ( is_file(config::storage."/sessions/".$sesskey) )
                        unlink(config::storage."/sessions/".$sesskey);
            //session::cleanFilesWithoutSessions();
        }
        $sql = 'DELETE FROM sessions WHERE expiry < ' . $time;
        db()->query($sql);

        //error()->note(' - cleared sessions; '.mysql_affected_rows().' rows removed.');
        return db()->affectedRows();
    }
    // }}}
    static function cleanFilesWithoutSessions(){
        $sessions = db()->fetchAllOne("SELECT sesskey FROM sessions");
        $files = scandir(config::storage."/sessions/");
        foreach($files AS $file){
            if($file != '.' && $file != '..' && !in_array($file,$sessions))
                unlink(config::storage."/sessions/".$file);
        }

    }
} 

?>

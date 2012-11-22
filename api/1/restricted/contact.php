<?php

class contact extends service {
    function _get($uid=null) {/*{{{*/
        if ( $uid == null )
            $uid = $_SESSION['id'];

        $c = db()->fetchAll('SELECT medium,text FROM user_contact WHERE uid=%d',$uid);
        $p = db()->fetchAll('SELECT email,email2,street,city,postcode,primaryphone,primaryphontype,secondaryphone,secondaryphonetype,ice FROM user_profile WHERE uid=%d',$uid);
        return array('contact'=>$c,'profile'=>$p);
    }/*}}}*/
}

?>

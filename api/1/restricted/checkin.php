<?php

class checkin extends service {
    function _get($uid=null) {
        if ( $uid == null )
            $uid = $_SESSION['id'];


        $events = db()->fetchAllOne("SELECT id FROM events WHERE active='Y'");
        $res = db()->fetchSingle('SELECT checkedin ,checkedinby FROM user_eventinfo WHERE uid=%d AND event IN (%d)',$uid,implode(',',$events));

        if ( $res['checkedin'] == '0000-00-00 00:00:00' ) {
            return array(
                'checkedin' => false
            );
        } else {
            return array(
                'checkedin' => true,
                'time' => $res['checkedin'],
                'by' => $res['checkedinby']
            );
        }
    }
}


?>

<?php

class eventinfo extends service {
    function _get($events=null, $uid=null) {/*{{{*/
        if ( $events == null || $events == "current" ) {
            $events = array(db()->fetchOne("SELECT id FROM events WHERE active ='Y' AND end > CURRENT_DATE() ORDER BY start LIMIT 1"));
        } else {
            $events = explode('|',$events);

            // Only allow numbers
            $events = preg_grep('/^\d+$/',$events);
        }

        if ( $uid == null ) {
            $uid = $_SESSION['id'];
        }

        return $this->fetchEventinfo($events[0], $uid);
    }/*}}}*/

    function _checkin($event=null, $uid=null) {/*{{{*/
        // Check that the user have access
        $this->requireFlag('crewhantering');

        if ( $event == null || $event == "current" ) {
            $event = db()->fetchOne("SELECT id FROM events WHERE active ='Y' AND end > CURRENT_DATE() ORDER BY start LIMIT 1");
        } else {
            // Only allow numbers
            $event = intval($event);
        }

        if ( $uid == null ) {
            $uid = $_SESSION['id'];
        }

        $checkedinby = db()->fetchOne("SELECT username FROM users where uid = %d", $_SESSION['id']);

        db()->query("UPDATE user_eventinfo SET checkedin = now(), checkedinby = '%s' WHERE uid = %d AND event = %d", $checkedinby, $uid, $event);

        return $this->fetchEventinfo($event, $uid);
    }/*}}}*/

    function fetchEventinfo($event, $uid) {
        // Check that the user have access
        $this->requireFlag('crewhantering');

        if ( !$res = db()->fetchSingle('SELECT size, gsize, arrive, arrive_time, depart, depart_time, car, dinner, checkedin, checkedinby FROM user_eventinfo WHERE uid=%d and event in (%s)',$uid, $event) )
            return array(
                'error' => 'The user have not completed the "Event information"-form'
            );

        $eventinfo = array(
            'tshirt_size' => $res['size'],
            'gift_tshirt_size' => $res['gsize'],
            'arrival_date' => $res['arrive'],
            'arrival_time' => $res['arrive_time'],
            'departure_date' => $res['depart'],
            'departure_time' => $res['depart_time'],
            'dinner' => $res['dinner'] == 1,
            'car_registration_number' => $res['car'] != '' ? $res['car'] : null
        );

        if ( $res['checkedin'] == '0000-00-00 00:00:00' ) {
            $eventinfo['checkedin'] = false;
            $eventinfo['checkedin_at'] = null;
            $eventinfo['checkedin_by'] = null;
        } else {
            $eventinfo['checkedin'] = true;
            $eventinfo['checkedin_at'] = $res['checkedin'];
            $eventinfo['checkedin_by'] = $res['checkedinby'];
        }

        return $eventinfo;
    }
}

?>

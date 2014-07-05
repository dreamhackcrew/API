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

    function _search( $search ) {/*{{{*/

        // Check that the user have access
        //$this->requireFlag('crewhantering');

        if ( $events == null || $events == "current" ) {
            $events = array(db()->fetchOne("SELECT id FROM events WHERE active ='Y' AND end > CURRENT_DATE() ORDER BY start LIMIT 1"));
        } else {
            $events = explode('|',$events);

            // Only allow numbers
            $events = preg_grep('/^\d+$/',$events);
        }

		if ( !$events || !reset($events) )
			return !trigger_error('No event is active, please use ?event= to select event',E_USER_ERROR);

		$search = ltrim($search,'0');

		if ( !$search ) 
			return !trigger_error('The search string is to short',E_USER_ERROR);

        // Do the search
        if ( $u = db()->fetchAll("
			SELECT users.uid,username,firstname,lastname,city,car,allowed_arrive FROM users 
			LEFT JOIN user_profile 
				USING(uid) 
            LEFT JOIN user_eventinfo
                ON user_eventinfo.uid=users.uid AND user_eventinfo.event IN (%s)  
			WHERE 
				( concat(firstname,' ',lastname) LIKE '%%%2\$s%%' 
				OR username LIKE '%%%2\$s%%' 
				OR city LIKE '%%%2\$s%%' 
				OR birthdate = '%2\$s' 
				OR primaryphone LIKE '%%%2\$s%%' 
				OR secondaryphone LIKE '%%%2\$s%%' 
				OR user_profile.email LIKE '%%%2\$s%%'
				OR user_eventinfo.car LIKE '%%%2\$s%%'
				) AND NOT level = 'disabled'
            ORDER BY firstname, lastname DESC LIMIT 20
			",implode($events,','),$search) ) {
            foreach($u AS $key1=>$line1){

                // Get profile pictures
                if ( $pictures = db()->fetchAll("SELECT max(id) id,ident FROM images WHERE ident LIKE 'users.%%.%d' GROUP BY ident",$line1['uid']) )
                foreach($pictures as $key => $line) {
                    switch(substr($line['ident'],0,11) ) {
                        case 'users.badge':
                            if ( $hash = db()->fetchOne("SELECT file FROM images WHERE id=%d LIMIT 1",$line['id']) )
                                $u[$key1]['badge_picture'] = "api.crew.dreamhack.se/1/image/".$hash;
                            break;
                        case 'users.press':
                            if ( $hash = db()->fetchOne("SELECT file FROM images WHERE id=%d LIMIT 1",$line['id']) )
                                $u[$key1]['profile_picture'] = "api.crew.dreamhack.se/1/image/".$hash;
                            break;
                    }
                }


                // Get team memberships
                if ( $teams = db()->fetchAll("SELECT * FROM membership JOIN groups ON groups.gid=membership.gid AND groups.event IN (%s) WHERE uid=%d",implode($events,','),$line1['uid']) ) {
                    foreach($teams as $key => $line) {
                        $teams[$key] = db()->fetchAll("SELECT gid,name,is_team FROM groups WHERE lft <= %d AND rgt >= %d ORDER BY lft ASC",$line['lft'],$line['rgt']);
                    }

                    $u[$key1]['teams'] = $teams;
                }
            }
        }


        return $u;
    }/*}}}*/
}

?>

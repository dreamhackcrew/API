<?php

class user extends service {
    function _get($uid=null) {/*{{{*/
        if ( $uid == null )
            $uid = $_SESSION['id'];

        $fields = array(
            'uid' => 'uid',
            'username' => 'username',
            'firstname' => 'firstname',
            'lastname' => 'lastname',
        );

        if (isset($_GET['fields'])) {
            $_GET['fields'] = array_flip(explode(',',$_GET['fields']));
            $fields = array_intersect_key($fields,$_GET['fields']);
        }


        if ( is_numeric($uid) ) 
            $u = db()->fetchSingle('SELECT '.implode($fields,',').' FROM users WHERE uid=%d',$uid);
        else
            $u = db()->fetchSingle('SELECT '.implode($fields,',').' FROM users WHERE username LIKE "%s"',$uid);

        $pictures = db()->fetchAll("SELECT max(id) id,ident FROM images WHERE ident LIKE 'users.%.".$u['uid']."' GROUP BY ident");
        foreach($pictures as $key => $line) {
            switch(substr($line['ident'],0,11) ) {
                case 'users.badge':
                    if ( $hash = db()->fetchOne("SELECT file FROM images WHERE id=%d LIMIT 1",$line['id']) )
                        $u['badge_picture'] = "api.crew.dreamhack.se/1/image/".$hash;
                    break;
                case 'users.press':
                    if ( $hash = db()->fetchOne("SELECT file FROM images WHERE id=%d LIMIT 1",$line['id']) )
                        $u['profile_picture'] = "api.crew.dreamhack.se/1/image/".$hash;
                    break;
            }
        }

        if ( $u )
            return $u;
        else
            return array('error'=>'Could not find user');
    }/*}}}*/
    function _list() {/*{{{*/
        $fields = array(
            'uid',
            'username',
            'firstname',
            'lastname',
        );

        if (isset($_GET['fields'])) {
            $_GET['fields'] = explode(',',$_GET['fields']);
            $fields = array_intersect_key($fields,$_GET['fields']);
        }

        // Fetch all events that the authorized user is a member of
        $events = db()->fetchAllOne("SELECT event FROM membership JOIN groups USING(gid) WHERE uid=%d AND event>0",$_SESSION['id']);

        if ( isset($_GET['events']) ) {
            $e = explode(',',$_GET['events']);
            foreach($e as $key => $line) {
                if ( !in_array($line,$events) ) // Check that the user is a member of the selected event
                    unset($e[$key]); // Else remove it
            }

            $events = $e; // Replace list with all events with the new list
        }

        if ( !$events )
            return array();

        // Get all users that have been member of any of those events
        $u = db()->fetchAll('SELECT '.implode($fields,',').' FROM groups JOIN membership USING(gid) JOIN users USING(uid) WHERE event IN (%s)',implode(',',$events));
        return $u;
    }/*}}}*/
    function _search( $search ) {/*{{{*/
        $fields = array(
            'uid',
            'username',
            'firstname',
            'lastname',
        );

        if (isset($_GET['fields'])) {
            $_GET['fields'] = explode(',',$_GET['fields']);
            $fields = array_intersect_key($fields,$_GET['fields']);
        }

        // Get all the users events
        $events = db()->fetchAllOne("SELECT event FROM membership JOIN groups USING(gid) WHERE uid=%d AND event>0",$_SESSION['id']);

        if ( isset($_GET['events']) ) {
            if ( $e = explode(',',$_GET['events']) ) 
                foreach($e as $key => $line) {
                    if ( !in_array($line,$events) ) // Check that the user is a member of the selected event
                        unset($e[$key]); // Else remove it
                }

            $events = $e; // Replace list with all events with the new list
        }

        if ( !$events )
            return array();

        // Get all uids that have been member of any of those events
        $uids = db()->fetchAllOne('SELECT uid FROM groups JOIN membership USING(gid) WHERE event IN (%s)',implode(',',$events));

        if ( !$uids )
            return array();

        // Do the search
        if ( $u = db()->fetchAll('SELECT '.implode($fields,',').' FROM users WHERE uid IN (%s) AND username LIKE "%%%s%%"',implode(',',$uids),$search) )
            foreach($u AS $key1=>$line1){

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
            }


        return $u;
    }/*}}}*/
}

?>

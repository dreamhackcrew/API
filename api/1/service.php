<?php

function filter_methods($method) {
    // Only return methods that have a pre _
    if ( substr($method,0,1)=='_' && substr($method,1,1)!='_' )
        return substr($method,1);
    else
        return null;
}

class service {
    function __construct($request) {
		if ( is_callable(array($this,'construct')) )
			$this->construct();

        // Always add a _ to the requested method
        $command = '_'.$request[0];
        array_shift($request);

        // Check if the method exists
        if ( !is_callable(array($this,$command)) ) {
            header('HTTP/1.0 404 Not found');
            response(array(
                'error'=>'Method not found!',
                'available'=>array_filter(array_map('filter_methods',get_class_methods($this))) // Get all methods that starts with a _
            ));
        }

        // Call the method, and return the result
        response(call_user_func_array(array($this,$command),$request));
    }

    function requireFlag() {/*{{{*/
        $args = func_get_args();

        foreach($args as $key => $line) {
            if ( !is_string($line) )
                trigger_error('requireFlag arguments must be strings',E_USER_ERROR);

            // Add the flag to the list if it is a string.
            $flags[] = $line;
        }

        // Check that the user is signed in, if not.. send the user to the sign in
        if ( !isset($_SESSION['id']) ) {
            header('HTTP/1.0 401 Unauthorized');

            if ( isset($_SERVER['HTTP_X_URL_SCHEME']) && $_SERVER['HTTP_X_URL_SCHEME'] == "https") 
                header('WWW-Authenticate: Basic realm="Sign in with our Crew Corner account"');

            response(array(
                'error' => 'Access denied to a restricted service, please authorize first',
                'desc' => 'You are trying to access a restricted service without authorization.',
                'no' => E_USER_ERROR,
            ));
        }

        // If we dont have an access string, make one
        if ( !isset($_SESSION['access']) )
            $_SESSION['access'] = $this->makeAccessStr($_SESSION['id']);

        if ( !isset($_SESSION['access_flags']) )
            $_SESSION['access_flags'] = $this->getAccessFlags($_SESSION['access']);

        $flagsMissing = array();
        foreach($flags as $key => $line) {
            if ( !in_array($line,$_SESSION['access_flags']) )
                $flagsMissing[] = $line;
        }

        // We dont have access, throw error message
        if ( $flagsMissing ) 
            response(array(
                'error'=>'Access denied, insufficient permissions!',
                'access_flags_needed' => $flagsMissing
            ));
    }/*}}}*/

    function makeAccessStr($uid) {/*{{{*/

        // Start with the G-1 flag (signed in user) and the user id flag U{user id}
        $str = "G-1,|U$uid,";

        $level = db()->fetchOne("SELECT level FROM users WHERE uid=$uid");
        // The admin flag
        if ($level == 'admin')
            $str .= '|G-2,';
        // The developer flag, developers also gets the admin flag
        elseif ($level == 'developer')
            $str .= '|G-2,|G-4,';

        // Find all groups that the user is a member of
        if ($groups = db()->fetchAll("SELECT gid,name,lft,rgt FROM membership JOIN groups USING(gid) WHERE uid=$uid")) {
            $terms = array();
            foreach ($groups as $line) {
                // Get all child groups
                $terms[] = "lft BETWEEN {$line['lft']} AND {$line['rgt']}";

                // Find all parents
                $terms[] = "lft < {$line['lft']} AND rgt > {$line['rgt']}";
            }

            if ( $terms ) {
                // Get all the groups and childgroups that the user is a member of
                if ($groups = db()->fetchAllOne("SELECT gid FROM groups WHERE (".implode(' OR ',$terms).") AND NOT name LIKE '-%'")) 
                    // And add them to the access string
                    $str .= '|G'.implode($groups,',|G').',';
            }
        }

        // G-3 - member of a group in an active event
        if ( $events = db()->fetchAll("SELECT *, events.id, max(groups.gid) `gid` FROM events,groups,membership WHERE (groups.event=events.id OR groups.gid<0) AND membership.gid=groups.gid AND uid=$uid GROUP BY events.id")) {
            foreach ( $events as $event ) {
                // The E{event number} flag
                if ( $event['gid'] > 0 )
                    $str .= '|E'.$event['id'].',';

                // Remember if we find any active events
                if ( $event['active'] == 'Y' )
                    $active = true;
            }

            // Add the G-3 flag
            if ( isset($active) && $active )
                $str .= '|G-3,';
        }

        // Special flag groups, if the user is member of a group named something that starts with a - for example (-TA) we add the group flags like F-TA{group number}
        // This is used to identify team- and group-leaders for a group
        if ($flags = db()->fetchAll("SELECT * FROM membership JOIN groups USING (gid) WHERE name LIKE '-%' AND uid=$uid")) {
            foreach ($flags as $flag)
                $str .= '|F'.$flag['name'].''.$flag['parent'].',';
        }

        return $str;
    }/*}}}*/
    function getAccessFlags( $accessString ) {
        // If we dont get an access string, dont bother to run the search
        if ( !$accessString ) 
            return array();

        return db()->fetchAllOne("SELECT flag FROM api_access WHERE auth_string REGEXP '%s'",$accessString);
    }
}

?>

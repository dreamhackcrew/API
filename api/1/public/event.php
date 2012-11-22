<?php

class event extends service {
    function _get($events=null) {
        // Fetch all active events if no event was provided
        if ( $events==null)
            $events = db()->fetchAll("SELECT id,name,start,end,active FROM events WHERE active='Y'");
        elseif ( $events=='all')
            $events = db()->fetchAll("SELECT id,name,start,end,active FROM events");
        else {
            $events = explode('|',$events);

            // Only allow numbers
            $events = preg_grep('/^\d+$/',$events);

            if (!$events)
                return false;

            $events = db()->fetchAll("SELECT id,name,start,end,active FROM events WHERE id IN (%s)",implode(',',$events));
        }

        return $events;
    }
}

?>

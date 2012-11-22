<?php

class schedule extends service {
    function _location($id=null) {
        return db()->fetchSingle("SELECT location,head FROM schedule_locations WHERE location=%d",$id);
    }

    function _locations($events=null) {
        // Fetch all active events if no event was provided
        if ( $events==null) 
            $events = db()->fetchAllOne("SELECT id FROM events WHERE active='Y'");
        else
            $events = explode('|',$events);

        // Only allow numbers
        $events = preg_grep('/^\d+$/',$events);

        // Return false if nothing was found
        if ( !$events ) 
            return false;

        return db()->fetchAll("SELECT location,event,head FROM schedule_locations WHERE event IN (%s)",implode($events,','));
    }

    function _categories($events=null) {
        // Fetch all active events if no event was provided
        if ( $events==null)
            $events = db()->fetchAllOne("SELECT id FROM events WHERE active='Y'");
        else
            $events = explode('|',$events);

        // Only allow numbers
        $events = preg_grep('/^\d+$/',$events);
        // Return false if nothing was found
        if ( !$events ) 
            return false;

        return db()->fetchAll("SELECT category,event,head,color FROM schedule_categorys WHERE event IN (%s)",implode($events,','));
    }

    function _category($id=null) {
        return db()->fetchSingle("SELECT category,head,color FROM schedule_categorys WHERE category=%d",$id);
    }

    function _get($locations=null) {
        if ( $locations==null ) {
            // Fetch all active locations
            if( isset($_GET['event']) )
                $locations = $this->_locations($_GET['event']);
            else
                $locations = $this->_locations();
        } else {
            // Convert to array
            if ( !is_array($locations) )
                 $locations = explode('|',$locations);

            // Only allow numbers
            $locations = preg_grep('/^\d+$/',$locations);

            // Go thru everyone and load info
            if ( $locations )
                foreach($locations as $key => $row) {
                    if ( $row = $this->_location($row) )
                        $locations[$key] = $row;
                    else
                        unset($locations[$key]);
                }
        }

        // Fetch all events for the selected locations
        if ( $locations )
            foreach($locations as $key => $line) {
                $locations[$key]['events'] = db()->fetchAll("SELECT id,`start`,`stop`,`category`,`head`,`desc`,text,contact FROM schedule WHERE location=%d",$line['location']);
            }

        return $locations;
    }

}

?>

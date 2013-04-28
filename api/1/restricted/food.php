<?php

class food extends service {

    function _list($fid = null){
        // Select event/events
        if(!isset($_GET['event'])){
            $events = db()->fetchSingle("SELECT id,name,start,end,active FROM events WHERE active ='Y' AND end >= CURRENT_DATE() ORDER BY start LIMIT 1");
            $event = $events['id']; 
        }
        else
            $event = $_GET['event'];

        // Find all the meals
        $data = db()->fetchAll("SELECT * FROM user_food LEFT JOIN user_food_selected USING(fid) WHERE user_food.event=%d ",$event);

        // Rearrange the struct and group it by meal
        $ret = array();
        if($data)
            foreach($data as $line){
                $ret[$line['fid']]['datetime'] = $line['when'];
                $ret[$line['fid']]['name'] = $line['name'];
                if($line['eaten'])
                    $ret[$line['fid']]['eaten'][] = (int) $line['uid'];
                else
                    $ret[$line['fid']]['registered'][] = (int) $line['uid'];
            }

        // Send the data
        return $ret;
    }

    function _checkin($uid,$fid){
        // Check that the user have access to check in peope
        $this->requireFlag('matincheckning');

        // Find the meal
        if ( !$data = db()->fetchSingle("SELECT * FROM user_food_selected WHERE uid=%d AND fid=%d",$uid,$fid) )
            trigger_error('Selected user not found on selected meal');

        // Check in the user to the selected meal
        $tmp =  db()->query('UPDATE user_food_selected SET eaten=1 WHERE uid=%d AND fid=%d',$uid,$fid);
        return db()->affectedRows();
    }
}
?>

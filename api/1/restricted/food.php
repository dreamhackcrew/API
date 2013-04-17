<?php

class food extends service {

    function _list($fid = null){

        if(!isset($_GET['event'])){
            $events = db()->fetchSingle("SELECT id,name,start,end,active FROM events WHERE active ='Y' AND end >= CURRENT_DATE() ORDER BY start LIMIT 1");
            $event = $events['id']; 
        }
        else
            $event = $_GET['event'];

        $data = db()->fetchAll("SELECT * FROM user_food LEFT JOIN user_food_selected USING(fid) WHERE user_food.event=%d ",$event);

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

        return $ret;

    }

    function _checkin($uid,$fid){
        $data = db()->fetchSingle("SELECT * FROM user_food_selected WHERE uid=%d AND fid=%d",$uid,$fid);
        if(!$data){
            trigger_error('Selected user not found on selected meal');
        }
        $tmp =  db()->query('UPDATE user_food_selected SET eaten=1 WHERE uid=%d AND fid=%d',$uid,$fid);
        return db()->affectedRows();
    }
}
?>

<?php

class Tags {
	static function offline_orders($eventId) {
		$list = getDatabase()->all('SELECT attendee_id,order_id,firstname,lastname,email FROM cust_orders JOIN cust_attendee USING(attendee_id) 
									WHERE order_id IN (SELECT order_id FROM cust_orders WHERE event_id=:eventid 
									GROUP BY order_id HAVING COUNT(*)>0)',array('eventid'=>$eventId));
		return $list;
	}

	static function offline_tags($eventId) {
		$tags = getDatabase()->all('SELECT tag_id,order_id FROM tags 
									WHERE order_id IN (SELECT order_id FROM cust_orders WHERE event_id=:eventid
									GROUP BY order_id HAVING COUNT(*)>0)',array('eventid'=>$eventId));
		return $tags;
	}

}

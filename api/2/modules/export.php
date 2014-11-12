<?php

class Export extends BaseModule {
	static function tags($eventId) {
		if ( self::isAdmin()) {
			return getDatabase()->all('SELECT * FROM tags WHERE event_id=:eid', array('eid'=>$eventId)); 
		}
	}

	static function attendees() {
		if ( self::isAdmin()) {
			return getDatabase()->all('SELECT * FROM cust_attendee'); 
		}
	}

	static function orders($eventId) {
		if ( self::isAdmin()) {
			return getDatabase()->all('SELECT * FROM cust_orders WHERE event_id=:eid', array('eid'=>$eventId)); 
		}
	}

}

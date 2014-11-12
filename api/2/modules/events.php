<?php

class Events {
    static function getAll() {
        $events = db()->fetchAll("SELECT id,name,shortname,start,end,description,active,preparestart AS construction_start, prepareend AS construction_end FROM events");
        return $events;
    }

	static function getActive() {
        $events = db()->fetchAll("SELECT id,name,shortname,start,end,description,active,preparestart AS construction_start, prepareend AS construction_end FROM events WHERE active='Y'");
		return $events;
	}

    static function get($eventId) {
        $event = db()->fetchSingle("SELECT id,name,shortname,start,end,description,active,preparestart AS construction_start, prepareend AS construction_end FROM events WHERE id=%d",$eventId);
        return $event;
    }

	static function getShort($eventShort) {
        $event = db()->fetchSingle("SELECT id,name,shortname,start,end,description,active,preparestart AS construction_start, prepareend AS construction_end FROM events WHERE shortname='%s'",$eventShort);
        return $event;
	}
}

<?php


class BaseModule {

	public static function isAdmin($user = null) {
		if($user === null) {
			$user = $_SESSION['user_id'];
		}

		if ( getDatabase()->execute("SELECT * FROM membership WHERE group_id=0 AND user_id=:u",array('u'=>$user)) ) {
			return true;
		}

		return false;
	}

	public static function isTa($user = null) {
		if($user === null ) {
			$user = $_SESSION['id'];
		}

		if ( db()->fetchSingle("SELECT membership.gid FROM `membership` LEFT JOIN groups USING(gid) WHERE uid=%d AND event=%d AND name='-TA'",$user,$_GET['event']) ) {
			return true;
		}
		return false;
	}	
	
	public static function isGa($user = null) {
		if($user === null ) {
			$user = $_SESSION['id'];
		}

		if ( db()->fetchSingle("SELECT membership.gid FROM `membership` LEFT JOIN groups USING(gid) WHERE uid=%d AND event=%d AND name='-GA'",$user,$_GET['event']) ) {
			return true;
		}
		return false;
	}

	public static function isOa($user = null) {
		if($user === null ) {
			$user = $_SESSION['id'];
		}

		if ( db()->fetchSingle("SELECT membership.gid FROM `membership` LEFT JOIN groups USING(gid) WHERE uid=%d AND event=%d AND name='-OA'",$user,$_GET['event']) ) {
			return true;
		}
		return false;
	}


	public static function isSalespoint($user = null) {
		if($user === null) {
			$user = $_SESSION['user_id'];
		}

		if ( getDatabase()->execute("SELECT * FROM membership JOIN groups USING(group_id) where user_id=:u AND salespoint", array('u'=>$user)) ) {
			return true;
		}

		return false;
	}

}

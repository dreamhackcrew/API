<?php

include_once ('baseModule.php');
class Users extends BaseModule {

	private function getMembership($userId,$event) {
		$membership = db()->fetchAll("SELECT membership.uid,membership.gid,groups.event,groups.parent,groups.name,groups.lft,groups.rgt FROM `membership` LEFT JOIN groups USING(gid) WHERE uid='%d' AND event='%d'",$userId,$event);
		return $membership;
	}

	private function getTeamRoot($lft,$rgt) {
		$root = db()->fetchSingle("SELECT gid,name,parent,lft,rgt FROM groups WHERE lft<=%d AND rgt >=%d AND is_team='Y'",$lft,$rgt);
		return $root;
	}

	static function createGroupTree($list) {
		$return = array();

		// Start with the first one in the list
		while ( $level = reset($list) ) {
			unset($list[key($list)]); // Remove the current line

			if($level['rgt']-$level['lft'] !=1 ) { // If the group contains childs
				$childs = array();
				foreach ( $list as $key => $child ) { // Search the remaining groups for childs
					// Check if the group is a child
					if ( $level['lft'] < $child['lft'] && $child['rgt'] < $level['rgt'] ) {
						unset($list[$key]); // Then remove it from the list
						$childs[] = $child; // Save the child in the child list
					}
				}

				// If we have found childs, process them to
				if ($childs) {
					//insert users here aswell
					$level['childs'] = self::createGroupTree($childs);
				}
			}
			//insert users into level
			$return[] = $level;
		}	

		return $return;
	}

	static function getAll() {
		$users = db()->fetchAll('SELECT uid,username,firstname,lastname,logincount FROM users');
        return $users;
    }

    static function get($userId) {
		$user = db()->fetchSingle('SELECT uid,username,firstname,lastname,logincount FROM users WHERE uid=%d',$userId);
		$privilege = array();
		if ( self::isTa()) {
			$privilege['ta'] = "true";
		}
		if ( self::isGa()) {
			$privilege['ga'] = "true";
		}
		if ( self::isOa()) {
			$privilege['oa'] = "true";
		}
		$user['privilege'] = array_keys($privilege);
		return $user;
    }

	static function getSelf() {
		$userId = $_SESSION['id'];
		$self = db()->fetchSingle('SELECT uid,username,firstname,lastname,logincount FROM users WHERE uid=%d',$userId);
		$privilege = array();
		if ( self::isTa()) {
			$privilege['ta'] = "true";
		}
		if ( self::isGa()) {
			$privilege['ga'] = "true";
		}
		if ( self::isOa()) {
			$privilege['oa'] = "true";
		}
		$self['privilege'] = array_keys($privilege);
		return $self;
	}	

	static function relations() {
		//get current event
		if ( !$_GET['event'] ) {
			$current_event = db()->fetchAll("SELECT id FROM events WHERE active='Y'");
			$current_event = $current_event[0]['id'];
		} else {
			$current_event = $_GET['event'];
		}
		
		// get users relations, will return groups as team and groups as flag for team, if user has no team, return false
		if ( !$relations = self::getMembership($_SESSION['id'],$current_event) ) {
			return false;
		}

		//team flags
		$flags = array("-TA","-GA","-OA");
		$return = array();	
		foreach ( $relations as $relation ) {
			
			// create an array of just the Teams, always using the teams root incase they are placed in a group
			if ( !in_array($relation['name'], $flags) ) {
				$team_root = self::getTeamRoot($relation['lft'],$relation['rgt']);
				$return[$team_root['name']] = array();
			}

			// if user has the TA flag
			if ( $relation['name'] == "-TA") {
				if ( $ta = db()->fetchSingle("SELECT name,lft,rgt FROM groups WHERE gid=%d",$relation['parent']) ) {
					$members = db()->fetchAll("select uid,username,firstname,lastname from groups JOIN membership USING (gid) JOIN users USING(uid) WHERE lft>=%d AND rgt<=%d AND NOT name LIKE '-%%'",$ta['lft'],$ta['rgt']);
					$return[$ta['name']] = $members;
				}
			}
			
			// if user has the GA flag
			if ( $relation['name'] == "-GA") {
				if ( $ga = db()->fetchSingle("SELECT name,lft,rgt FROM groups WHERE gid=%d",$relation['parent']) ) {
					// figure out what team the person is in.
					$gateam = self::getTeamRoot($relation['lft'],$relation['rgt']);
					$gagroup = db()->fetchSingle("SELECT membership.gid,groups.parent,groups.name,groups.lft,groups.rgt FROM `membership` LEFT JOIN groups USING(gid) WHERE uid='%d' AND event='%d' AND lft>=%d AND rgt<=%d",$_SESSION['id'],$current_event,$gateam['lft'],$gateam['rgt']);	
					$members = db()->fetchAll("select uid,username,firstname,lastname from groups JOIN membership USING (gid) JOIN users USING(uid) WHERE lft>=%d AND rgt<=%d AND NOT name LIKE '-%%'",$gagroup['lft'],$gagroup['rgt']);
					$return[$ga['name']] = $members;
				}
			}
			
			// if this is a regular user, get self
			$return[$team_root['name']] = self::get($_SESSION['id']);
		}
		//return $return;
		return $relations;
	}

	static function relationsng() {
		$_SESSION['id'] = 196;
		//get event, either by _GET or just the last active event
		if ( !$_GET['event'] ) {
			$current_event = db()->fetchAll("SELECT id FROM events WHERE active='Y'");
			$current_event = $current_event[0]['id'];
		} else {
			$current_event = $_GET['event'];
		}
		
		// get users relations, will return groups as team and groups as flag for team, if user has no team, return false
		if ( !$relations = self::getMembership($_SESSION['id'],$current_event) ) {
			return false;
		}

		//team flags
		$flags = array("-TA","-GA","-OA");
		$group_list = array();	
		foreach ( $relations as $relation ) {
			
			// create an array of just the Teams, always using the teams root incase they are placed in a group
			if ( !in_array($relation['name'], $flags) ) {
				$team_root = self::getTeamRoot($relation['lft'],$relation['rgt']);
				$tree[$team_root['name']] = array();
				//$tree[$team_root['name']] = db()->fetchAll("SELECT gid,name,parent,lft,rgt FROM `groups` WHERE lft>=%d AND rgt<=%d AND NOT name LIKE '-%%'",$relation['lft'],$relation['rgt']);
				$test = db()->fetchAll("SELECT gid,name,parent,lft,rgt FROM `groups` WHERE lft>=%d AND rgt<=%d AND NOT name LIKE '-%%' ORDER BY lft",$relation['lft'],$relation['rgt']);
				$test2 = self::createGroupTree($test);
			}

		}
		
	return array("ret" => $test2, "source" => $test);
	}


	static function phonenumber($username) {
		//check to see if the person searching is crew in current event
		$current_event = db()->fetchAll("SELECT id FROM events WHERE active='Y'");
		$current_event = $current_event[0]['id'];
		if ( !$active = db()->fetchSingle("SELECT * from membership LEFT JOIN groups USING(gid) WHERE uid=%d and event=%d",$_SESSION['id'],$current_event) ) {
			return false;
		}
		$phonenr = db()->fetchSingle("SELECT users.uid,user_contact.medium,user_contact.text FROM users LEFT JOIN user_contact USING(uid) WHERE username='%s' AND medium='Mobil'",$username);
		return $phonenr;
	}
}

?>

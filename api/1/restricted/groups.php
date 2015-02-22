<?php

class groups extends service {
	function _team( $nameOrGid ){
		$events = $_GET['events'];

        if ( $events == null || $events == "current" ) {
            $events = array(db()->fetchOne("SELECT id FROM events WHERE active ='Y' AND end > CURRENT_DATE() ORDER BY start LIMIT 1"));
        } else {
            $events = explode('|',$events);

            // Only allow numbers
            $events = preg_grep('/^\d+$/',$events);
		}

		if ( !$events || !reset($events) )
			$events = array(0);

		$teams = db()->fetchAll("SELECT gid, name, parent, lft, rgt FROM groups WHERE is_team='Y' AND event IN (".implode($events,',').") AND (name LIKE '%s' OR gid = '%s')", $nameOrGid,$nameOrGid);

		foreach($teams as $key => $team) {
				$gids = array($team['gid']);
				if ( $childs = db()->fetchAll("SELECT gid,name,parent,lft,rgt FROM `groups` WHERE lft>=%d AND rgt<=%d AND NOT name LIKE '-%%' ORDER BY lft",$team['lft'],$team['rgt']) ) {
				foreach($childs as $line)
					$gids[] = $line['gid'];

				$memberList = array();
				if ( $members = db()->fetchAll("SELECT users.uid, users.username,users.firstname, users.lastname,  membership.gid FROM membership JOIN users using(uid) WHERE gid IN (".implode($gids,',').")") ) {
					foreach($members as $key2 => $line) {
						unset( $line['gid']);
						$memberList[$members[$key2]['gid']][] = $line;
					}
				}

				$teams[$key] = self::createGroupTree($childs,$memberList);
			}
		}

		return $teams;
	}



	static function createGroupTree($list,$members = array()) {
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
					$level['childs'] = self::createGroupTree($childs, $members);
				}

			}
			//insert users into level
			unset($level['lft']);
			unset($level['rgt']);

			if ( isset($members[$level['gid']]) ) {
				$level['members'] = $members[$level['gid']];
			}

			$return[] = $level;
		}	

		return $return;
	}
}

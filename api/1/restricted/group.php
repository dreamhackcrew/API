<?php

class group extends service {
	function construct() {
        $this->tree = new hierarchical('groups');
	}
	function _get($gid=null) {

		if ( !is_numeric($gid) )
			return array('error'=>'Group id must be numeric');

        // Fetch all events that the authorized user is a member of
        $events = array_flip(db()->fetchAllOne("SELECT event FROM membership JOIN groups USING(gid) WHERE uid=%d AND event>0",$_SESSION['id']));

		//return $this->tree->tree( $gid,'','gid,name,parent,event');
	
		//Get a list of all subgroups
		$groups = $this->tree->do_list( $gid,array_flip(array('gid','name','parent','event')));

		$group_ids = array();
		foreach($groups as $key=>$line)
			if ( in_array($line['event'],$events) )
				unset($groups[$key]);
			else
				$group_ids[] = $line['gid'];

		// Get all group and subgroup members
		$members = db()->fetchAll('SELECT gid,uid,username,firstname,lastname FROM membership JOIN users USING (uid) WHERE membership.gid IN (%s)',implode(',',$group_ids) );

		// Incorperate all members to the group list
		foreach($groups as $key => $group) {
			foreach($members as $key2 => $member) {
				if ( $group['gid'] == $member['gid'] ) {
					if ( $pictures = db()->fetchAll("SELECT max(id) id,ident FROM images WHERE ident LIKE 'users.%.".$member['uid']."' GROUP BY ident") )
						foreach($pictures as $key => $line) {
							switch(substr($line['ident'],0,11) ) {
								case 'users.badge':
									if ( $hash = db()->fetchOne("SELECT file FROM images WHERE id=%d LIMIT 1",$line['id']) )
										$member['badge_picture'] = "api.crew.dreamhack.se/1/image/".$hash;
									break;
								case 'users.press':
									if ( $hash = db()->fetchOne("SELECT file FROM images WHERE id=%d LIMIT 1",$line['id']) )
										$member['profile_picture'] = "api.crew.dreamhack.se/1/image/".$hash;
									break;
							}
						}
					unset($member['gid']);
					$groups[$key]['members'][] = $member;
					unset($members[$key2]);
				}
			}
		}
		return $groups;
	}
}

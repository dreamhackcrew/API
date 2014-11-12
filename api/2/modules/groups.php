<?php

class Groups extends BaseModule {
	static function getAll() { //TODO: shorten to one query (low importance)
		if(self::isAdmin()){
			//max: admins get all groups returned
			$events = getDatabase()->all("SELECT group_id,name FROM groups WHERE NOT deleted");
		} else {
			//max: everyone else only gets groups where they are members returned
	        $events = getDatabase()->all("SELECT groups.group_id,groups.name FROM membership JOIN groups USING(group_id) WHERE user_id=:u AND NOT deleted GROUP BY group_id",array('u'=>$_SESSION['user_id']));
		}
		return $events;
    }

	static function get($groupId) { 

		if(self::isAdmin()){
			//admin
			$group = getDatabase()->one("SELECT groups.group_id,groups.name FROM groups WHERE group_id=:id AND NOT deleted",array('id'=>$groupId));
		} else {
			//user
			$group = getDatabase()->one("SELECT groups.group_id,groups.name FROM membership JOIN groups USING(group_id) WHERE user_id=:u AND group_id=:id AND NOT deleted",array('u'=>$_SESSION['user_id'],'id'=>$groupId)); 
		}

		return $group;
	}

	static function put($groupId, $post) {
		getApi()->checkFields($post,array(
            'required' => array(
                'name',
            ),
			'optional' => array(
				'group_id',
            )
        ));

		if(self::isAdmin()){
			return getDatabase()->update('groups',array('name'=>$post['name']), 'WHERE group_id=:gid',array('gid'=>$groupId), 'group_id', array('group_id','name'));
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}
	}

	static function getScans($groupId) { //TODO: Security
		if ( getDatabase()->one('SELECT user_id FROM membership WHERE user_id=:u AND group_id=:g',array('u'=>$_SESSION['user_id'],'g'=>$groupId)) ) {
			return getDatabase()->all('SELECT * FROM lists JOIN scans USING(list_id) WHERE group_id=:gid',array('gid'=>$groupId) );
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}

	}

	static function createGroup($post) {
		getApi()->checkFields($post,array(
			'required' => array(
				'name'
			),
			'optional' => array(
			)
		));

		if(self::isAdmin()){
			if ( !getDatabase()->one('SELECT * FROM groups WHERE name=:n AND NOT deleted',array('n'=>$post['name'])) ) {
				return getDatabase()->insert('groups',$post,'group_id',array('group_id','name'));
			} else {
			http_response_code(403);
			trigger_error('Group already exists');	
			}
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}
	}

	static function removeGroup($groupId) { 
		if(self::isAdmin()){
			return getDatabase()->update('groups',array('deleted'=>1, 'deleted_by'=>$_SESSION['user_id']), 'WHERE group_id=:gid', array('gid'=>$groupId), 'group_id', array('group_id','deleted'));
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}

	}

	static function getMembers($groupId) { 
		if(self::isAdmin()){
			return getDatabase()->all('SELECT user_id,name FROM users JOIN membership USING(user_id) WHERE group_id=:gid',array('gid'=>$groupId) );
		} else { //user is not admin
			if ( getDatabase()->one('SELECT user_id FROM membership WHERE user_id=:uid AND group_id=:gid',array('uid'=>$_SESSION['user_id'],'gid'=>$groupId)) ) {
				return getDatabase()->all('SELECT group_id,user_id,users.name FROM membership JOIN users USING(user_id) WHERE group_id=:gid',array('gid'=>$groupId) );
			} else {
			http_response_code(403);
			trigger_error('Access is denied');
			}	
		}
	}

	static function removeMember($groupId,$userId) { 
		if(self::isAdmin()){
			$result = getDatabase()->execute('DELETE FROM membership WHERE group_id=:g AND user_id=:u',array('u'=>$userId,'g'=>$groupId));
			if(!empty($result))
				return new stdClass();
			return false;
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}
	}

	static function addMember($groupId,$user) { 
		if(!self::isAdmin()){
			http_response_code(403);
			return trigger_error('Access is denied');	
		} 

		getApi()->checkFields($user,array(
			'required' => array(
				'user_id'
			),
			'optional' => array(
				'name'
			)
		));

		if( $existingUser = self::getMember($groupId,$user['user_id']) )
			return $existingUser;

		unset($user['name']);
		$user['group_id'] = $groupId;
		if(getDatabase()->insert('membership',$user)){
			return self::getMember($groupId,$user['user_id']);
		}
	}

	static function getMember($groupId,$userId){
		return getDatabase()->one('SELECT user_id,name FROM users JOIN membership USING(user_id) WHERE user_id=:uid AND group_id=:gid',array('gid'=>$groupId,'uid'=>$userId) );
	}
}

?>

<?php

class Users {
	static function getAll() {
		$users = getDatabase()->all('SELECT users.user_id,users.name,users.email FROM users
									LEFT JOIN membership USING(user_id) 
									WHERE (group_id IN(SELECT group_id FROM `membership` WHERE user_id=:u) OR (SELECT count(user_id) FROM `membership` WHERE user_id=:u AND group_id=0) >0) AND NOT deleted
									group by users.user_id',array('u'=>$_SESSION['user_id']) );
        return $users;
    }

    static function get($userId) {
		$user = getDatabase()->all('SELECT users.user_id,users.name,users.email FROM users 
									LEFT JOIN membership USING(user_id) 
									WHERE (group_id IN(SELECT group_id FROM `membership` WHERE user_id=:u) OR (SELECT count(user_id) FROM `membership` WHERE user_id=:u AND group_id=0) >0) AND user_id=:user_id AND NOT deleted 
									group by users.user_id',array('u'=>$_SESSION['user_id'],'user_id'=>$userId) );
		return $user;
    }

    static function getScans($userId) {
        if ( !getDatabase()->one("SELECT user_id FROM users WHERE user_id=:id",array('id'=>$userId)) )
            return array();

        if ( !$scans = getDatabase()->all("SELECT * FROM scans WHERE user_id=:id",array('id'=>$userId)) )
            return array();

        return $scans;
    }


    static function put($userId, $post) {
        $fields = getApi()->checkFields($post,array(
            'required' => array(
                'name',
                'email',
            ),
            'optional' => array(
				'user_id',
				'password'
            )
        ));

		if (isset($post['password'])) {
			$post['password'] = sha1($post['password']);
		}

        unset($fields['user_id'],$fields['password']);

        return getDatabase()->update('users',$post, 'WHERE user_id=:id',array('id'=>$userId),  'user_id',$fields);
	}

	static function removeUser($userId) {

		//max: get a list of both the remover and the target to compare group memberships
		$remover_groups = getDatabase()->allone('SELECT group_id FROM membership WHERE user_id=:id',array('id'=>$_SESSION['user_id']));
		$target_groups  = getDatabase()->allone('SELECT group_id FROM membership WHERE user_id=:id',array('id'=>$userId));

		$isAdmin = (in_array(0,$remover_groups))? true:false;

		$diff = array_intersect($target_groups, $remover_groups);
		
		//max: if array is not empty, delete the user from these groups
		if (!empty($diff) ) {
			getDatabase()->execute('DELETE FROM membership WHERE group_id IN ('.implode(',',$diff).') AND user_id=:u',array('u'=>$userId));
		}	
		if ($isAdmin) 
			getDatabase()->execute('DELETE FROM membership WHERE group_id IN ('.implode(',',$target_groups).') AND user_id=:u',array('u'=>$userId));
		
		//max: if user doesn't belong to any groups, set users as deleted
		if (!getDatabase()->one('SELECT user_id FROM membership WHERE user_id=:u',array('u'=>$userId)) ) {
			return getDatabase()->update('users',array('deleted'=>1, 'deleted_by'=>$_SESSION['user_id']), 'WHERE user_id=:u',array('u'=>$userId), 'user_id', array('user_id','deleted'));
		}
	}

	static function createUser($post) {
		$fields = getApi()->checkFields($post,array(
			'required' => array(
				'name',
				'password',
				'group'
			),
			'optional' => array(
				'email',
			)
		));

		//check is user is logged in and is member of group he/she is trying to create user in.
		if (!getDatabase()->one("SELECT user_id FROM membership WHERE (group_id=:g OR group_id=0) and user_id=:u",array('g'=>$post['group'], 'u'=>$_SESSION['user_id'])) ) {
			http_response_code(401);
			trigger_error('Access is denied: You dont belong to this group');
		}

		//Check if username is already taken
		if ( getDatabase()->one("SELECT user_id FROM users WHERE name=:n AND NOT deleted",array('n'=>$post['name'])) ) {
			//username already exists
			http_response_code(400);
			trigger_error('Error: Username already exists');
		}

		//else create the user

		//Hash the password
		$post['password'] = sha1($post['password']);

		//update the array with new fields to return
		unset($fields['password']);
		unset($fields['group']);
		$fields['user_id'] = 'user_id';

		//remoev the group from post
		$group = $post['group'];
		unset($post['group']);

		// Insert user into database, and save result for later return.
		$user = getDatabase()->insert('users',$post,'user_id',$fields);

		// Add user to group, if group_id > 0
		if ( $group > 0 ) {
			// check if group actually exists
			if ( getDatabase()->one('SELECT group_id FROM groups WHERE group_id=:g',array('g'=>$group)) ) {
				getDatabase()->insert('membership',array('user_id'=>$user['user_id'], 'group_id'=>$group));
			} else {
				http_response_code(500);
				trigger_error('Error: Group does not exist');
			}
		}

		return $user;
	}

}

?>

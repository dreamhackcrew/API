<?php

class Lists extends BaseModule {
    static function getAll() {
		if(self::isAdmin()){
            // Admins
            if ( !$lists = getDatabase()->all("SELECT lists.list_id,lists.name,groups.group_id,groups.name `group_name`  FROM groups LEFT JOIN lists USING (group_id) WHERE not lists.deleted ORDER BY groups.name,lists.name") ) 
                return array();
        } else {
            // Normal users
            if ( !$lists = getDatabase()->all("SELECT lists.list_id,lists.name,groups.group_id,groups.name `group_name`  FROM membership LEFT JOIN lists USING (group_id) JOIN groups USING (group_id) WHERE user_id=:uid AND not lists.deleted AND not groups.deleted ORDER BY groups.name,lists.name",array('uid'=>$_SESSION['user_id'])) ) 
                return array();
        
        }

        $lists = self::addDefaultGroups($lists);

        return $lists;
    }

    static function get($listId) {
        if(self::isAdmin())
            return getDatabase()->one("SELECT lists.list_id,lists.name,groups.group_id,groups.name `group_name` FROM lists JOIN groups USING (group_id) WHERE list_id=:lid",array('lid'=>$listId) );

        if ( !$list = getDatabase()->one("SELECT lists.list_id,lists.name,groups.group_id,groups.name `group_name` FROM membership LEFT JOIN lists USING (group_id) JOIN groups USING (group_id) WHERE user_id=:uid AND list_id=:lid",array('uid'=>$_SESSION['user_id'],'lid'=>$listId)) )
            return new stdClass;

        return $list;
    }

    static function add($post) {
        getApi()->CheckFields($post,array(
			'required' => array(
                'group_id',
                'name'
			),
			'optional' => array(
			)
		));

        // TODO: Safety
        // TODO: Check name length

        $list_id = getDatabase()->insert('lists',$post);

        return getDatabase()->one("SELECT lists.list_id,lists.name,groups.group_id,groups.name `group_name`  FROM lists JOIN groups USING (group_id) WHERE list_id=:list_id AND not lists.deleted AND not groups.deleted",array('list_id'=>$list_id) ); 
    }

    static function edit($list_id,$post) {
        getApi()->CheckFields($post,array(
			'required' => array(
                'name'
			),
            'optional' => array(
                'list_id',
                'group_id',
                'group_name',
			)
        ));

        unset($post['list_id']);
        unset($post['group_id']);
        unset($post['group_name']);

        // TODO: Safety
        // TODO: Check name length

        getDatabase()->update('lists',$post,'WHERE list_id=:id',array('id'=>$list_id));

        return getDatabase()->one("SELECT lists.list_id,lists.name,groups.group_id,groups.name `group_name`  FROM lists JOIN groups USING (group_id) WHERE list_id=:list_id",array('list_id'=>$list_id) ); 
    }

    static function delete($list_id) {
        // TODO: Safety

        getDatabase()->update('lists',array('deleted'=>1),'WHERE list_id=:id',array('id'=>$list_id));

        return true;
    }


//#### BY GROUP (/groups/x/lists) #######################################################################################
    static function getAllByGroup($groupId) {
        if ( !$lists = getDatabase()->all("SELECT * FROM lists WHERE group_id=:gid ORDER BY name",array('gid'=>$groupId)) )
            return array();

        $lists = self::addDefaultGroups($lists);

        return $lists;
    }

    static function getByGroup($groupId, $listId) {
        if ( !$list = getDatabase()->one("SELECT * FROM lists WHERE group_id=:gid AND list_id=:lid",array('gid'=>$groupId,'lid'=>$listId)) )
            return new stdClass;

        return $list;
    }

    static function addByGroup($group_id,$post) {
        getApi()->CheckFields($post,array(
			'required' => array(
                'name'
			),
			'optional' => array(
			)
        ));

        $post['group_id'] = $group_id;

        // TODO: Safety
        // TODO: Check name length

        $list_id = getDatabase()->insert('lists',$post);

        return getDatabase()->one("SELECT lists.*,groups.group_id,groups.name `group_name`  FROM lists JOIN groups USING (group_id) WHERE list_id=:list_id",array('list_id'=>$list_id) ); 
    }

    static function getScans($groupId, $listId) {
        if ( !getDatabase()->one("SELECT * FROM lists WHERE group_id=:gid AND list_id=:lid",array('gid'=>$groupId,'lid'=>$listId)) )
            return array();

        if ( !$scans = getDatabase()->all("SELECT * FROM scans WHERE list_id=:lid",array('lid'=>$listId)) )
            return array();

        return $scans;
	}

	static function addScan($list, $post) {
		getApi()->CheckFields($post,array(
			'required' => array(
				'tag_id',
			),
			'optional' => array(
				'scanner',
				'scanner_alias',
				'attendee_id',
				'timestamp',
			)
		));

		//max: check to see if user has access to post to this list
		if ( !getDatabase()->one('SELECT group_id,list_id FROM membership JOIN lists USING(group_id) WHERE user_id=:u AND list_id=:l',array('u'=>$_SESSION['user_id'], 'l'=>$list)) ) {
			http_response_code(403);
			trigger_error('Access to list is denied');
		}

		//max: check if tag has a unique user associated with it, if not, send all the people associated with the order_id and make them select themselfs.
		if ( $tag = getDatabase()->one('SELECT * FROM tags WHERE tag_id=:t AND attendee_id=0',array('t'=>$post['tag_id'])) ) {
			return getDatabase()->all('SELECT * FROM cust_orders JOIN cust_attendee USING(attendee_id) WHERE order_id=:oid',array('oid'=>$tag['order_id']));

		} else {

		    //max: add some IMPORTANT stuff to the array.
		    $post['user_id'] = $_SESSION['user_id'];
		    $post['list_id'] = $list;	

			//max: now insert the tag
			getDatabase()->insert('scans',$post,'scan_id');
			$tag = getDatabase()->one('SELECT * FROM tags WHERE tag_id=:t',array('t'=>$post['tag_id']));
			return getDatabase()->all('SELECT * FROM cust_orders JOIN cust_attendee USING(attendee_id) WHERE attendee_id=:att',array('att'=>$tag['attendee_id']));

		}
	}

	static function listScans($list) {

		// Check to see if user has access to list the content of list.
		if ( !self::isAdmin() && !getDatabase()->one('SELECT group_id,list_id FROM membership JOIN lists USING(group_id) WHERE user_id=:u AND list_id=:l',array('u'=>$_SESSION['user_id'], 'l'=>$list)) ) {
			http_response_code(403);
			trigger_error('Access to list is denied');
		}
		
		//max: return all entry's for that list
		$scans =  getDatabase()->all('
				SELECT scan_id,tag_id,timestamp,cust_attendee.*, scanner,scanner_alias
				FROM scans 
				LEFT JOIN tags USING(tag_id)
				LEFT JOIN cust_attendee USING(attendee_id)
				WHERE list_id=:l
			'
			,array('l'=>$list));


		$scanList = array();
		foreach ($scans as $scanKey=>$scan){
			if(!$acceptedFields = json_decode($scan['accepted_fields']))
				$acceptedFields = array();
				$scanList[$scanKey] = array(
					'scan_id'=>$scan['scan_id'],
					'tag_id'=>$scan['tag_id'],
					'timestamp'=>$scan['timestamp'],
					'scanner'=>$scan['scanner'],
					'scanner_alias'=>$scan['scanner_alias'],
				);
			foreach($scan as $field=>$value){
				if(in_array($field,$acceptedFields) && $value !== null){
					$scanList[$scanKey]['fields'][$field] = $value;
                }

            }
            
            $scanList[$scanKey]['avatar'] =  'http://www.gravatar.com/avatar/'.md5(strtolower(trim($scan['email'])));
			
		}
		
		return $scanList;
    }






    static function addDefaultGroups($lists) {
        // Add default lists
        foreach($lists as $key => $list) {
            // Ignore all groups that have a list
            if ( !is_null($list['list_id']) )
                continue;

            $newList = getDatabase()->insert('lists',array(
                'group_id' => $list['group_id'],
                'name' => 'Default',
            ),'list_id');

            if ( $newList ) {
                $lists[$key]['list_id'] = $newList['list_id'];
                $lists[$key]['name'] = $newList['name'];
            }
        }

        return $lists;
    }
}

?>

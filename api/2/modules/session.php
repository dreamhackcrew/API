<?php

class Session {    
    static function login($post) {
        // Validate that all required fields are present
        getApi()->checkFields($post,array(
            'required' => array(
                    'username','password'
            ),
			'optional' => array(
					'sessionId'
			)
        ));
        // Validate user against database

        if ( !$user_id = getDatabase()->one("SELECT user_id FROM users where (name=:u OR (email=:u AND email>'')) and password=:p",array('u'=>$post['username'],'p'=>sha1($post['password']))) ) {
            http_response_code(401);
            trigger_error('Unauthorized');
        }

		if(!session_id())
			session_start();

        $_SESSION['user_id'] = $user_id['user_id'];

        return array('sessionId'=>session_id());
   }

    static function logout() {
        // Logout function
		session_destroy();		
    }
}

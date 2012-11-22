<?php

function filter_methods($method) {
    if ( substr($method,0,1)=='_' && substr($method,1,1)!='_' )
        return substr($method,1);
    else
        return null;
}

class service {
    function __construct($request) {

        $command = '_'.$request[0];
        array_shift($request);

        if ( !is_callable(array($this,$command)) ) {
            header('HTTP/1.0 404 Not found');
            response(array(
                'error'=>'Method not found!',
                'available'=>array_filter(array_map('filter_methods',get_class_methods($this)))
            ));
        }

        response(call_user_func_array(array($this,$command),$request));
    }
}

?>

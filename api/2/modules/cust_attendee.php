<?php

class cust_attendee {
    static function post($post) {
         $fields = getApi()->checkFields($post,array(
             'required' => array(
                'code',
                'firstname',
                'lastname',
                'username',
                'email',
                'ref_user', 
                'phone', 
                'nationality', 
                'country', 
                'gender', 
                'city', 
                'address', 
                'zip',
                'accepted_fields',
                'order_owner',
            ),
            'optional' => array(
                'ordernr'
            )
        ));

        $post['attendee_id'] = $post['ref_user'];
        $order['order_id'] = $post['ordernr'];
        $order['attendee_id'] = $post['ref_user'];
        $order['code'] = $post['code'];
        $order['order_owner'] = $post['order_owner'];
        $order['event_id'] = 1;
        $order['revision'] = getDatabase()->one('SELECT revision FROM cust_orders WHERE order_id=:order_id AND event_id=:event_id AND code=:code',array('order_id'=>$order['order_id'],'event_id'=>$order['event_id'],'code'=>$order['code']));
        $order['revision'] = $order['revision']['revision'] + 1;
        unset($post['ref_user']);
        unset($fields['ref_user']);
        unset($post['ordernr']);
        unset($post['code']);
        unset($post['order_owner']);

        getDatabase()->insertOrUpdate('cust_orders',$order,array('order_id','event_id','code'));

        getDatabase()->insertOrUpdate('cust_attendee',$post,'attendee_id',$fields);

        return $post['attendee_id'];
    }
}


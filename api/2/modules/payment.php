<?php

class Payment extends BaseModule {

	static function hasCredit($attId) {
		if ( getDatabase()->execute("SELECT * FROM balance WHERE attendee_id=:attid AND has_credit",array('attid'=>$attId) ) ) {
			return true;
		}	
		return false;
	}

	static function validateAccount($attId) {
		if ( !getDatabase()->one('SELECT * FROM balance WHERE attendee_id=:att_id',array('att_id'=>$attId)) ) {;
			getDatabase()->insert('balance',array('attendee_id'=>$attId,'balance'=>0));
			getDatabase()->insert('log',array('attendee_id'=>$attId,'type'=>'payment','message'=>'created balance for attendee_id: '.$attId),'log_id');
		}
	}

	static function getPrepaid($attId) {
		return getDatabase()->all('SELECT prepaid_id,product_id FROM prepaid WHERE attendee_id=:attid AND NOT used', array('attid'=>$attId) );
	}

	static function getBalance($attId) {
		$balance = getDatabase()->one('SELECT balance FROM balance WHERE attendee_id=:attid', array('attid'=>$attId) );
		return (int)$balance['balance'];
	}

	static function searchPrepaid(&$prepaidList,$productId) {
		foreach ( $prepaidList as $key => $prepaid ) {
			if ( $prepaid['product_id'] == $productId ) {
				global $usedPrepaid;
				$usedPrepaid[] = $prepaidList[$key]['prepaid_id'];
				unset($prepaidList[$key]);
				return true;
			}
		}
		return false;
	}

	static function getProductCost($productId) {
		$costtax = getDatabase()->one('SELECT net,tax FROM products WHERE product_id=:pid', array('pid'=>$productId) );
		return $costtax;
	}


	/* */

	static function genReceipt($receiptId) {
		if ( self::isSalespoint() ) {
			$logged = getdatabase()->one('SELECT * FROM receipts WHERE id=:rid',array('rid'=>$receiptId));
			return $logged;	
	
		}
	}

	static function logReceipt($transactions, $attId, $userId, $tag_id, $scanner, $scanner_alias) {
		$event_id = getDatabase()->one('SELECT event_id FROM tags WHERE tag_id=:tagid',array('tagid'=>$tag_id));
		$total_net = "0";
		$total_tax = "0";

		foreach ( $transactions as $transaction ) {
			$total_net += $transaction['net'];
			$total_tax += $transaction['tax'];
		}

		$insert = array(
			'event_id' => $event_id['event_id'],
			'attendee_id' => $attId,
			'tag_id' => $tag_id,
			'total_net' => $total_net,
			'total_tax' => $total_tax,
			'scanner' => $scanner,
			'scanner_alias' => $scanner_alias,
			'user_id' => $userId,
			'receipt' => json_encode($transactions)
		);

	getDatabase()->insert('receipts',$insert);

	}


	static function credit($attId,$post) { //TODO: Add consumed_location to log
		if ( self::isSalespoint() ) {
			self::validateAccount($attId);
			$transactions = $post['transactions'];
			$tag_id = $post['tag_id'];
			$scanner = $post['scanner'];
			$scanner_alias = $post['scanner_alias'];
			$userId = $_SESSION['user_id'];

			$balance = self::getBalance($attId);
			$prepaidList = self::getPrepaid($attId);
			$success = "true";

			global $usedPrepaid;
			$usedPrepaid = array();
			$newBalance = "";

			/*
			 * if the customer has prepaid items, use these first. If there are not enough prepaid
			 * items, go over to balance. If the customer doesn't have enough balance, check if user
			 * has credit. If user has credit, let customer go to negative, if not, return false.
			 */

			foreach ( $transactions as $key => $transaction ) {
				if ( !isset($transaction[$key]['prepaid']) )
					$transactions[$key]['prepaid'] = 0;
				if ( !isset($transaction[$key]['debit']) )
					$transactions[$key]['debit'] = 0;
				if ( !isset($transaction[$key]['net']) )
					$transactions[$key]['net'] = 0;
				if ( !isset($transaction[$key]['tax']) )
					$transactions[$key]['tax'] = 0;
				
				for ( $i=0 ; $i < $transaction['amount'] ; $i++ ) {
					if ( self::searchPrepaid($prepaidList,$transaction['product_id']) ) {
						$transactions[$key]['prepaid']++;
					} else {
						$cost = self::getProductCost($transaction['product_id']);
						$totalcost = $cost['net']+$cost['tax'];
						if ( self::hasCredit($attId) ) {
							$balance-=$totalcost;
							$transactions[$key]['debit'] += $totalcost;
							$transactions[$key]['net'] += $cost['net'];
							$transactions[$key]['tax'] += $cost['tax'];
							$newBalance += $totalcost;
						} else {
							//does not have credit
							if ( $balance-$totalcost < 0 ) {
								$balance-=$totalcost;
								$transactions[$key]['debit'] += $totalcost;
								$transactions[$key]['net'] += $cost['net'];
								$transactions[$key]['tax'] += $cost['tax'];
								$newBalance += $totalcost;
								$success = "false";
							} else {
								$balance-=$totalcost;
								$transactions[$key]['debit'] += $totalcost;
								$transactions[$key]['net'] += $cost['net'];
								$transactions[$key]['tax'] += $cost['tax'];
								$newBalance += $totalcost;
							}
						}
					}
				}
			}

			/*
			 * If true, proceede to remove prepaid items from $usedPrepaid and $newBalance.
			 */
			foreach ( $usedPrepaid as $prepaid ) {
				getDatabase()->update('prepaid', array('used'=>1, 'consumed'=>date('Y-m-d H:m:s')), 'WHERE prepaid_id=:ppid', array('ppid'=>$prepaid));
				getDatabase()->insert('log',array('attendee_id'=>$attId,'type'=>'payment','message'=>'consumed prepaid item: '.$prepaid),'log_id');
			}
			
				getDatabase()->execute('UPDATE balance SET balance = balance - :negbal WHERE attendee_id=:attid', array('negbal'=>$newBalance, 'attid'=>$attId)); 
				getDatabase()->insert('log',array('attendee_id'=>$attId,'type'=>'payment','message'=>'withdrew amount from balance: '.$newBalance),'log_id');

			self::logReceipt($transactions, $attId, $userId, $tag_id, $scanner, $scanner_alias);
		}
		return $transactions;
	}


	/*
	static function debit($attId,$data) {
	
	}
	 */
	
	static function addPrepaid($attId, $post) {;
		if(self::isAdmin()) {;
		 getApi()->checkFields($post,array(
            'required' => array(
				'product_id',
            ),
			'optional' => array(
            )
		));

		$ins = getDatabase()->insert('prepaid',array('attendee_id'=>$attId,'product_id'=>$post['product_id'],'purchased'=>date('Y-m-d H:i:s')),'prepaid_id',array('prepaid_id') );
		
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}
		return $ins;
	}
	
	static function usePrepaid($attId, $post) {
		if(self::isAdmin()) {
		 getApi()->checkFields($post,array(
            'required' => array(
				'product_id',
				'consumed_location'
            ),
			'optional' => array(
            )
		));

		 if ( !getDatabase()->one('SELECT * FROM prepaid WHERE attendee_id=:attid AND product_id=:prod AND NOT used', array('attid'=>$attId, 'prod'=>$post['product_id'])) ) {
			 echo "customer doesn't have enough prepaid for this product";
		 } 

		$ret = getDatabase()->update('prepaid', array('used'=>1,'consumed'=>date('Y-m-d H:i:s'),'consumed_location'=>$post['consumed_location']), 'WHERE attendee_id=:attid AND NOT used LIMIT 1', array('attid'=>$attId), 'prepaid_id', array('prepaid_id','product_id','used') );

		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}
		return $ret;
	}

	static function listPrepaid($attId) {
		if(self::isAdmin()) {
			return getDatabase()->all('SELECT * FROM prepaid WHERE attendee_id=:attid AND NOT used', array('attid'=>$attId));
		} else {
			http_response_code(403);
			trigger_error('Access is denied');	
		}
	}

}

//tables
//-log (user)
//-products (products)
//-coupons (payment)
//-balance (payment)
//
//
// get list of items to be purchased.
//
// (array) get current prepaid list from database
// (int) get current balance from database
//
// foreach item in purchase list, check in prepaid array if prepaid item exists. if prepaid doesn't exist, subtract from balance.


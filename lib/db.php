<?php

require_once('config.php');

function db(){
    return db::getInstance();
}

class db {
	const		version		= '3.0.0';
	const		database	= 'mysql';
    public		$_db;
    private $tables = array();
    protected static $instance = null;
    private $_mysqli = null;
    private $prefix=null;
    private $repaircount = 0;

	// __construct {{{
	/**
	* Initiate database connect
	*
	*/
	public function __construct()
	{
	//register_shutdown_function('session_write_close');
	}
	// }}}
    function __destruct(){
        //if($this->_mysqli)
        //    $this->_mysqli->close();
    }

	function ping() {
        if($this->_mysqli)
			return $this->_mysqli->ping();

		return false;
	}
    function connect($server,$user,$password,$database){

        $this->_mysqli = mysqli_init();

		if(!$this->_mysqli->real_connect($server, $user, $password,$database)){
            $this->_mysqli = null;
			return !trigger_error('Broken connection to database server: '.$server."<br/>\n".mysqli_connect_error(),E_USER_WARNING);
        }

        $this->scanTables();
        /* change character set to utf8 */
        if (!$this->_mysqli->set_charset("utf8"))
            trigger_error('Error setting charset to utf8!',E_USER_WARNING);
    }
    public static function getInstance($AutoCreate=false) {// {{{
        if($AutoCreate===true && !self::$instance) {
            self::init();
        }
        return self::$instance;
    }
   // }}}
    public function setPrefix($p) {// {{{
        $this->prefix = $p;
    }
   // }}}
    public function getPrefix() {// {{{
        return $this->prefix;
    }
   // }}}
    public static function init() {// {{{
        return self::$instance = new self();
    }
   // }}}
  // scanTables {{{
   private function scanTables()
   {
       //if(!$this->tables)
		if ( $tables = $this->fetchAllOne("SHOW tables") )
			foreach( $tables as $table ){
                /* THIS IS TO SLOW TO USE
                $tmp = array();
                $fields = $this->fetchAll("SHOW FIELDS FROM `".$table."`");
                foreach($fields AS $field){
                    $tmp[] = $field['Field'];
                }
                */
				$this->$table =  $table ;
            }
       return true;
   }

   // }}}

   // escapeStr {{{
   /**
    * Perform mysql_real_escape_string on $args
    * Returns escaped (string|array)
    *
    * @param (string|array)   $str, array or string
    */
   public function escapeStr($str)
   {
       if(!$this->_mysqli)
           return false;
      $strip = get_magic_quotes_gpc();

      if(is_array($str)) {                // if array do loop
         foreach($str as $key => $val) {
            if ($strip)
               $val = stripslashes($val);

            $str[$key] =$this->_mysqli->real_escape_string($val);
         }

      } else {                            // else just escape the string.


         if ($strip)
            $str = stripslashes($str);


         $str = $this->_mysqli->real_escape_string($str);
      }
      return $str;
   }

   // }}}
   // insertId {{{
   /**
    * A port of mysql_insert_id()
    *
    * @return  int   mysql_insert_id
    */
   public function insertId()
   {
      return $this->_mysqli->insert_id;
   }

   // }}}
   // query {{{
   /**
    * perform a database query
    *
    * @param string $sql the sql statement
    */
   public function query($sql)
   {    
      if($this->_mysqli === null)
          return false;
      $orgsql = $sql;

      if ( trim($sql) == '' ){
         return !@trigger_error('Database query was emty!');
      }

      if ( func_num_args() > 1 ) {
          $args = array_slice(func_get_args(),1);
          $sql = vsprintf($sql,$this->escapeStr($args));
      }

      $sql = str_replace('##',$this->getPrefix(),$sql);

      if( !$q = $this->_mysqli->query($sql) ) {
        trigger_error('A database error have occurred, please contact your system administrator!');
        echo $sql;
        echo $this->_mysqli->error;;
      }

      return $q;

   }

   // }}}
   // fetchRow {{{
   /**
    * fetch one row with mysql_fetch_row
    * Returns array or false
    *
    */
   public function fetchRow($qryId = null)
   {
      if ($qryId != null)
         return ($r = $qryId->fetch_row() ) ? $r : false;
      return false;
   }

   // }}}
   // fetchAssoc {{{
   /**
    * Fetch one row with mysql_fetch_assoc
    * Returns associative array or false
    *
    */
   public function fetchAssoc($qryId = null)
   {
      if ($qryId)
         return ($r = $qryId->fetch_assoc() ) ? $r : false;
      return false;
   }

   // }}}
   // affectedRows {{{
   /**
    * do mysql_affected_rows
    *
    *
    */
   public function affectedRows()
   {
      return $this->_mysqli->affected_rows;
   }
   // }}}
   // numRows {{{
   /**
    * do mysql_num_rows
    *
    *
    */
   public function numRows()
   {
      $this->_mysqli->store_result();
      return $this->_mysqli->num_rows;
   }
   // }}}

   // fetchAll {{{
   /**
    * Perform query.
    * Return multidimensional array or false
    *
    * @param string  $sql sql query string
    */
   public function fetchAll($sql = '')
   {
      if ( func_num_args() > 1 ) {
          $args = array_slice(func_get_args(),1);
          $args = $this->escapeStr($args);
          $sql = vsprintf($sql,$args);
      }

      $q = $this->query($sql);

      if ($q) {
         $r = array();

         while($result = $this->fetchAssoc($q) ) {
            $r[] = $result;
         }

         //mysql_free_result($q);
         $q->close();
         return (count($r) > 0) ? $r : false;
      } else {
         return false;
      }
   }

   // }}}
   // fetchSingle {{{
   /**
    * Perform query.
    * Return array or false
    *
    * @param string  $sql sql query string
    */
   public function fetchSingle($sql = '')
   {

      if ( func_num_args() > 1 ) {
          $args = array_slice(func_get_args(),1);
          $args = $this->escapeStr($args);
          $sql = vsprintf($sql,$this->escapeStr($args));
      }

      if( $q = $this->query($sql) ) {
          if($r = $this->fetchAssoc($q)){
            //mysql_free_result($q);
            $q->close();
            return $r;
          }
          return array();
      }
      return false;

   }

   // }}}
   // fetchOne {{{
   /**
    * Perform query.
    * Return array or false
    *
    * @param string  $sql sql query string
    */
   public function fetchOne($sql = '')
   {
      if ( func_num_args() > 1 ) {
          $args = array_slice(func_get_args(),1);
          $args = $this->escapeStr($args);
          $sql = vsprintf($sql,$this->escapeStr($args));
      }


      if ( $q = $this->query($sql) ) {
          if($r=$this->fetchRow($q)){
                //mysql_free_result($q);
                $q->close();
                return $r[0];
          }
      }
      return  false;

   }

   // }}}
   // fetchAllOne {{{
   /**
    * Perform query.
    * Return array or false
    *
    * @param string  $sql sql query string
    */
   public function fetchAllOne($sql = '')
   {
      if ( func_num_args() > 1 ) {
          $args = array_slice(func_get_args(),1);
          $args = $this->escapeStr($args);
          $sql = vsprintf($sql,$this->escapeStr($args));
      }

      $q = $this->query($sql);

      if ($q) {
         $r = array();

         while($result = $this->fetchRow($q) ) {
            $r[] = $result[0];
         }

        // mysql_free_result($q);
         $q->close();
         return (count($r) > 0) ? $r : false;
      } else {
         return false;
      }


   }

   // }}}

   // insert {{{
   /**
    * Return select sql statement
    *
    * @param array   $fields  fields to insert
    * @param string  $table   table to do insert on
    */
   public function insert($fields,$table)
   {

      if ( !is_array($fields) || count($fields)==0 )
        return !trigger_error('Can not insert a post without data in the database!',E_USER_WARNING);

      $table  = $this->escapeStr($table);

      $sql    = sprintf('INSERT INTO `%s` SET ',$table);

      foreach($fields as $key => $val) {
         $key = $this->escapeStr($key);
         $val = $this->escapeStr($val);

         if (!isset($notFirst)) {
            $notFirst = 'Y';
         } else $sql .= ', ';

         if (is_int($val)) {
            $sql .= sprintf('`%s` = %d', $key, intval($val) );
         } elseif (is_array($val)) {
            $sql .= sprintf('`%s` = %s', $key, $val['txt']);
         } elseif (is_string($val)) {
            $sql .= sprintf('`%s` = "%s"', $key, $val);
         } else {
            return false;
         }
      }

      if ( $q = $this->query($sql)) return ( $this->insertId() ? $this->insertId() : $q );
      
	  return false;
   }
    // }}}
   // update {{{
   /**
    * Return select sql statement
    *
    * @param array   $fields  fields to insert
    * @param string  $table   table to do insert on
    */
   public function update($fields,$table,$where)
   {

      if ( !is_array($fields) || count($fields)==0 )
        return !trigger_error('Can not insert a post without data in the database!',E_USER_WARNING);

      $table  = $this->escapeStr($table);

      $sql    = sprintf('UPDATE `%s` SET ',$table);

      foreach($fields as $key => $val) {
         $key = $this->escapeStr($key);
         $val = $this->escapeStr($val);

         if (!isset($notFirst)) {
            $notFirst = 'Y';
         } else $sql .= ', ';

         if (is_int($val)) {
            $sql .= sprintf('`%s` = %d', $key, intval($val) );
         } elseif (is_array($val)) {
            $sql .= sprintf('`%s` = %s', $key, $val['txt']);
         } elseif (is_string($val)) {
            $sql .= sprintf('`%s` = "%s"', $key, $val);
         } else {
            return false;
         }
      }

      if ( func_num_args() > 3 ) {
          $args = array_slice(func_get_args(),3);
          $where = vsprintf($where,$this->escapeStr($args));
      }

      $sql .= " ".$where;

      if ( $q = $this->query($sql)) return ( $this->insertId() ? $this->insertId() : $q );
      
	  return false;
   }
    // }}}

    // }}}
    public static function now(){// {{{
        //returns mysql NOW() format.
        return date("Y-m-d H:i:s");
    }
    // }}}
    public static function curdate(){// {{{
        //returns mysql CURDATE() format.
        return date("Y-m-d");
    }
    // }}}
}

?>

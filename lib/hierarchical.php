<?php

class hierarchical {

    const version = '1.0.0';
	private $info = array();

    function __construct($table,$id=NULL,$child=NULL) {
		$this->table = $table;
		$this->id = $id;
		$this->child = $child;
	}

	function init( $do = NULL, $basic = false ) {

		if ( $do == NULL )
			$do = $this->table;

		if ( isset( $this->info[$do]['basic'] ) && $basic )
			return true;

		if ( isset( $this->info[$do]['ready'] ) )
			return true;
        
		if ( trim($do) == '' )
			return !trigger_error('No table is defined, failed hierarchical initiation',E_USER_ERROR);

        if ( !$tables = db()->fetchAll("DESCRIBE `$do`"))
            return !trigger_error('Table "'.$do.'" don´t exists!',E_USER_ERROR);

		$this->info[$do] = array();

        foreach($tables as $line) {
            if ($line['Key'] == 'PRI')
                $this->info[$do]['id'] = $line['Field'];
            if ($line['Field'] == 'lft')
                $lft_found = true;
            if ($line['Field'] == 'rgt')
                $rgt_found = true;
            if ($line['Field'] == 'prio')
                $this->info[$do]['prio'] = true;
        }

		if( !isset($this->info[$do]['id']) || trim($this->info[$do]['id']) == '' )
			return !trigger_error('Table "'.$do.'" don´t have a primary key!',E_USER_ERROR);

        if ( !isset($lft_found) || !isset($rgt_found) ) {
            db()->installTables(  array(
                $do => array(
                    array(
                        'Field'   => 'lft',
                        'Type'    => 'int(11)'
                    ),
                    array(
                        'Field'   => 'rgt',
                        'Type'    => 'int(11)'
                    ),
                    array(
                        'Field'   => 'parent',
                        'Type'    => 'int(11)'
                    )
                )
            ));
            db()->query("ALTER TABLE `%s` ADD INDEX ( `lft` , `rgt` )",$do);

			if ( !isset($this->id) || trim($this->id) == '' )
				$this->id = $this->info[$do]['id'];

			$this->$this->info[$do]['basic'] = true;

            if ( !$this->rebuild() )
				return false;
        }

		$this->info[$do]['basic'] = true;

		if ( $do == $this->table ) {
			if ( !isset($this->id) || trim($this->id) == '' )
				$this->id = $this->info[$do]['id'];

			if ( ( !isset($this->prio) || trim($this->prio) == '' ) && isset($this->info[$do]['prio']) )
				$this->prio = $this->info[$do]['prio'];

			// integrity check... only on the main table
				if (!$integrity = db()->fetchSingle("SELECT count(*) as rows, max(rgt) as max FROM `{$do}`") )
					return !trigger_error('Table "'.$do.'" failed integrity check',E_USER_ERROR);

				if ( ($integrity['rows'] * 2) != $integrity['max'] && !$basic ) {
					trigger_error('Table "'.$do.'" failed integrity check, trying to repair tree!',E_USER_ERROR);
					if ( !$this->rebuild() )
						return false;
				}
		} else {
			if ( !isset($this->childId) || trim($this->childId) == '' )
				$this->childId = $this->info[$do]['id'];
		}


		
		$this->info[$do]['ready'] = true;

		if ( isset($this->child) && $this->child )
			return $this->init($this->child);

		return true;

    }

    function setId( $id ) {
        if ( is_numeric($id) || strlen($id) < 2 )
            return !trigger_error('Id field must be a string and at least 2 chars long');

        $this->id = $id;
    }

    function setChild( $table ) {
		if ( trim($table) > '' )
	        $this->child = $table;
    }

    function setPrio( $prio ) {
        if ( is_numeric($prio) || strlen($prio) < 2 )
            return !trigger_error('Priority field must be a string and at least 2 chars long');

        $this->info[$this->table]['prio'] = $prio;
    }

	function tree($root,$where='',$what='*') {
		$data = $this->do_tree($root,$where,$what);

        return $data['childs'];
    }

    function get_tree($root,$where = '',$what='*') {

        if (!$this->init())
			return !trigger_error('Hierarchical isn´t initiated!',E_USER_WARNING);

        $where = trim($where);
        if ( $where != '' )
            $where = '('.$where.') AND ';


        $what = explode( ',', $what );
        if ( !in_array('*',$what) ) {
            $what[] = "`{$this->id}`";
            $what[] = "`lft`";
            $what[] = "`rgt`";
        }

        $what = array_unique($what);
        $what = implode($what, ', ');


        if (is_array($root)) {
            if (!$row = db()->fetchSingle("SELECT min(lft) as lft, max(rgt) as rgt FROM {$this->table} WHERE $where parent IN (".implode($root,',').')'))
                return false;
        }
        else {
			if ( $root ) {
	           if (!$row = db()->fetchSingle("SELECT min(lft) as lft, max(rgt) as rgt FROM {$this->table} WHERE $where parent=$root"))
		            return false;
			} else {
				if (!$row = db()->fetchSingle("SELECT min(lft) as lft, max(rgt) as rgt FROM {$this->table} WHERE $where 1=1"))
					return false;
			}
        }
        if (!($row['lft'] > 0 && $row['rgt'] > 0))
            return false;
    
        return db()->fetchAll("SELECT $what FROM {$this->table} WHERE $where lft BETWEEN %d AND %d ORDER BY lft ASC;",$row['lft'],$row['rgt']);
    }

    function do_tree($root,$where='',$what='*') {
        if(!$result = $this->get_tree($root,$where,$what))
            return false;

        $right = array();
        $ret = array();
        $level = array();

        foreach ($result as $row) {
            while (count($right)>0 && ($right[count($right)-1] < $row['rgt'])) {
                array_pop($right);
                array_pop($level);
            }

            $l = '';
            foreach($level as $line)
                $l .= "['childs'][$line]";

            eval('$ret'.$l.'[\'childs\']['.$row[$this->id].'] = $row;');

            $right[] = $row['rgt'];
            $level[] = $row[$this->id];
        }
        return $ret;
    }

    function selectOptions($root,$where='',$head = 'head') {
            if(!$result = $this->get_tree($root,$where))
                return array();

            $right = array();
            $options = array();

            foreach ($result as $row) {
                while (count($right)>0 && ($right[count($right)-1] < $row['rgt'])) {
                    array_pop($right);
                }

                $options[] = array(
                    'text'  => str_repeat(' -',count($right)) . ' ' . $row[$head],
                    'val' => $row[$this->id]
                );

                $right[] = $row['rgt'];
            }
            return $options;
    }

    function do_level($root,$where = '') {
        if(!$result = $this->get_tree($root,$where))
            return false;

        foreach ($result as $row) {
            if ($row['parent'] == $root)
                $ret[] = $row;
        }

        return $ret;
    }

    function do_list( $root,$fields = '' ) {
        if( !$result = $this->get_tree($root) ){
            //@trigger_error('No childs to "'.$root.'" was found!');
            return  array();
        }
        $ret = array();

        foreach ( $result as $row )
            if ( $fields != '' )
                if( is_array($fields) )
                    $ret[] = array_intersect_key($row,$fields);
                else
                    if ( isset($row[$fields]) )
                        $ret[] = $row[$fields];
                    else
                        trigger_error('Field "'.$fields.'" not found in source',E_USER_NOTICE);
            else
                $ret[] = $row;

        return $ret;
    }

    function do_path($id,$field = '',$separator = '') {

        if (!$this->init())
            return !trigger_error('Hierarchical isn´t initiated!',E_USER_WARNING);

        if ( !$data = db()->fetchSingle("SELECT lft,rgt FROM `{$this->table}` WHERE `{$this->id}`='$id'"))
            return !trigger_error('Main post not found in '.$this->table.'!',E_USER_WARNING);

        if( trim($field) != '' ) {
            if( $path = db()->fetchAllOne("SELECT $field FROM {$this->table} WHERE lft <= {$data['lft']} AND rgt >= {$data['rgt']} ORDER BY lft ASC"))
                if (trim($separator) != '')
                    return implode($path,$separator);
                else
                    return $path;
        } else {
            if( $path = db()->fetchAll("SELECT * FROM {$this->table} WHERE lft <= {$data['lft']} AND rgt >= {$data['rgt']} ORDER BY lft ASC"))
                return $path;
        }

        return false;
    }

    function descendants($lft,$rgt) {
        return ($rgt - $lft - 1) / 2;
    }

    function rebuild($parent = 0, $left = 0) {
        db()->query("UPDATE {$this->table} SET lft=-1, rgt=-1");

        if ( !$this->do_rebuild($parent, $left) )
            return !trigger_error('rebuild tree failed',E_USER_ERROR);

        return true;
    }

    function do_rebuild($parent, $left) {

        if ( !$this->init( NULL, true ) )
            return !trigger_error('Hierarchical isn´t initiated, can not save!',E_USER_WARNING);

        if (!isset($this->table))
            return !trigger_error('Table isn´t defined',E_USER_WARNING);

        if (!is_numeric($parent))
            return !trigger_error('$parent isn´t a numeric value',E_USER_WARNING);

        $right = $left+1;

        if ( isset($this->prio) ) {
            if($result = db()->fetchAllOne("SELECT {$this->id} FROM {$this->table} WHERE parent=$parent ORDER BY prio;"))
                foreach($result as $row)
                    $right = self::do_rebuild($row, $right);
        } else {
            if($result = db()->fetchAllOne("SELECT {$this->id} FROM {$this->table} WHERE parent=$parent;"))
                foreach($result as $row)
                    $right = self::do_rebuild($row, $right);
       }

        db()->query("UPDATE {$this->table} SET lft=$left, rgt=$right WHERE {$this->id}=$parent;");
        if ( isset($this->child) && $this->child )
            db()->query("UPDATE {$this->child} SET lft=$left, rgt=$right WHERE {$this->id}=$parent;");

        return $right+1;
    }

    function add( $data, $parent = -1 ) {

        if (!$this->init())
            return !trigger_error('Hierarchical isn´t initiated, can not save!',E_USER_WARNING);
       
        if ( $parent != -1 )
            $data['parent'] = $parent;

        if ( !isset($data['parent']) )
            return !trigger_error('Input parent id isn´t set, can not save!',E_USER_WARNING);

        if ( !is_numeric($data['parent']) )
            return !trigger_error('Input parent isn´t a numeric value, can not save!',E_USER_WARNING);

        if ( isset($this->prio) && isset($data['prio']) && is_numeric($data['prio']) ) {
            if ( ($rgt = db()->fetchOne("SELECT rgt FROM {$this->table} WHERE parent={$data['parent']} AND prio<={$data['prio']} ORDER BY prio DESC,lft DESC LIMIT 1")) === false)
                @trigger_error('Failed to select the insert point of the post, trying parent!');
        } else {
            if( ($rgt = db()->fetchOne("SELECT rgt FROM {$this->table} WHERE parent={$data['parent']} ORDER BY lft DESC LIMIT 1")) === false )
                @trigger_error('Failed to select the insert point of the post, trying parent!');
        }

        if ( !isset($rgt) || isset($rgt) && !is_numeric($rgt) )
            if ( ($rgt = db()->fetchOne("SELECT lft FROM {$this->table} WHERE {$this->id}={$data['parent']}")) === false )
                if ( db()->fetchAll("SELECT * FROM {$this->table}") === false )
                    $rgt = 0;
                else
                    if ( ($rgt = db()->fetchOne("SELECT lft FROM {$this->table} WHERE parent={$data['parent']} ORDER BY prio LIMIT 1")) === false )
                        return !trigger_error('Failed to select the insert point of the post!',E_USER_ERROR);

        $data['lft'] = $rgt + 1;
        $data['rgt'] = $rgt + 2;
		$time = microtime(true);
		
		// Skapa ett hål
        if ( db()->query("UPDATE {$this->table} SET rgt=rgt+2 WHERE lft < %d AND rgt >= %d ",$data['lft'],$data['lft']) &&
             db()->query("UPDATE {$this->table} SET rgt=rgt+2,lft=lft+2 WHERE lft >= %d",$data['rgt']) ) {

			// Uppdatera i spegel trädet
            if ( isset($this->child) && $this->child ) {
                db()->query("UPDATE {$this->child} SET rgt=rgt+2 WHERE lft < %d AND rgt >= %d ",$data['lft'],$data['lft']);
                db()->query("UPDATE {$this->child} SET rgt=rgt+2,lft=lft+2 WHERE lft >= %d",$data['rgt']);
            }

			// Lägg till datan
			if ( !db()->insert( $data,$this->table ) )
				return !trigger_error('Failed to save data in tree!',E_USER_ERROR);

			$insert = mysql_insert_id();
			
			// Kontrollera så att det inte blivit något fel
			/*
            if ( $failed = db()->fetchAll("SELECT * FROM {$this->table} AS a, {$this->table} AS b 
					WHERE (a.lft = b.lft OR a.lft = b.rgt OR a.rgt = b.rgt) AND a.{$this->id} <> b.{$this->id}") ) {
				
				trigger_error('Faild to do a cleean insert, rebuilding tree!',E_USER_ERROR);
                $this->rebuild();
            }
			*/

			// Returnera idt datan fick
            return $insert;
        }

        return !trigger_error('Failed to save data in tree!',E_USER_ERROR);
    }

    function remove ( $id, $force = false ) {

        if (!$this->init())
            return !trigger_error('Hierarchical isn´t initiated!',E_USER_WARNING);

        if ( !is_numeric($id) )
            return !trigger_error('Row identifiaction isn´t a numeric value!');

        if ( !$post = db()->fetchSingle("SELECT lft,rgt FROM {$this->table} WHERE {$this->id}=%d",$id) )
            return !trigger_error('The selected row could not be found in the database!',E_USER_ERROR);

        $posts = ($post['rgt']-$post['lft']+1 * 2)-1;

        if ( $posts > 2 )
            if ( $force )
                $childs = $this->do_list( $id, $this->id );
            else
                return !trigger_error('The selected post have childs! Remove aborted');

        if ( !db()->query("DELETE FROM {$this->table} WHERE lft BETWEEN {$post['lft']} AND {$post['rgt']}") )
            return !trigger_error("Failed to remove the selected post and its childs!");

        if ( db()->query("UPDATE {$this->table} SET rgt=rgt-$posts,lft=lft-$posts WHERE lft>{$post['rgt']}") &&
             db()->query("UPDATE {$this->table} SET rgt=rgt-$posts WHERE  rgt > {$post['rgt']} AND lft < {$post['lft']} ")
            ) {
            if ( isset($this->child) && $this->child ) {
                db()->query("UPDATE {$this->child} SET rgt=rgt-$posts,lft=lft-$posts WHERE lft>{$post['rgt']}");
                db()->query("UPDATE {$this->child} SET rgt=rgt-$posts WHERE  rgt > {$post['rgt']} AND lft < {$post['lft']} ");
            }
            return true;
        }
        $this->rebuild();
        return !trigger_error('Failed to remove data in tree, reversing and rebuilding tree!',E_USER_ERROR);
    }

    function move ( $id, $parent, $prio = 0 ) {

        if (!$this->init())
            return !trigger_error('Hierarchical isn´t initiated!',E_USER_WARNING);

        if ( !is_numeric($id) )
            return !trigger_error('Row identifiaction isn´t a numeric value!');

        if ( !is_numeric($parent) )
            return !trigger_error('Row parent identifiaction isn´t a numeric value!',E_USER_WARNING);


        $row = db()->fetchSingle("SELECT * FROM {$this->table} WHERE {$this->id}=%d",$id);
        $this->remove( $id );
        $row['parent'] = $parent;
        return $this->add($row);
        /*
        // ---------------------------------------------------------------------------------------------------------
        if ( !$post = db()->fetchSingle("SELECT lft,rgt FROM {$this->table} WHERE {$this->id}=%d",$id) )
            return !trigger_error('The selected post could not be found in the database!',E_USER_ERROR);

        if ( isset($this->prio) && $prio && is_numeric($prio) ) {
            if ( ($rgt = db()->fetchOne("SELECT rgt FROM {$this->table} WHERE parent=$parent AND prio<=$prio ORDER BY prio DESC,lft DESC LIMIT 1")) === false)
                @trigger_error('Failed to select the insert point of the post, trying parent!');
        } else {
            if( ($rgt = db()->fetchOne("SELECT rgt FROM {$this->table} WHERE parent=$parent ORDER BY lft DESC,{$this->id} LIMIT 1")) === false )
                @trigger_error('Failed to select the insert point of the post, trying parent!');
        }

        if ( !isset($rgt) || isset($rgt) && !is_numeric($rgt) )
            if ( ($rgt = db()->fetchOne("SELECT lft FROM {$this->table} WHERE {$this->id}=$parent")) === false )
                if ( db()->fetchAll("SELECT * FROM {$this->table}") === false )
                    $rgt = 0;
                else
                    if ( ($rgt = db()->fetchOne("SELECT lft FROM {$this->table} WHERE parent=$parent ORDER BY prio LIMIT 1")) === false )
                        return !trigger_error('Failed to select the insert point of the post!',E_USER_ERROR);

        $shift = $post['lft'] - ($rgt + 1);
        $posts = $post['rgt'] - $post['lft'] + 1;

        $move = db()->fetchOne("SELECT count(*) FROM {$this->table}") * 2;

        //echo $move;


        // flytta posten till slutet..
        db()->query("UPDATE {$this->table} SET lft=lft+$move, rgt=rgt+$move, parent=$parent WHERE {$this->id}=$id");

        if ( $shift > 0 ) { // flytta upp en post
            // shifta alla poster emellan
            db()->query("UPDATE {$this->table} SET rgt=rgt-$posts, lft=lft-$posts WHERE rgt BETWEEN {$post['rgt']} AND $rgt ");


        } else { // flytta ner
            // shifta alla poster emellan
            db()->query("UPDATE {$this->table} SET rgt=rgt-$posts, lft=lft-$posts WHERE rgt > {$post['rgt']} AND lft < $rgt AND lft > {$post['rgt']} ");
            $shift += $posts;
        }

        // minska alla parents med antalet poster som flyttat
        db()->query("UPDATE {$this->table} SET rgt=rgt-$posts WHERE lft < {$post['lft']} AND rgt > {$post['rgt']}");

        // öka alla parents med antalet poster som flyttat till
        db()->query("UPDATE {$this->table} SET rgt=rgt+$posts WHERE lft < $rgt AND rgt > $rgt");

        // flytta in på rätt pos
        db()->query("UPDATE {$this->table} SET lft=lft-$move-$shift, rgt=rgt-$move-$shift WHERE lft >$move");


        // innan


        //db()->query("UPDATE {$this->table} SET rgt=rgt+$shift+1 WHERE rgt > $rgt AND lft < $rgt");

        // efter


        */
    }

	function _check( $parent = 0 ) {

        if (!$this->init())
            return !trigger_error('Hierarchical isn´t initiated!',E_USER_WARNING);

		if ( $childs = db()->fetchAll("SELECT {$this->id},name,lft,rgt FROM {$this->table} WHERE parent=%d",$parent) )
		foreach ( $childs as $line ) {
			$count = db()->fetchOne("SELECT count(*) FROM {$this->table} WHERE lft BETWEEN %d AND %d",$line['lft'],$line['rgt']);

			if ( ($count*2) != ($line['rgt'] - $line['lft']+1) ) {
				if ( $this->_check( $line[$this->id] ) )
					return !trigger_error('Failed integrity check count in ID#'.$line[$this->id].' count is <b>'.($count*2).'</b> and shuld be <b>'.($line['rgt'] - $line['lft']+1).'</b>');
			}
		}

		return true;

	}
}

?>

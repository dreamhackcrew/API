<!DOCTYPE html>
<html>  
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="chrome=1">
        <meta http-equiv="Expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
        <meta http-equiv="Pragma" content="no-cache"> 
        <title>DHCC API</title>
        <link href="/layout/css/bootstrap.min.css" rel="stylesheet" media="screen">
        <link href="/layout/css/bootstrap-responsive.css" rel="stylesheet">
        <link href="/layout/css/base.css" rel="stylesheet">
    </head>
    <body>

    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="/">Dreamhack Crew Corner API</a>
          <div class="nav-collapse collapse">
            <ul class="nav">
              <?php
                function findDocs($path) {
                    $ret = scandir("api$path");
                    foreach($ret as $key => $line) {
                        unset($ret[$key]);
                        if ( substr($line,-3) == '.md' )
                            $ret[substr($line,0,-3)] = $path.$line;
                    }
                    return $ret;
                }

                $general = findDocs('/1/');
                $public = findDocs('/1/public/');
                $restricted = findDocs('/1/restricted/');

                $auth = array();
                $versions = scandir('api/');
                foreach($versions as $key => $line ) {
                    if ( is_numeric($line) )
                        continue;

                    if ( !is_file("api/$line/check.php") )
                        continue;

                    $docs = findDocs('/'.$line.'/');
                    $auth = array_merge(
                        $auth,
                        array("label_$line"=>ucfirst($line)),
                        $docs
                    );
                }


                $tree=array(
                    '<i class="icon-home icon-white"></i> Home' => '/',
                    '<i class="icon-lock icon-white"></i> Authorize' => $auth,
                    '<i class="icon-book icon-white"></i> v1 ' => array_merge(
                        $general,
                        array('label1'=>'Public'),
                        $public,
                        array('label2'=>'Restricted <span class="label label-important">Requires authentication</span>'),
                        $restricted
					),
                    '<i class="icon-book icon-white"></i> v2 (dev)' => array_merge(
						findDocs('/2/'),
                        array('label1'=>'Available areas'),
						findDocs('/2/modules/') 
					)
                );

                printMenu($tree);

                function printMenu($tree,$path = array()) {
                    $active = false;

                    foreach($tree as $key => $line) {
                        if ( is_array($line) ) {
                            array_push($path,$key);

                            echo '<li class="dropdown'.($active?' active':'').'"><a href="#" class="dropdown-toggle" data-toggle="dropdown">'.$key.' <b class="caret"></b></a>';
                            echo '<ul class="dropdown-menu">';
                                echo printMenu($line,$path);
                            echo '</ul></li>';
                            array_pop($path);
                        } elseif( substr($key,0,5) == 'label' ) {
                            echo '<li style="padding: 3px 20px;padding-left:10px;white-space:nowrap; color:#000;font-weight:bold;">'.$line.'</li>';
                        } elseif( $key == 'divider' ) {
                            echo '<li class="divider"></li>';
                            echo '<li style="padding: 3px 20px;padding-left:10px">'.$line.'</li>';
                        } else {
                            echo '<li '.('/'.substr($_GET['command'],0,strlen($line)) == $line?' class="active"':'').'><a href="'.$line.'">'.ucfirst($key).'</a></li>';
                        }
                    }

                    return $active;
                }
              ?>
            </ul>
          </div>
          <?php if (isset($_SESSION['id'])) { ?>
          <div class="nav-collapse collapse pull-right">
            <ul class="nav">
              <li><a href="?exit"><i class="icon-off icon-white"></i> Logout</a></li>
            </ul>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="container">

<?php

if ( !isset($request[0]) ) {
    response(array(
        'error'=>'No image defined!',
    ));
}

//$request[0] = explode('.',$request[0]);

$image = $request[0];
$width = 100;
$height = 100;

if ( isset($_GET['width']) && is_numeric($_GET['width']) )
    $width = min($_GET['width'],1000);

if ( isset($_GET['height']) && is_numeric($_GET['height']) )
    $height = min($_GET['height'],1000);

image::showThumb($image,$height,$width);

class image {
	static function storage() {
		return '/var/www/crew.dreamhack.se/storage/images/';
	}

    static function getId( $id ) {
        if ( is_file( self::storage().$id ) )
			return $id;

            return false;

        return $id;
    }

	static function showThumb( $ident , $height, $width, $radius = 0 ) {
        if ( ($data = db()->fetchSingle("SELECT * FROM images WHERE ident='%s' ORDER BY id DESC LIMIT 1",$ident)) == '' )
			return !trigger_error('File identification not found in database!');
        
        if ( !is_numeric($height) || !is_numeric($width) )  
            return !trigger_error('Syntax error');
        
        if ( !is_file( self::storage().$ident ) )
            return !trigger_error('Sourcefile "'.$ident.'" not found!');

        if ( !is_file( self::storage().$ident.'.'.$height.'.'.$width ) )
            if ( !self::makeThumb( $ident , $height, $width ) )
                return !trigger_error('Failed to make thumbnail',E_USER_WARNING);

        $file = self::storage().$ident.'.'.$height.'.'.$width;

        header('Content-type: '.$data['type']);
        die(file_get_contents($file));
    }

    static function makeThumb( $id , $height, $width, $radius = 0 ) {

        if ( !is_numeric($height) || !is_numeric($width) )  
            return !trigger_error('Syntax error');

        if ( !is_file( self::storage().$id ) )
            return !trigger_error('Sourcefile with id "'.$id.'" not found!');

        if ( !$file = image::getStats($id) )
            return !trigger_error('File identification not found in database!');
       
        if ( !$im = self::createFromMime( self::storage().$file['file'], $file['type'] ) )
            return !trigger_error('Failed read file!');
        
        $sh = imagesy($im);
        $sw = imagesx($im);

		if ( $height < 1 )
			$heightd = $sh * ($width / $sw);
		else
			$heightd = $height;

		if ( $width < 1 )
			$widthd = $sw * ($heightd / $sh);
		else
			$widthd = $width;
    
        $thumb = imagecreatetruecolor( $widthd, $heightd );
		
		if ( $file['BoxW'] != 0 ) { // Om det finns en definierad yta som ska visas
			if ( $file['BoxH'] < $file['BoxW'] ) { 	
				$dw = ( $file['BoxW'] * $sw / 100 );
				$dh = ( $heightd / $widthd ) * $dw;
			} else {
				$dh = ( $file['BoxH'] * $sh / 100 ) ;
				$dw = ( $widthd / $heightd ) * $dh;
			}

			$dx = ( $file['BoxX'] * $sw / 100 ) - ($dw / 2) + ( $file['BoxW'] * $sw / 200 );
			$dy = ( $file['BoxY'] * $sh / 100 ) - ($dh / 2) + ( $file['BoxH'] * $sh / 200 );
		} else {
			$ratioSrc = ($sh / $sw);
			$ratioDst = ($heightd / $widthd);

			if ( $ratioSrc > $ratioDst ) {
				$dw = $sw;
				$dh = ( $heightd / $widthd ) * $dw;
			} else {
				$dh = $sh;
				$dw = ( $widthd / $heightd ) * $dh;
			}

			$dx = ( $sw / 2 ) - ( $dw / 2 );
			$dy = ( $sh / 2 ) - ( $dh / 2 );
		}

		//p("dh:$dh dw:$dw dx$dx dy$dy");
												
        if ( !imagecopyresampled( $thumb, $im, 0, 0, $dx, $dy, $widthd, $heightd, $dw, $dh ) )
            return !trigger_error('Failed to resize image!');
        
		//$textcolor = imagecolorallocate($thumb, 255, 255, 255);
		//imagestring($thumb, 1, 0, 0, "dh:$dh dw:$dw", $textcolor);
		//imagestring($thumb, 1, 0, 10,"dx$dx dy$dy", $textcolor);

		$filename = self::storage().$file['file'].'.'.$height.'.'.$width;

		if ( !$radius ) {

			if ( !self::saveFileFromMime( $thumb, $filename, 'image/jpeg') )
				return !trigger_error('Failed to save thumbnail');

		} else {

			if ( !self::roundCorners( $thumb, $radius ) )
				return !trigger_error('Failed to make round corners on thumbnail');

			if ( !self::saveFileFromMime( $thumb, $filename.'.'.$radius, 'image/png') )
				return !trigger_error('Failed to save thumbnail');
		}

		return is_file($filename);
    }

	function getStats( $ident ) {

		if ( !$id = self::getId( $ident) )
			return @!trigger_error('File identification not found in database!');

	    if ( !is_file( self::storage().$id ) )
            return !trigger_error('Sourcefile with id "'.$id.'" not found!');

        if ( !$data = db()->fetchSingle("SELECT * FROM images WHERE file='%s'",$id) )
            return !trigger_error('File identification not found in database!');
       
        if ( !$im = self::createFromMime( self::storage().$data['file'], $data['type'] ) )
            return !trigger_error('Failed read file!');
        
		$data['width']		= imagesx($im);
		$data['height']		= imagesy($im);
	
		return $data;
	}

    static function createFromMime( $file, $mime ) {
        
        if ( !is_file($file) )
            return !trigger_error('File "'.$file.'" not found!');

        switch ($mime) {
            case 'image/gif':
                return imagecreatefromgif( $file );

            case 'image/jpeg':
            case 'image/pjpeg':
                return imagecreatefromjpeg( $file );

            case 'image/png':
                return imagecreatefrompng( $file );

            default:
                return !trigger_error('Mime type "'.$mime.'" not supported!');
        }

    }

    static function saveFileFromMime( $image, $file, $mime ) {
        
        switch ($mime) {
            case 'image/gif':
                return imagegif( $image,$file );

            case 'image/jpeg':
            case 'image/pjpeg':
                return imagejpeg( $image,$file );

            case 'image/png':
                return imagepng( $image,$file );
            
            default:
                return !trigger_error('Mime type "'.$mime.'" not supported!');
        }
    }
}


?>

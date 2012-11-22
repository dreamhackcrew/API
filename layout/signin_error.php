<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">  
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="chrome=1">
        <meta http-equiv="Expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
        <meta http-equiv="Pragma" content="no-cache"> 

        <meta name = "viewport" content = "width = device-width, initial-scale = 1, user-scalable = no">
        <title>DHCC API</title>

        <script type="text/javascript" src="/js/mootools-core-1.4.5-full-nocompat-yc.js"></script>
        <link href="/layout/signin.css" rel="stylesheet"/>
    </head>
    <body>
        <div id="container">
            <div id="content">
                <h1>Dreamhack Crew</h1>
                <span>Sign in with your Crew Corner account</span>

                <div class="error">
                    <?php echo $error; ?>
                    <?php if ( $desc ) echo '<span>'.$desc.'</span>'; ?>
                </div>
            </div>
        </div>
    </body>
</html>

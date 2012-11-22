        <h2>Documentation</h2>
        <script language="javascript">
            showDoc = function(file,obj) {
                //obj.parentNode.getElements('li').removeClass('active');
                //obj.addClass('active');

                /*new Request({
                    url: 'pages/doc.php?file='+file,
                    onSuccess: function(responseText){
                        $('doc').innerHTML = responseText;
                    },
                    onFailure: function(){
                        $('doc').innerHTML = '<div style="color:#f00">Sorry, your request failed :(</div>';
                    }
                }).send();*/
                $('#doc').innerHTML = '';
                $('#doc').load('pages/doc.php?file='+file);

            }
        </script>
        <div id="doc"></div>
        <ul id="files">
        <?php
        
            $files = scandir('wiki');
            foreach($files as $line) {
                if ( substr($line,0,1) == '.' )
                    continue;

                echo '<li onclick="showDoc(\''.$line.'\',this);">'.$line.'</li>';
            }
        
        ?></ul>


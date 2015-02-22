		</div>
<?php if ( !isset($_SERVER['HTTP_X_URL_SCHEME']) || $_SERVER['HTTP_X_URL_SCHEME'] != "https") { ?> 
		<script src="http://code.jquery.com/jquery-latest.js"></script>
<?php } else { ?> 
		<script src="https://code.jquery.com/jquery-latest.js"></script>
<?php } ?> 
        <script src="/layout/js/bootstrap.js"></script>
    </body>
</html>


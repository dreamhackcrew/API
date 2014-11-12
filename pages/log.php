
<?php if ( $rows = db()->fetchAll("SELECT * FROM api_customers JOIN api_messages ON customer_key = customer WHERE owner=%d ORDER BY api_messages.id DESC LIMIT 20",$_SESSION['id']) ) {
	echo '<h2>API Error log</h2>';

    echo '<table id="api_log" class="table table-striped">';
    echo '<tr>';
    echo "<th>Application name</th>";
    echo "<th>Message</th>";
    echo "<th>Data</th>";
    echo '</tr>';

    foreach($rows as $key => $line) {
        echo '<tr class="'.($key%2?'odd':'even').' '.$line['state'].'">';
		echo "<td>{$line['name']}</td>";
		echo "<td>{$line['message']}</td>";
		echo "<td>{$line['data']}</td>";
		echo '</tr>';
	}

	echo '</table>';
}

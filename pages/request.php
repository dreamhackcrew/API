<?php 

if ( isset($_SESSION['id']) ) {
    $customer_key = '';

    if ( isset($_POST['name']) && $_POST['name'] ) {
        $customer_key = sha1(md5(time()));
        $customer_secret = sha1(md5(rand(-32676,32767)));

        $id = db()->insert(
            array(
                'customer_key' => $customer_key,
                'customer_secret' => $customer_secret,
                'name' => $_POST['name'],
                'owner' => $_SESSION['id'],
                'created' => db()->now(),
                'state' => 'active'
            ),'api_customers'
        );
    }
    
    if ( isset($_GET['reset']) && db()->fetchOne("SELECT customer_key FROM api_customers WHERE customer_key='%s' AND owner=%d",$_GET['reset'],$_SESSION['id'])  ) {
        $customer_key = $_GET['reset'];
        $customer_secret = sha1(md5(rand(-32676,32767)));

        // Save the new secret
        db()->query("UPDATE api_customers SET customer_secret='%s' WHERE customer_key='%s' AND owner=%d LIMIT 1",$customer_secret,$customer_key,$_SESSION['id']);

        // Trash all authorized users
        db()->query("DELETE FROM api_access_token WHERE customer='%s'",$customer_key);
    }

    if ( isset($_GET['enable_https']) ) 
        db()->query("UPDATE api_customers SET allow_https=1 WHERE customer_key='%s' AND owner=%d",$_GET['enable_https'],$_SESSION['id']); 
    if ( isset($_GET['disable_https']) ) 
        db()->query("UPDATE api_customers SET allow_https=0 WHERE customer_key='%s' AND owner=%d",$_GET['disable_https'],$_SESSION['id']); 

    if ( isset($_GET['enable']) ) 
        db()->query("UPDATE api_customers SET state='active' WHERE customer_key='%s' AND owner=%d",$_GET['enable'],$_SESSION['id']);

    if ( isset($_GET['disable']) && db()->fetchOne("SELECT customer_key FROM api_customers WHERE customer_key='%s' AND owner=%d",$_GET['disable'],$_SESSION['id']) ) {
        db()->query("UPDATE api_customers SET state='disabled' WHERE customer_key='%s' AND owner=%d",$_GET['disable'],$_SESSION['id']);

        // Trash all authorized users
        db()->query("DELETE FROM api_access_token WHERE customer='%s'",$_GET['disable']);
    }
?>
    <h2>Request API key</h2>
        <form method="post" class="form-horizontal">
            <div class="control-group">
                <label class="control-label" for="name">Application name</label>
                <div class="controls">
                    <input type="text" name="name">
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <input type="submit" class="btn btn-primary" value="Request API key">
                </div>
            </div>
        </form>
<?php if ( $rows = db()->fetchAll("SELECT * FROM api_customers WHERE owner=%d ORDER BY name",$_SESSION['id']) ) {
    echo '<h2>Your API keys</h2>';
    echo '<table id="api_keys" class="table table-striped">';
    echo '<tr>';
    echo "<th>Application name</th>";
    echo "<th>Customer key</th>";
    echo "<th>Customer secret</th>";
    echo "<th>Created</th>";
    echo "<th>Allow https</th>";
    echo "<th>State</th>";
    echo '</tr>';

    foreach($rows as $key => $line) {
        echo '<tr class="'.($key%2?'odd':'even').' '.$line['state'].'">';
        echo "<td>{$line['name']}</td>";
        echo "<td>{$line['customer_key']}</td>";

        if ( $line['customer_key'] == $customer_key ) {
            echo '<td>'.$customer_secret.'<div class="alert alert-error"><strong>Warning!</strong> This key is only displayed once!</div></td>';
        } else {
            echo "<td>hidden <input type=\"button\" class=\"btn btn-danger pull-right\" value=\"Reset key\" onclick=\"if (confirm('Are you shure?')) location.href='?reset={$line['customer_key']}';\"></td>";
        }

        echo "<td>{$line['created']}</td>";

        if ( $line['allow_https'] ) {
            echo "<td><input type=\"button\" class=\"btn btn-success pull-right\" value=\"Enabled\" onclick=\"location.href='?disable_https={$line['customer_key']}';\"></td>";
        } else {
            echo "<td><input type=\"button\" class=\"btn pull-right\" value=\"Disabled\" onclick=\"location.href='?enable_https={$line['customer_key']}';\"></td>";
        }

        if ( $line['state'] == 'active' ) {
            echo "<td><input type=\"button\" class=\"btn btn-success pull-right\" value=\"Active\" onclick=\"location.href='?disable={$line['customer_key']}';\"></td>";
        } elseif ( $line['state'] == 'disabled' ) {
            echo "<td><input type=\"button\" class=\"btn btn-warning pull-right\" value=\"Disabled\" onclick=\"location.href='?enable={$line['customer_key']}';\"></td>";
        } else {
            echo "<td><span class=\"label\">{$line['state']}</span></td>";
        }
        echo '</tr>';
    }
    echo '</table>';
}
?>
<?php } else { ?>
        <h2>Sign in with your Crew Corner account</h2>
        <form method="post" class="form-horizontal" action="/">
            <div class="control-group">
                <label class="control-label" for="username">Username</label>
                <div class="controls">
                    <input type="text" name="username">
                </div>
            </div>
            <div class="control-group <?php if (isset($_POST['username'])) echo "error"; ?>">
                <label class="control-label" for="password">Password</label>
                <div class="controls">
                    <input type="password" name="password">
                    <?php if (isset($_POST['username'])) echo '<span class="help-inline">Wrong username or password</span>'; ?>
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <input type="submit" value="Sign in" class="btn btn-primary">
                </div>
            </div>
        </form>
<?php } ?>

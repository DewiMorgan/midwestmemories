<?php /** @noinspection SpellCheckingInspection */
declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<?php
/**
 * Remote command executor. Handy for installing/updating.
 * ToDo: DELETEME. This is a security vulnerability waiting to happen. Replace with a proper deploy pipeline.
 * For now, using hashed param (e.g. from `php -r 'echo password_hash("blahblah", PASSWORD_DEFAULT)."\n";'`
 */
const ID_HASH = '$2y$10$n0RQJRIzOShzEjcYSVRKie4OJUPBw0PA/fqbLLMuNzKj4OGFDzCv2';

$password = $_GET['i'] ?? '';

if (!password_verify($password, ID_HASH)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Handle a command if the user has submitted one.
$cmd = '';
if (!empty($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $descriptorSpec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    // Run the command with those descriptors, in the current directory, with i/o through $pipes:
    $pipes = [];
    // The $process useless var NEEDS to exist, or this doesn't work.
    $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__, null);

    // We can now read from the two output pipes :
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    logCommand();
}

/**
 * Build a string to describe and log the incoming query.
 * @return string The data that was logged.
 */
function logCommand(): string
{
    $filename = 'inst-mwm.log';
    $timestamp = date('Y-m-d H:i:s: ');
    $data = $timestamp . $_POST['cmd'] . "\n";
    file_put_contents($filename, $data, FILE_APPEND);
    return $data;
}

$isLiveSite = str_contains(__DIR__, 'midwestmemoriesfamily');
?>
<head>
    <?php if ($isLiveSite) { ?>
        <link rel="shortcut icon" href="/favicon.ico">
    <?php } else { ?>
        <link rel="icon" href="/favicon-test.ico" type="image/x-icon">
    <?php } ?>
    <meta charset="utf-8">
    <title>Web Shell - <?php echo $isLiveSite ? 'Live Site' : 'Test Site' ?></title>
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
            background-color: #efefef;
            padding: 10px;
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
        <input type="hidden" name="key" value="<?= htmlspecialchars($_REQUEST['key'] ?? '') ?>">
        <label for="cmd"><strong>Command</strong></label>
        <div class="form-group">
            <input type="text" name="cmd" id="cmd" value="<?= htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8') ?>"
                   onfocus="this.setSelectionRange(this.value.length, this.value.length);" autofocus required>
            <button type="submit">Execute</button>
        </div>
    </form>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST'):
        ?>
        <h2>Output</h2>
        <?php
        if (isset($stdout)):
            ?>
            <pre><?= htmlspecialchars($stdout, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php
        else:
            ?>
            <p><small>No stdout.</small></p>
        <?php
        endif;
        ?>
        <h2>Errors</h2>
        <?php
        if (isset($stderr)):
            ?>
            <pre><?= htmlspecialchars($stderr, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php
        else:
            ?>
            <p><small>No stderr.</small></p>
        <?php
        endif;
        ?>
    <?php
    endif;
    ?>
</main>
</body>
</html>

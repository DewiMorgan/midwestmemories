<?php
/**
 * Callback for changes in DropBox. Only have 10 seconds to respond, so make it fast!
 */

declare(strict_types=1);

namespace MidwestMemories;

date_default_timezone_set('US/Central');
session_start();

require_once(__DIR__ . '/src/autoload.php');

new DropboxWebhook();
exit(0);

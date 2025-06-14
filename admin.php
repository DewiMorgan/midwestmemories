<?php

/**
 * Management page for admins.
 * ToDo:
 *  Chain all these processes up from the web hook handler, using a single timeout time.
 *  Maybe have them re-trigger each other or something.
 *  Maybe a web cron to hit the webhook? Or does cpanel allow cron jobs? Edit crontab manually?
 */

declare(strict_types=1);

namespace MidwestMemories;

date_default_timezone_set('US/Central');
session_start();

require_once(__DIR__ . '/app/autoload.php');

new Admin();
exit(0);

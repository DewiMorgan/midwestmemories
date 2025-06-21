<?php

/**
 * Management page for admins.
 * ToDo: Maybe a web cron to hit the webhook? Or does cpanel allow cron jobs? Edit crontab manually?
 */

declare(strict_types=1);

namespace MidwestMemories;

date_default_timezone_set('US/Central');
session_start();

require_once(__DIR__ . '/src/autoload.php');

new AdminGateway();
exit(0);

<?php
/** The main site page.  */

declare(strict_types=1);

namespace MidwestMemories;

date_default_timezone_set('US/Central');
session_start();

require_once('app/autoload.php');

new Index();

<?php
/** The main site page.  */

declare(strict_types=1);

namespace MidwestMemories;

use app\test1;
use \app\test2;
use MidwestMemories\test3;
use \MidwestMemories\test4;
use \test5;
use test6;
// This is where I want it to be.
use MidwestMemories\Index;

date_default_timezone_set('US/Central');
session_start();

require_once('app/autoload.php');

new Index();

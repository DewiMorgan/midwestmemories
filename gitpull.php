<?php

declare(strict_types=1);

/** This is a very basic updater. All it does is the one command.
 * ToDo: DELETEME. This should be replaced by a proper deploy pipeline.
 */
echo shell_exec("git pull");

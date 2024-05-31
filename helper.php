<?php
namespace nova\framework;


use nova\framework\log\Logger;

function runtime($msg): void
{
    $t = (microtime(true) - $GLOBALS['__nova_app_start__'])  * 1000;
    Logger::info("$msg run in $t ms");
}
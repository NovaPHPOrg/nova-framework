<?php
namespace nova\framework;


use nova\framework\log\Logger;
use nova\framework\request\RouteObject;
use nova\framework\text\Text;

function runtime($msg): void
{
    $t = (microtime(true) - $GLOBALS['__nova_app_start__'])  * 1000;
    Logger::info("$msg run in $t ms");
}

function route($module = "", $controller = "", $action = "",$params = []): RouteObject
{
    return new RouteObject($module, $controller, $action,$params);
}
<?php
namespace nova\framework;


use nova\framework\log\Logger;
use nova\framework\request\RouteObject;

function runtime($msg): float
{
    $t = (microtime(true) - $GLOBALS['__nova_app_start__'])  * 1000;
    Logger::info("$msg run in $t ms");
    return $t;
}

function route($module = "", $controller = "", $action = "",$params = []): RouteObject
{
    return new RouteObject($module, $controller, $action,$params);
}
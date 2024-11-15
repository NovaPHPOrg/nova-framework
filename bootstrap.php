<?php
declare(strict_types=1);
namespace nova\framework;
error_reporting(E_ALL);
ini_set('display_errors', '1');
$GLOBALS['__nova_app_start__'] = microtime(true);
$GLOBALS['__nova_app_config__'] = require dirname(__FILE__,3) .DIRECTORY_SEPARATOR ."config.php";
$GLOBALS['__nova_session_id__'] = uniqid('session_', true);
include "constants.php";
App::getInstance()->start();
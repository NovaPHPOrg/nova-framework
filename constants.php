<?php
namespace nova\framework;
include "helper.php";
const VERSION = "5.0.0"; //框架版本
//根目录
define("ROOT_PATH", dirname(__FILE__, 3));
define("DS", DIRECTORY_SEPARATOR);
$wait = [
    "cache".DS."iCacheDriver",
    "cache".DS."Cache",
    "cache".DS."FileCacheDriver",
    "cache".DS."ApcuCacheDriver",
    "cache".DS."CacheException",
    "log".DS."Logger",
    "autoload".DS."Loader"
];

foreach ($wait as $file) {
    include dirname(__FILE__).DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR.$file.".php";
}

(new autoload\Loader())->register();

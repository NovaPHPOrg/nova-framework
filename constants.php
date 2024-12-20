<?php
declare(strict_types=1);

namespace nova\framework;
include "helper.php";
const VERSION = "5.0.0"; //框架版本
//根目录
define("ROOT_PATH", dirname(__FILE__, 3));
define("DS", DIRECTORY_SEPARATOR);
date_default_timezone_set(config('timezone') ?? "Asia/Shanghai");
$domains = config("domain");
$serverName = $_SERVER["HTTP_HOST"];
if (!in_array("0.0.0.0", $domains) && !in_array($serverName, $domains)) {
    exit("[ NovaPHP ] Domain Error ：" . htmlspecialchars($serverName) . " not in config.domain list.");
}

$wait = [
    "log" . DS . "File",
    "cache" . DS . "iCacheDriver",
    "cache" . DS . "Cache",
    "cache" . DS . "FileCacheDriver",
    "cache" . DS . "ApcuCacheDriver",
    "cache" . DS . "CacheException",
    "log" . DS . "Logger",
    "autoload" . DS . "Loader"
];

foreach ($wait as $file) {
    include dirname(__FILE__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $file . ".php";
}

(new autoload\Loader())->register();
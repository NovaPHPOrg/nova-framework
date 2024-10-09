<?php
namespace nova\framework;


use nova\framework\exception\AppExitException;
use nova\framework\log\Logger;
use nova\framework\log\VarDump;
use nova\framework\request\Response;
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

function file_type(string $filename): string
{
    $mime_types = array(
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        "woff2" => 'font/woff2',
        "ttf" => 'font/ttf',
    );
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $ext = strtolower($extension);
    if (array_key_exists($ext, $mime_types)) {
        return $mime_types[$ext];
    }
    return 'application/octet-stream';
}

function config($key = null, $set = null): mixed
{
    if ($set !== null && $key !== null) {
        $GLOBALS['__nova_app_config__'][$key] = $set;
        return $set;
    }
    if ($key) {
        return $GLOBALS['__nova_app_config__'][$key] ?? null;
    }
    return $GLOBALS['__nova_app_config__'];
}

function dump(...$args)
{
    if (!App::getInstance()->debug) return;
    $line = debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line'] . "\n";
    $dump = new VarDump();

    $tpl = "";
    if ($line !== "") {
        $tpl .= <<<EOF
<style>pre {display: block;padding: 10px;margin: 0 0 10px;font-size: 13px;line-height: 1.42857143;color: #333;word-break: break-all;word-wrap: break-word;background-color:#f5f5f5;border: 1px solid #ccc;border-radius: 4px;}</style>
<div style="text-align: left">
<pre class="xdebug-var-dump" dir="ltr"><small>{$line}</small>\r\n
EOF;
    }
    foreach ($args as $arg) {
        $html = (new VarDump())->dumpType($arg);
        $tpl .= "<div>{$html}</div>";
    }
    $tpl .= '</pre></div>';
    throw new AppExitException(Response::asHtml($tpl));
}

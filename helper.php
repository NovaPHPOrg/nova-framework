<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework;

use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\VarDump;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use nova\framework\route\RouteObject;

/**
 * 计算并记录运行时间
 * @param  string $msg 要记录的消息
 * @return float  返回运行时间（毫秒）
 */
function runtime(string $msg): float
{
    $t = Context::instance()->calcAppTime() * 1000;
    Logger::debug("$msg run in $t ms");
    return $t;
}

/**
 * 创建路由对象
 * @param  string      $module     模块名
 * @param  string      $controller 控制器名
 * @param  string      $action     动作名
 * @param  array       $params     路由参数
 * @return RouteObject 返回路由对象
 */
function route(string $module = "", string $controller = "", string $action = "", array $params = []): RouteObject
{
    return new RouteObject($module, $controller, $action, $params);
}

/**
 * 获取文件的MIME类型
 * @param  string $filename 文件名
 * @return string 返回MIME类型，如果未知则返回 'application/octet-stream'
 */
function file_type(string $filename): string
{
    // MIME类型映射表
    $mime_types = array(
        // 文本文件
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
        // 图片文件
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
        // 压缩文件
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        // 音视频文件
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        // Adobe文件
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        // MS Office文件
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        // OpenOffice文件
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        // 字体文件
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

/**
 * 获取或设置配置项
 * @param  string|null $key 配置键名
 * @param  mixed|null  $set 要设置的值
 * @return mixed       返回配置值或整个配置数组
 */
function config(string $key = null, mixed $set = null): mixed
{
    $context = Context::instance();
    if ($set !== null && $key !== null) {
        $context->config()->set($key, $set);
        return $set;
    }
    if ($key) {
        return $context->config()->get($key);
    }
    return $context->config()->all();
}

function isCli(): bool
{
    return PHP_SAPI === 'cli' && !isWorkerman();

}

function isWorkerman(): bool
{
    return class_exists('Workerman\Worker', false);
}

/**
 * 调试变量输出
 * 在CLI模式下直接输出到控制台
 * 在Web模式下以HTML格式输出并终止程序
 *
 * @param  mixed            ...$args 要输出的变量
 * @throws AppExitException 在Web模式下会抛出此异常以终止程序
 */
function dump(...$args): void
{
    // 非调试模式直接返回
    if (!Context::instance()->isDebug()) {
        return;
    }

    // 获取调用位置信息
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $line = sprintf("%s:%d\n", $trace['file'], $trace['line']);

    if (isCli()) {
        // CLI模式输出
        echo "\n";
        echo "\033[33m" . str_repeat('-', 80) . "\033[0m\n"; // 黄色分隔线
        echo "\033[36m{$line}\033[0m"; // 青色文件位置

        foreach ($args as $arg) {
            $dumper = new VarDump(htmlOutput: false);
            echo $dumper->dumpType($arg) . "\n";
        }

        echo "\033[33m" . str_repeat('-', 80) . "\033[0m\n"; // 黄色分隔线
        echo "\n";
        return;
    }

    // Web模式输出
    $styles = <<<EOF
<style>
        .dump-container {
            text-align: left;
            margin: 20px;
            font-family: Consolas, Monaco, monospace;
        }
        .dump-container pre {
            display: block;
            padding: 15px;
            margin: 0 0 10px;
            font-size: 13px;
            line-height: 1.42857143;
            color: #333;
            word-break: break-all;
            word-wrap: break-word;
            background-color: #f8f8f8;
            border: 1px solid #eee;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .dump-container .file-info {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
            padding: 5px;
            background: #f1f1f1;
            border-radius: 3px;
        }
        .dump-container .var-content {
            margin: 10px 0;
            padding: 5px;
        }
        .dump-container .var-separator {
            border-top: 1px dashed #ddd;
            margin: 10px 0;
        }
</style>
EOF;

    $tpl = $styles . '<div class="dump-container">';
    $tpl .= '<pre>';
    $tpl .= '<div class="file-info">' . htmlspecialchars($line) . '</div>';

    foreach ($args as $index => $arg) {
        if ($index > 0) {
            $tpl .= '<div class="var-separator"></div>';
        }
        $dumper = new VarDump(htmlOutput: true);
        $html = $dumper->dumpType($arg);
        $tpl .= '<div class="var-content">' . $html . '</div>';
    }

    $tpl .= '</pre></div>';

    // 抛出异常以终止程序
    throw new AppExitException(
        Response::asHtml($tpl),
        "Dump variables"
    );
}

/**
 * 通用节流函数，多少秒内只允许执行一次 callback
 *
 * @param  string     $key      唯一限流标识（如用户IP、接口名等）
 * @param  int        $ttl      间隔秒数，比如10秒只允许执行一次
 * @param  callable   $callback 成功通过限流时要执行的逻辑
 * @return mixed|null 返回 callback 的结果 或 null（被限流）
 */
function throttle(string $key, int $ttl, callable $callback): mixed
{
    $cacheKey = 'throttle_' . md5($key);

    $cache = Context::instance()->cache;

    // 使用 PSR-16 兼容的 Cache，如果你自己封装了 Cache 类，请替换这里
    if ($cache->get($cacheKey)) {
        return null; // 表示被限流了
    }

    // 放行，执行回调
    $result = $callback();

    // 设置限流标识，$ttl 秒内不能再进
    $cache->set($cacheKey, true, $ttl);

    return $result;
}

function uuid(): string
{
    $data = random_bytes(16);

    // 设置版本号（4）：xxxx -> 0100xxxx
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // 设置变体（10xxxxxx）
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    // 转成格式化字符串
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

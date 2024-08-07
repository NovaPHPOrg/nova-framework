<?php

namespace nova\framework\exception;

use nova\framework\App;
use nova\framework\log\Logger;
use nova\framework\request\Argument;
use nova\framework\request\Response;
use nova\framework\request\ResponseType;
use Throwable;
use const nova\framework\VERSION;

class ErrorHandler
{
    public static function register(): void
    {
        $old_error_handler = set_error_handler([__CLASS__, 'appError'], E_ALL);
        set_exception_handler([__CLASS__, 'appException']);
    }


    /**
     *
     * App异常退出
     * @param $e Throwable
     * @throws AppExitException
     */
    public static function appException(Throwable $e): void
    {

        if ($e instanceof AppExitException) {
            return;//Exit异常不进行处理
        }

        if (!App::getInstance()->debug) return;
        $hasCatchError = $GLOBALS['__nova_frame_error__'] ?? false;
        if ($hasCatchError) return;
        $GLOBALS['__nova_frame_error__'] = true;
        throw new AppExitException(self::getExceptionResponse($e));
    }

    static function getExceptionResponse(Throwable $e): Response
    {
        $html = self::customExceptionHandler($e);
        return new Response($html, 500, ResponseType::HTML);
    }


    static function customExceptionHandler(Throwable $exception): string
    {
        $trace = $exception->getTrace();
        array_unshift($trace, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => '',
            'class' => get_class($exception),
            'type' => '',
            'args' => []
        ]);


        error_clear_last();
        //避免递归调用
        Logger::error($exception->getMessage());
        $traces = sizeof($trace) === 0 ? debug_backtrace() : $trace;
        $trace_text = [];
        foreach ($traces as $i => &$call) {
            $trace_text[$i] = sprintf("#%s %s(%s): %s%s%s", $i, $call['file'] ?? "", $call['line'] ?? "", $call["class"] ?? "", $call["type"] ?? "", $call['function'] ?? "");
            Logger::error($trace_text[$i]);
        }

        $tpl = file_get_contents(ROOT_PATH . "/nova/framework/error/exception.html");
         $classFullName = get_class($exception);
    $classParts = explode('\\', $classFullName);
    $className = end($classParts);

    $tpl = str_replace("{PHP_VERSION}", PHP_VERSION, $tpl);
    $tpl = str_replace("{NOVA_VERSION}", VERSION, $tpl);
    $tpl = str_replace("{ERROR_TYPE}", $className, $tpl);
    $tpl = str_replace("{ERROR_MESSAGE}", $exception->getMessage(), $tpl);
    $first = true;

    $TEMPLATE_LIST = "";
    $TEMPLATE_CONTAINER = "";


    foreach ($traces as $key => $trace) {

        if (is_array($trace) && !empty($trace["file"])) {
            $trace["keyword"] = $trace["keyword"] ?? "";
            $sourceLine = self::errorFile($trace["file"], $trace["line"], $trace["keyword"]);
            $trace["line"] = $sourceLine["line"];
            unset($sourceLine["line"]);
            if ($sourceLine) {

                if ($first) {
                    $first = false;
                    $TEMPLATE_LIST .= <<<EOF
<li class="active" onclick="showCode('{$key}')"><b>{$trace['file']}</b><span class="number">#{$trace['line']}</span></li>
EOF;
                } else {
                    $TEMPLATE_LIST .= <<<EOF
<li onclick="showCode('{$key}')"><b>{$trace['file']}</b><span class="number">#{$trace['line']}</span></li>
EOF;
                }



                $file = $trace['file'];

                $clazz = $trace["class"] ?? "";

                $type = $trace["type"] ?? "";

                $function = $trace['function'] ?? "";


                $TEMPLATE_CONTAINER .= <<<EOF
<template id="header{$key}">
<div class="param-group" >
        <div class="param-item">
            <span class="highlight-class">$clazz</span>
            <span class="highlight-type">$type</span>
EOF;
                if (!empty($function)) {
                    $TEMPLATE_CONTAINER .= <<<EOF
            <span class="highlight-function">{$function}(
EOF;
                    $args = $trace['args'] ?? [];
                    foreach ($args as $i => $arg) {

                        //判断i是奇数还是偶数
                        $color = "highlight-args1";
                        if ($i % 2 != 0) {
                            $color = "highlight-args2";
                        }

                        $argStr = str_replace("\n","<br>",print_r($arg, true));

                        $TEMPLATE_CONTAINER .= <<<EOF
            <span class="highlight-args $color" style="margin-right: 4px">&nbsp;&nbsp;{$argStr}&nbsp;,</span>
EOF;
                    }

                    $TEMPLATE_CONTAINER .= <<<EOF
            )</span>
EOF;
                }
                $TEMPLATE_CONTAINER .= <<<EOF
        </div>
    </div>
</template>
<template id="file{$key}">

EOF;

                foreach ($sourceLine as $line) {
                    $TEMPLATE_CONTAINER .= <<<EOF
$line
EOF;
                }
                $TEMPLATE_CONTAINER .= <<<EOF
</template>
EOF;

            }
        }
    }

    $tpl = str_replace("{TEMPLATE_LIST}", $TEMPLATE_LIST, $tpl);

    $tpl = str_replace("{TEMPLATE_CONTAINER}", $TEMPLATE_CONTAINER, $tpl);


    $request = App::getInstance()->getReq();

    $requestInfo = $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "<br>";
    $headers = $request->getHeaders();
    $REQUEST_HEADERS = "";
    foreach ($headers as $key => $value) {
        if (empty($value)) {
            continue;
        }
        $requestInfo .= "$key: $value<br>";
        $REQUEST_HEADERS .= <<<EOF
<tr>
                <td class="key">$key</td>
                <td class="value">$value</td>
            </tr>
EOF;

    }
    $requestInfo .= "<br>";
    $raw = Argument::raw();
    if (empty($raw)) {
        $raw = "(empty)";
    }
    $requestInfo .= $raw;
    $tpl = str_replace("{REQUEST_INFO}", $requestInfo, $tpl);
    $tpl = str_replace("{REQUEST_URI}", $request->getNowAddress(), $tpl);
    $tpl = str_replace("{REQUEST_METHOD}", $_SERVER['REQUEST_METHOD'], $tpl);
    $tpl = str_replace("{REQUEST_HEADERS}", $REQUEST_HEADERS, $tpl);
    $tpl = str_replace("{REQUEST_BODY}", $raw, $tpl);

    $REQUEST_ROUTING = <<<EOF
<tr>
                <td class="key">Module</td>
                <td class="value">{$request->route->module}</td>
            </tr>
<tr>
                <td class="key">Controller</td>
                <td class="value">{$request->route->controller}</td>
            </tr>
<tr>
                <td class="key">Action</td>
                <td class="value">{$request->route->action}</td>
            </tr>
EOF;

    $tpl = str_replace("{REQUEST_ROUTING}", $REQUEST_ROUTING, $tpl);

    $REQUEST_SERVER = "";
    foreach ($_SERVER as $key => $value) {
        $REQUEST_SERVER .= <<<EOF
<tr>
                <td class="key">$key</td>
                <td class="value">$value</td>
            </tr>
EOF;

    }
    $tpl = str_replace("{REQUEST_SERVER}", $REQUEST_SERVER, $tpl);


        return $tpl;
    }


    /**
     * @param string $file 错误文件名
     * @param int $line 错误文件行,若为-1则指定msg查找
     * @param string $msg 当line为-1才有效
     * @return array
     */
    public
    static function errorFile(string $file, int $line = -1, string $msg = ""): array
    {
        $lineCount = 15;
        if (!(file_exists($file) && is_file($file))) {
            return [];
        }
        $data = file($file);
        $count = count($data) - 1;
        $returns = [];
        if ($line == -1) {
            //查找文本
            for ($i = 0; $i <= $count; $i++) {
                if (str_contains($data[$i], $msg)) {
                    $line = $i + 1;
                    break;
                }
            }
        }
        $returns["line"] = $line;
        $start = $line - $lineCount;
        if ($start < 1) {
            $start = 1;
        }
        $end = $line + $lineCount;
        if ($end > $count) {
            $end = $count + 1;
        }

        for ($i = $start; $i <= $end; $i++) {

            $number = '<span class="ln-num" data-num="' . $i . '"></span>';

            if ($i == $line) {
                $returns[] = "<span id='current'>" . $number . self::highlightCode($data[$i - 1]) . "</span>";
            } else {
                $returns[] = $number . self::highlightCode($data[$i - 1]);
            }
        }
        return $returns;
    }

    /**
     * 高亮代码
     * @param string $code
     * @return bool|string|string[]
     */
    private
    static function highlightCode(string $code): array|bool|string
    {
        $code = preg_replace('/(\/\*\*)/', '///**', $code);
        $code = preg_replace('/(\s\*)[^\/]/', '//*', $code);
        $code = preg_replace('/(\*\/)/', '//*/', $code);
        if (preg_match('/<\?(php)?[^[:graph:]]/i', $code)) {
            $return = highlight_string($code, true);
        } else {
            $return = preg_replace('/(&lt;\?php)+/i', "",
                highlight_string("<?php " . $code, true));
        }
        return str_replace(['//*/', '///**', '//*'], ['*/', '/**', '*'], $return);
    }


    /**
     * 报错退出
     * @param int $errno
     * @param string $err_str
     * @param string $err_file
     * @param int $err_line
     * @return bool
     * @throws WarningException
     * @throws ErrorException
     * @throws DeprecatedException
     * @throws StrictException
     * @throws NoticeException
     */
    public
    static function appError(int $errno, string $err_str, string $err_file = '', int $err_line = 0): bool
    {
        if ($errno == E_WARNING) {
            throw new WarningException("WARNING: $err_str in $err_file on line $err_line");
        } elseif ($errno == E_NOTICE) {
            throw new NoticeException("NOTICE: $err_str in $err_file on line $err_line");
        } elseif ($errno == E_STRICT) {
            throw new StrictException("STRICT: $err_str in $err_file on line $err_line");
        } elseif ($errno == 8192) {
            throw new DeprecatedException("DEPRECATED: $err_str in $err_file on line $err_line");
        } else throw new ErrorException("ERROR: $err_str in $err_file on line $err_line");
    }

}
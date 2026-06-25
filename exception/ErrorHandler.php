<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\exception;

use ErrorException;

use function mb_strlen;
use function mb_substr;

use nova\framework\core\Context;
use nova\framework\http\Response;
use Throwable;

/**
 * 错误处理器类
 *
 * 负责处理框架运行时的错误和异常：
 * - 注册全局错误和异常处理器
 * - 将 PHP 错误转换为异常
 * - 生成友好的错误页面
 * - 记录错误日志
 * - 支持开发和生产环境不同的错误处理策略
 */
class ErrorHandler
{
    /**
     * 注册错误处理器
     *
     * 将该类注册为全局的错误和异常处理器
     */
    public static function register(): void
    {
        set_error_handler([__CLASS__, 'appError'], E_ALL);
        set_exception_handler([__CLASS__, 'appException']);
    }

    /**
     * 处理PHP错误
     *
     * 将PHP错误转换为异常，统一错误处理流程
     *
     * @param  int            $errno    错误级别
     * @param  string         $err_str  错误信息
     * @param  string         $err_file 错误文件
     * @param  int            $err_line 错误行号
     * @return void           是否处理了错误
     * @throws ErrorException
     */
    public static function appError(int $errno, string $err_str, string $err_file = '', int $err_line = 0): void
    {
        $str = match ($errno) {
            E_WARNING => "WARNING",
            E_NOTICE => "NOTICE",
            E_STRICT => "STRICT",
            8192 => "DEPRECATED",
            default => "ERROR"
        };

        throw new ErrorException("$str: $err_str in $err_file on line $err_line");
    }

    /**
     * 处理应用异常
     *
     * 根据环境配置决定是否显示详细错误信息：
     * - 开发环境：显示详细的错误信息和调用栈
     * - 生产环境：显示友好的错误页面
     *
     * @param  Throwable        $e 捕获的异常
     * @throws AppExitException 当需要中断应用执行时
     */
    public static function appException(Throwable $e): void
    {
        $context = Context::instance();

        // Exit异常直接返回，不进行处理
        if ($e instanceof AppExitException) {
            return;
        }

        // 非调试模式或已经处理过错误时直接返回
        if (!$context->isDebug()) {
            return;
        }
        if ($context->get("hasCatchError", false)) {
            return;
        }

        // 标记已处理错误，避免递归
        $context->set("hasCatchError", true);

        // 抛出带有错误响应的异常
        throw new AppExitException(self::getExceptionResponse($e));
    }

    /**
     * 生成异常响应
     *
     * @param  Throwable $e 需要处理的异常
     * @return Response  包含错误信息的响应对象
     */
    public static function getExceptionResponse(Throwable $e): Response
    {
        $html = self::customExceptionHandler($e);
        return Response::asHtml($html);
    }

    /**
     * 自定义异常处理器
     *
     * 生成包含详细错误信息的HTML页面，包括：
     * - 错误类型和消息
     * - 文件位置和行号
     * - 调用栈信息
     *
     * @param  Throwable $exception 需要处理的异常
     * @return string    格式化的HTML错误页面
     */
    private static function customExceptionHandler(Throwable $exception): string
    {
        // 准备调用栈信息
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

        // 格式化调用栈
        $traces = sizeof($trace) === 0 ? debug_backtrace() : $trace;

        // 加载错误模板
        $tpl = file_get_contents(ROOT_PATH . "/nova/framework/error/exception.html");
        if ($tpl === false) {
            return "Error template not found";
        }

        // 获取异常类名
        $classFullName = get_class($exception);
        $classParts = explode('\\', $classFullName);
        $className = end($classParts);

        // 替换基本错误信息
        $tpl = str_replace([
            "{PHP_VERSION}",
            "{NOVA_VERSION}",
            "{ERROR_TYPE}",
            "{ERROR_MESSAGE}"
        ], [
            PHP_VERSION,
            Context::VERSION,
            $className,
            $exception->getMessage()
        ], $tpl);

        // 生成错误文件列表和代码容器 (使用 details/summary 结构)
        $TEMPLATE_LIST = "";
        $first = true;

        foreach ($traces as $key => $trace) {
            if (!is_array($trace) || empty($trace["file"])) {
                continue;
            }

            $trace["keyword"] = $trace["keyword"] ?? "";
            $sourceLine = self::errorFile($trace["file"], $trace["line"], $trace["keyword"]);
            $trace["line"] = $sourceLine["line"];
            unset($sourceLine["line"]);

            if (!$sourceLine) {
                continue;
            }

            // 构建 summary 内容：类名、类型、函数名、参数
            $summaryHtml = '';

            // 添加类名、类型和函数信息
            $clazz = $trace["class"] ?? "";
            $type = $trace["type"] ?? "";
            $function = $trace['function'] ?? '';

            if (!empty($clazz)) {
                $summaryHtml .= "<span class=\"trace-class\">{$clazz}</span>";
            }
            if (!empty($type)) {
                $summaryHtml .= "<span class=\"trace-type\">{$type}</span>";
            }
            if (!empty($function)) {
                $summaryHtml .= "<span class=\"trace-function\">{$function}(";

                // 添加函数参数
                if (!empty($trace['args'])) {
                    foreach ($trace['args'] as $i => $arg) {
                        $color = ($i % 2 == 0) ? "trace-args1" : "trace-args2";
                        $argStr = htmlspecialchars(print_r($arg, true), ENT_QUOTES);
                        // 限制参数显示长度为最多200个UTF-8字符
                        if (mb_strlen($argStr, 'UTF-8') > 200) {
                            $argStr = mb_substr($argStr, 0, 197, 'UTF-8') . '...';
                        }

                        $argStr = str_replace("\n", "<br>", $argStr);
                        $summaryHtml .= "<span class=\"trace-args {$color}\">&nbsp;&nbsp;{$argStr}&nbsp;,</span>";
                    }
                }

                $summaryHtml .= ")</span>";
            }

            // 生成 details 元素
            // 第一个 trace 默认展开，其他折叠
            if ($first) {
                $TEMPLATE_LIST .= "<details class=\"trace-entry\" open>";
                $first = false;
            } else {
                $TEMPLATE_LIST .= "<details class=\"trace-entry\">";
            }
            $TEMPLATE_LIST .= "<summary class=\"trace-summary\">";
            $TEMPLATE_LIST .= "<span class=\"trace-arrow\">▶</span>";
            $TEMPLATE_LIST .= "<span class=\"trace-file\">{$trace['file']}</span>";
            $TEMPLATE_LIST .= "<span class=\"trace-line\">#{$trace['line']}</span>";
            $TEMPLATE_LIST .= "<span class=\"trace-args\">{$summaryHtml}</span>";
            $TEMPLATE_LIST .= "</summary>";
            $TEMPLATE_LIST .= "<div class=\"trace-code\">";

            // 添加代码内容，标记当前行
            foreach ($sourceLine as $line) {
                $TEMPLATE_LIST .= "<div class=\"code-line\">{$line}</div>";
            }

            $TEMPLATE_LIST .= "</div>";
            $TEMPLATE_LIST .= "</details>";
        }

        // 替换模板变量
        $tpl = str_replace("{TEMPLATE_LIST}", $TEMPLATE_LIST, $tpl);

        return $tpl;
    }

    /**
     * 获取错误文件的相关代码行
     *
     * @param  string $file 文件路径
     * @param  int    $line 行号，-1表示使用关键字查找
     * @param  string $msg  关键字
     * @return array  包含行号和代码行的数组
     */
    public static function errorFile(string $file, int $line = -1, string $msg = ""): array
    {
        $lineCount = 15; // 上下文行数

        if (!(file_exists($file) && is_file($file))) {
            return [];
        }

        $data = file($file);
        if ($data === false) {
            return [];
        }

        $count = count($data) - 1;
        $returns = [];

        // 如果未指定行号，通过关键字查找
        if ($line == -1) {
            for ($i = 0; $i <= $count; $i++) {
                if (str_contains($data[$i], $msg)) {
                    $line = $i + 1;
                    break;
                }
            }
        }

        $returns["line"] = $line;

        // 计算显示范围
        $start = max(1, $line - $lineCount);
        $end = min($count + 1, $line + $lineCount);

        // 生成代码行HTML
        for ($i = $start; $i <= $end; $i++) {
            $number = '<span class="line-num">' . $i . '</span>';
            $codeContent = self::highlightCode($data[$i - 1]);

            if ($i == $line) {
                $returns[] = '<div class="code-line current"><span class="line-num">' . $i . '</span><span class="code-content">' . $codeContent . '</span></div>';
            } else {
                $returns[] = '<div class="code-line"><span class="line-num">' . $i . '</span><span class="code-content">' . $codeContent . '</span></div>';
            }
        }

        return $returns;
    }

    /**
     * 代码高亮处理
     *
     * @param  string $code 需要高亮的代码
     * @return string 高亮后的HTML
     */
    private static function highlightCode(string $code): string
    {
        // 处理注释标记，避免highlight_string解析错误
        $code = preg_replace('/(\/\*\*)/', '///**', $code);
        $code = preg_replace('/(\s\*)[^\/]/', '//*', $code);
        $code = preg_replace('/(\*\/)/', '//*/', $code);

        // 高亮处理
        if (preg_match('/<\?(php)?[^[:graph:]]/i', $code)) {
            $return = highlight_string($code, true);
        } else {
            $return = preg_replace(
                '/(&lt;\?php)+/i',
                "",
                highlight_string("<?php " . $code, true)
            );
        }

        // 还原注释标记
        return str_replace(['//*/', '///**', '//*'], ['*/', '/**', '*'], $return);
    }
}

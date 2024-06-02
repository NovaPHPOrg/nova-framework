<?php

namespace nova\framework\exception;

use nova\framework\App;
use nova\framework\log\Logger;
use nova\framework\request\Response;
use nova\framework\request\ResponseType;
use Throwable;

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

        if(!App::getInstance()->debug)return;
        $hasCatchError = $GLOBALS['__nova_frame_error__'] ?? false;
        if ($hasCatchError) return;
        $GLOBALS['__nova_frame_error__'] = true;
        throw new AppExitException(self::getExceptionResponse($e));
    }

    static function getExceptionResponse(Throwable $e): Response{
        $html =  self::customExceptionHandler($e);
        return new Response($html,200,ResponseType::HTML);
    }


   static function customExceptionHandler(Throwable $exception):string{
        $trace = $exception->getTrace();
        array_unshift($trace, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => '',
            'class' => get_class($exception),
            'type' => '',
            'args' => []
        ]);

        $GLOBALS['__nova_frame_error__'] = true;
        error_clear_last();
        //避免递归调用
        Logger::error($exception->getMessage());
        $traces = sizeof($trace) === 0 ? debug_backtrace() : $trace;
        $trace_text = [];
        foreach ($traces as $i => &$call) {
            $trace_text[$i] = sprintf("#%s %s(%s): %s%s%s", $i, $call['file'] ?? "", $call['line'] ?? "", $call["class"] ?? "", $call["type"] ?? "", $call['function'] ?? "");
            Logger::error($trace_text[$i]);
        }




        $html = "<div style='font-family: Arial, sans-serif; background-color: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;'>";
        $html .= "<h3 style='margin-top: 0;'>Uncaught Exception: " . get_class($exception) . "</h3>";
        $html .= "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";

        foreach ($trace as $index => $frame) {
            $html .= ErrorHandler::generateErrorHtml("Stack frame #$index", '', '', $frame['file'], $frame['line']);
        }

        $html .= "</div>";

        return $html;
    }

    static function generateErrorHtml($title, $errno, $errstr, $errfile, $errline): string
    {
        if (!file_exists($errfile)) {
            return '';
        }

        $fileLines = file($errfile);
        $contextRange = 5;
        $startLine = max(0, $errline - $contextRange - 1);
        $endLine = min(count($fileLines), $errline + $contextRange);
        $errorContext = array_slice($fileLines, $startLine, $endLine - $startLine, true);
        $codeContext = implode('', $errorContext);
        $highlightedCode = highlight_string("<?php\n" . $codeContext, true);
        $highlightedCode = preg_replace('/^<code><span style="color: #000000">\&lt;\?php<br \/>\n<\/span>/', '<code><span style="color: #000000">', $highlightedCode, 1);

        $highlightedCodeLines = explode("\n", $highlightedCode);
        foreach ($highlightedCodeLines as $index => $line) {
            if ($index + 1 == $errline - $startLine + 1) {
                $highlightedCodeLines[$index] = "<span style='background-color: #f8d7da; color: #721c24; display: inline-block; width: 100%;'>" . $line . "</span>";
            }
        }
        $highlightedCode = implode("\n", $highlightedCodeLines);

        $html = "<h4>$title</h4>";
        if ($errno) {
            $html .= "<p><strong>Error [$errno]:</strong> $errstr</p>";
        }
        $html .= "<p><strong>File:</strong> $errfile</p>";
        $html .= "<p><strong>Line:</strong> $errline</p>";

        $html .= "<details open>";
        $html .= "<summary>Context</summary>";
        $html .= "<pre style='background-color: #f1f1f1; padding: 10px; border-radius: 5px; border: 1px solid #ddd; overflow: auto; white-space: pre-wrap;'>";
        $html .= $highlightedCode;
        $html .= "</pre>";
        $html .= "</details>";

        return $html;
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
    public static function appError(int $errno, string $err_str, string $err_file = '', int $err_line = 0): bool
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
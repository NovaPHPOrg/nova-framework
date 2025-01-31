<?php
declare(strict_types=1);

namespace nova\framework\json;

use Exception;
use nova\framework\core\Logger;
use Throwable;

/**
 * JSON 解码异常类
 * 
 * 当 JSON 字符串解码失败时抛出此异常
 */
class JsonDecodeException extends Exception
{
    /**
     * 构造函数
     *
     * @param string $message    错误信息
     * @param string $json       导致错误的 JSON 字符串
     * @param int $code         错误代码
     * @param Throwable|null $previous  上一个异常（用于异常链）
     */
    public function __construct(string $message = "", $json = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::warning("json decode error => $message",[$json]);
    }
}
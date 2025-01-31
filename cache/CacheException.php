<?php
declare(strict_types=1);

namespace nova\framework\cache;

use Exception;
use Throwable;

/**
 * 缓存操作异常类
 * 
 * 该类用于处理缓存相关操作过程中出现的异常情况。
 * 继承自PHP标准异常类(Exception)，并自动记录警告日志。
 */
class CacheException extends Exception
{
    /**
     * CacheException构造函数
     *
     * @param string $message 异常信息
     * @param int $code 异常代码
     * @param Throwable|null $previous 上一个异常（用于异常链）
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
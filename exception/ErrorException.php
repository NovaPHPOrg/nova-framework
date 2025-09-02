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

use Exception;
use nova\framework\core\Logger;
use Throwable;

/**
 * 错误异常类
 *
 * 该类扩展了PHP标准异常类，并增加了自动日志记录功能。
 * 当抛出此异常时，错误信息会自动记录到日志系统中。
 */
class ErrorException extends Exception
{
    /**
     * ErrorException 构造函数
     *
     * @param string         $message  错误信息
     * @param int            $code     错误代码
     * @param Throwable|null $previous 前一个异常（用于异常链）
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::error($message);
    }
}

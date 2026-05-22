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
use Throwable;

/**
 * 由 ErrorHandler 将 PHP 错误转换而来的异常，由 App 统一记录日志。
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
    }
}

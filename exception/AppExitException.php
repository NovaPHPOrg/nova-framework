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
use nova\framework\core\Context;
use nova\framework\http\Response;

/**
 * 应用程序退出异常类
 * 用于在需要立即终止应用程序执行并返回响应时抛出
 */
class AppExitException extends Exception
{
    /** @var Response 需要返回的响应对象 */
    private Response $response;

    /**
     * 构造函数
     * @param mixed  $response 需要返回的响应
     * @param string $message  异常信息
     */
    public function __construct($response, $message = "App Exit")
    {
        if (Context::instance()->isDebug()) {
            $message .= "( Called By {$this->getPreviousFunction()} )";
        }
        parent::__construct($message, 0, null);
        $this->response = $response;
    }

    /**
     * 获取响应对象
     * @return Response
     */
    public function response(): Response
    {
        return $this->response;
    }

    /**
     * 获取调用此异常的上一个函数名
     * @return string|null 返回函数名，如果无法获取则返回null
     */
    public function getPreviousFunction(): ?string
    {
        $backtrace = debug_backtrace();
        return $backtrace[2]['function'] ?? null;
    }
}

<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\route;

use Exception;
use nova\framework\core\Logger;

/**
 * 控制器异常类
 *
 * 用于处理控制器相关的异常情况，包括路由错误等
 */
class ControllerException extends Exception
{
    /**
     * 与异常相关的路由对象
     *
     * @var RouteObject|null
     */
    private ?RouteObject $route;

    /**
     * 构造函数
     *
     * @param string $message 异常信息
     * @param RouteObject|null $route 相关的路由对象
     */
    public function __construct(string $message = "", RouteObject $route = null)
    {
        parent::__construct($message, 0, null);
        Logger::error($message);
        $this->route = $route;
    }

    /**
     * 获取与异常相关的路由对象
     *
     * @return RouteObject|null 返回路由对象，如果不存在则返回null
     */
    public function route(): ?RouteObject
    {
        return $this->route;
    }
}

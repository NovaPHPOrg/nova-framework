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

use nova\framework\core\NovaApp;
use nova\framework\http\Request;
use nova\framework\http\Response;

/**
 * 控制器基类
 *
 * 所有应用控制器都应继承此类，提供了基础的请求处理和初始化功能
 */
class Controller extends NovaApp
{
    /**
     * 当前请求实例
     *
     * @var Request
     */
    protected Request $request;

    /**
     * 构造函数
     *
     * 初始化控制器并获取当前请求实例
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = $this->context->request();
    }

    /**
     * 控制器初始化方法
     *
     * 在执行具体的操作方法之前会先调用此方法
     * 可以在此方法中进行权限检查等通用操作
     *
     * @return Response|null 如果返回Response对象，则直接输出该响应；返回null则继续执行后续操作
     */
    public function init(): ?Response
    {
        return null;
    }
}

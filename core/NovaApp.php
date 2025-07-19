<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\core;

/**
 * Nova 应用程序主类
 *
 * 这是 Nova 框架的核心应用程序类，负责管理应用程序的上下文和生命周期。
 * 该类作为应用程序的入口点，提供框架的基础功能。
 *
 * @package nova\framework\core
 * @author Nova Framework
 * @since 1.0.0
 */
class NovaApp
{
    /**
     * 应用程序上下文实例
     *
     * 存储应用程序的全局上下文信息，包括配置、环境变量等。
     *
     * @var Context
     */
    protected Context $context;

    /**
     * 构造函数
     *
     * 初始化 NovaApp 实例，创建并设置应用程序上下文。
     * 在应用程序启动时自动调用。
     *
     * @throws \Exception 当上下文初始化失败时抛出异常
     */
    public function __construct()
    {
        $this->context = Context::instance();
    }

}

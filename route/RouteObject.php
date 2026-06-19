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

/**
 * 路由对象类
 * 用于存储和处理单个路由的详细信息，包括模块、控制器、动作和参数
 */
class RouteObject extends AbstractRouteObject
{
    /**
     * 获取控制器类名（app 模块）
     */
    protected function getControllerClass(): string
    {
        return "app\\controller\\{$this->module}\\{$this->controller}";
    }
}

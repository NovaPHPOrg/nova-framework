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

use function nova\framework\config;

use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\NovaApp;
use nova\framework\event\EventManager;

use function nova\framework\route;

/**
 * 路由管理类
 *
 * 负责处理HTTP请求的路由注册和分发，提供完整的路由管理功能：
 * - 支持多种HTTP方法的路由注册
 * - 支持参数化路由和正则匹配
 * - 提供路由缓存和性能优化
 * - 支持默认路由规则
 *
 * 使用示例：
 * ```php
 * Route::getInstance()
 *     ->get('/users/{id}', route('user', 'main', 'show'))
 *     ->post('/users', route('user', 'main', 'create'));
 * ```
 */
class Route extends NovaApp
{
    use RouteTrait;

    /**
     * 构造函数，禁止直接实例化
     * 使用单例模式管理路由实例
     */
    private function __construct()
    {
        parent::__construct();
    }

    /**
     * 根据URI和请求方法分发路由
     *
     * @param  string              $uri    请求URI
     * @param  string              $method HTTP请求方法
     * @return AbstractRouteObject 匹配的路由对象，失败返回 null
     */
    public function dispatch(string $uri, string $method): AbstractRouteObject
    {
        $this->uri = $this->removeQueryStringVariables($uri);

        if (empty($this->uri)) {
            $this->uri = '/';
        }

        Logger::debug(sprintf(
            'Route dispatch: %s %s',
            $method,
            $this->uri,
        ));

        EventManager::getInstance()->trigger("route.before", $this->uri);

        if (config('default_route') ?? false) {
            $this->routeIndex['ANY']["/{module}/{controller}/{action}"] = route("{module}", "{controller}", "{action}");
            $this->routeIndex['ANY']["/{module}/{controller}"] = route("{module}", "{controller}", "index");
            $this->routeIndex['ANY']["/{module}"] = route("{module}", "main", "index");
        }

        $routeObj = $this->findMatchingRoute($method);

        EventManager::getInstance()->trigger("route.after", $routeObj);

        if ($routeObj === null) {
            Logger::debug(sprintf(
                'Route miss: %s %s',
                $method,
                $this->uri,
            ));
            throw new ControllerException("Route not found: " . $this->uri);
        }

        Logger::debug(sprintf(
            'Route matched: %s %s -> %s params=%d ',
            $method,
            $this->uri,
            $routeObj,
            count($routeObj->params),
        ));

        return $routeObj;
    }

    /**
     * 获取Route类的单例实例
     *
     * @return self Route类实例
     */
    public static function getInstance(): self
    {
        return Context::instance()->getOrCreateInstance("Route", function () {
            return new Route();
        });
    }
}

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

use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\NovaApp;
use nova\framework\event\EventManager;
use function nova\framework\config;
use function nova\framework\route;

/**
 * 路由管理类
 * 负责处理HTTP请求的路由注册和分发
 */
class Route extends NovaApp
{
    /** @var array 正则规则缓存 */
    private static array $ruleCache = [];
    /** @var array 按HTTP方法索引的路由缓存 */
    private array $routeIndex = [];
    /** @var string 当前请求的URI */
    private string $uri = "";
    /** @var string 网站根路径 */
    private string $root = "";

    /**
     * 构造函数，禁止直接实例化
     */
    private function __construct()
    {
        parent::__construct();
    }

    public function getOrPost(string $uri, RouteObject $mapper): self
    {
        $this->get($uri, $mapper)->post($uri, $mapper);
        return $this;
    }

    /**
     * 注册 POST 请求路由
     *
     * @param string $uri 路由URI，支持参数模式，如: /users
     * @param RouteObject $mapper 路由映射对象
     * @return self        返回当前实例以支持链式调用
     */
    public function post(string $uri, RouteObject $mapper): self
    {
        $this->add($uri, $mapper, "POST");
        return $this;
    }

    /**
     * 添加路由到路由表中
     *
     * @param string $uri 路由URI
     * @param RouteObject $mapper 路由映射对象
     * @param string $method HTTP请求方法
     */
    private function add(string $uri, RouteObject $mapper, string $method = ""): void
    {
        // 规范化URI
        $uri = '/' . trim($uri, '/');

        if (!empty($method)) {
            $this->routeIndex[$method][$uri] = $mapper;
        } else {
            $this->routeIndex['ANY'][$uri] = $mapper;
        }
    }

    /**
     * 注册 GET 请求路由
     * 同时会自动注册对应的 HEAD 请求路由
     *
     * @param string $uri 路由URI，支持参数模式，如: /users/{id}
     * @param RouteObject $mapper 路由映射对象
     * @return self        返回当前实例以支持链式调用
     */
    public function get(string $uri, RouteObject $mapper): self
    {
        $this->add($uri, $mapper, "GET");
        $this->add($uri, $mapper, "HEAD");
        return $this;
    }

    /**
     * 注册 PATCH 请求路由
     * 通常用于部分更新资源
     *
     * @param string $uri 路由URI，支持参数模式，如: /users/{id}
     * @param RouteObject $mapper 路由映射对象
     * @return self        返回当前实例以支持链式调用
     */
    public function patch(string $uri, RouteObject $mapper): self
    {
        $this->add($uri, $mapper, "PATCH");
        return $this;
    }

    /**
     * 注册 OPTIONS 请求路由
     * 用于响应浏览器的预检请求
     *
     * @param string $uri 路由URI，支持参数模式，如: /users/{id}
     * @param RouteObject $mapper 路由映射对象
     * @return self        返回当前实例以支持链式调用
     */
    public function options(string $uri, RouteObject $mapper): self
    {
        $this->add($uri, $mapper, "OPTIONS");
        return $this;
    }

    /**
     * 注册 PUT 请求路由
     * 通常用于完整更新资源
     *
     * @param string $uri 路由URI，支持参数模式，如: /users/{id}
     * @param RouteObject $mapper 路由映射对象
     * @return self        返回当前实例以支持链式调用
     */
    public function put(string $uri, RouteObject $mapper): self
    {
        $this->add($uri, $mapper, "PUT");
        return $this;
    }

    /**
     * 注册 DELETE 请求路由
     * 用于删除资源
     *
     * @param string $uri 路由URI，支持参数模式，如: /users/{id}
     * @param RouteObject $mapper 路由映射对象
     * @return self        返回当前实例以支持链式调用
     */
    public function delete(string $uri, RouteObject $mapper): self
    {
        $this->add($uri, $mapper, "DELETE");
        return $this;
    }

    /**
     * 根据URI和请求方法分发路由
     *
     * @param string $uri 请求URI
     * @param string $method HTTP请求方法
     * @return RouteObject         匹配的路由对象
     * @throws ControllerException 当路由未找到时抛出异常
     */
    public function dispatch(string $uri, string $method): RouteObject
    {
        $this->uri = $this->removeQueryStringVariables($uri);

        if (empty($this->uri)) {
            $this->uri = '/';
        }

        EventManager::getInstance()->trigger("route.before", $this->uri);

        Logger::debug("Route dispatch: $method " . $this->uri);

        if (config('default_route') ?? false) {
            $this->routeIndex['ANY']["/{module}/{controller}/{action}"] = route("{module}", "{controller}", "{action}");
        }


        $routeObj = $this->findMatchingRoute($method);

        EventManager::getInstance()->trigger("route.after", $routeObj);

        if ($routeObj === null) {
            throw new ControllerException("Route not found: " . $this->uri);
        }

        Logger::debug("Route object: $this->uri ->  $routeObj");

        return $routeObj;
    }

    /**
     * 移除URI中的查询字符串变量
     * 同时处理/public和/index.php前缀
     *
     * @param string $uri 原始URI
     * @return string 处理后的URI
     */
    private function removeQueryStringVariables(string $uri): string
    {
        $raw = $uri;

        // URI净化
        $uri = filter_var($uri, FILTER_SANITIZE_URL);

        // 移除查询字符串
        $parts = explode('?', $uri, 2);
        if (count($parts) > 1) {
            $uri = $parts[0];
        }

        // 规范化路径
        $uri = '/' . trim($uri, '/');

        if (str_starts_with($uri, "/public")) {
            $uri = substr($uri, 7);
            Logger::warning("Don't use /public in uri: $uri, it's unsafe. Please use nginx or apache to set root path.");
        }

        if (str_starts_with($uri, "/index.php")) {
            $uri = substr($uri, 10);
        }

        $this->root = Context::instance()->request()->getBasicAddress() . str_replace($uri, "", $raw);
        Logger::debug("Route removeQueryStringVariables: $uri");

        return $uri;
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

    /**
     * 查找匹配的路由
     *
     * @param string $method HTTP请求方法
     * @return RouteObject|null 返回匹配的路由对象，如果未找到则返回null
     */
    private function findMatchingRoute(string $method): ?RouteObject
    {
        // 先检查指定方法的路由
        if (isset($this->routeIndex[$method])) {
            foreach ($this->routeIndex[$method] as $uri => $route) {
                $rule = $this->buildRegexRule($uri);
                Logger::debug("Route key: $uri  rule: $rule  uri: " . $this->uri);

                if (preg_match($rule, $this->uri, $matches)) {
                    $this->setMatchedParameters($matches);
                    $route->updateParams($matches);
                    return $route;
                }
            }
        }

        // 检查通用路由
        if (isset($this->routeIndex['ANY'])) {
            foreach ($this->routeIndex['ANY'] as $uri => $route) {
                $rule = $this->buildRegexRule($uri);
                Logger::debug("Route key: $uri  rule: $rule  uri: " . $this->uri);

                if (preg_match($rule, $this->uri, $matches)) {
                    $this->setMatchedParameters($matches);
                    $route->updateParams($matches);
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * 构建路由正则表达式规则
     *
     * @param string $key 路由规则
     * @return string 转换后的正则表达式
     */
    private function buildRegexRule(string $key): string
    {
        if (isset(self::$ruleCache[$key])) {
            return self::$ruleCache[$key];
        }

        $rule = '@^' . str_ireplace(
                ['\\\\', '.', '/', '@number}', '@word}', '{', '}'],
                ['', '\.', '\/', '>\d+)', '>\w+)', '(?P<', '>.+?)'],
                strtolower($key)
            ) . '$@ui';

        self::$ruleCache[$key] = $rule;
        return $rule;
    }

    /**
     * 设置匹配到的路由参数到$_GET数组中
     *
     * @param array $matches 正则匹配结果
     */
    private function setMatchedParameters(array $matches): void
    {
        foreach ($matches as $k => $v) {
            if (is_string($k)) {
                $_GET[$k] = htmlspecialchars($v);
            }
        }
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 获取网站根路径
     *
     * @return string 网站根路径
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * 清除路由缓存
     */
    public function clearCache(): void
    {
        self::$ruleCache = [];
    }
}

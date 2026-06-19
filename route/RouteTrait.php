<?php

declare(strict_types=1);

namespace nova\framework\route;

use nova\framework\core\Logger;

/**
 * 路由 Trait
 *
 * 提供路由注册和分发的核心逻辑，可被插件直接 use
 *
 * 使用示例：
 * ```php
 * class PluginRouter
 * {
 *     use RouteTrait;
 *
 *     public function __construct()
 *     {
 *         $this->get('/plugin/api', route('plugin', 'api', 'index'));
 *     }
 * }
 * ```
 */
trait RouteTrait
{
    /**
     * 按HTTP方法索引的路由缓存
     */
    private array $routeIndex = [];

    /**
     * 当前请求的URI
     */
    private string $uri = "";

    /**
     * 网站根路径
     */
    private string $root = "";

    /**
     * 注册同时支持GET和POST的路由
     */
    public function getOrPost(string $uri, AbstractRouteObject $mapper): self
    {
        $this->get($uri, $mapper)->post($uri, $mapper);
        return $this;
    }

    /**
     * 注册 POST 请求路由
     */
    public function post(string $uri, AbstractRouteObject $mapper): self
    {
        $this->add($uri, $mapper, "POST");
        return $this;
    }

    /**
     * 添加路由到路由表中
     */
    protected function add(string $uri, AbstractRouteObject $mapper, string $method = ""): void
    {
        $uri = '/' . ltrim($uri, '/');

        if (!empty($method)) {
            $this->routeIndex[$method][$uri] = $mapper;
        } else {
            $this->routeIndex['ANY'][$uri] = $mapper;
        }
    }

    /**
     * 注册 GET 请求路由
     */
    public function get(string $uri, AbstractRouteObject $mapper): self
    {
        $this->add($uri, $mapper, "GET");
        $this->add($uri, $mapper, "HEAD");
        return $this;
    }

    /**
     * 注册 PATCH 请求路由
     */
    public function patch(string $uri, AbstractRouteObject $mapper): self
    {
        $this->add($uri, $mapper, "PATCH");
        return $this;
    }

    /**
     * 注册 OPTIONS 请求路由
     */
    public function options(string $uri, AbstractRouteObject $mapper): self
    {
        $this->add($uri, $mapper, "OPTIONS");
        return $this;
    }

    /**
     * 注册 PUT 请求路由
     */
    public function put(string $uri, AbstractRouteObject $mapper): self
    {
        $this->add($uri, $mapper, "PUT");
        return $this;
    }

    /**
     * 注册 DELETE 请求路由
     */
    public function delete(string $uri, AbstractRouteObject $mapper): self
    {
        $this->add($uri, $mapper, "DELETE");
        return $this;
    }

    /**
     * 插件路由分发（静默模式）
     *
     * @return AbstractRouteObject|null 匹配的路由，失败返回 null
     */
    public function dispatch(string $uri, string $method): ?AbstractRouteObject
    {
        $this->uri = $this->removeQueryStringVariables($uri);

        if (empty($this->uri)) {
            $this->uri = '/';
        }

        Logger::debug(sprintf('Plugin route dispatch: %s %s', $method, $this->uri));

        try {
            return $this->findMatchingRoute($method);
        } catch (ControllerException $e) {
            Logger::debug(sprintf('Plugin route miss: %s', $e->getMessage()));
            return null;
        }
    }

    /**
     * 查找匹配的路由
     */
    private function findMatchingRoute(string $method): ?AbstractRouteObject
    {
        if (isset($this->routeIndex[$method])) {
            foreach ($this->routeIndex[$method] as $uri => $route) {
                $rule = $this->buildRegexRule($uri);

                if (preg_match($rule, $this->uri, $matches)) {
                    $this->setMatchedParameters($matches);
                    $route->updateParams($matches);
                    return $route;
                }
            }
        }

        if (isset($this->routeIndex['ANY'])) {
            foreach ($this->routeIndex['ANY'] as $uri => $route) {
                $rule = $this->buildRegexRule($uri);

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
     */
    private function buildRegexRule(string $key): string
    {
        $rule = '@^' . str_ireplace(
            ['\\\\', '.', '/', '@number}', '@word}', '{', '}'],
            ['', '\.', '\/', '>\d+)', '>\w+)', '(?P<', '>.+?)'],
            strtolower($key)
        ) . '$@ui';

        return $rule;
    }

    /**
     * 设置匹配到的路由参数到$_GET数组中
     */
    private function setMatchedParameters(array $matches): void
    {
        foreach ($matches as $k => $v) {
            if (is_string($k)) {
                $_GET[$k] = htmlspecialchars(urldecode($v));
            }
        }
    }

    /**
     * 移除URI中的查询字符串变量
     */
    private function removeQueryStringVariables(string $uri): string
    {
        $raw = $uri;

        $uri = filter_var($uri, FILTER_SANITIZE_URL);

        $parts = explode('?', $uri, 2);
        if (count($parts) > 1) {
            $uri = $parts[0];
        }

        $uri = '/' . ltrim($uri, '/');

        if (str_starts_with($uri, "/public")) {
            $uri = substr($uri, 7);
        }

        if (str_starts_with($uri, "/index.php")) {
            $uri = substr($uri, 10);
        }

        $this->root = $this->getBasicAddress() . str_replace($uri, "", $raw);

        return $uri;
    }

    /**
     * 获取基础访问地址
     */
    private function getBasicAddress(): string
    {
        $scheme = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on'
            ? 'https://' : 'http://';
        return $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * 获取当前请求的URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 获取网站根路径
     */
    public function getRoot(): string
    {
        return $this->root;
    }

}

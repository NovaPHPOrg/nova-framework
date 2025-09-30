<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace nova\framework\http;

use nova\framework\route\RouteObject;

/**
 * HTTP请求处理类
 *
 * 该类负责处理所有与HTTP请求相关的操作，包括：
 * - 请求头的处理
 * - URL和域名解析
 * - 请求方法判断
 * - 参数获取
 * - 客户端信息获取
 *
 * @author Ankio
 * @version 1.0
 * @since 2025-01-01
 */
class Request
{
    /**
     * 请求唯一标识符
     * 用于跟踪和识别不同的请求
     */
    protected string $id;

    /**
     * 路由对象
     * 存储当前请求匹配的路由信息
     */
    private ?RouteObject $route = null;

    /**
     * 请求头数组
     * 缓存解析后的HTTP请求头信息
     */
    private array $headers = [];

    /**
     * 构造函数
     *
     * 初始化请求ID，为每个请求生成唯一标识符
     */
    public function __construct()
    {
        $this->id = uniqid("req_", true);
    }

    /**
     * 获取路由对象
     *
     * @return RouteObject|null 返回当前请求的路由对象
     */
    public function getRoute(): ?RouteObject
    {
        return $this->route;
    }

    /**
     * 设置路由对象
     *
     * @param RouteObject $route 路由对象
     */
    public function setRoute(RouteObject $route): void
    {
        $this->route = $route;
    }

    /**
     * 获取请求唯一标识符
     *
     * @return string 返回请求ID
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * 获取请求URI
     *
     * @return string 返回当前请求的URI
     */
    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取指定请求头的值
     *
     * @param  string     $headName 头部字段名称
     * @return mixed|null 返回头部值，不存在时返回null
     */
    public function getHeaderValue($headName): mixed
    {
        if (empty($this->headers)) {
            $this->initHeaders();
        }
        if (isset($this->headers[$headName])) {
            return $this->headers[$headName];
        }
        return null;
    }

    /**
     * 初始化请求头信息
     *
     * 支持Apache和Nginx等不同服务器环境
     * 处理各种HTTP头部信息的解析
     */
    private function initHeaders(): void
    {
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
            return;
        }
        $this->headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $this->headers[ucfirst(strtolower(str_replace('_', '-', substr($key, 5))))] = $value;
            }
            if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $this->headers['AUTHORIZATION'] = $_SERVER['PHP_AUTH_DIGEST'];
            } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
                $this->headers['AUTHORIZATION'] = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
            }
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $this->headers['CONTENT-LENGTH'] = $_SERVER['CONTENT_LENGTH'];
            }
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $this->headers['CONTENT-TYPE'] = $_SERVER['CONTENT_TYPE'];
            }
        }
    }

    /**
     * 获取所有请求头信息
     *
     * @return array 返回所有请求头数组
     */
    public function getHeaders(): array
    {
        if (empty($this->headers)) {
            $this->initHeaders();
        }
        return $this->headers;
    }

    /**
     * 获取域名（不含端口）
     *
     * 例如：example.com
     *
     * @return string 返回域名
     */
    public function getDomainNoPort(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        if ($host === '') {
            return '';
        }
        $scheme = $this->getHttpScheme();
        $parsed = parse_url($scheme . $host, PHP_URL_HOST);
        return $parsed ?: $host;
    }

    /**
     * 获取域名（含端口）
     *
     * 例如：example.com 或 example.com:8088
     *
     * @return string 返回域名
     */
    public function getDomain(): string
    {
        return $_SERVER["HTTP_HOST"];
    }

    /**
     * 获取当前访问的完整地址
     *
     * 例如：https://example.com/index/main
     *
     * @return string 返回完整的访问地址
     */
    public function getNowAddress(): string
    {
        return $this->getHttpScheme() . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取当前请求的HTTP协议类型
     *
     * @return string 返回 'http://' 或 'https://'
     */
    public function getHttpScheme(): string
    {
        // 判断是否为 HTTPS
        if ($this->isHttps()) {
            $httpScheme = 'https://';
        } else {
            $httpScheme = 'http://';
        }

        return $httpScheme;
    }

    /**
     * 判断当前请求是否为HTTPS
     *
     * 通过多种方式检测HTTPS状态
     *
     * @return bool 如果是HTTPS返回true，否则返回false
     */
    private function isHttps(): bool
    {
        return (
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) == 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        );
    }

    /**
     * 获取基础访问地址
     *
     * 例如：https://example.com/
     *
     * @return string 返回基础地址
     */
    public function getBasicAddress(): string
    {
        return $this->getHttpScheme() . $_SERVER["HTTP_HOST"];
    }

    /**
     * 尝试获取服务器 IP。
     *
     * 优先级：
     *   1) $_SERVER['SERVER_ADDR']（Web 环境最快）
     *   2) gethostbyname()/gethostbynamel()（SAPI 无关）
     *   3) shell_exec('hostname -I')      （Linux 通用，需要 shell 权限）
     *   4) sockets trick (UDP connect)    （最兜底，需 sockets 扩展）
     *
     * @return string|null 找不到就 null
     */
    public function getServerIp(): ?string
    {

        // 1) Web 环境最直接
        if (!empty($_SERVER['SERVER_ADDR']) &&
            filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP)
        ) {
            return $_SERVER['SERVER_ADDR'];
        }

        // 2) 主机名解析（CLI 也能用）
        $hostname = gethostname();
        if ($hostname) {
            // 单 IP
            $ip = gethostbyname($hostname);
            if ($ip !== $hostname && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 实在拿不到
        return null;
    }

    /**
     * 获取GET参数
     *
     * @param  string|null $key     参数键名
     * @param  mixed|null  $default 默认值
     * @return mixed       返回参数值
     */
    public function get(string $key = null, mixed $default = null): mixed
    {
        return Arguments::get($key, $default);
    }

    /**
     * 获取客户端真实IP
     *
     * 移除可能存在的端口号
     *
     * @return string 返回客户端IP地址
     */
    public function getClientIP(): string
    {
        $ip = $_SERVER["REMOTE_ADDR"];
        // 移除可能存在的端口号
        if (str_contains($ip, ':')) {
            $ip = strstr($ip, ':', true);
        }
        return $ip;
    }

    /**
     * 判断是否为PJAX请求
     *
     * @return bool 如果是PJAX请求返回true
     */
    public function isPjax(): bool
    {
        return isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] === 'true';
    }

    /**
     * 判断是否为AJAX请求
     *
     * @return bool 如果是AJAX请求返回true
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * 判断是否为GET请求
     *
     * @return bool 如果是GET请求返回true
     */
    public function isGet(): bool
    {
        return $this->getRequestMethod() === 'GET';
    }

    /**
     * 获取请求方法
     *
     * @return string 返回HTTP请求方法
     */
    public function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * 判断是否为POST请求
     *
     * @return bool 如果是POST请求返回true
     */
    public function isPost(): bool
    {
        return $this->getRequestMethod() === 'POST';
    }

    /**
     * 获取服务器端口
     *
     * @return int 返回服务器端口号
     */
    public function port(): int
    {
        return intval($_SERVER['SERVER_PORT']);
    }

    /**
     * 获取POST参数
     *
     * @param  string|null $key     参数键名
     * @param  mixed|null  $default 默认值
     * @return mixed       返回参数值
     */
    public function post(string $key = null, mixed $default = null): mixed
    {
        return Arguments::post($key, $default);
    }

    /**
     * 获取路由参数
     *
     * @param  string|null $key     参数键名
     * @param  mixed|null  $default 默认值
     * @return mixed       返回参数值
     */
    public function arg(string $key = null, mixed $default = null): mixed
    {
        return Arguments::arg($key, $default);
    }

    /**
     * 获取JSON请求数据
     *
     * @return array 返回解析后的JSON数据
     */
    public function json(): array
    {
        return Arguments::json();
    }

    /**
     * 获取原始请求数据
     *
     * @return string 返回原始请求内容
     */
    public function raw(): string
    {
        return Arguments::raw();
    }

    public function file(string $name): ?UploadModel
    {
        return Arguments::file($name);
    }
}

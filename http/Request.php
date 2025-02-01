<?php

namespace nova\framework\http;

use nova\framework\core\Context;
use nova\framework\route\RouteObject;

/**
 * Request类 - 处理HTTP请求
 * 
 * 该类负责处理所有与HTTP请求相关的操作，包括：
 * - 请求头的处理
 * - URL和域名解析
 * - 请求方法判断
 * - 参数获取
 */
class Request
{
    /**
     * 请求唯一标识符
     */
    protected string $id;
    

    /**
     * 路由对象
     */
    private ?RouteObject $route = null;

    /**
     * 请求头数组
     */
    private array $headers = [];

    /**
     * 构造函数 - 初始化请求ID
     */
    public function __construct()
    {
        $this->id = uniqid("req_", true);
    }

    function setRoute(RouteObject $route): void
    {
        $this->route = $route;
    }
    
    function getRoute(): ?RouteObject
    {
        return $this->route;
    }

    /**
     * 获取请求唯一标识符
     * @return string 返回请求ID
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * 获取请求URI
     * @return string 返回当前请求的URI
     */
    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取header头部内容
     * @param string $headName 头部字段名称
     * @return mixed|null 返回头部值，不存在时返回null
     */
    public function getHeaderValue($headName): mixed
    {
        if(empty($this->headers)){
            $this->initHeaders();
        }
        if (isset($this->headers[$headName])) {
            return $this->headers[$headName];
        }
        return null;
    }

    /**
     * 获取所有请求头信息
     * @return array 返回所有请求头数组
     */
    public function getHeaders(): array
    {
        if(empty($this->headers)){
            $this->initHeaders();
        }
        return $this->headers;
    }

    /**
     * 初始化请求头信息
     * 支持Apache和Nginx等不同服务器环境
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
     * 获取当前请求的HTTP协议类型
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
     * 获取域名，无端口
     * 例如：example.com
     * @return string
     */
    public function getDomainNoPort(): string
    {
        return $_SERVER["SERVER_NAME"];
    }

    /**
     * 获取域名
     * 例如：example.com 或 example.com:8088
     * @return string
     */
    public function getDomain(): string
    {
        return $_SERVER["HTTP_HOST"];
    }

    /**
     * 获取当前访问的地址
     * 例如：https://example.com/index/main
     * @return string
     */
    public function getNowAddress(): string
    {
        return $this->getHttpScheme() . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取当前访问的地址
     * 例如：https://example.com/
     * @return string
     */
    public function getBasicAddress(): string
    {
        return $this->getHttpScheme() . $_SERVER["HTTP_HOST"] ;
    }

    /**
     * 获取服务器IP地址
     * @param Context $context 上下文对象
     * @return string 返回服务器IP地址
     */
    public function getServerIp(Context $context): string
    {
        $ip = $context->config()->get('ip');
        if (!empty($ip))return $ip;

        $data = gethostbyname($_SERVER["SERVER_NAME"]);
        if ($data !== $_SERVER["SERVER_NAME"]) {
            $context->config()->set('ip',$data);
            return $data;
        }
        return $data;
    }

    /**
     * 获取客户端真实IP
     * @return string
     */
    public function getClientIP(): string
    {
        return $_SERVER["REMOTE_ADDR"];
    }

    /**
     * 判断是否为PJAX请求
     * @return bool 如果是PJAX请求返回true，否则返回false
     */
    public function isPjax(): bool
    {
        return (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true');
    }

    /**
     * 判断是否为AJAX请求
     * @return bool 如果是AJAX请求返回true，否则返回false
     */
    public function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }

    /**
     * 判断是否为GET请求
     * @return bool 如果是GET请求返回true，否则返回false
     */
    public function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }

    /**
     * 获取请求方法
     * @return string 返回HTTP请求方法（GET、POST等）
     */
    public function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * 判断是否为POST请求
     * @return bool 如果是POST请求返回true，否则返回false
     */
    public function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    /**
     * 获取请求端口号
     * @return int 返回服务器端口号
     */
    public function port(): int
    {
        return intval($_SERVER['SERVER_PORT']);
    }

    /**
     * 获取GET参数
     * @param string|null $key 参数键名
     * @param mixed|null $default 默认值
     * @return mixed 返回参数值
     */
    public function get(string $key = null, mixed $default = null): mixed
    {
        return Arguments::get($key, $default);
    }

    /**
     * 获取POST参数
     * @param string|null $key 参数键名
     * @param mixed|null $default 默认值
     * @return mixed 返回参数值
     */
    public function post(string $key = null, mixed $default = null): mixed
    {
        return Arguments::post($key, $default);
    }

    /**
     * 获取任意请求参数（GET/POST）
     * @param string|null $key 参数键名
     * @param mixed|null $default 默认值
     * @return mixed 返回参数值
     */
    public function arg(string $key = null, mixed $default = null): mixed
    {
        return Arguments::arg($key, $default);
    }

    /**
     * 获取JSON格式的请求数据
     * @return array 返回解析后的JSON数组
     */
    public function json(): array
    {
        return Arguments::json();
    }

    /**
     * 获取原始请求数据
     * @return string 返回原始请求数据
     */
    public function raw(): string
    {
        return Arguments::raw();
    }
}
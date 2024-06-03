<?php

namespace nova\framework\request;

class Request
{
   private array $headers = [];

   private string $module = "";
   private string $controller = "";

   private string $action = "";

    public function __construct($module,$controller,$action)
    {
        $this->action = $action;
        $this->controller = $controller;
        $this->module = $module;
    }


    public function get(string $key = null, mixed $default = null): mixed
    {
       return Argument::get($key,$default);
    }

    public function post(string $key = null, mixed $default = null): mixed
    {
        return Argument::post($key,$default);
    }

    public function arg(string $key = null, mixed $default = null): mixed
    {
        return Argument::arg($key,$default);
    }

    public function json(): array
    {
        return Argument::json();
    }

    public function raw(): string
    {
        return Argument::raw();
    }

    public function getModule(): string
    {
        return $this->module;
    }


    public function getController(): string
    {
        return $this->controller;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getUri(): string
    {
       return $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取header头部内容
     * @param $headName
     * @return mixed|null
     */
    public  function getHeaderValue($headName): mixed
    {
        if(empty($this->headers)){
            $this->initHeaders();
        }
        if (isset($this->headers[$headName])) {
            return $this->headers[$headName];
        }
        return null;
    }

    private  function initHeaders(): void
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
     * 获取浏览器的http协议
     * @return string
     */
    function getHttpScheme(): string
    {
        static $httpScheme = null;

        if ($httpScheme !== null) {
            return $httpScheme;
        }

        // 判断是否为 HTTPS
        if ($this->isHttps()) {
            $httpScheme = 'https://';
        } else {
            $httpScheme = 'http://';
        }

        return $httpScheme;
    }

    private function isHttps(): bool
    {
        return (
            (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
            (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        );
    }


    /**
     * 获取域名，无端口
     * 例如：example.com
     * @return string
     */
    public  function getDomainNoPort(): string
    {
        return $_SERVER["SERVER_NAME"];
    }

    /**
     * 获取域名
     * 例如：example.com 或 example.com:8088
     * @return string
     */
    public  function getDomain(): string
    {
        return $_SERVER["HTTP_HOST"];
    }

    /**
     * 获取当前访问的地址
     * 例如：https://example.com/index/main
     * @return string
     */
    public  function getNowAddress(): string
    {
        return $this->getHttpScheme() . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取当前服务器IP
     * 例如：127.0.0.1
     */
    public  function getServerIp(): string
    {
        return gethostbyname(gethostname());
    }

    /**
     * 获取客户端真实IP
     * @return string
     */
    public  function getClientIP(): string
    {
        return $_SERVER["REMOTE_ADDR"];
    }


    /**
     * 是否PJAX请求
     * @return bool
     */
    public  function isPjax(): bool
    {
        return (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true');
    }


    /**
     * 是否AJAX请求
     * @return bool
     */
    public  function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }


    /**
     * 是否GET请求
     * @return bool
     */
    public  function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }

    public  function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * 是否POST请求
     * @return bool
     */
    public  function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }



}
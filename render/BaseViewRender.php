<?php

namespace nova\framework\render;

abstract class BaseViewRender
{
    private array $headers = [];
    private string $body = "";

    protected string $content_type = 'text/html';
    //状态
    protected int $code = 200;


    public function __construct($code = 200, $content_type = 'text/html', $body = "")
    {
        $this->code = $code;
        $this->content_type = $content_type;
        $this->body = $body;
    }

    /**
     * 渲染数据
     * @param mixed ...$data
     * @return BaseViewRender
     */
    abstract function render(...$data): static;

    /**
     * @param string $body
     */
    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }
    /**
     * 设置请求头
     * @param $k
     * @param $v
     * @return $this
     */
    public function setHeader($k, $v): static
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 设置缓存
     * @param $min
     * @return $this
     */
    public function cache($min): static
    {
        $seconds_to_cache = $min * 60;
        $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
        $this->headers["Expires"] = $ts;
        $this->headers["Pragma"] = "cache";
        $this->headers["Cache-Control"] = "max-age=$seconds_to_cache";
        return $this;
    }


    /**
     * 设置响应类型
     * @param $type
     * @return $this
     */
    public function setContentType($type): static
    {
        $this->content_type = $type;
        return $this;
    }

    /**
     * 渲染的输出类型
     * @return string
     */
    function getContentType(): string
    {
        return $this->content_type;
    }

    /**
     * 设置响应类型
     * @param $code
     * @return $this
     */
    public function setCode($code): static
    {
        $this->code = $code;
        return $this;
    }

    function getCode(): int
    {
        return $this->code;
    }

    /**
     * 控制器渲染自定义错误
     * @param $controller
     * @param $method
     * @return string|null
     */
    function onControllerError($controller, $method): ?string
    {
        return null;
    }


}
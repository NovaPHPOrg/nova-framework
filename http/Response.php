<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\http;

use DOMDocument;
use Exception;
use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\NovaApp;
use nova\framework\event\EventManager;

use function nova\framework\file_type;

use nova\framework\json\Json;
use nova\framework\json\JsonEncodeException;

use SimpleXMLElement;

/**
 * HTTP响应类
 * 用于处理各种类型的HTTP响应，包括JSON、XML、HTML、文件下载等
 */
class Response extends NovaApp
{
    /** @var mixed 响应数据 */
    protected mixed $data;

    /** @var int HTTP状态码 */
    protected int $code = 200;

    /** @var array HTTP响应头 */
    protected array $header = [];

    /** @var ResponseType 响应类型 */
    protected ResponseType $type;

    /**
     * 构造函数
     * @param mixed        $data   响应数据
     * @param int          $code   HTTP状态码
     * @param ResponseType $type   响应类型
     * @param array        $header 响应头
     */
    protected function __construct(mixed $data = '', int $code = 200, ResponseType $type = ResponseType::HTML, array $header = [])
    {
        parent::__construct();
        $this->data = $data;
        $this->code = $code;
        $this->header = $header;
        $this->type = $type;
    }

    /**
     * 创建响应对象
     * @param  mixed        $data   响应数据
     * @param  int          $code   HTTP状态码
     * @param  ResponseType $type   响应类型
     * @param  array        $header 响应头
     * @return Response
     */
    public static function createResponse(mixed $data = '', int $code = 200, ResponseType $type = ResponseType::HTML, array $header = []): Response
    {
        $context = Context::instance();
        $clazz = $context->getResponseClass();
        if (!class_exists($clazz)) {
            $clazz = Response::class;
        }
        return new $clazz($data, $code, $type, $header);
    }

    /**
     * 创建重定向响应
     * @param  string   $url     重定向目标URL
     * @param  int      $timeout 延迟重定向时间（秒）
     * @return Response
     */
    public static function asRedirect(string $url, int $timeout = 0): Response
    {
        if ($timeout === 0) {
            return self::createResponse('', 302, ResponseType::REDIRECT, ['Location' => $url]);
        }
        return self::createResponse('', 200, ResponseType::REDIRECT, ['refresh' => "$timeout;url=$url"]);
    }
    /**
     * 创建JSON格式响应
     * @param  array    $data   响应数据
     * @param  int      $code   HTTP状态码
     * @param  array    $header 响应头
     * @return Response
     */
    public static function asJson(array $data, int $code = 200, array $header = []): Response
    {
        return self::createResponse($data, $code, ResponseType::JSON, $header);
    }
    /**
     * 创建XML格式响应
     * @param  array    $data   响应数据
     * @param  int      $code   HTTP状态码
     * @param  array    $header 响应头
     * @return Response
     */
    public static function asXml(array $data, int $code = 200, array $header = []): Response
    {
        return self::createResponse($data, $code, ResponseType::XML, $header);
    }
    /**
     * 创建文件下载响应
     * @param  string   $filePath 文件路径
     * @param  string   $fileName 文件名
     * @param  array    $header   响应头
     * @return Response
     */
    public static function asFile(string $filePath, string $fileName, array $header = []): Response
    {
        $response = self::createResponse('', 200, ResponseType::FILE, $header);
        $response->withFile($filePath, $fileName);
        return $response;
    }
    /**
     * 创建文本格式响应
     * @param  string   $data   响应数据
     * @param  array    $header 响应头
     * @param  int      $code   HTTP状态码
     * @return Response
     */
    public static function asText(string $data = '', array $header = [], int $code = 200): Response
    {
        return self::createResponse($data, $code, ResponseType::TEXT, $header);
    }
    /**
     * 创建HTML格式响应
     * @param  string   $data   响应数据
     * @param  array    $header 响应头
     * @param  int      $code   HTTP状态码
     * @return Response
     */
    public static function asHtml(string $data = '', array $header = [], int $code = 200): Response
    {
        return self::createResponse($data, $code, ResponseType::HTML, $header);
    }
    /**
     * 创建Server-Sent Events响应
     * @param  callable $callback 回调函数
     * @param  array    $header   响应头
     * @param  int      $code     HTTP状态码
     * @return Response
     */
    public static function asSSE(callable $callback, array $header = [], int $code = 200): Response
    {
        $response = self::createResponse($callback, $code, ResponseType::SSE, $header);
        $response->withSSE();
        return $response;
    }

    /**
     * 创建无响应内容
     * @param  string   $filePath
     * @param  array    $header   响应头
     * @param  int      $code
     * @return Response
     */
    public static function asStatic(string $filePath, array $header = [], int $code = 200): Response
    {
        return self::createResponse($filePath, $code, ResponseType::STATIC, $header);
    }

    /**
     * 创建无响应内容
     * @param  array    $header 响应头
     * @return Response
     */
    public static function asNone(array $header = []): Response
    {
        return self::createResponse('', 200, ResponseType::NONE, $header);
    }

    /**
     * 创建原始数据响应
     * @param  mixed    $data   响应数据
     * @param  array    $header 响应头
     * @return Response
     */
    public static function asRaw(mixed $data, array $header = []): Response
    {
        return self::createResponse($data, 200, ResponseType::RAW, $header);
    }

    /**
     * 设置响应缓存
     * @param  int      $min 缓存时间(分钟)
     * @return Response 返回Response对象以支持链式调用
     */
    public function cache($min): Response
    {
        $seconds_to_cache = $min * 60;
        $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
        $this->header["Expires"] = $ts;
        $this->header["Pragma"] = "cache";
        $this->header["Cache-Control"] = "max-age=$seconds_to_cache";
        return $this;
    }

    /**
     * 配置SSE响应所需的头信息
     */
    private function withSSE(): void
    {
        $this->header['Content-Type'] = 'text/event-stream';
        $this->header['Cache-Control'] = 'no-cache';
        $this->header['Connection'] = 'keep-alive';
        $this->header['X-Accel-Buffering'] = 'no';
        // ini_set('output_buffering', 'off');
        // ini_set('zlib.output_compression', false);
    }

    /**
     * 过滤文件路径，移除潜在的安全隐患
     * @param  string $filePath 需要过滤的文件路径
     * @return string 过滤后的文件路径
     */
    private function filterFilePath(string $filePath): string
    {
        return str_replace(["../", "./", "..\\", ".\\"], '', $filePath);
    }

    /**
     * 设置文件下载相关的响应头和数据
     * @param string $filePath 文件路径
     * @param string $fileName 下载时显示的文件名
     */
    public function withFile(string $filePath, string $fileName): void
    {
        $filePath = $this->filterFilePath($filePath);
        Logger::info("Response file: $filePath");
        if (file_exists($filePath)) {
            $this->data = $filePath;
            $this->header['Content-Disposition'] = 'attachment; filename="' . $fileName . '"';
            $this->header['Accept-Ranges'] = 'bytes';
            $this->header['Connection'] = 'Keep-Alive';
            $this->header['Content-Description'] = 'File Transfer';
            $this->header['Content-Transfer-Encoding'] = 'binary';
            $this->header['Content-Length'] = filesize($filePath);
            $this->header['Content-Type'] = 'application/octet-stream';
        } else {
            $this->data = "File not found";
            $this->header['Content-Type'] = 'text/plain';
            $this->code = 404;
        }
    }

    /**
     * 关闭输出缓冲
     */
    private function closeOutput()
    {
        ob_implicit_flush();
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * 发送响应到客户端
     * 根据不同的响应类型调用相应的处理方法
     */
    public function send(): void
    {
        if ($this->context->isDebug()) {
            $this->header[] = "Server-Timing: " .
                "Total;dur=" . round(($this->context->calcAppTime()) * 1000, 4) . ";desc=\"Total Time\"";
        }

        $this->header["Server"] = "Apache";
        $this->header["X-Powered-By"] = "NovaPHP";
        $this->header["Date"] = gmdate('D, d M Y H:i:s T');

        switch ($this->type) {
            case ResponseType::JSON:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'application/json';
                }
                $this->sendJSON();
                break;
            case ResponseType::XML:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'application/xml';
                }
                $this->sendXml();
                break;
            case ResponseType::SSE:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'text/event-stream';
                }
                $this->sendSSE();
                break;
            case ResponseType::FILE:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'application/octet-stream';
                }
                $this->sendFile();
                break;
            case ResponseType::STATIC:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'application/octet-stream';
                }
                $this->sendStatic();
                break;
            case ResponseType::HTML:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'text/html';
                }
                $this->sendHtml();
                break;
            case ResponseType::TEXT:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'text/plain';
                }
                $this->sendText();
                break;
            case ResponseType::NONE:
            case ResponseType::REDIRECT:
                $this->sendHeaders();
                break;
            case ResponseType::RAW:
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'application/octet-stream';
                }
                $this->sendRaw();
        }

    }

    /**
     * 发送原始数据响应
     */
    protected function sendRaw(): void
    {
        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        echo $this->data;
    }

    /**
     * 发送HTTP响应头
     */
    protected function sendHeaders(): void
    {

        if (!headers_sent() && !empty($this->header)) {
            http_response_code($this->code);
            foreach ($this->header as $name => $val) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        header($name . ':' . $v);
                    }
                } else {
                    if (!is_string($name)) {
                        header($val);
                    } else {
                        header($name . ':' . $val);
                    }
                }

            }
        }
        // $this->closeOutput();
    }

    /**
     * 发送SSE(Server-Sent Events)响应
     * 用于实现服务器推送功能
     */
    protected function sendSSE(): void
    {
        set_time_limit(0);
        $callback = $this->data;
        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        $callback(function ($data, $event = null) {
            if ($data == null) {
                return;
            }
            echo "event: $event\n";
            echo "data: " . $data . "\n\n";
            ob_flush();
            flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });
        while (!connection_aborted()) {
            sleep(1);
        }

    }

    /**
     * 发送文件下载响应
     * 支持断点续传功能
     */
    protected function sendFile(): void
    {
        if ($this->code == 404) {
            $this->sendHeaders();
            echo $this->data;
            return;
        }
        $fileSize = filesize($this->data);

        $range = $this->parseRange($fileSize);

        if ($range !== null) {
            [$start, $end] = $range;
            $length = $end - $start + 1;
            $this->code = 206;
            $this->header['Content-Range'] = "bytes $start-$end/$fileSize";
            $this->header['Content-Length'] = $length;
            $this->sendHeaders();
            if ($this->isHead()) {
                return;
            }
            $this->outputFile($start, $length);
        } else {
            $this->code = 200;
            $this->header['Content-Length'] = $fileSize;
            $this->sendHeaders();
            if ($this->isHead()) {
                return;
            }
            readfile($this->data);
        }
    }

    /**
     * 解析HTTP Range头
     * @param  int        $fileSize 文件大小
     * @return array|null 返回开始和结束位置的数组，如果无效则返回null
     */
    protected function parseRange(int $fileSize): ?array
    {
        if (!isset($_SERVER['HTTP_RANGE'])) {
            return null;
        }

        $rangeHeader = $_SERVER['HTTP_RANGE'];
        if (!preg_match('/bytes=\d*-\d*/', $rangeHeader)) {
            return null;
        }

        [$start, $end] = explode('-', substr($rangeHeader, 6));
        $start = $start === '' ? 0 : intval($start);
        $end = $end === '' ? $fileSize - 1 : intval($end);

        if ($start > $end || $end >= $fileSize) {
            return null;
        }

        return [$start, $end];
    }

    /**
     * 输出文件内容
     * @param int $start  开始位置
     * @param int $length 长度
     */
    protected function outputFile(int $start, int $length): void
    {
        $handle = fopen($this->data, 'rb');
        fseek($handle, $start);
        $bytesSent = 0;

        while (!feof($handle) && $bytesSent < $length) {
            $buffer = fread($handle, min(8192, $length - $bytesSent));
            echo $buffer;
            $bytesSent += strlen($buffer);
            flush();
        }

        fclose($handle);
    }

    /**
     * 完成请求处理
     * 用于在发送响应后执行清理工作
     */
    public static function finish(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        flush();
    }

    /**
     * 发送静态文件响应
     * 支持缓存控制和条件请求
     */
    private function sendStatic(): void
    {
        $addr = $this->data;
        $addr = $this->filterFilePath($addr);
        Logger::info("Response file: $addr");
        // 验证文件是否存在且可读
        if (!file_exists($addr) || !is_readable($addr)) {
            $this->code = 404;
            $this->header["Content-Type"] = "text/plain";
            $this->sendHeaders();
            Logger::warning("File not found: $addr");
            echo "File not found.";
            return;
        }
        $this->header["Content-Type"] = file_type($addr);
        $lastModifiedTime = filemtime($addr);
        $etag = md5_file($addr);
        // 检查 If-Modified-Since 和 If-None-Match 头
        if (
            (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedTime) ||
            (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
        ) {
            $this->code = 304;
            $this->sendHeaders();
            Logger::info("File not modified: $addr");
            return;
        }

        if (preg_match("/.*\.(gif|jpg|jpeg|png|bmp|swf|woff|woff2)?$/", $addr)) {
            $this->cache(60 * 24 * 365);
        } elseif (preg_match("/.*\.(js|css)?$/", $addr)) {
            $this->cache(60 * 24 * 180);
        } elseif (preg_match("/.*\.(html|htm)?$/", $addr)) {
            $this->cache(60);
            $this->preLoad(file_get_contents($addr));
        }
        // 设置 Last-Modified 和 ETag 头
        $this->header["Last-Modified"] = gmdate("D, d M Y H:i:s", $lastModifiedTime) . " GMT";
        $this->header["ETag"] = $etag;

        // 清空输出缓冲区，确保文件流输出正确
        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        Logger::info("Send static file: $addr");
        // 读取并输出文件内容
        $output  = EventManager::trigger("response.static.before", $addr, true);
        if ($output !== true) {
            readfile($addr);
        }

        EventManager::trigger("response.static.after", $addr);
    }

    private function sendJSON(): void
    {
        try {
            $send = Json::encode($this->data);
        } catch (JsonEncodeException $e) {
            $send = json_encode(["error" => "Server error"]);
            $this->code = 500;
        }

        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        echo $send;

    }

    /**
     * 将数组转换为XML格式
     * @param array            $data    需要转换的数组数据
     * @param SimpleXMLElement $xmlData XML对象引用
     */
    private function arrayToXml($data, &$xmlData): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key; // 数字键使用"item"前缀
            }
            if (is_array($value)) {
                $subNode = $xmlData->addChild($key);
                $this->arrayToXml($value, $subNode);
            } else {
                $xmlData->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * 将数组转换为XML字符串
     * @param  array  $array       需要转换的数组
     * @param  string $rootElement XML根元素名称
     * @param  string $xmlVersion  XML版本
     * @param  string $xmlEncoding XML编码
     * @return string 生成的XML字符串
     */
    private function convertArrayToXml($array, $rootElement = 'root', $xmlVersion = '1.0', $xmlEncoding = 'UTF-8'): string
    {
        $xmlData = new SimpleXMLElement("<?xml version=\"$xmlVersion\" encoding=\"$xmlEncoding\"?><$rootElement></$rootElement>");
        $this->arrayToXml($array, $xmlData);
        return $xmlData->asXML();
    }

    /**
     * 发送XML格式响应
     */
    private function sendXml(): void
    {
        try {
            $xmlStr = $this->convertArrayToXml($this->data);
        } catch (Exception $e) {
            $this->code = 500;
            $xml = new SimpleXMLElement('<root/>');
            $xml->addChild("Server Error");
            $xmlStr = $xml->asXML();
        }

        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        echo $xmlStr;
    }

    /**
     * 检查是否为HEAD请求
     * @return bool 是否为HEAD请求
     */
    protected function isHead(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'HEAD';
    }

    /**
     * 发送HTML格式响应
     */
    private function sendHtml(): void
    {
        $data = $this->data;
        $this->preLoad($data);
        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        EventManager::trigger("response.html.before", $data);
        echo $data;
        EventManager::trigger("response.html.after", $data);
    }

    /**
     * 预加载资源
     * 通过Link header实现资源预加载，优化页面加载性能
     * @param string $data HTML内容
     */
    private function preLoad($data): void
    {
        if ($this->isHead()) {
            return;
        }
        if ($this->context->request()->isPjax()) {
            return;
        }
        try {
            $count = 20;
            libxml_use_internal_errors(true);

            $dom = new DOMDocument();
            $dom->loadHTML($data);

            $push = " ";

            // 处理 script 标签，只加载 .js 后缀
            $scripts = $dom->getElementsByTagName('script');
            foreach ($scripts as $script) {
                if ($script->hasAttribute('src')) {
                    $src = $script->getAttribute('src');
                    if (str_ends_with($src, '.js')) {
                        $push .= "<{$src}>; rel=preload; as=script; nopush,";
                        $count--;
                    }
                }
            }

            // 处理 link 标签，只加载 css 和字体
            $links = $dom->getElementsByTagName('link');
            foreach ($links as $link) {
                if ($link->hasAttribute('href')) {
                    $href = $link->getAttribute('href');
                    $rel = $link->getAttribute('rel');

                    // 只处理 stylesheet 和 icon/font
                    if ($rel === 'stylesheet' && str_ends_with($href, '.css')) {
                        $push .= "<{$href}>; rel=preload; as=style; nopush,";
                        $count--;
                    } elseif ($rel === 'font') {
                        $push .= "<{$href}>; rel=preload; as=font; nopush,";
                        $count--;
                    }
                }
            }

            // 处理 img 标签，排除 base64 和 ico
            $imgs = $dom->getElementsByTagName('img');
            foreach ($imgs as $img) {
                if ($img->hasAttribute('src')) {
                    $src = $img->getAttribute('src');
                    // 排除 base64 和 ico 图片
                    if (!str_contains($src, 'data:') && !str_ends_with($src, '.ico')) {
                        $push .= "<{$src}>; rel=preload; as=image; nopush,";
                        if ($count-- <= 0) {
                            break;
                        }
                    }
                }
            }

            if (libxml_get_errors()) {
                libxml_clear_errors();
            }

            // 移除最后一个逗号并设置 header
            $push = rtrim($push, ',');
            if (!empty(trim($push))) {
                $this->header['Link'] = $push;
            }

        } catch (Exception $e) {
            Logger::error("Preload error: " . $e->getMessage());
        }

    }

    /**
     * 发送文本格式响应
     */
    private function sendText(): void
    {
        $this->sendHeaders();
        if ($this->isHead()) {
            return;
        }
        echo $this->data;
    }

    /**
     * 获取响应数据
     * @return string 响应数据
     */
    public function getData(): string
    {
        return $this->data ?? "";
    }
}

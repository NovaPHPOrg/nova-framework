<?php

namespace nova\framework\request;

use DOMDocument;
use Exception;
use nova\framework\App;
use nova\framework\event\EventManager;
use nova\framework\exception\NoticeException;
use nova\framework\log\Logger;
use nova\framework\text\Json;
use nova\framework\text\JsonEncodeException;
use SimpleXMLElement;
use function nova\framework\file_type;

class Response
{
    protected mixed $data;
    protected int $code = 200;
    protected array $header = [];


    protected ResponseType $type;


    public function __construct(mixed $data = '', int $code = 200, ResponseType $type = ResponseType::HTML, array $header = [])
    {
        $this->data = $data;
        $this->code = $code;
        $this->header = $header;
        $this->type = $type;
    }

    public static function asRedirect(string $url, int $timeout = 0): Response
    {
        if ($timeout === 0) {
            return new Response('', 302, ResponseType::REDIRECT, ['Location' => $url]);
        }
        return new Response('', 200, ResponseType::REDIRECT, ['refresh' => "$timeout;url=$url"]);
    }

    public static function asJson(array $data, int $code = 200, array $header = []): Response
    {
        return new Response($data, $code, ResponseType::JSON, $header);
    }

    public static function asXml(array $data, int $code = 200, array $header = []): Response
    {
        return new Response($data, $code, ResponseType::XML, $header);
    }

    public static function asFile(string $filePath, string $fileName, array $header = []): Response
    {
        $response = new Response('', 200, ResponseType::FILE, $header);
        $response->withFile($filePath, $fileName);
        return $response;
    }

    public static function asText(string $data = '', array $header = []): Response
    {
        return new Response($data, 200, ResponseType::TEXT, $header);
    }

    public static function asHtml(string $data = '', array $header = []): Response
    {
        return new Response($data, 200, ResponseType::HTML, $header);
    }

    public static function asSSE(callable $callback, array $header = []): Response
    {
        $response = new Response($callback, 200, ResponseType::SSE, $header);
        $response->withSSE();
        return $response;
    }

    public static function asStatic(string $filePath, array $header = []): Response
    {
        return new Response($filePath, 200, ResponseType::STATIC, $header);
    }


    public static function asNone(array $header = []): Response
    {
        return new Response('', 200, ResponseType::NONE, $header);
    }

    public static function asRaw($data, array $header = []): Response
    {
        return new Response($data, 200, ResponseType::RAW, $header);
    }

    public function cache($min): Response
    {
        $seconds_to_cache = $min * 60;
        $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
        $this->header["Expires"] = $ts;
        $this->header["Pragma"] = "cache";
        $this->header["Cache-Control"] = "max-age=$seconds_to_cache";
        return $this;
    }


    private function withSSE(): void
    {
        $this->header['Content-Type'] = 'text/event-stream';
        $this->header['Cache-Control'] = 'no-cache';
        $this->header['Connection'] = 'keep-alive';
        $this->header['X-Accel-Buffering'] = 'no';
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', false);
    }

    private function filterFilePath(string $filePath): string
    {
        return str_replace(["../", "./", "..\\", ".\\"], '', $filePath);
    }

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

    private function closeOutput()
    {
        try {
            ob_implicit_flush();
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        } catch (NoticeException $exception) {

        }
    }

    public function send(): void
    {
        if (App::getInstance()->debug) {
            $this->header[] = "Server-Timing: " .
                "Total;dur=" . round((microtime(true) - $GLOBALS['__nova_app_start__']) * 1000, 4) . ";desc=\"Total Time\"";
        }

        $this->header["Server"] = "Apache";
        $this->header["X-Powered-By"] = "NovaPHP";
        $this->header["Date"] = gmdate('D, d M Y H:i:s T');
        if (!isset($this->header['Content-Type']))
            $this->header['Content-Type'] = 'application/octet-stream';

        switch ($this->type) {
            case ResponseType::JSON:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'application/json';
                $this->sendJSON();
                break;
            case ResponseType::XML:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'application/xml';
                $this->sendXml();
                break;
            case ResponseType::SSE:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'text/event-stream';
                $this->sendSSE();
                break;
            case ResponseType::FILE:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'application/octet-stream';
                $this->sendFile();
                break;
            case ResponseType::STATIC:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'application/octet-stream';
                $this->sendStatic();
                break;
            case ResponseType::HTML:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'text/html';
                $this->sendHtml();
                break;
            case ResponseType::TEXT:
                if (!isset($this->header['Content-Type']))
                $this->header['Content-Type'] = 'text/plain';
                $this->sendText();
                break;
            case ResponseType::NONE:
            case ResponseType::REDIRECT:
                $this->sendHeaders();
                break;
            case ResponseType::RAW:
                $this->sendRaw();
        }

    }

    protected function sendRaw():void
    {
        $this->sendHeaders();
        echo $this->data;
    }

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
        $this->closeOutput();
    }

    protected function sendSSE(): void
    {
        set_time_limit(0);
        $callback = $this->data;
        $this->sendHeaders();

        while (true) {
            $result = $callback();
            if ($result === null) {
                $echo = "data: \n\n";
            } elseif (!$result) {
                break;
            } else {
                if (!isset($result["event"]) || !isset($result["data"])) {
                    $echo = "data: \n\n";
                    Logger::warning("SSE data format error, event and data is required");
                } else {
                    $echo = "event: " . $result["event"] . PHP_EOL;
                    $echo .= "data: " . $result["data"] . PHP_EOL;
                    $echo .= PHP_EOL;
                }
            }
            Logger::info("SSE: $echo");
            echo $echo;
            if (connection_aborted()) {
                break;
            }
            sleep(1);
        }
    }

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
            $this->outputFile($start, $length);
        } else {
            $this->code = 200;
            $this->header['Content-Length'] = $fileSize;
            $this->sendHeaders();
            readfile($this->data);
        }
    }

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

    static function finish(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        flush();
    }

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
        Logger::info("Send static file: $addr");
        // 读取并输出文件内容
        readfile($addr);

        EventManager::trigger("response.sendStatic", $addr);
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
        echo $send;

    }

    private function arrayToXml($data, &$xmlData): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key; // 数字键使用“item”前缀
            }
            if (is_array($value)) {
                $subNode = $xmlData->addChild($key);
                $this->arrayToXml($value, $subNode);
            } else {
                $xmlData->addChild($key, htmlspecialchars($value));
            }
        }
    }

    private function convertArrayToXml($array, $rootElement = 'root', $xmlVersion = '1.0', $xmlEncoding = 'UTF-8'): string
    {
        $xmlData = new SimpleXMLElement("<?xml version=\"$xmlVersion\" encoding=\"$xmlEncoding\"?><$rootElement></$rootElement>");
        $this->arrayToXml($array, $xmlData);
        return $xmlData->asXML();
    }

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
        echo $xmlStr;
    }

    private function sendHtml(): void
    {
        $this->preLoad($this->data);
        $this->sendHeaders();
        echo $this->data;
    }


    private function preLoad($data): void
    {
        try {
            libxml_use_internal_errors(true);

            $dom = new DOMDocument();
            $dom->loadHTML($data);

            $push = " ";
            $scripts = $dom->getElementsByTagName('script');
            foreach ($scripts as $script) {
                if ($script->hasAttribute('src')) {
                    $push .= "<" . $script->getAttribute('src') . ">; rel=preload; as=script ; nopush,";
                }
            }

            $links = $dom->getElementsByTagName('link');
            foreach ($links as $link) {
                if ($link->hasAttribute('href')) {
                    $href = $link->getAttribute('href');
                    $type = "style";
                    if (!str_contains($href, ".css")) {
                        $type = "font";
                    }
                    $push .= "<" . $href . ">; rel=preload; as=$type ; nopush,";
                }
            }
            $imgs = $dom->getElementsByTagName('img');
            foreach ($imgs as $img) {
                if ($img->hasAttribute('src')) {
                    $push .= "<" . $img->getAttribute('src') . ">; rel=preload; as=image ; nopush,";
                }
            }

            if (libxml_get_errors()) {
                libxml_clear_errors();
            }

            $this->header['Link'] = $push;

        } catch (Exception $e) {
            Logger::error("Preload error: " . $e->getMessage());
        }

    }

    private function sendText(): void
    {
        $this->sendHeaders();
        echo $this->data;
    }

    public function getData():string
    {
        return $this->data ?? "";
    }
}

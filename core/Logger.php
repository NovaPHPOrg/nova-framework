<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;
use Throwable;

/**
 * 请求级日志：内存缓冲 → 超阈值刷入 tmpfile → 析构时合并追加到 {host}-{day}.log。
 * 级别：Debug / Info / Warning / Error（生产环境仅 Warning、Error）
 */
class Logger extends Instance
{
    private const int LOG_TTL_DAYS = 180;

    /** 内存缓冲超过此大小则刷入 tmpfile */
    private const int BUFFER_FLUSH_BYTES = 65536;

    private const array LEVEL = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
    ];

    private bool $debug;
    private int $minLevel;
    private string $buffer = '';
    private int $bufferBytes = 0;
    /** @var resource */
    private $tempHandle;
    private string $logFile;
    private string $logDir;
    private bool $hasContent = false;
    private bool $requestHeaderWritten = false;

    public function __construct()
    {
        $this->debug = Context::instance()->isDebug();
        $this->minLevel = $this->debug ? self::LEVEL['DEBUG'] : self::LEVEL['WARNING'];
        $this->logDir = LOG_PATH;
        $this->logFile = $this->logDir . DIRECTORY_SEPARATOR . self::resolveLogHost() . '-' . date('Y-m-d') . '.log';

        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0755, true) && !is_dir($this->logDir)) {
            throw new RuntimeException("Failed to create log directory: {$this->logDir}");
        }

        $temp = $this->createTempLogFile();
        if ($temp === false) {
            throw new RuntimeException('Failed to create temporary log file');
        }
        $this->tempHandle = $temp;

        if (mt_rand(1, 100) === 1) {
            $this->cleanupOldLogs();
        }
    }

    private function createTempLogFile()
    {
        if ($this->debug) {
            $dir = TEMP_PATH . DS ;
            File::mkDir($dir);
            $file = $dir . uniqid("log_").".log";
            return fopen($file, "a+");
        }

        return tmpfile();

    }

    public static function error(mixed $message, array $context = []): void
    {
        static::getInstance()->write($message, 'ERROR', $context);
    }

    public static function warning(mixed $message, array $context = []): void
    {
        static::getInstance()->write($message, 'WARNING', $context);
    }

    public static function info(mixed $message, array $context = []): void
    {
        static::getInstance()->write($message, 'INFO', $context);
    }

    public static function debug(mixed $message, array $context = []): void
    {
        static::getInstance()->write($message, 'DEBUG', $context);
    }

    /**
     * 跳过指定框架文件，返回上级调用方（相对路径:行号 类::方法()）
     *
     * @param string ...$skipFileSuffixes 要跳过的文件名后缀，如 'Route.php'
     */
    public static function caller(string ...$skipFileSuffixes): string
    {
        $skip = ['Logger.php', ...$skipFileSuffixes];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? null;
            if ($file === null) {
                continue;
            }

            foreach ($skip as $suffix) {
                if (str_ends_with($file, $suffix)) {
                    continue 2;
                }
            }

            $path = defined('ROOT_PATH')
                ? ltrim(str_replace('\\', '/', str_replace(ROOT_PATH, '', $file)), '/')
                : basename($file);
            $line = $frame['line'] ?? 0;

            return "{$path}:{$line}";
        }

        return 'unknown';
    }

    private function write(mixed $message, string $level, array $context = []): void
    {
        if ((self::LEVEL[$level] ?? 0) < $this->minLevel) {
            return;
        }

        $this->writeRequestHeader();

        foreach ($this->formatMessage($message, $level, $context) as $line) {
            $this->appendLine($line);
        }
    }

    /**
     * @return list<string>
     */
    private function formatMessage(mixed $message, string $level, array $context): array
    {
        if ($message instanceof Throwable) {
            return $this->formatThrowable($message, $level, $context);
        }

        $text = match (true) {
            is_string($message) => $message,
            is_scalar($message) => (string) $message,
            default => json_encode($message, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[unencodable]',
        };

        if ($text === '') {
            return [];
        }

        $lines = [$this->formatLogLine($level, $text)];
        if ($context !== []) {
            $lines[] = $this->formatLogLine($level, 'context: ' . self::encodeContext($context));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function formatThrowable(Throwable $e, string $level, array $context): array
    {
        $lines = [];
        $lines[] = $this->formatLogLine($level, 'EXCEPTION ' . $e::class . ': ' . $e->getMessage());
        $lines[] = '  at ' . $e->getFile() . ':' . $e->getLine();

        $previous = $e->getPrevious();
        $depth = 0;
        while ($previous instanceof Throwable && $depth < 8) {
            $lines[] = '  caused by ' . $previous::class . ': ' . $previous->getMessage();
            $lines[] = '    at ' . $previous->getFile() . ':' . $previous->getLine();
            $previous = $previous->getPrevious();
            ++$depth;
        }

        $lines[] = '  stack trace:';
        foreach (explode("\n", $e->getTraceAsString()) as $frame) {
            $frame = trim($frame);
            if ($frame !== '') {
                $lines[] = '  ' . $frame;
            }
        }

        if ($context !== []) {
            $lines[] = '  context: ' . self::encodeContext($context);
        }

        return $lines;
    }

    private function formatLogLine(string $level, string $text): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $trace[4] ?? $trace[3] ?? $trace[2] ?? ['file' => 'unknown', 'line' => 0];

        return sprintf(
            "[%s] [%s] [%s:%d] %s",
            date('Y-m-d H:i:s'),
            $level,
            basename($caller['file'] ?? 'unknown'),
            $caller['line'] ?? 0,
            $text
        );
    }

    private static function encodeContext(array $context): string
    {
        return print_r($context, true);
    }

    private function writeRequestHeader(): void
    {
        if ($this->requestHeaderWritten) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
        $request_id = Context::instance()->requestId();
        $this->appendLine("=== {$method} {$uri} | {$ip} | id:{$request_id} ===");
        $this->requestHeaderWritten = true;
    }

    private function appendLine(string $line): void
    {
        $chunk = $line . "\n";
        $len = strlen($chunk);
        $this->buffer .= $chunk;
        $this->bufferBytes += $len;
        $this->hasContent = true;

        if ($this->bufferBytes >= self::BUFFER_FLUSH_BYTES) {
            $this->flushBufferToTemp();
        }
    }

    private function flushBufferToTemp(): void
    {
        if ($this->buffer === '') {
            return;
        }

        fwrite($this->tempHandle, $this->buffer);
        $this->buffer = '';
        $this->bufferBytes = 0;
    }

    public function __destruct()
    {
        $this->flushBufferToTemp();

        if (!$this->hasContent) {
            if (is_resource($this->tempHandle)) {
                fclose($this->tempHandle);
            }
            return;
        }

        rewind($this->tempHandle);
        $content = stream_get_contents($this->tempHandle);
        fclose($this->tempHandle);

        if ($content === false || $content === '') {
            return;
        }

        $block = "\n" . rtrim($content) . "\n";

        $daily = fopen($this->logFile, 'a');
        if ($daily === false) {
            return;
        }

        if (flock($daily, LOCK_EX)) {
            fwrite($daily, $block);
            flock($daily, LOCK_UN);
        }
        fclose($daily);
    }

    private static function resolveLogHost(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'cli';
        if ($host !== 'cli' && str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = strstr($host, ':', true) ?: $host;
        }

        return preg_replace('/[^\w.-]+/', '_', $host) ?: 'cli';
    }

    private function cleanupOldLogs(): void
    {
        $threshold = time() - self::LOG_TTL_DAYS * 86400;
        foreach (glob($this->logDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }

        if ($this->debug) {
            $threshold = time() - 300;
            foreach (glob(TEMP_PATH . DS . '*.log') ?: [] as $file) {
                if (is_file($file) && filemtime($file) < $threshold) {
                    @unlink($file);
                }
            }
        }
    }
}

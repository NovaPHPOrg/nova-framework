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

use Exception;

use function nova\framework\isCli;

use RuntimeException;

/**
 * 增强版日志处理类
 *
 * 特性：
 * - 支持 PSR-3 标准的8个日志级别
 * - 实现缓冲区机制提高性能
 * - 支持上下文信息记录
 * - 自动日志轮转和文件分割
 * - 支持临时文件写入，避免并发问题
 * - CLI模式下实时输出日志
 *
 * 使用示例：
 * ```php
 * Logger::info("用户登录成功");
 * Logger::error("操作失败", ['user_id' => 123, 'action' => 'login']);
 * Logger::debug("调试信息", ['debug_data' => $data]);
 * ```
 */
class Logger extends NovaApp
{
    /** @var bool 是否处于调试模式 */
    private bool $debug;
    /** @var resource|false 日志文件句柄 */
    private $handle;
    /** @var string 主日志文件路径 */
    private string $logFile;
    /** @var string 临时日志文件路径 */
    private string $tempFile;
    /** @var array 日志缓冲区 */
    private array $buffer = [];
    /** @var int 缓冲区大小，达到此数量时会自动刷新到文件 */
    private int $bufferSize = 100; // 10MB
    /** @var string 日志目录路径 */
    private string $logDir;
    private const int LOG_TTL_DAYS = 180;
    /**
     * 构造函数
     * 初始化日志系统，设置必要的路径和文件
     */
    public function __construct()
    {
        parent::__construct();
        $this->debug = $this->context->isDebug();
        $this->logDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'logs';
        $this->logFile = $this->logDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        $this->tempFile = $this->logDir . DIRECTORY_SEPARATOR . $this->context->getSessionId() . '.log';

        $this->initialize();
        if ($this->debug) {
            $this->bufferSize = 1;
        }
    }

    /**
     * 初始化日志系统
     * 创建日志目录并打开临时文件句柄
     *
     * @throws RuntimeException 当无法创建日志文件时
     */
    private function initialize(): void
    {
        $this->createDirectory($this->logDir);
        $this->handle = fopen($this->tempFile, 'a');
        if ($this->handle === false) {
            throw new RuntimeException("Cannot open log file: {$this->tempFile}");
        }
    }

    /**
     * 创建目录（如果不存在）
     *
     * @param  string           $path 要创建的目录路径
     * @throws RuntimeException 当目录创建失败时
     */
    private function createDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException("Failed to create directory: $path");
        }
    }

    /**
     * 紧急情况：系统不可用
     *
     * @param mixed $message 日志消息
     * @param array $context 上下文信息
     */
    public static function emergency($message, array $context = []): void
    {
        self::getInstance()->write($message, 'EMERGENCY', $context);
    }

    /**
     * 写入日志
     *
     * @param mixed  $message 日志消息，可以是字符串或任何可JSON序列化的数据
     * @param string $level   日志级别
     * @param array  $context 上下文信息，将被JSON序列化
     */
    protected function write(mixed $message, string $level, array $context = []): void
    {
        if (mt_rand(1, 100) === 1) {
            $this->cleanupOldLogs();
        }

        // 非调试模式下跳过低级别日志
        if (!$this->debug && in_array($level, ['DEBUG', 'INFO', 'NOTICE','WARNING'])) {
            return;
        }

        // 对于错误级别的日志，自动添加堆栈跟踪和调试信息
        if (in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])) {
            if (!isset($context['stack_trace'])) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
                // 移除最上层的Logger相关调用
                array_shift($trace);
                array_shift($trace);

                $context['stack_trace'] = $this->formatStackTrace($trace);
                $context['debug_info'] = [
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true),
                    'php_version' => PHP_VERSION,
                    'server_time' => date('Y-m-d H:i:s'),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                ];

                if ($message instanceof \Throwable) {
                    $context['exception'] = [
                        'class' => get_class($message),
                        'code' => $message->getCode(),
                        'file' => $message->getFile(),
                        'line' => $message->getLine(),
                        'message' => $message->getMessage(),
                        'trace' => $message->getTraceAsString()
                    ];
                    $message = $message->getMessage();
                }
            }
        }

        // 获取调用信息
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1];

        // 格式化日志消息
        $logMessage = $this->formatLogMessage($message, $level, $caller, $context);

        if (empty(trim($logMessage))) { //数据为空就不写了
            return;
        }

        // 添加到缓冲区
        $this->buffer[] = $logMessage;

        // 检查是否需要刷新缓冲区
        if (count($this->buffer) >= $this->bufferSize) {
            try {
                $this->flush();
            } catch (Exception $e) {
                if (isCli()) {
                    echo "Logger Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    /**
     * 格式化堆栈跟踪信息
     *
     * @param  array $trace debug_backtrace()的结果
     * @return array 格式化后的堆栈信息
     */
    private function formatStackTrace(array $trace): array
    {
        $stackTrace = [];
        foreach ($trace as $i => $t) {
            $stackTrace[] = [
                'file' => $t['file'] ?? 'unknown',
                'line' => $t['line'] ?? 0,
                'function' => $t['function'] ?? '',
                'class' => $t['class'] ?? '',
                'type' => $t['type'] ?? '',
            ];
        }
        return $stackTrace;
    }

    /**
     * 格式化日志消息
     *
     * @param  mixed  $message 原始消息
     * @param  string $level   日志级别
     * @param  array  $caller  调用者信息
     * @param  array  $context 上下文信息
     * @return string 格式化后的日志字符串
     */
    private function formatLogMessage(mixed $message, string $level, array $caller, array $context): string
    {
        $timestamp = microtime(true);
        $datetime = date('Y-m-d H:i:s', (int)$timestamp);
        $milliseconds = sprintf("%03d", ($timestamp - floor($timestamp)) * 1000);

        $messageStr = is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE);

        // 基础日志信息
        $log = sprintf(
            "[%s.%s] [%s] [%s:%d] %s\n",
            $datetime,
            $milliseconds,
            str_pad($level, 9),
            basename($caller['file']),
            $caller['line'],
            $messageStr
        );

        // 如果有上下文信息，分类型添加
        if (!empty($context)) {
            // 如果是错误级别的日志，优化显示格式
            if (isset($context['stack_trace']) || isset($context['debug_info']) || isset($context['exception'])) {
                if (isset($context['exception'])) {
                    $log .= "Exception Info:\n";
                    $log .= "  Class:   " . $context['exception']['class'] . "\n";
                    $log .= "  Code:    " . $context['exception']['code'] . "\n";
                    $log .= "  File:    " . $context['exception']['file'] . "\n";
                    $log .= "  Line:    " . $context['exception']['line'] . "\n";
                    $log .= "  Message: " . $context['exception']['message'] . "\n";
                    $log .= "  Stack Trace:\n";
                    foreach (explode("\n", $context['exception']['trace']) as $traceLine) {
                        $log .= "    " . $traceLine . "\n";
                    }
                }

                if (isset($context['stack_trace'])) {
                    $log .= "Call Stack:\n";
                    foreach ($context['stack_trace'] as $trace) {
                        $log .= sprintf(
                            "  %s%s%s() at %s:%d\n",
                            $trace['class'],
                            $trace['type'],
                            $trace['function'],
                            $trace['file'],
                            $trace['line']
                        );
                    }
                }

                if (isset($context['debug_info'])) {
                    $log .= "Debug Info:\n";
                    $log .= "  Memory Usage:    " . $this->formatBytes($context['debug_info']['memory_usage']) . "\n";
                    $log .= "  Peak Memory:     " . $this->formatBytes($context['debug_info']['peak_memory']) . "\n";
                    $log .= "  PHP Version:     " . $context['debug_info']['php_version'] . "\n";
                    $log .= "  Server Time:     " . $context['debug_info']['server_time'] . "\n";
                    $log .= "  Request URI:     " . $context['debug_info']['request_uri'] . "\n";
                    $log .= "  Request Method:  " . $context['debug_info']['request_method'] . "\n";
                }

                // 移除已处理的特殊字段
                unset($context['stack_trace'], $context['debug_info'], $context['exception']);
            }

            // 添加其他上下文信息（如果有）
            if (!empty($context)) {
                $log .= "Additional Context:\n";
                
                // 特殊处理trace字段
                if (isset($context['trace'])) {
                    $log .= "Trace:\n";
                    $traceStr = $context['trace'];
                    // 将trace字符串按行分割并格式化显示
                    $traceLines = explode("\n", $traceStr);
                    foreach ($traceLines as $traceLine) {
                        $traceLine = trim($traceLine);
                        if (!empty($traceLine)) {
                            $log .= "  " . $traceLine . "\n";
                        }
                    }
                    // 从context中移除trace，避免重复显示
                    unset($context['trace']);
                }
                
                // 显示其他context字段（如果还有的话）
                if (!empty($context)) {
                    $log .= "Other Context:\n";
                    $log .= "  " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
            }
        }

        $log .= str_repeat('-', 80) . "\n"; // 添加分隔线
        return $log;
    }

    /**
     * 格式化字节大小为人类可读格式
     *
     * @param  int    $bytes 字节数
     * @return string 格式化后的大小
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 刷新缓冲区，将日志写入文件
     *
     * @throws Exception 当写入文件失败时
     */
    private function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            if (!is_resource($this->handle)) {
                $this->handle = fopen($this->tempFile, 'a');
            }

            fwrite($this->handle, implode('', $this->buffer));
            $this->buffer = [];
        } catch (Exception $e) {
            if (isCli()) {
                echo "Logger Error: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * 获取Logger单例实例
     *
     * @return Logger
     */
    private static function getInstance(): Logger
    {
        return Context::instance()->getOrCreateInstance('logger', function () {
            return new Logger();
        });
    }

    /**
     * 警报：必须立即采取行动
     * 例如：整个网站都挂了、数据库不可用等
     */
    public static function alert($message, array $context = []): void
    {
        self::getInstance()->write($message, 'ALERT', $context);
    }

    /**
     * 严重错误：危急情况
     * 例如：应用组件不可用、意外异常等
     */
    public static function critical($message, array $context = []): void
    {
        self::getInstance()->write($message, 'CRITICAL', $context);
    }

    /**
     * 错误：运行时错误，不需要立即处理
     * 但通常应该被记录和监控
     */
    public static function error($message, array $context = []): void
    {
        self::getInstance()->write($message, 'ERROR', $context);
    }

    /**
     * 警告：出现异常但不是错误
     * 例如：使用了被废弃的API、错误的使用方法等
     */
    public static function warning($message, array $context = []): void
    {
        self::getInstance()->write($message, 'WARNING', $context);
    }

    /**
     * 通知：普通但重要的事件
     */
    public static function notice($message, array $context = []): void
    {
        self::getInstance()->write($message, 'NOTICE', $context);
    }

    /**
     * 信息：感兴趣的事件
     * 例如：用户登录、SQL日志等
     */
    public static function info($message, array $context = []): void
    {
        self::getInstance()->write($message, 'INFO', $context);
    }

    /**
     * 调试：调试信息
     * 仅在开发环境使用
     */
    public static function debug($message, array $context = []): void
    {
        self::getInstance()->write($message, 'DEBUG', $context);
    }

    /**
     * 析构函数
     * 确保所有日志都被写入并正确关闭文件句柄
     */
    public function __destruct()
    {
        try {
            // 刷新剩余的缓冲区
            $this->flush();

            if (is_resource($this->handle)) {
                fclose($this->handle);
            }

            // 合并临时日志到主日志文件
            if (file_exists($this->tempFile)) {
                $handler = fopen($this->logFile, 'a');
                if ($handler && flock($handler, LOCK_EX)) {
                    $tmpContent = file_get_contents($this->tempFile);
                    if ($tmpContent !== false) {
                        fwrite($handler, $tmpContent);
                    }
                    flock($handler, LOCK_UN);
                    fclose($handler);
                    unlink($this->tempFile);
                }
            }
        } catch (Exception $e) {
            if (isCli()) {
                echo "Logger Error: " . $e->getMessage() . "\n";
            }
        }
    }

    private function cleanupOldLogs(): void
    {

        $threshold = time() - self::LOG_TTL_DAYS * 86400;

        foreach (glob($this->logDir . DIRECTORY_SEPARATOR . '*.log*') as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }
}

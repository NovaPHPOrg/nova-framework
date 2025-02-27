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
use RuntimeException;
use function nova\framework\isCli;

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
    /**
     * 日志级别定义，遵循 PSR-3 标准
     * 数字越小，级别越高
     */
    private const array LOG_LEVELS = [
        'EMERGENCY' => 0, // 系统不可用
        'ALERT'     => 1, // 必须立即采取行动
        'CRITICAL'  => 2, // 危急情况
        'ERROR'     => 3, // 运行时错误
        'WARNING'   => 4, // 警告但不是错误
        'NOTICE'    => 5, // 普通但重要的事件
        'INFO'      => 6, // 感兴趣的事件
        'DEBUG'     => 7  // 详细的调试信息
    ];

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
    private int $bufferSize = 100;
    
    /** @var string 日志目录路径 */
    private string $logDir;
    
    /** @var int 单个日志文件的最大大小（字节） */
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    
    /** @var int 最大保留的日志文件数量 */
    private const int MAX_FILES = 5;

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
     * @param string $path 要创建的目录路径
     * @throws RuntimeException 当目录创建失败时
     */
    private function createDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException("Failed to create directory: $path");
        }
    }

    /**
     * 写入日志
     *
     * @param mixed $message 日志消息，可以是字符串或任何可JSON序列化的数据
     * @param string $level 日志级别
     * @param array $context 上下文信息，将被JSON序列化
     */
    protected function write($message, string $level, array $context = []): void
    {
        // 检查日志级别
        $configLevel = $this->context->config()->get('log_level', self::LOG_LEVELS['DEBUG']);
        if (self::LOG_LEVELS[$level] > $configLevel) {
            return;
        }

        // 非调试模式下跳过低级别日志
        if (!$this->debug && in_array($level, ['DEBUG', 'INFO', 'NOTICE'])) {
            return;
        }

        // 获取调用信息
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1];

        // 格式化日志消息
        $logMessage = $this->formatLogMessage($message, $level, $caller, $context);

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
     * 格式化日志消息
     * 
     * @param mixed $message 原始消息
     * @param string $level 日志级别
     * @param array $caller 调用者信息
     * @param array $context 上下文信息
     * @return string 格式化后的日志字符串
     */
    private function formatLogMessage($message, string $level, array $caller, array $context): string
    {
        $timestamp = microtime(true);
        $datetime = date('Y-m-d H:i:s', (int)$timestamp);
        $milliseconds = sprintf("%03d", ($timestamp - floor($timestamp)) * 1000);
        
        $messageStr = is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE);
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        return sprintf(
            "[%s.%s] [%s] [%s:%d] %s%s\n",
            $datetime,
            $milliseconds,
            str_pad($level, 9),
            basename($caller['file']),
            $caller['line'],
            $messageStr,
            $contextStr
        );
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
     * 日志文件轮转
     * 当日志文件超过最大大小时，进行文件轮转
     * 例如：log.txt -> log.txt.1 -> log.txt.2 -> ...
     */
    private function rotateLogFile(): void
    {
        if (!file_exists($this->logFile) || filesize($this->logFile) < self::MAX_FILE_SIZE) {
            return;
        }

        for ($i = self::MAX_FILES - 1; $i > 0; $i--) {
            $oldFile = "{$this->logFile}.{$i}";
            $newFile = "{$this->logFile}." . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        rename($this->logFile, "{$this->logFile}.1");
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
     * 获取Logger单例实例
     * 
     * @return Logger
     */
    private static function getInstance(): Logger
    {
        return Context::instance()->getOrCreateInstance('logger', function() {
            return new Logger();
        });
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
                $this->rotateLogFile();
                
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
}
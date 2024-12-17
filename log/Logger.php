<?php
declare(strict_types=1);

namespace nova\framework\log;

use Exception;
use RuntimeException;
use function nova\framework\config;
use function nova\framework\isCli;

class Logger
{
    const  TYPE_ERROR = "ERROR";
    const  TYPE_INFO = "INFO";
    const  TYPE_WARNING = "WARNING";
    private bool $debug ;
    private $handle;
    private string $log;
    private string $session_id;
    private string $dir = ROOT_PATH . DIRECTORY_SEPARATOR .'runtime'.DIRECTORY_SEPARATOR.'logs';
    private string $temp;
    public function __construct()
    {
        $this->debug = config('debug') ?? false;
        $this->log = $this->dir . DIRECTORY_SEPARATOR . date('Y-m-d').'.log';
        File::mkDir($this->dir);
        $this->session_id = $GLOBALS['__nova_session_id__'];
        //获取随机文件名
        $this->temp = $this->dir . DIRECTORY_SEPARATOR . $this->session_id . '.log';
        $this->handle = fopen($this->temp, 'a');

    }
    protected function write($msg,$type = Logger::TYPE_INFO): void
    {
        if($this->debug === false && ( $type === Logger::TYPE_INFO || $type === Logger::TYPE_WARNING) && !isCli()) {
            return;
        }

        // 检查文件句柄是否有效
        if (!is_resource($this->handle)) {
            try {
                $this->handle = fopen($this->temp, 'a');
            } catch (Exception $e) {
                if (isCli()) {
                    echo "Logger Error: Cannot open log file {$this->temp}\n";
                }
                return;
            }
        }

        $m_timestamp = floatval(sprintf("%.3f", microtime(true)));
        $timestamp = floor($m_timestamp);
        $milliseconds = str_pad(strval(round(($m_timestamp - $timestamp) * 1000)), 3, "0");

        $trace = debug_backtrace();
        $file = basename($trace[1]['file']);
        $msg = '[ ' . $file . ':' . $trace[1]['line'] . ' ] '.$msg ;
        $str = '[ ' . date('Y-m-d H:i:s', intval($timestamp)) . '.' . $milliseconds . ' ] [ ' . $type . ' ] ' . $msg . "\n";
        
        if (isCli()) {
            echo $str;
            return;
        }

        try {
            if (fwrite($this->handle, $str) === false) {
                // 尝试重新打开文件并再次写入
                if (is_resource($this->handle)) {
                    fclose($this->handle);
                }
                $this->handle = fopen($this->temp, 'a');
                if (!fwrite($this->handle, $str)) {
                    throw new RuntimeException("Failed to write to log file: {$this->temp}");
                }
            }
        } catch (Exception $e) {
            if (isCli()) {
                echo "Logger Error: " . $e->getMessage() . "\n";
            }
        }
    }

    private static function getInstance(): Logger
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Logger();
        }
        return $instance;
    }

    public static function info($msg): void
    {
        Logger::getInstance()->write($msg, Logger::TYPE_INFO);
    }

    public static function error($msg): void
    {
        Logger::getInstance()->write($msg, Logger::TYPE_ERROR);
    }

    public static function warning($msg): void
    {
        Logger::getInstance()->write($msg, Logger::TYPE_WARNING);
    }

    public function __destruct()
    {
        try {
            if (is_resource($this->handle)) {
                fclose($this->handle);
            }

            // 确保日志文件目录存在且可写
            if (!is_dir(dirname($this->log))) {
                File::mkDir(dirname($this->log));
            }

            if (!is_writable(dirname($this->log))) {
                throw new RuntimeException("Log directory is not writable: " . dirname($this->log));
            }

            $handler = fopen($this->log, 'a');
            if (!$handler) {
                throw new RuntimeException("Cannot open log file: {$this->log}");
            }

            if (flock($handler, LOCK_EX)) {
                if (file_exists($this->temp)) {
                    $tmpHandler = fopen($this->temp, 'r');
                    if ($tmpHandler) {
                        while (!feof($tmpHandler)) {
                            $content = fgets($tmpHandler);
                            if ($content !== false) {
                                fwrite($handler, $content);
                            }
                        }
                        fclose($tmpHandler);
                        unlink($this->temp);
                    }
                }
                flock($handler, LOCK_UN);
            }

            if (file_exists($this->log) && filesize($this->log) == 0) {
                unlink($this->log);
            }

            fclose($handler);
        } catch (Exception $e) {
            if (isCli()) {
                echo "Logger Error: " . $e->getMessage() . "\n";
            }
        }
    }

}
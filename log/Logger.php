<?php

namespace nova\framework\log;

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
        $m_timestamp = sprintf("%.3f", microtime(true));
        $timestamp = floor($m_timestamp);
        $milliseconds = str_pad(strval(round(($m_timestamp - $timestamp) * 1000)), 3, "0");
        //定位到是哪一行调用输出日志
        $trace = debug_backtrace();
        //只要文件名
        $file = basename($trace[1]['file']);
        $msg = '[ ' . $file . ':' . $trace[1]['line'] . ' ] '.$msg ;
        $str = '[ ' . date('Y-m-d H:i:s', $timestamp) . '.' . $milliseconds . ' ] [ ' . $type . ' ] ' . $msg . "\n";
        //判断是否是cli模式
        if (isCli()) {
            echo $str;
            return;
        }

        fwrite($this->handle, $str);
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

        fclose($this->handle);
        if (php_sapi_name() === 'cli') {
            return;
        }
        $handler = fopen($this->log, 'a');
        if (flock($handler, LOCK_EX)) {
            $tmpHandler = fopen($this->temp, 'r');
            //逐行读取写入
            while (!feof($tmpHandler)) {
                fwrite($handler, fgets($tmpHandler));
            }
            fclose($tmpHandler);
            unlink($this->temp);
            flock($handler, LOCK_UN);
        }
        if (filesize($this->log) == 0) {
            unlink($this->log);
        }
        fclose($handler);

    }

}
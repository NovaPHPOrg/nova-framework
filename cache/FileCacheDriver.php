<?php
declare(strict_types=1);

namespace nova\framework\cache;

/**
 * 文件缓存驱动类
 * 实现基于文件系统的缓存存储机制
 */
class FileCacheDriver implements iCacheDriver
{
    /** @var string 缓存文件的基础目录路径 */
    private string $baseDir;

    /**
     * 构造函数
     * @param bool $shared 是否共享缓存（预留参数）
     */
    public function __construct($shared = false)
    {
        $this->baseDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    /**
     * 生成缓存键的文件路径
     * @param string $key 原始缓存键
     * @return string 处理后的文件路径
     */
    private function getKey(string $key): string
    {
        $keys = explode(DIRECTORY_SEPARATOR, str_replace("/", DIRECTORY_SEPARATOR, $key));

        if (count($keys) < 2) {
            $keys = ["default", $keys[0]];
        }

        $keys = array_map(fn($key) => substr(md5($key), 8, 6), $keys);

        $subDir = join(DIRECTORY_SEPARATOR, $keys);
        return $subDir . ".cache";
    }

    /**
     * 获取完整的缓存文件路径
     * @param string $key 缓存键
     * @return string 完整的文件系统路径
     */
    private function getFilePath(string $key): string
    {
        return $this->baseDir . $this->getKey($key);
    }

    /**
     * 从文件中读取缓存数据
     * @param string $file 文件路径
     * @return array|null 读取的数据，失败返回null
     */
    private function readFromFile(string $file): ?array
    {
        if (!file_exists($file)) {
            return null;
        }

        try {
            // 打开文件
            $fp = fopen($file, 'r');
            if (!$fp) {
                return null;
            }

            try {
                // 获取共享锁以确保读取时数据一致性
                if (!flock($fp, LOCK_SH)) {
                    return null;
                }

                // 读取并反序列化数据
                $content = fread($fp, max(1, filesize($file)));
                $data = @unserialize($content);
                
                return is_array($data) ? $data : null;
            } finally {
                // 确保释放文件锁和关闭文件句柄
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 将数据写入缓存文件
     * @param string $file 文件路径
     * @param array $data 要写入的数据
     * @return void 写入是否成功
     */
    private function writeToFile(string $file, array $data): void
    {
        try {
            // 确保目录存在
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 打开文件用于写入
            $fp = fopen($file, 'w+');
            if (!$fp) {
                return;
            }

            try {
                // 获取排他锁以确保写入时的数据一致性
                if (!flock($fp, LOCK_EX)) {
                    return;
                }

                // 序列化数据并写入文件
                fwrite($fp, serialize($data)) !== false;
                return;
            } finally {
                // 确保释放文件锁和关闭文件句柄
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * 获取缓存值
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed 缓存值或默认值
     */
    public function get($key, $default = null): mixed
    {
        $file = $this->getFilePath($key);

        $data = $this->readFromFile($file);
        if (is_array($data) && isset($data['expire']) && isset($data['data'])) {
            if ($data['expire'] == 0 || $data['expire'] > time()) {
                return $data['data'];
            }
            unlink($file); // 文件过期时删除
        }
        return $default;
    }

    /**
     * 设置缓存值
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间（秒）
     */
    public function set($key, $value, $expire): void
    {
        $file = $this->getFilePath($key);
        $subDir = dirname($file);
        if (!is_dir($subDir)) {
            mkdir($subDir, 0777, true);
        }

        $this->writeToFile($file, [
            'expire' => $expire == 0 ? 0 : time() + $expire,
            'data' => $value
        ]);
    }

    /**
     * 删除指定的缓存项
     * @param string $key 缓存键
     */
    public function delete($key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * 清空所有缓存
     */
    public function clear(): void
    {
        $this->deleteDirectory($this->baseDir);
    }

    /**
     * 递归删除目录及其内容
     * @param string $dir 要删除的目录路径
     */
    private function deleteDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                (is_dir($filePath)) ? $this->deleteDirectory($filePath) : unlink($filePath);
            }
            rmdir($dir);
        }
    }

    /**
     * 删除指定前缀的所有缓存项
     * @param string $key 缓存键前缀
     */
    public function deleteKeyStartWith($key): void
    {
        $dir = $this->baseDir . $this->getKey($key);
        $dir = str_replace(".cache", "", $dir);
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    /**
     * 获取缓存项的剩余生存时间（TTL）
     * @param string $key 缓存键
     * @return int 剩余秒数，0表示永不过期，-1表示已过期或不存在
     */
    public function getTtl($key): int
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            $data = $this->readFromFile($file);
            if (is_array($data) && isset($data['expire'])) {
                if ($data['expire'] == 0) {
                    return 0;
                }
                return $data['expire'] - time();
            }
        }
        return -1;
    }
}

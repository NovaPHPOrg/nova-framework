<?php
declare(strict_types=1);

namespace nova\framework\cache;


class FileCacheDriver implements iCacheDriver
{
    private string $baseDir;

    public function __construct($shared = false)
    {
        $this->baseDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    private function getKey($key): string
    {
        $keys = explode(DIRECTORY_SEPARATOR, str_replace("/", DIRECTORY_SEPARATOR, $key));

        if (count($keys) < 2) {
            $keys = ["default", $keys[0]];
        }

        $keys = array_map(fn($key) => substr(md5($key), 8, 6), $keys);

        $subDir = join(DIRECTORY_SEPARATOR, $keys);
        return $subDir . ".cache";
    }

    private function getFilePath($key): string
    {
        return $this->baseDir . $this->getKey($key);
    }

    public function get($key, $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            $fp = fopen($file, 'r');
            if ($fp && flock($fp, LOCK_SH)) { // 获取共享锁
                $content = fread($fp, max(1, filesize($file)));
                $data = unserialize($content);
                flock($fp, LOCK_UN); // 释放锁
                fclose($fp);

                if (is_array($data) && isset($data['expire']) && isset($data['data'])) {
                    if ($data['expire'] == 0 || $data['expire'] > time()) {
                        return $data['data'];
                    }
                    unlink($file); // 文件过期时删除
                }
            } else {
                fclose($fp);
            }
        }
        return $default;
    }

    public function set($key, $value, $expire): void
    {
        $file = $this->getFilePath($key);
        $subDir = dirname($file);
        if (!is_dir($subDir)) {
            mkdir($subDir, 0777, true);
        }

        $fp = fopen($file, 'w+');
        if ($fp && flock($fp, LOCK_EX)) { // 获取独占锁
            $data = [
                'expire' => $expire == 0 ? 0 : time() + $expire,
                'data' => $value
            ];
            fwrite($fp, serialize($data)); // 写入数据
            flock($fp, LOCK_UN); // 释放锁
        }
        fclose($fp);
    }


    public function delete($key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clear(): void
    {
        $this->deleteDirectory($this->baseDir);
    }

    private function deleteDirectory($dir): void
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

    public function deleteKeyStartWith($key): void
    {
        $dir = $this->baseDir . $this->getKey($key);
        $dir = str_replace(".cache", "", $dir);
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    public function getTtl($key): int
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            $fp = fopen($file, 'r');
            if ($fp && flock($fp, LOCK_SH)) { // 获取共享锁
                $content = fread($fp, max(1, filesize($file)));
                $data = unserialize($content);
                flock($fp, LOCK_UN); // 释放锁
                fclose($fp);

                if (is_array($data) && isset($data['expire'])) {
                    if ($data['expire'] == 0) {
                        return 0;
                    }
                    return $data['expire'] - time();
                }
            } else {
                fclose($fp);
            }
        }
        return -1;
    }
}

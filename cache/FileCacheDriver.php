<?php

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
            $expire = (int)file_get_contents($file, false, null, -10);

            if ($expire == 0 || $expire > time()) {
                return unserialize(file_get_contents($file, false, null, 0, -10));
            }
            unlink($file); // 文件过期时删除
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

        $expire = $expire == 0 ? 0 : time() + $expire;
        file_put_contents($file, serialize($value) . $expire);
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
            $expire = (int)file_get_contents($file, false, null, -10);

            if ($expire == 0) {
                return -1; // 永不过期
            }
            $ttl = $expire - time();
            return max($ttl, 0);
        }
        return 0;
    }
}

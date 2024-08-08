<?php

namespace cache;

use nova\framework\cache\iCacheDriver;

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
        $keys = explode(DS, str_replace("/", DS, $key));

        if (count($keys) < 2) {
            $keys = ["default", $keys[0]];
        }

        $keys = array_map(function ($key) {
            return substr(md5($key), 8, 6);
        }, $keys);

        $subDir = join(DIRECTORY_SEPARATOR, $keys);
        return $subDir   . ".cache";
    }

    private function getFilePath($key): string
    {
        return $this->baseDir . $this->getKey($key);
    }

    public function get($key, $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = unserialize($content);
            if (!is_array($data) || !isset($data['expire']) || !isset($data['data'])) {
                unlink($file);
                return $default;
            }
            if ($data['expire'] == 0 || $data['expire'] > time()) {
                return $data['data'];
            }
            unlink($file);
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
        $data = [
            'expire' => $expire == 0 ? 0 : time() + $expire,
            'data' => $value
        ];
        file_put_contents($file, serialize($data));
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
        $dir = $this->getKey($key);
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

}

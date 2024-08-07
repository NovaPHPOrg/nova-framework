<?php

namespace cache;

use nova\framework\cache\iCacheDriver;

class FileCacheDriver implements iCacheDriver
{

    private string $dir = ROOT_PATH . DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR;

    public function __construct($shared = false)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    private function getKey($key): string
    {
        return md5($key).".cache";
    }

    public function get($key,$default = null): mixed
    {
        $file = $this->dir .$this->getKey($key);
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = unserialize($content);
            if ($data['expire'] == 0 || $data['expire'] > time()) {
                return $data['data'];
            }
            unlink($file);
        }
        return $default;
    }

    public function set($key, $value, $expire): void
    {
        $file = $this->dir .$this->getKey($key);
        $data = [
            'expire' => $expire == 0 ? 0 : time() + $expire,
            'data' => $value
        ];
        file_put_contents($file, serialize($data));
    }

    public function delete($key): void
    {
        $file = $this->dir .$this->getKey($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clear(): void
    {
        $files = scandir($this->dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            unlink($this->dir . $file);
        }
    }


    public function deleteKeyStartWith($key): void
    {
        $files = scandir($this->dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (str_starts_with($file, $key)) {
                unlink($this->dir . $file);
            }
        }
    }
}
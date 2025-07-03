<?php
/*
 * Copyright (c) 2025.
 * 文件缓存驱动：前 10 字节存放过期时间戳；0 视为永不过期。
 * 读取/写入均加锁；随机 GC（默认 0.1 % 概率）仅扫描时间戳，效率极高。
 */

declare(strict_types=1);

namespace nova\framework\cache;

class FileCacheDriver implements iCacheDriver
{
    /** @var string 缓存目录 */
    private string $baseDir;

    /**
     * @param bool $shared 预留参数
     */
    public function __construct(bool $shared = false)
    {
        $this->baseDir = ROOT_PATH . '/runtime/cache/';
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    /* ===================== 对外接口 ===================== */

    public function get(string $key, mixed $default = null): mixed
    {
        $this->maybeGc();                       // 概率 GC

        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return $default;
        }

        $fp = @fopen($file, 'r');
        if (!$fp) {
            return $default;
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                return $default;
            }

            // ① 读取 10 字节过期时间戳
            $expire = (int)fread($fp, 10);
            if ($expire !== 0 && $expire < time()) {
                flock($fp, LOCK_UN);
                fclose($fp);
                @unlink($file);                // 已过期，立即删除
                return $default;
            }

            // ② 读取真实内容
            $value = @unserialize(stream_get_contents($fp));
            return ($value === false) ? $default : $value;

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function set(string $key, mixed $value, ?int $expire): bool
    {
        $this->maybeGc();                       // 概率 GC

        $file = $this->getFilePath($key);
        if (!is_dir($dir = dirname($file))) {
            mkdir($dir, 0777, true);
        }

        $fp = @fopen($file, 'w');
        if (!$fp) {
            return false;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            $ttl = (int)$expire;
            $ts  = $ttl === 0 ? 0 : time() + $ttl;   // 0 = 永不过期
            fwrite($fp, sprintf('%010d', $ts));      // 固定 10 字节
            fwrite($fp, serialize($value));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return true;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->deleteDirectory($this->baseDir);
        return true;
    }

    public function deleteKeyStartWith(string $key): bool
    {
        $dir = dirname($this->getFilePath($key));
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
        return true;
    }

    public function getTtl(string $key): int
    {
        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return -1;
        }

        $fp = @fopen($file, 'r');
        if (!$fp) {
            return -1;
        }

        $expire = (int)fread($fp, 10);
        fclose($fp);

        if ($expire === 0) {
            return 0;                           // 永不过期
        }
        return max(-1, $expire - time());
    }

    /* ===================== 内部辅助 ===================== */

    /** 概率触发 GC（默认 0.1 %） */
    private function maybeGc(): void
    {
        if (mt_rand(1, 500) === 1) {
            $this->gc($this->baseDir,1000);
        }
    }

    /** 仅读取文件头 10 字节判断是否过期 */
     function gc(string $startKey,int $maxCount): void
    {
        $now  = time();
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->baseDir.DS.$startKey, \FilesystemIterator::SKIP_DOTS)
        );

        $n = 0;
        foreach ($iter as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            $fp   = @fopen($path, 'r');
            if (!$fp) {
                continue;
            }
            $expire = (int)fread($fp, 10);
            fclose($fp);

            if ($expire !== 0 && $expire < $now) {
                @unlink($path);
                if ($maxCount && ++$n >= $maxCount) {
                    break;
                }
            }
        }
    }

    /** 递归删除目录 */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->deleteDirectory($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /** 生成缓存文件路径（保持原散列规则） */
    private function getFilePath(string $key): string
    {
        $parts = explode('/', str_replace('\\', '/', $key));
        if (count($parts) < 2) {
            array_unshift($parts, 'default');
        }
        $parts = array_map(fn($k) => substr(md5($k), 8, 6), $parts);
        return $this->baseDir . implode('/', $parts) . '.cache';
    }
}

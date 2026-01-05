<?php

/*
 * Copyright (c) 2025.
 * 文件缓存驱动：前 10 字节存放过期时间戳；0 视为永不过期。
 * 读取/写入均加锁；随机 GC（默认 0.1 % 概率）仅扫描时间戳，效率极高。
 */

declare(strict_types=1);

namespace nova\framework\cache;

use ErrorException;
use nova\framework\core\File;

/**
 * 文件缓存驱动类
 *
 * 实现基于文件系统的缓存存储，具有以下特点：
 * - 文件格式：前10字节存储过期时间戳，后续为序列化的数据
 * - 线程安全：读写操作均使用文件锁
 * - 自动清理：随机触发垃圾回收，仅扫描时间戳提高效率
 * - 目录结构：基于键名生成多级目录结构，避免单目录文件过多
 *
 * @package nova\framework\cache
 */
class FileCacheDriver implements iCacheDriver
{
    /** @var string 缓存文件存储的基础目录路径 */
    private string $baseDir;

    /**
     * 构造函数
     *
     * 初始化缓存驱动，创建缓存目录
     *
     * @param bool $shared 预留参数，用于未来扩展共享缓存功能
     */
    public function __construct(bool $shared = false)
    {
        // 设置缓存目录为 runtime/cache/
        $this->baseDir = ROOT_PATH . '/runtime/cache/';
        // 如果目录不存在则创建，权限为 0777
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    /* ===================== 对外接口 ===================== */

    /**
     * 获取缓存值
     *
     * 读取缓存文件，检查是否过期，返回对应的值或默认值
     *
     * @param  string $key     缓存键名
     * @param  mixed  $default 默认值，当缓存不存在或已过期时返回
     * @return mixed  缓存值或默认值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // 随机触发垃圾回收
        $this->maybeGc();

        // 获取缓存文件路径
        $file = $this->getFilePath($key);
        if (!File::exists($file)) {
            return $default;
        }
        [$key, $value] = $this->readFileWithKey($file, $default);
        return $value;
    }

    /**
     * 设置缓存值
     *
     * 将数据序列化后写入文件，文件头部存储过期时间戳
     *
     * @param  string   $key    缓存键名
     * @param  mixed    $value  要缓存的值
     * @param  int|null $expire 过期时间（秒），null 表示永不过期
     * @return bool     操作是否成功
     */
    public function set(string $key, mixed $value, ?int $expire): bool
    {
        $this->maybeGc();

        $file = $this->getFilePath($key);
        $dir = dirname($file);
        File::mkDir($dir);

        // 使用临时文件，避免直接覆盖导致数据损坏
        $tmpFile = $file . '.' . uniqid('tmp', true);

        // 二进制模式，避免在部分环境下出现换行转换等问题
        $fp = fopen($tmpFile, 'wb');

        if (!$fp) {
            return false;
        }

        $success = false;

        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            $ttl = (int)$expire;
            $ts  = $ttl === 0 ? 0 : time() + $ttl;

            // 写入过期时间戳（10字节）
            if (fwrite($fp, sprintf('%010d', $ts)) === false) {
                return false;
            }

            // 写入原始 key 长度（2字节）和 key 内容
            $keyLen = strlen($key);
            if (fwrite($fp, pack('n', $keyLen)) === false) {
                return false;
            }
            if (fwrite($fp, $key) === false) {
                return false;
            }

            // 写入序列化后的数据
            if (fwrite($fp, serialize($value)) === false) {
                return false;
            }

            // 所有写入成功
            $success = true;

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);

            if ($success) {
                // 原子替换：写入成功后才覆盖原文件
                // 高并发下可能被 clear()/deleteKeyStartWith() 删除临时文件或目录；
                // 同时本框架会把 WARNING 转成 ErrorException，所以这里必须 try/catch。
                $renamed = false;
                try {
                    $renamed = rename($tmpFile, $file);
                } catch (ErrorException) {
                    $renamed = false;
                }

                if (!$renamed) {
                    // 目录可能被外部清理，重建后再试一次（不改变原有路径规则）
                    File::mkDir(dirname($file));
                    try {
                        $renamed = rename($tmpFile, $file);
                    } catch (ErrorException) {
                        $renamed = false;
                    }
                }

                if (!$renamed) {
                    $success = false;
                    try {
                        unlink($tmpFile);
                    } catch (ErrorException) {
                        // ignore
                    }
                }
            } else {
                // 写入失败，删除临时文件
                try {
                    unlink($tmpFile);
                } catch (ErrorException) {
                    // ignore
                }
            }
        }

        return $success;
    }
    private function readFileWithKey(string $file, mixed $default = null): array
    {
        $fp = @fopen($file, 'r');
        if (!$fp) {
            return [$file, $default];
        }

        $expired = false;

        try {
            if (!flock($fp, LOCK_SH)) {
                return [$file, $default];
            }

            // 读取过期时间戳（10字节）
            $expire = (int)fread($fp, 10);
            if ($expire !== 0 && $expire < time()) {
                $expired = true;
                return [$file, $default];
            }

            // 读取key长度（2字节）
            $header = fread($fp, 2);
            if (strlen($header) < 2) {
                // 格式错误，删除文件
                $expired = true;
                return [$file, $default];
            }

            $keyLen = unpack('n', $header)[1];

            // 防御异常keyLen（可能是损坏或旧格式文件）
            if ($keyLen <= 0 || $keyLen > 1000) {
                $expired = true;
                return [$file, $default];
            }

            // 读取原始key
            $origKey = fread($fp, $keyLen);
            if (strlen($origKey) !== $keyLen) {
                // 读取失败，文件损坏
                $expired = true;
                return [$file, $default];
            }

            // 读取并反序列化数据
            $serialized = stream_get_contents($fp);

            $value = unserialize($serialized);

            return [$origKey, $value];

        } catch (ErrorException $exception) {
            File::del($file, true);
            return [$file, $default];
        } finally {
            if (is_resource($fp)) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            if ($expired) {
                File::del($file, true);
            }
        }
    }
    public function is_serialized($str): bool
    {
        if (!is_string($str)) {
            return false;
        }
        $str = trim($str);

        if ($str === 'N;') {
            return true; // 特殊值 null
        }
        if (!preg_match('/^[aOsibd]:/', $str)) {
            return false; // 必须是合法前缀
        }
        // 最后一个分号 / 花括号必须存在
        return str_ends_with($str, ';') || str_ends_with($str, '}');
    }

    /**
     * 删除指定的缓存项
     *
     * @param  string $key 要删除的缓存键名
     * @return bool   操作是否成功
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (is_file($file)) {
            File::del($file, true);
        }
        return true;
    }

    /**
     * 清空所有缓存
     *
     * 删除整个缓存目录
     *
     * @return bool 操作是否成功
     */
    public function clear(): bool
    {
        File::del($this->baseDir, true);
        return true;
    }

    /**
     * 删除以指定键名开头的所有缓存
     *
     * 删除对应目录下的所有文件
     *
     * @param  string $key 键名前缀
     * @return bool   操作是否成功
     */
    public function deleteKeyStartWith(string $key): bool
    {
        $dir = dirname($this->getFilePath($key."/default"));
        File::del($dir, true);
        return true;
    }

    /**
     * 获取缓存项的剩余生存时间
     *
     * @param  string $key 缓存键名
     * @return int    剩余秒数，-1 表示不存在，0 表示永不过期
     */
    public function getTtl(string $key): int
    {
        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return -1;
        }

        // 读取文件头部的过期时间戳
        $fp = @fopen($file, 'r');
        if (!$fp) {
            return -1;
        }

        $expire = (int)fread($fp, 10);
        fclose($fp);

        if ($expire === 0) {
            return 0;                           // 永不过期
        }
        return max(-1, $expire - time());       // 计算剩余时间
    }

    /**
     * 概率触发垃圾回收
     *
     * 默认 0.1% 的概率触发，避免频繁清理影响性能
     */
    private function maybeGc(): void
    {
        if (mt_rand(1, 500) === 1) {
            $this->gc('', 1000);
        }
    }

    /**
     * 垃圾回收：清理过期的缓存文件
     *
     * 仅读取文件头 10 字节判断是否过期，提高清理效率
     *
     * @param string $startKey 起始键名，用于分片清理
     * @param int    $maxCount 最大清理数量，0 表示无限制
     */
    public function gc(string $startKey, int $maxCount): bool
    {
        $now  = time();
        $dir = $this->baseDir.DS.$startKey;
        if (!file_exists($dir)) {
            return false;
        }

        // 递归遍历目录
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $n = 0;
        foreach ($iter as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            try {
                $fp   = fopen($path, 'r');
                if (!$fp) {
                    continue;
                }
            } catch (ErrorException $exception) {
                continue;
            }

            // 只读取前 10 字节判断过期时间
            $expire = (int)fread($fp, 10);
            fclose($fp);

            // 删除过期文件
            if ($expire !== 0 && $expire < $now) {
                File::del($path);
                if ($maxCount && ++$n >= $maxCount) {
                    break;
                }
            }
        }
        return true;
    }

    /**
     * 根据键名生成缓存文件路径
     *
     * 保持原有的散列规则，生成多级目录结构：
     * - 将键名按 '/' 分割
     * - 如果少于 2 级，添加 'default' 前缀
     * - 对每级取 MD5 的前 6 位作为目录名
     * - 最终文件扩展名为 .cache
     *
     * @param  string $key 缓存键名
     * @return string 缓存文件的完整路径
     */
    private function getFilePath(string $key): string
    {
        // 将反斜杠转换为正斜杠，并按斜杠分割
        $parts = explode('/', str_replace('\\', '/', $key));

        // 确保至少有 2 级目录结构
        if (count($parts) < 2) {
            array_unshift($parts, 'default');
        }

        // 对每级目录名取 MD5 的前 6 位
        $parts = array_map(fn ($k) => substr(md5($k), 8, 6), $parts);

        // 拼接完整路径
        return $this->baseDir . implode('/', $parts) . '.cache';
    }

    /**
     * 获取指定起始键名开始的所有缓存项
     *
     * 遍历指定目录下的所有缓存文件，读取文件内容并返回键值对数组
     * 注意：由于文件路径是通过MD5散列生成的，返回的键名是文件路径而不是原始键名
     *
     * @param  string $startKey 起始键名
     * @return array  缓存项数组，键为文件路径，值为缓存值
     */
    public function getAll(string $startKey): array
    {
        $result = [];
        $dir = dirname($this->getFilePath($startKey."/default"));
        if (!file_exists($dir)) {
            return [];
        }

        $files = glob($dir . '/*.cache');
        if (!$files) {
            return [];
        }

        foreach ($files as $fileInfo) {
            [$key, $value] = $this->readFileWithKey($fileInfo);
            if ($value === null) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

}

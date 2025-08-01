<?php

/*
 * Copyright (c) 2025.
 * 文件缓存驱动：前 10 字节存放过期时间戳；0 视为永不过期。
 * 读取/写入均加锁；随机 GC（默认 0.1 % 概率）仅扫描时间戳，效率极高。
 */

declare(strict_types=1);

namespace nova\framework\cache;

use Exception;
use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\framework\exception\ErrorException;

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

        // 以只读模式打开文件
        try{
            $fp = fopen($file, 'r');
        }catch (ErrorException $exception){
            //不存在导致无法读取
            return $default;
        }
        if (!$fp) {
            return $default;
        }

        $expired = false;

        try {
            // 获取共享锁（读锁）
            if (!flock($fp, LOCK_SH)) {
                return $default;
            }

            // ① 读取前 10 字节作为过期时间戳
            $expire = (int)fread($fp, 10);
            if ($expire !== 0 && $expire < time()) {
                $expired = true;      // 标记为过期，稍后删除
                return $default;
            }

            // ② 读取剩余内容并反序列化
            try {
                $value = @unserialize(stream_get_contents($fp));
                return ($value === false) ? $default : $value;
            } catch (Exception $exception) {
                Logger::error("FileCache Exception:".$exception->getMessage(), $exception->getTrace());
                return $default;
            }

        } finally {
            // 确保资源正确释放
            if (is_resource($fp)) {          // 避免二次 flock/fclose
                flock($fp, LOCK_UN);         // 释放锁
                fclose($fp);                 // 关闭文件
            }
            if ($expired) {                  // 安全删除过期文件
                File::del($file,true);
            }
        }
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
        // 随机触发垃圾回收
        $this->maybeGc();

        // 获取缓存文件路径
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        File::mkDir($dir);
        // 以写入模式打开文件
        try{
            $fp = fopen($file, 'w');
        }catch (ErrorException $exception){
            try {
                File::mkDir($dir);
                $fp = fopen($file, 'w');
            }catch (Exception $exception){
                return false;
            }
        }
        if (!$fp) {
            return false;
        }

        try {
            // 获取排他锁（写锁）
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            // 计算过期时间戳
            $ttl = (int)$expire;
            $ts  = $ttl === 0 ? 0 : time() + $ttl;   // 0 = 永不过期

            // 写入 10 字节的过期时间戳（固定长度）
            fwrite($fp, sprintf('%010d', $ts));

            // 写入序列化的数据
            fwrite($fp, serialize($value));
        } finally {
            // 释放锁并关闭文件
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return true;
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
            File::del($file,true);
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
        File::del($this->baseDir,true);
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
        $dir = dirname($this->getFilePath($key));
        File::del($dir,true);
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

    /* ===================== 内部辅助 ===================== */

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
    public function gc(string $startKey, int $maxCount): void
    {
        $now  = time();
        $dir = $this->baseDir.DS.$startKey;
        if (!file_exists($dir)) {
            return;
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
            $fp   = @fopen($path, 'r');
            if (!$fp) {
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
}

<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\cache;

use function nova\framework\config;

use nova\framework\core\Logger;

/**
 * 缓存管理类
 *
 * 该类提供了统一的缓存操作接口，支持多种缓存驱动
 */
class Cache
{
    /** @var iCacheDriver 缓存驱动实例 */
    private iCacheDriver $driver;

    /**
     * 构造函数
     *
     * @param bool        $shared      是否共享缓存
     * @param string|null $driverClazz 指定的缓存驱动类名
     */
    public function __construct(bool $shared = false, ?string $driverClazz = null)
    {
        $driver = $driverClazz ?? config('cache.driver');

        try {
            if ($driver == null || !class_exists($driver) || !in_array('nova\framework\cache\iCacheDriver', class_implements($driver))) {
                throw new CacheException("Cache driver {$driver} not found");
            }
            $this->driver = new $driver($shared);
        } catch (CacheException $e) {
            // 当指定的缓存驱动无效时，默认使用文件缓存驱动
            $this->driver = new FileCacheDriver($shared);
            Logger::warning($e->getMessage());
        }
    }

    /**
     * 设置缓存
     *
     * @param string $key   缓存键
     * @param mixed  $value 缓存值
     * @param int    $ttl   过期时间（秒），0表示永不过期
     */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->driver->set($key, $value, $ttl);
    }

    /**
     * 获取缓存
     *
     * @param  string $key     缓存键
     * @param  mixed  $default 默认值
     * @return mixed  缓存值或默认值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    /**
     * 删除指定的缓存
     *
     * @param string $key 缓存键
     */
    public function delete(string $key): void
    {
        $this->driver->delete($key);
    }

    /**
     * 删除所有以指定键开头的缓存
     *
     * @param string $key 缓存键前缀
     */
    public function deleteKeyStartWith(string $key): void
    {
        $this->driver->deleteKeyStartWith($key);
    }

    /**
     * 清空所有缓存
     */
    public function clear(): void
    {
        $this->driver->clear();
    }

    /**
     * 获取缓存的剩余生存时间
     *
     * @param  string $key 缓存键
     * @return int    剩余秒数，-1表示已过期，0表示永不过期
     */
    public function getTtl(string $key): int
    {
        return $this->driver->getTtl($key);
    }
}

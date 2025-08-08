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

/**
 * 缓存驱动接口
 * 定义了缓存操作的标准方法
 */
interface iCacheDriver
{
    /**
     * 构造函数
     * @param bool $shared 是否共享实例
     */
    public function __construct(bool $shared = false);

    /**
     * 获取缓存值
     * @param  string     $key     缓存键名
     * @param  mixed|null $default 默认值（当缓存不存在时返回）
     * @return mixed      缓存值或默认值
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 设置缓存
     * @param  string   $key    缓存键名
     * @param  mixed    $value  缓存值
     * @param  int|null $expire 过期时间（秒）
     * @return bool     是否设置成功
     */
    public function set(string $key, mixed $value, ?int $expire): bool;

    /**
     * 删除指定缓存
     * @param  string $key 缓存键名
     * @return bool   是否删除成功
     */
    public function delete(string $key): bool;

    /**
     * 删除指定前缀的所有缓存
     * @param  string $key 缓存键名前缀
     * @return bool   是否删除成功
     */
    public function deleteKeyStartWith(string $key): bool;

    /**
     * 清空所有缓存
     * @return bool 是否清空成功
     */
    public function clear(): bool;

    /**
     * 获取缓存的剩余生存时间
     * @param  string $key 缓存键名
     * @return int    剩余生存时间（秒），如果键不存在则返回 -1，如果键存在但没有过期时间则返回 -2
     */
    public function getTtl(string $key): int;

    /**
     * 垃圾回收机制
     * 清理指定起始键名开始的缓存项，最多清理指定数量
     * @param  string $startKey 起始键名
     * @param  int    $maxCount  最大清理数量
     * @return bool   是否清理成功
     */
    public function gc(string $startKey, int $maxCount): bool;

    /**
     * 获取指定起始键名开始的所有缓存项
     * @param  string $startKey 起始键名
     * @return array  缓存项数组，键为缓存键名，值为缓存值
     */
    public function getAll(string $startKey): array;
}

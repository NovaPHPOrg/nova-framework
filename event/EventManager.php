<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\event;

use nova\framework\core\Context;
use nova\framework\core\Logger;
use ReflectionClass;
use ReflectionException;
use function nova\framework\config;

/**
 * 事件管理器类
 *
 * 用于管理框架中的事件监听和触发机制。支持：
 * - 事件的注册与监听
 * - 基于优先级的事件处理
 * - 单次或多次事件触发
 * - 事件监听器的管理
 *
 * @package nova\framework\event
 */
class EventManager
{
    /**
     * @var array<string, array<int, callable>> 事件监听器列表
     *                                          结构: [
     *                                          事件名 => [
     *                                          优先级 => 回调函数
     *                                          ]
     *                                          ]
     */
    private array $events = [];

    /**
     * 注册框架启动事件
     *
     * 从配置文件中读取并注册框架启动时需要执行的事件处理器
     * 每个事件处理器类都必须实现静态的 register 方法
     *
     */
    public static function register(): void
    {
        $events = config("framework_start");
        if (!is_array($events)) {
            Logger::debug("Framework start events configuration is not an array");
            return;
        }

        foreach ($events as $event) {
            try {
                $ref = new ReflectionClass($event);
                if (!$ref->hasMethod('register')) {
                    throw new ReflectionException("Method 'register' not found in {$event}");
                }
                $ref->getMethod('register')->invoke(null);
            } catch (ReflectionException $e) {
                Logger::error("Event: {$event} register failed: " . $e->getMessage());
            }
        }
    }

    public static function addListener(string $event_name, callable $func, int $level = 1000): void
    {
        self::getInstance()->_addListener($event_name, $func, $level);
    }

    /**
     * 监听事件
     *
     * @param string $event_name 事件名
     * @param callable $func 事件处理函数，接收参数 (string $event_name, mixed &$data)
     * @param int $level 优先级(0-最高，默认1000)
     */
    public function _addListener(string $event_name, callable $func, int $level = 1000): void
    {
        if (!isset($this->events[$event_name])) {
            $this->events[$event_name] = [];
        }

        $level = max(0, $level);

        while (isset($this->events[$event_name][$level])) {
            $level++;
        }

        $this->events[$event_name][$level] = $func;
        ksort($this->events[$event_name]);
    }

    public static function getInstance(): EventManager
    {
        return Context::instance()->getOrCreateInstance("EventManager", function () {
            return new EventManager();
        });
    }

    public static function trigger(string $event_name, mixed &$data = null, bool $once = false): mixed
    {
        return self::getInstance()->_trigger($event_name, $data, $once);
    }

    /**
     * 触发事件
     *
     * @param string $event_name 事件名
     * @param mixed|null       &$data 事件携带的数据，通过引用传递可在事件处理中修改
     * @param bool $once 是否只获取第一个非空返回值
     * @return array|mixed|null 返回事件处理结果:
     *                          - 当 $once 为 true 时，返回第一个非空结果
     *                          - 当 $once 为 false 时，返回所有处理结果的数组
     *                          - 当没有监听器时，返回 null
     */
    public function _trigger(string $event_name, mixed &$data = null, bool $once = false): mixed
    {
        if (!array_key_exists($event_name, $this->events)) {
            return null;
        }

        $list = $this->events[$event_name];
        $results = [];

        foreach ($list as $key => $event) {
            // 记录事件触发的日志，包含优先级信息
            Logger::debug("Event: $event_name, level: $key");
            $results[$key] = $event($event_name, $data);

            // 当返回 false 或在 $once 模式下获得非空返回值时终止处理
            if (false === $results[$key] || (!is_null($results[$key]) && $once)) {
                break;
            }
        }

        return $once ? end($results) : $results;
    }

    /**
     * 删除事件
     * @param string $event_name 事件名称
     */
    public function removeListener(string $event_name): void
    {
        unset($this->events[$event_name]);
    }

    /**
     * 获取所有已注册的事件列表
     *
     * @return array<string, array<int, callable>> 返回事件名到处理器数组的映射
     */
    public function list(): array
    {
        return $this->events;
    }

    /**
     * 检查事件是否已注册
     *
     * @param string $event_name 要检查的事件名
     * @return bool   如果事件已注册返回 true，否则返回 false
     */
    public function hasListener(string $event_name): bool
    {
        return isset($this->events[$event_name]);
    }

    /**
     * 获取指定事件的监听器数量
     *
     * @param string $event_name 事件名
     * @return int    返回监听器数量，如果事件未注册则返回 0
     */
    public function getListenerCount(string $event_name): int
    {
        return isset($this->events[$event_name]) ? count($this->events[$event_name]) : 0;
    }
}

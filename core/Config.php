<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

/**
 * 配置管理类
 *
 * 用于管理应用程序的配置信息，提供完整的配置管理功能：
 * - 配置文件的加载和保存
 * - 多层级配置的读取和设置
 * - 配置数组的合并操作
 * - 配置变更的自动保存
 *
 * 配置文件格式为PHP数组，支持多级嵌套结构
 * 支持点号分隔的路径访问，如：'database.mysql.host'
 *
 * 使用示例：
 * ```php
 * $config = new Config();
 * $config->set('database.host', 'localhost');
 * $value = $config->get('database.host', 'default');
 * ```
 */
class Config
{
    /**
     * @var array<string, mixed> 存储配置数据的数组
     *                           支持多维数组结构，用于存储所有配置信息
     */
    protected array $config = [];
    /**
     * @var string 配置文件路径
     *             默认为项目根目录下的config.php文件
     *             如果config.php不存在，将尝试加载example.config.php
     */
    private string $configPath;

    private string $configHash;

    /**
     * @var array<string, mixed> 进程启动时从磁盘加载的原始配置快照
     *                           用于保存时 diff 出本进程真正改动过的顶层节点
     */
    private array $initialConfig = [];

    /**
     * 构造函数
     * 初始化配置文件路径，加载配置文件内容，并计算初始哈希值
     *
     * @throws RuntimeException 当配置文件加载失败时抛出
     */
    public function __construct()
    {
        $this->configPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "config.php";
        $this->loadConfig();
        $this->configHash = md5(json_encode($this->config));
        $this->initialConfig = $this->config;

    }

    /**
     * 从磁盘读取最新的配置数组
     *
     * Workerman 等长驻进程下，Zend OPcache 可能仍缓存着旧的 config.php 编译结果，
     * 读取前强制失效，保证拿到磁盘上的最新配置（未启用 opcache 时为无操作）
     *
     * @return array<string, mixed> 磁盘上的配置数组，文件不存在时返回空数组
     */
    private function readDisk(): array
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->configPath, true);
        }
        if (!file_exists($this->configPath)) {
            return [];
        }
        $config = require $this->configPath;
        return is_array($config) ? $config : [];
    }

    /**
     * 加载配置文件
     * 首先尝试加载config.php，如果不存在则尝试加载example.config.php
     *
     * @throws RuntimeException 当两个配置文件都不存在时抛出异常
     */
    private function loadConfig(): void
    {
        if (file_exists($this->configPath)) {
            $this->config = $this->readDisk();
        } else {
            $exampleConfigPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "example.config.php";
            if (!file_exists($exampleConfigPath)) {
                exit("配置文件不存在：config.php");
            }
            $this->config = require $exampleConfigPath;
        }
    }

    /**
     * 递归合并配置数组
     * 将新的配置数组合并到现有配置中，支持深度合并
     * 如果目标路径不存在或不是数组，将创建新数组
     *
     * @param string               $path  配置路径，使用点号分隔
     * @param array<string, mixed> $value 要合并的配置数组
     *
     * @example
     * ```php
     * $config->merge('database', [
     *     'mysql' => [
     *         'host' => 'localhost',
     *         'port' => 3306
     *     ]
     * ]);
     * ```
     */
    public function merge(string $path, array $value): void
    {
        $original = $this->get($path, []);
        if (!is_array($original)) {
            $original = [];
        }

        $merged = $this->mergeArrays($original, $value);
        $this->set($path, $merged);
    }

    /**
     * 获取配置值
     * 支持使用点号分隔的路径访问多层级配置
     *
     * @param  string $path    配置路径，使用点号分隔，如：'db.host'
     * @param  mixed  $default 当配置项不存在时返回的默认值
     * @return mixed  返回查找到的配置值，如果未找到则返回默认值
     *
     * @example
     * ```php
     * // 获取数据库主机配置
     * $host = $config->get('database.mysql.host', 'localhost');
     * // 获取完整的数据库配置
     * $dbConfig = $config->get('database');
     * ```
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $config = $this->config;

        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                return $default;
            }
            $config = $config[$key];
        }

        return $config;
    }

    /**
     * 递归合并两个数组
     * 支持深度合并，如果键存在且都是数组则递归合并，否则用新值覆盖
     *
     * @param  array<string, mixed> $original 原始数组
     * @param  array<string, mixed> $new      新数组
     * @return array<string, mixed> 返回合并后的数组
     */
    private function mergeArrays(array $original, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
                $original[$key] = $this->mergeArrays($original[$key], $value);
            } else {
                $original[$key] = $value;
            }
        }
        return $original;
    }

    /**
     * 设置配置值
     * 支持使用点号分隔的路径设置多层级配置
     * 如果路径中的键不存在，将自动创建对应的数组结构
     *
     * @param string $path  配置路径，使用点号分隔，如：'db.host'
     * @param mixed  $value 要设置的配置值，可以是任意类型
     *
     * @example
     * ```php
     * // 设置数据库主机
     * $config->set('database.mysql.host', 'localhost');
     * // 设置完整的邮件配置
     * $config->set('mail', ['host' => 'smtp.example.com', 'port' => 587]);
     * ```
     */
    public function set(string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $config = &$this->config;

        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                $config[$key] = [];
            }
            $config = &$config[$key];
        }

        $config = $value;

    }

    /**
     * 保存配置到文件
     * 仅当配置发生变化时才会执行写入操作。
     * 写入前先重新读取磁盘上的最新配置，只把本进程改动过的顶层节点合并进去，
     * 避免长驻进程（Workerman）下用启动时的旧快照整体覆盖其他进程刚保存的值。
     *
     * @throws RuntimeException 当文件写入失败时抛出异常
     */
    private function saveConfig(): void
    {
        $hash = md5(json_encode($this->config));
        if ($this->configHash === $hash) {
            return;
        }

        // diff 出本进程真正改动过的顶层节点
        $changed = array_filter($this->config, function ($value, $key) {
            return !array_key_exists($key, $this->initialConfig) || $this->initialConfig[$key] !== $value;
        }, ARRAY_FILTER_USE_BOTH);
        if ($changed === []) {
            $this->configHash = $hash;
            return;
        }

        // 基于磁盘最新配置做合并，而不是用内存快照整体覆盖：
        // Workerman 多 worker / 长驻进程下，其他进程可能在本进程运行期间保存过配置，
        // 整体覆盖会把那些新值抹掉（表现为“保存后再刷新配置没生效”）
        $disk = $this->readDisk();
        foreach ($changed as $key => $value) {
            $disk[$key] = $value;
        }

        $newHash = md5(json_encode($disk));
        if ($newHash === md5(json_encode($this->readDisk()))) {
            // 磁盘上已经是要写入的内容（例如其他进程已保存过相同值）
            $this->config = $disk;
            $this->initialConfig = $disk;
            $this->configHash = $newHash;
            return;
        }

        Logger::debug("Config changed: {$this->configHash},", array_keys($changed));
        $content = "<?php\nreturn " . var_export($disk, true) . ";";
        // 多进程（Workerman 多 worker）并发写时加独占锁，避免写入交错损坏文件
        if (file_put_contents($this->configPath, $content, LOCK_EX) === false) {
            throw new RuntimeException("无法保存配置文件：{$this->configPath}");
        }
        $this->config = $disk;
        $this->initialConfig = $disk;
        $this->configHash = $newHash;
        // 让 OPcache 立即感知文件变化，防止后续请求 require 到旧的编译缓存
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->configPath, true);
        }

    }

    /**
     * 析构函数
     * 在对象销毁时自动保存配置到文件
     * 仅当配置发生变化时才会写入文件
     */
    public function __destruct()
    {
        $this->saveConfig();
    }

    /**
     * 获取完整的配置数组
     *
     * @return array<string, mixed> 返回当前所有配置的数组副本
     */
    public function all(): array
    {
        return $this->config;
    }
}

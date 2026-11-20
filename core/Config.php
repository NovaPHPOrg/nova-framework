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
     */
    protected array $config = [];

    /**
     * @var string 配置文件路径
     */
    private string $configPath;

    /**
     * @var int 加载 config.php 时的文件修改时间戳，-1 表示磁盘上不存在该文件
     */
    private int $initialMtime = -1;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->configPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "config.php";
        $this->loadConfig();
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
        clearstatcache(true, $this->configPath);
        $config = require $this->configPath;
        return is_array($config) ? $config : [];
    }

    /**
     * 加载配置文件
     */
    private function loadConfig(): void
    {
        $example = $this->readExampleConfig();

        if (file_exists($this->configPath)) {
            $this->config = $this->readDisk();
            $this->initialMtime = (int)filemtime($this->configPath);
        } elseif ($example !== []) {
            $this->config = $example;
            $this->initialMtime = -1;
        } else {
            exit("配置文件不存在：config.php");
        }

        foreach ($example as $key => $value) {
            if (!array_key_exists($key, $this->config)) {
                $this->config[$key] = $value;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readExampleConfig(): array
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "example.config.php";
        if (!file_exists($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }

    /**
     * 递归合并配置数组
     * 将新的配置数组合并到现有配置中，支持深度合并
     *
     * @param string               $path  配置路径，使用点号分隔
     * @param array<string, mixed> $value 要合并的配置数组
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
     * @return mixed
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
     *
     * @param string $path  配置路径，使用点号分隔
     * @param mixed  $value 要设置的配置值
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
     *
     * 仅当配置发生变化且磁盘未被外部修改时才写入。
     * 通过比较文件修改时间戳来判断磁盘是否被其他进程/手动编辑更新过。
     * 如果磁盘比我们的快照更新，直接放弃写入，避免覆盖他人的修改。
     *
     * @throws RuntimeException 当文件写入失败时抛出异常
     */
    private function saveConfig(): void
    {
        clearstatcache(true, $this->configPath);
        if (!file_exists($this->configPath)) {
            $this->writeConfig();
            return;
        }

        $diskMtime = (int)filemtime($this->configPath);
        // 磁盘比我们的快照更新，说明被其他进程或手动编辑改过，放弃写入
        if ($diskMtime >= $this->initialMtime) {
            return;
        }

        $this->writeConfig();
    }

    /**
     * 将配置写入磁盘
     */
    private function writeConfig(): void
    {
        $content = "<?php\nreturn " . var_export($this->config, true) . ";";

        $fp = @fopen($this->configPath, 'c');
        if ($fp) {
            flock($fp, LOCK_EX);
        }

        try {
            if (file_put_contents($this->configPath, $content, LOCK_EX) === false) {
                throw new RuntimeException("无法保存配置文件：{$this->configPath}");
            }
        } finally {
            if ($fp) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->configPath, true);
        }
    }

    /**
     * 析构函数
     * 在对象销毁时自动保存配置到文件
     */
    public function __destruct()
    {
        $this->saveConfig();
    }

    /**
     * 获取完整的配置数组
     */
    public function all(): array
    {
        return $this->config;
    }
}

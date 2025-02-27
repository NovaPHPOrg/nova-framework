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

use Throwable;

/**
 * 类自动加载器
 *
 * 负责框架的类自动加载功能，支持以下特性：
 * - PSR-4 风格的自动加载
 * - 类文件路径缓存
 * - 自定义命名空间映射
 * - 缓存持久化
 *
 * 使用示例：
 * ```php
 * $loader = new Loader();
 * $loader->setNamespace([
 *     'app\\' => 'app/',
 *     'plugins\\' => 'plugins/'
 * ]);
 * ```
 */
class Loader
{
    /**
     * 类文件路径缓存
     *
     * 格式：[
     *     '完整类名' => '文件路径',
     *     'nova\framework\core\Logger' => '/path/to/Logger.php'
     * ]
     *
     * @var array<string, string>
     */
    private array $autoloadFilesCache;

    /**
     * 缓存文件路径
     * 用于持久化存储类文件映射关系
     *
     * @var string
     */
    private string $file = '';

    /**
     * 命名空间映射配置
     *
     * 格式：[
     *     '命名空间前缀' => '目录路径',
     *     'app\\' => 'app/'
     * ]
     *
     * @var array<string, string>
     */
    private array $namespace = [];

    private string $hash = '';

    private function hash(array $cache): string
    {
        return md5(serialize($cache));
    }

    /**
     * 构造函数
     *
     * 初始化自动加载器：
     * 1. 创建缓存目录
     * 2. 加载已有的类文件映射缓存
     * 3. 注册自动加载函数
     */
    public function __construct()
    {
        $this->autoloadFilesCache = [];

        // 设置缓存文件路径
        $this->file = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR
            . 'cache' . DIRECTORY_SEPARATOR . 'autoload.php';

        // 确保缓存目录存在
        $path = dirname($this->file);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // 尝试加载现有的缓存文件
        if (file_exists($this->file)) {
            try {
                $this->autoloadFilesCache = include_once $this->file;
            } catch (Throwable $e) {
                // 缓存文件损坏时，重置缓存
                $this->autoloadFilesCache = [];
            }

            $this->hash = $this->hash($this->autoloadFilesCache);
        }

        // 注册自动加载函数
        spl_autoload_register(function () {
            $this->autoload(...func_get_args());
        }, true, true);
    }

    /**
     * 析构函数
     *
     * 将类文件映射缓存持久化到文件系统
     * 格式化为PHP数组返回语句，便于直接require加载
     */
    public function __destruct()
    {
        if ($this->hash($this->autoloadFilesCache) != $this->hash) {
            file_put_contents($this->file, "<?php\nreturn " . var_export($this->autoloadFilesCache, true) . ";");
        }

    }

    /**
     * 设置命名空间映射
     *
     * @param array $namespace 命名空间映射配置
     *                         格式：['命名空间前缀' => '目录路径']
     */
    public function setNamespace(array $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * 自动加载实现
     *
     * 按以下顺序查找类文件：
     * 1. 检查缓存中是否存在
     * 2. 遍历命名空间映射进行查找
     * 3. 查找框架默认命名空间
     *
     * @param string $raw 完整的类名（包含命名空间）
     */
    public function autoload(string $raw): void
    {
        // 首先检查缓存
        if (array_key_exists($raw, $this->autoloadFilesCache)) {
            $this->load($this->autoloadFilesCache[$raw]);
            return;
        }

        // 合并自定义命名空间和框架默认命名空间
        $namespace = $this->namespace;
        $namespace += [
            'nova\\' => 'nova' . DIRECTORY_SEPARATOR,
        ];

        // 遍历所有命名空间前缀
        foreach ($namespace as $prefix => $replace) {
            // 将类名转换为文件路径
            $realClass = str_replace(
                "\\",
                DIRECTORY_SEPARATOR,
                str_replace($prefix, $replace, $raw)
            ) . ".php";
            $file = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $realClass;

            // 如果文件存在，则加载并缓存
            if (file_exists($file)) {
                $this->autoloadFilesCache[$raw] = $file;
                $this->load($file);
                return;
            }
        }
    }

    /**
     * 加载类文件
     *
     * @param string $file 类文件的完整路径
     */
    private function load(string $file): void
    {
        require_once $file;
    }
}

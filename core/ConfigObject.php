<?php

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

/**
 * 配置对象抽象基类
 *
 * 自动管理配置文件的加载、保存和更新机制。
 * 配置文件路径基于类名自动推导，支持运行时修改并在析构时自动保存。
 *
 * @package nova\framework\core
 * @author Nova Framework
 */
abstract class ConfigObject
{
    /**
     * @var string
     */
    private string $node;


    /**
     * 构造函数
     *
     * 初始化配置对象，自动推导节点名，设置配置文件路径，
     * 加载配置数据并计算初始哈希值用于后续变更检测。
     *
     * @throws RuntimeException 当无法读取配置文件时抛出异常
     */
    final public function __construct()
    {
        $this->node = $this->inferNode();
        $this->loadConfig();
    }

    /**
     * 获取当前配置数据
     *
     * 返回所有配置属性的关联数组，自动排除内部私有变量。
     * 该方法用于序列化配置数据以便保存到文件或进行变更检测。
     *
     * @return array 配置数据的关联数组
     */
    protected function getConfig(): array
    {
        $cfg = get_object_vars($this);
        // 移除内部私有变量，这些不属于配置数据
        unset($cfg['node']);
        return $cfg;
    }

    public function hash(): string
    {
        return md5(json_encode($this->getConfig()));
    }

    /**
     * 加载配置文件
     *
     * 从配置文件路径加载配置数据并映射到当前对象属性。
     * 支持Workerman环境的特殊处理，如果配置文件不存在则尝试加载示例配置。
     *
     * @throws RuntimeException 当无法读取配置文件时抛出异常
     */
    private function loadConfig(): void
    {
        $cfg = Context::instance()->config()->get($this->node, $this->getConfig());
        $this->map($cfg);
    }

    /**
     * 将配置数组映射到对象属性
     *
     * 遍历配置数组并将键值对映射到对象的对应属性，
     * 只有当属性存在时才进行赋值，避免创建不存在的属性。
     *
     * @param array $cfg 配置数据数组
     */
    private function map(array $cfg): void
    {
        foreach ($cfg as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }

    /**
     * 析构函数
     *
     * 对象销毁时自动检测配置是否发生变化，如果有变化则自动保存到配置文件。
     * 通过比较当前配置的MD5哈希值与初始哈希值来判断是否需要保存。
     *
     * @throws RuntimeException 当无法保存配置文件时抛出异常
     */
    public function __destruct()
    {
        $cfg  = $this->getConfig();
        Context::instance()->config()->set($this->node, $cfg);
    }

    /**
     * 推导配置节点名称
     *
     * 基于类名自动推导配置节点名：
     * 1. 获取类的短名（去掉命名空间）
     * 2. 移除末尾的"Config"后缀
     * 3. 首字母转换为小写
     *
     * 例如：LoginConfig → login，WafConfig → waf
     *
     * @return string 推导出的节点名称
     */
    private function inferNode(): string
    {
        $short = (strrchr(static::class, '\\'))
            ? substr(strrchr(static::class, '\\'), 1)
            : static::class;

        $key = preg_replace('/Config$/', '', $short);

        return lcfirst($key);   // Login → login，Waf → waf
    }

}

<?php

declare(strict_types=1);

namespace nova\framework\core;

abstract class ConfigObject
{
    /** 缓存推导出的节点名，避免重复计算 */
    private string $node;

    final public function __construct()
    {
        $this->node = $this->inferNode();

        $defaults = get_object_vars($this);

        $cfg = Context::instance()
            ->config()
            ->get($this->node, $defaults);

        foreach ($cfg as $k => $v) {
            $this->$k = $v;
        }
    }

    public function __destruct()
    {
        Context::instance()
            ->config()
            ->set($this->node, get_object_vars($this));
    }

    /** 推导节点名：类短名去掉 "Config"，首字母小写 */
    private function inferNode(): string
    {
        $short = (strrchr(static::class, '\\'))
            ? substr(strrchr(static::class, '\\'), 1)
            : static::class;

        $key = preg_replace('/Config$/', '', $short);

        return lcfirst($key);   // Login → login，Waf → waf
    }

}

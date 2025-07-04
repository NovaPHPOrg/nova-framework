<?php

namespace nova\framework\core;

abstract class ConfigObject
{
    /** 读取配置 + 覆盖默认 */
    final public function __construct()
    {
        $cfg = Context::instance()->config()->get(
            static::key(),
            get_object_vars($this)
        );
        foreach ($cfg as $k => $v) {
            $this->$k = $v;
        }
    }

    /** 写回配置（可选） */
    public function __destruct()
    {
        Context::instance()->config()->set(
            static::key(),
            get_object_vars($this)
        );
    }

    /** 配置节点名，如 "login"、"waf" */
    abstract protected static function key(): string;

}
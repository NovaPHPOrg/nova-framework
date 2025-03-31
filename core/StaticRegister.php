<?php

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

abstract class StaticRegister
{
    public static function register(): void
    {
        $cls = get_called_class();
        $key = 'static:' . $cls;

        if (!Context::instance()->get($key, false)) {
            static::registerInfo();  // 调用抽象方法，等待子类实现
            Context::instance()->set($key, true);
        }
    }

    // 子类必须实现的方法
    public static function registerInfo(): void
    {
        throw new RuntimeException("子类必须重写registerInfo方法");
    }
}

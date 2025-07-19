<?php

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

/**
 * 静态注册器抽象类
 *
 * 这是一个模板方法模式的实现，用于管理静态注册逻辑。
 * 子类需要实现 registerInfo() 方法来定义具体的注册逻辑。
 * 该类使用 Context 来确保注册过程只执行一次。
 *
 * @package nova\framework\core
 * @author Nova Framework
 * @since 1.0.0
 */
abstract class StaticRegister
{
    /**
     * 执行静态注册
     *
     * 使用模板方法模式，确保注册逻辑只执行一次。
     * 通过 Context 实例来跟踪注册状态，避免重复注册。
     *
     * @return void
     * @throws RuntimeException 当子类未实现 registerInfo 方法时抛出
     */
    public static function register(): void
    {
        $cls = get_called_class();
        $key = 'static:' . $cls;

        if (!Context::instance()->get($key, false)) {
            static::registerInfo();  // 调用抽象方法，等待子类实现
            Context::instance()->set($key, true);
        }
    }

    /**
     * 注册信息的具体实现
     *
     * 这是一个抽象方法，子类必须重写此方法来定义具体的注册逻辑。
     * 例如：注册路由、注册服务、注册事件监听器等。
     *
     * @return void
     * @throws RuntimeException 当子类未实现此方法时抛出
     */
    public static function registerInfo(): void
    {
        throw new RuntimeException("子类必须重写registerInfo方法");
    }
}

<?php

declare(strict_types=1);

namespace nova\framework\core;

/**
 * ArgObject 类
 *
 * 这是一个参数对象基类，用于处理数组数据到对象属性的自动映射和类型转换。
 * 该类提供了以下主要功能：
 * 1. 自动将数组数据映射到对象属性
 * 2. 自动类型转换（基于属性默认值的类型）
 * 3. 数据验证支持
 * 4. 对象到数组的转换
 *
 * 使用示例：
 * ```php
 * class UserConfig extends ArgObject {
 *     public string $name = '';
 *     public int $age = 0;
 *     public bool $isActive = false;
 *
 *     public function onValidate(): void {
 *         if (empty($this->name)) {
 *             throw new \Exception('用户名不能为空');
 *         }
 *     }
 * }
 *
 * $data = ['name' => '张三', 'age' => '25', 'isActive' => '1'];
 * $config = new UserConfig($data);
 * ```
 *
 * @package nova\framework\core
 * @author Nova Framework
 * @since 1.0.0
 */
class ArgObject
{
    /**
     * 构造函数
     *
     * 初始化对象并自动映射数组数据到对象属性。
     * 支持自动类型转换和验证。
     *
     * @param array|null $item 要映射到对象属性的数组数据，如果为null则使用空数组
     *
     * @throws \Exception 当验证失败时抛出异常
     *
     * @example
     * ```php
     * $data = ['name' => '张三', 'age' => '25'];
     * $obj = new MyArgObject($data);
     * ```
     */
    public function __construct(?array $item = null)
    {
        // 当参数为null时，默认使用空数组
        $item = $item ?? [];

        // 如果数组为空，直接返回，不进行后续处理
        if (empty($item)) {
            return;
        }

        // 遍历对象的所有属性
        foreach (get_object_vars($this) as $key => $defaultValue) {
            // 检查数组中是否存在对应的键
            if (isset($item[$key])) {
                $value = $item[$key];
                // 如果类型解析通过，则赋值给对象属性
                if ($this->onParseType($key, $value, $defaultValue)) {
                    $this->$key = $value;
                }
            }
        }

        // 执行验证逻辑
        $this->onValidate();
    }

    /**
     * 类型解析回调方法
     *
     * 在构造函数初始化参数时调用，用于处理类型转换。
     * 子类可以覆盖此方法来自定义类型转换逻辑。
     *
     * @param  string $key  当前初始化的属性名
     * @param  mixed  $val  当前初始化要赋予的值（通过引用传递，可以修改）
     * @param  mixed  $demo 初始化对象的默认值，用于确定目标类型
     * @return bool   是否允许写入到对象中，返回false表示不允许
     *
     * @example
     * ```php
     * public function onParseType(string $key, mixed &$val, mixed $demo): bool {
     *     // 自定义类型转换逻辑
     *     if ($key === 'email') {
     *         $val = filter_var($val, FILTER_VALIDATE_EMAIL);
     *         return $val !== false;
     *     }
     *     return parent::onParseType($key, $val, $demo);
     * }
     * ```
     */
    public function onParseType(string $key, mixed &$val, mixed $demo): bool
    {
        // 特殊处理布尔类型
        if (is_bool($demo)) {
            // 将各种布尔值表示转换为真正的布尔值
            $val = ($val === "1" || $val === 1 || $val === "true" || $val === "on" || $val === true);
        } else {
            // 使用Text类的parseType方法进行类型转换
            $val = Text::parseType($demo, $val);
        }
        return true;
    }

    /**
     * 验证回调方法
     *
     * 在对象初始化完成后进行验证。
     * 子类可以覆盖此方法实现自定义验证逻辑。
     *
     * @return void
     *
     * @throws \Exception 当验证失败时抛出异常
     *
     * @example
     * ```php
     * public function onValidate(): void {
     *     if (empty($this->name)) {
     *         throw new \Exception('用户名不能为空');
     *     }
     *     if ($this->age < 0 || $this->age > 150) {
     *         throw new \Exception('年龄必须在0-150之间');
     *     }
     * }
     * ```
     */
    public function onValidate(): void
    {
        // 基类中为空实现，子类可覆盖
    }

    /**
     * 将对象转换为数组
     *
     * 将当前对象的所有属性转换为关联数组。
     * 支持回调处理，可以对每个属性进行自定义转换。
     *
     * @param  bool  $callback 是否对每一项进行回调处理，默认为true
     * @return array 转换后的数组
     *
     * @example
     * ```php
     * $obj = new MyArgObject(['name' => '张三', 'age' => 25]);
     * $array = $obj->toArray(); // 返回 ['name' => '张三', 'age' => 25]
     * $array = $obj->toArray(false); // 跳过回调处理
     * ```
     */
    public function toArray(bool $callback = true): array
    {
        // 获取对象的所有属性
        $ret = get_object_vars($this);

        // 如果不需要回调处理，直接返回
        if (!$callback) {
            return $ret;
        }

        // 对数组中的每个元素进行回调处理
        foreach ($ret as $key => &$value) {
            $this->onToArray($key, $value, $ret);
        }

        return $ret;
    }

    /**
     * 数组转换回调方法
     *
     * 在将对象转换为数组的过程中，对每一项进行回调处理。
     * 子类可以覆盖此方法来自定义转换逻辑。
     *
     * @param  string $key   当前的属性名
     * @param  mixed  $value 当前要转换的值（通过引用传递，可以修改）
     * @param  array  $ret   当前转换后的数组（通过引用传递，可以修改）
     * @return void
     *
     * @example
     * ```php
     * public function onToArray(string $key, mixed &$value, array &$ret): void {
     *     // 调用父类方法处理基本类型转换
     *     parent::onToArray($key, $value, $ret);
     *
     *     // 自定义转换逻辑
     *     if ($key === 'created_at' && $value instanceof \DateTime) {
     *         $value = $value->format('Y-m-d H:i:s');
     *     }
     * }
     * ```
     */
    public function onToArray(string $key, mixed &$value, array &$ret): void
    {
        // 将布尔值转换为整数（true -> 1, false -> 0）
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }
    }
}

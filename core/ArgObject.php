<?php

declare(strict_types=1);

namespace nova\framework\core;

class ArgObject
{
    /**
     * 初始化
     * @param array|null $item
     */
    public function __construct(?array $item = null)
    {
        // 当参数为null时，默认使用空数组
        $item = $item ?? [];

        if (empty($item)) {
            return;
        }

        // 遍历对象属性
        foreach (get_object_vars($this) as $key => $defaultValue) {
            if (isset($item[$key])) {
                $value = $item[$key];
                // 如果类型解析通过，则赋值给对象属性
                if ($this->onParseType($key, $value, $defaultValue)) {
                    $this->$key = $value;
                }
            }
        }

        $this->onValidate();
    }

    /**
     * 当准备进行格式化的时候，该函数会在__construct初始化参数时进行调用
     * @param string $key 当前初始化的key
     * @param mixed $val 当前初始化要赋予的值
     * @param mixed $demo 初始化对象的默认值
     * @return bool   是否允许写入到对象中，返回false是不允许
     */
    public function onParseType(string $key, mixed &$val, mixed $demo): bool
    {
        if (is_bool($demo)) {
            $val = ($val === "1" || $val === 1 || $val === "true" || $val === "on" || $val === true);
        } else {
            $val = Text::parseType($demo, $val);
        }
        return true;
    }

    /**
     * 在对象初始化完成后进行验证
     * 子类可以覆盖此方法实现验证逻辑
     */
    public function onValidate(): void
    {
        // 基类中为空实现，子类可覆盖
    }

    /**
     * 将object对象转换为数组
     * @param bool $callback 是否对每一项进行回调处理
     * @return array
     */
    public function toArray(bool $callback = true): array
    {
        $ret = get_object_vars($this);

        if (!$callback) {
            return $ret;
        }

        // 更简洁的实现方式，直接对数组进行遍历处理
        foreach ($ret as $key => &$value) {
            $this->onToArray($key, $value, $ret);
        }

        return $ret;
    }

    /**
     * 在将object对象转换为数组的过程中，对每一项进行回调
     * @param string $key 当前的key值
     * @param mixed $value 当前转为数组的值
     * @param array $ret 当前初始化后的数组
     * @return void
     */
    public function onToArray(string $key, mixed &$value, array &$ret): void
    {
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }
    }
}

<?php

namespace nova\framework\text;

class ArgObject
{
    /**
     * 初始化
     * @param array|null $item
     */
    public function __construct(?array $item = [])
    {
        if (!empty($item)) {
            foreach (get_object_vars($this) as $key => $val) {
                if(isset($item[$key])){
                    $data = $item[$key];
                    $data = Text::parseType($val, $data);
                    if ($this->onParseType($key, $data, $val)) {
                        if (gettype($val) === gettype($data)) {
                            $this->$key = $data;
                        }
                    }

                }
            }
        }
    }

    /**
     * 当准备进行格式化的时候，该函数会在__construct初始化参数时进行调用
     * @param string $key 当前初始化的key
     * @param mixed $val 当前初始化要赋予的值
     * @param mixed $demo 初始化对象的默认值
     * @return bool 是否允许写入到对象中，返回false是不允许
     */
    public function onParseType(string $key, mixed &$val, mixed $demo): bool
    {
        if (is_bool($demo)) {
            $val = ($val === "1" || $val === 1 || $val === "true" || $val === "on" || $val === true);
        }
        $this->onValidate();
        return true;
    }

    public function onValidate(): void
    {

    }


    /**
     * 将object对象转换为数组
     * @param bool $callback 是否对每一项进行回调
     * @return array
     */
    public function toArray(bool $callback = true): array
    {
        $ret = get_object_vars($this);
        if (!$callback) return $ret;
        array_walk($ret, function (&$value, $key, $arr) {
            $this->onToArray($key, $value, $arr['ret']);
        }, ['ret' => &$ret]);
        return $ret;
    }

    /**
     * 在将object对象转换为数组的过程中，对每一项进行回调
     * @param $key string  当前的key值
     * @param $value mixed 当前转为数组的值
     * @param $ret [] 当前初始化后的数组
     * @return void
     */
    public function onToArray(string $key, mixed &$value, &$ret): void
    {
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }
    }

}
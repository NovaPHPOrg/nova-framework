<?php

namespace nova\framework\text;

class Text
{
    /**
     * 字符串转换utf-8
     * @param $text
     * @param string $encode_code
     * @return string
     */
    static function convert($text, string $encode_code = "UTF-8"): string
    {
        $encode = mb_detect_encoding($text, mb_detect_order());
        if ($encode !== $encode_code)
            $text = mb_convert_encoding($text, $encode_code, $encode);
        return $text;
    }


    /**
     * 传入样例类型，对目标进行类型转换
     * @param $sample mixed 样例
     * @param $data  mixed 需要转换的类型
     * @return bool|float|int|mixed|string
     */
static function parseType(mixed $sample, mixed $data): mixed
    {
        if (is_array($data)) return $data;
        elseif (is_int($sample)) {
            if (is_numeric($data)) return intval($data);
            return $sample;
        }
        elseif (is_string($sample)) return strval($data);
        elseif (is_bool($sample)) return boolval($data);
        elseif (is_float($sample)) return floatval($data);
        elseif (is_double($sample)) return doubleval($data);
        return $data;
    }
}
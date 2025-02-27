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

/**
 * Text 类
 * 提供文本处理和类型转换的工具类
 */
class Text
{
    /**
     * 将字符串转换为指定编码（默认UTF-8）
     * 
     * @param mixed $text 需要转换的文本
     * @param string $encode_code 目标编码，默认为"UTF-8"
     * @return string 转换后的文本
     * 
     * @example
     * Text::convert("你好", "UTF-8");
     */
    static function convert($text, string $encode_code = "UTF-8"): string
    {
        $encode = mb_detect_encoding($text, mb_detect_order());
        if ($encode !== $encode_code)
            $text = mb_convert_encoding($text, $encode_code, $encode);
        return $text;
    }

    /**
     * 根据样例值的类型，将数据转换为相应的类型
     * 如果转换失败，将返回样例值
     * 
     * @param mixed $sample 样例值，用于确定目标类型
     * @param mixed $data 需要转换的数据
     * @return mixed 转换后的数据，转换失败时返回样例值
     * 
     * @example
     * Text::parseType(123, "456"); // 返回整数 456
     * Text::parseType("hello", 123); // 返回字符串 "123"
     * Text::parseType(true, 1); // 返回布尔值 true
     * Text::parseType(1.23, "4.56"); // 返回浮点数 4.56
     * Text::parseType(null, "test"); // 返回 null
     * Text::parseType(123, "abc"); // 返回整数 123（转换失败返回样例值）
     * 
     * @note 如果传入的数据是数组，则直接返回数组不做转换
     */
    static function parseType(mixed $sample, mixed $data): mixed
    {
        // 处理特殊情况
        if ($data === null || $sample === null) return null;
        if (is_array($data)) return $data;

        // 根据样例类型进行转换
        return match(gettype($sample)) {
            'integer' => is_numeric($data) ? intval($data) : $sample,
            'string' => strval($data),
            'boolean' => filter_var($data, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $sample,
            'double', 'float' => is_numeric($data) ? floatval($data) : $sample,
            'NULL' => null,
            default => $data
        };
    }
}
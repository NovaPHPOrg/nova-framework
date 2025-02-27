<?php
/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\json;

use JsonException;

/**
 * JSON 数据处理工具类
 * 提供 JSON 编码解码及相关工具方法
 */
class Json
{
    /**
     * 将 JSON 字符串解码为 PHP 数据
     *
     * @param string $string 需要解码的 JSON 字符串
     * @param bool $isArray 是否将对象解码为关联数组
     * @return mixed 解码后的 PHP 数据
     * @throws JsonDecodeException 当解码失败时抛出异常
     */
    public static function decode(string $string, bool $isArray = false): mixed
    {
        try {
            return json_decode(self::removeUtf8Bom($string), $isArray, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonDecodeException($e->getMessage(), $string, $e->getCode(), $e);
        }
    }

    /**
     * 移除字符串开头的 UTF-8 BOM 标记
     *
     * @param string $text 需要处理的文本
     * @return string 处理后的文本
     */
    public static function removeUtf8Bom(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }

    /**
     * 将 PHP 数组编码为 JSON 字符串
     *
     * @param array $array 需要编码的 PHP 数组
     * @param bool $unicode 是否编码 Unicode 字符（true 则不编码，false 则编码）
     * @return string 编码后的 JSON 字符串
     * @throws JsonEncodeException 当编码失败时抛出异常
     */
    public static function encode(array $array, bool $unicode = false): string
    {
        $options = $unicode ? JSON_UNESCAPED_UNICODE : JSON_PARTIAL_OUTPUT_ON_ERROR;
        
        $result = json_encode($array, $options);
        if ($result === false) {
            throw new JsonEncodeException(json_last_error_msg(), $array);
        }
        return $result;
    }
}
<?php

namespace nova\framework\text;

class Json
{
    /**
     * @param string $string  需要解码的字符串
     * @param bool $isArray 是否解码为数组
     * @return mixed
     * @throws JsonDecodeException
     */
    static function decode(string $string, bool $isArray = false): mixed
    {
        try {
            return json_decode(self::removeUtf8Bom($string), $isArray, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonDecodeException($e->getMessage(),$string,$e->getCode(),$e);
        }
    }

    static function removeUtf8Bom($text): string
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }

    /**
     * @param array $array string 需要编码的字符串
     * @param bool $unicode 是否编码unicode字符
     * @return string
     * @throws JsonEncodeException
     */
    static function encode(array $array, bool $unicode = false): string
    {
        $result = json_encode($array, $unicode?JSON_UNESCAPED_UNICODE:JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($result === false) {
            throw new JsonEncodeException(json_last_error_msg(),$array);
        }
        return $result;
    }

}
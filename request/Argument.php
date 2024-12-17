<?php
declare(strict_types=1);

namespace nova\framework\request;

use nova\framework\text\Json;
use nova\framework\text\JsonDecodeException;
use nova\framework\text\Text;

class Argument
{
    /**
     * 从get参数中获取
     * @param ?string $key
     * @param mixed|null $default
     * @return bool|float|int|mixed|string|null
     */
    static function get(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $_GET;
        if (isset($_GET[$key])) {
            return Text::parseType($default, $_GET[$key]);
        }
        return $default;
    }

    /**
     * 从post参数中获取
     * @param ?string $key
     * @param mixed|null $default
     * @return bool|float|int|mixed|string|null
     */
    static function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $_POST;
        if (isset($_POST[$key])) {
            return Text::parseType($default, $_POST[$key]);
        }
        return $default;
    }

    /**
     * 所有参数
     * @param ?string $key
     * @param mixed|null $default
     * @return bool|float|int|mixed|string|null
     */
    static function arg(string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($_POST, $_GET);
        if ($key === null) return $all;
        if (isset($all[$key])) {
            return Text::parseType($default, $all[$key]);
        }
        return $default;
    }

    /**
     * 获取json数组
     * @return array
     */
    static function json(): array
    {
        $raw = self::raw();
        if ($raw === null) return [];
        try {
            return Json::decode($raw, true);
        } catch (JsonDecodeException $e) {
            return [];
        }
    }

    /**
     * 获取原始数据
     * @return ?string
     */
    static function raw(): ?string
    {
        return file_get_contents("php://input") ?? null;
    }
}
<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace nova\framework\http;

use nova\framework\core\Text;
use nova\framework\json\Json;
use nova\framework\json\JsonDecodeException;

class Arguments
{
    /**
     * 从get参数中获取
     * @param  ?string $key
     * @param mixed|null $default
     * @return bool|float|int|mixed|string|null
     */
    public static function get(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        if (isset($_GET[$key])) {
            return Text::parseType($default, $_GET[$key]);
        }
        return $default;
    }

    /**
     * 从post参数中获取
     * @param  ?string $key
     * @param mixed|null $default
     * @return bool|float|int|mixed|string|null
     */
    public static function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        if (isset($_POST[$key])) {
            return Text::parseType($default, $_POST[$key]);
        }
        return $default;
    }

    /**
     * 所有参数
     * @param  ?string $key
     * @param mixed|null $default
     * @return bool|float|int|mixed|string|null
     */
    public static function arg(string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($_POST, $_GET);
        if ($key === null) {
            return $all;
        }
        if (isset($all[$key])) {
            return Text::parseType($default, $all[$key]);
        }
        return $default;
    }

    /**
     * 获取json数组
     * @return array
     */
    public static function json(): array
    {
        $raw = self::raw();
        if ($raw === null) {
            return [];
        }
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
    public static function raw(): ?string
    {
        return file_get_contents("php://input") ?? null;
    }
}

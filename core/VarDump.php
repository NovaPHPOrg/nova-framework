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

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

/**
 * VarDump 类 - 变量调试输出工具
 *
 * 该类提供了一个强大的变量调试输出工具，支持以下功能：
 * - 支持多种数据类型的格式化输出
 * - 支持对象和数组的递归输出
 * - 支持循环引用检测
 * - 支持 HTML/纯文本格式化输出
 * - 支持回调函数的输出
 * - 支持最大递归深度限制
 */
class VarDump
{
    /**
     * HTML 样式定义
     * 为不同类型的输出定义颜色样式
     */
    private const array STYLES = [
        'object' => 'color: #333;',                    // 对象类型样式
        'string' => 'color: #cc0000',                  // 字符串类型样式
        'reference' => 'color: #333;font-weight: bold', // 引用类型样式
        'null' => 'color: #3465a4',                    // null 类型样式
        'boolean' => 'color: #75507b',                 // 布尔类型样式
        'integer' => 'color: #4e9a06',                 // 整数类型样式
        'float' => 'color: #f57900',                   // 浮点数类型样式
        'array' => 'color: #333',                      // 数组类型样式
        'empty' => 'color: #888a85',                   // 空值样式
        'resource' => 'color: #3465a4',                // 资源类型样式
        'unknown' => 'color: #3465a4'                  // 未知类型样式
    ];

    /**
     * 最大递归深度
     * 防止无限递归导致的内存溢出
     */
    private const int MAX_DEPTH = 10;

    /**
     * @var int 对象ID计数器
     */
    private static int $objId = 1;

    /**
     * @var array 缩进填充数组
     */
    private static array $pads = [];

    /**
     * @var string 输出缓冲
     */
    private string $output = "";

    /**
     * @var array 已访问的变量数组，用于检测循环引用
     */
    private array $vars = [];

    /**
     * @var bool 是否启用HTML输出
     */
    private bool $htmlOutput;

    /**
     * @var int 当前递归深度
     */
    private int $currentDepth = 0;

    /**
     * 构造函数
     *
     * @param bool $htmlOutput 是否启用HTML输出格式
     */
    public function __construct(bool $htmlOutput = true)
    {
        $this->htmlOutput = $htmlOutput;
    }

    /**
     * 输出回调函数
     * 使用反射获取并格式化回调函数的代码
     *
     * @param callable $func 要输出的回调函数
     */
    public function dumpCallback(callable $func): void
    {
        try {
            $refFunc = new ReflectionFunction($func);
            $start = $refFunc->getStartLine() - 1;
            $end = $refFunc->getEndLine() - 1;
            $filename = $refFunc->getFileName();
            $lines = $filename ? file($filename) : [];
            $fun = implode("", array_slice($lines, $start, $end - $start + 1));
            $count = $end - $start;
        } catch (ReflectionException $e) {
            $fun = "Dump Error: " . $e->getMessage();
            $count = 0;
        }

        $this->output .= sprintf(
            "%s '%s' (length=%d)",
            $this->format('reference', 'callback'),
            $this->format('string', $fun),
            $count
        );
    }

    /**
     * 格式化输出
     * 根据配置返回HTML格式或纯文本格式的输出
     *
     * @param string $type 变量类型
     * @param string $content 内容
     * @param array $attributes 额外的HTML属性
     * @return string 格式化后的字符串
     */
    private function format(string $type, string $content, array $attributes = []): string
    {
        if (!$this->htmlOutput) {
            return $content;
        }

        $style = self::STYLES[$type] ?? '';
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        return sprintf('<span style="%s">%s</span>', $style, $content);
    }

    /**
     * 输出变量类型为字符串
     * @param mixed $param
     * @return string
     */
    public function dumpTypeAsString(mixed $param): string
    {
        ob_start();
        var_dump($param);
        $result = ob_get_clean();
        return (string)$result;
    }

    /**
     * 将变量转为JSON格式输出
     * 支持递归处理对象和数组
     *
     * @param mixed $param 要转换的变量
     * @return array JSON格式的数组
     */
    public function dumpTypeToJson(mixed $param): array
    {
        if ($this->currentDepth >= self::MAX_DEPTH) {
            return ['MAX_DEPTH_REACHED'];
        }

        if (!is_iterable($param)) {
            return [];
        }

        $this->currentDepth++;
        $data = [];

        foreach ($param as $key => $value) {
            $data[$key] = match (true) {
                is_object($value) => $this->handleObject($value),
                is_array($value) => $this->handleArray($value),
                default => $value
            };
        }

        $this->currentDepth--;
        return $data;
    }

    /**
     * 处理对象类型
     * 检测循环引用并格式化对象
     *
     * @param object $value 要处理的对象
     * @return array|string 处理结果
     */
    private function handleObject(object $value): array|string
    {
        $hash = spl_object_hash($value);
        if (in_array($hash, $this->vars, true)) {
            return "reference &" . get_class($value);
        }

        $this->vars[] = $hash;
        return $this->processObject($value);
    }

    /**
     * 处理对象属性
     * 使用反射获取对象的所有属性
     *
     * @param object $value 要处理的对象
     * @return array  对象属性数组
     */
    private function processObject(object $value): array
    {
        if (get_class($value) === 'stdClass') {
            return (array)$value;
        }

        try {
            $reflect = new ReflectionClass($value);
            $properties = $reflect->getProperties();
            $array = ["Class [" . get_class($value) . "]"];

            foreach ($properties as $property) {
                $property->setAccessible(true);
                $propertyName = $property->getName();
                $array[$propertyName] = $this->dumpTypeToJson($property->getValue($value));
            }

            return $array;
        } catch (ReflectionException $e) {
            return ["Error: " . $e->getMessage()];
        }
    }

    /**
     * 处理数组类型
     * 检测循环引用并格式化数组
     *
     * @param array $value 要处理的数组
     * @return array|string 处理结果
     */
    private function handleArray(array $value): array|string
    {
        if (in_array($value, $this->vars, true)) {
            return "reference &array";
        }

        $this->vars[] = $value;
        return $this->dumpTypeToJson($value);
    }

    /**
     * 自动选择类型输出
     * 根据变量类型选择合适的格式化方法
     *
     * @param mixed $param 要输出的变量
     * @param int $indentLevel 缩进级别
     * @return string 格式化后的字符串
     */
    public function dumpType(mixed $param, int $indentLevel = 0): string
    {
        if ($this->currentDepth >= self::MAX_DEPTH) {
            return $this->format('reference', 'MAX_DEPTH_REACHED');
        }

        $this->currentDepth++;
        $this->handleReference($param);

        $output = match (gettype($param)) {
            'NULL' => $this->format('null', 'null'),
            'boolean' => $this->formatBoolean($param),
            'integer' => $this->formatInteger($param),
            'double' => $this->formatFloat($param),
            'string' => $this->formatString($param),
            'array' => $this->formatArray($param, $indentLevel),
            'object' => $this->formatObject($param, $indentLevel),
            'resource' => $this->format('resource', 'resource'),
            default => $this->format('unknown', 'unknown type')
        };

        $this->currentDepth--;
        return $output;
    }

    /**
     * 处理引用类型
     * 检测并处理循环引用
     *
     * @param mixed $param 要处理的变量
     */
    private function handleReference(mixed $param): void
    {
        if (is_object($param)) {
            $hash = spl_object_hash($param);
            if (!empty($param) && in_array($hash, $this->vars, true)) {
                $this->output .= $this->format('reference', 'reference &' . get_class($param));
                return;
            }
            $this->vars[] = $hash;
        } elseif (is_array($param)) {
            if (!empty($param) && in_array($param, $this->vars, true)) {
                $this->output .= $this->format('reference', 'reference &array');
                return;
            }
            $this->vars[] = $param;
        }
    }

    /**
     * 格式化布尔值
     *
     * @param bool $param 布尔值
     * @return string 格式化后的字符串
     */
    private function formatBoolean(bool $param): string
    {
        return sprintf(
            "%s %s",
            $this->format('reference', 'boolean'),
            $this->format('boolean', $param ? 'true' : 'false')
        );
    }

    /**
     * 格式化整数
     *
     * @param int $param 整数值
     * @return string 格式化后的字符串
     */
    private function formatInteger(int $param): string
    {
        return sprintf(
            "%s %s",
            $this->format('reference', 'int'),
            $this->format('integer', (string)$param)
        );
    }

    /**
     * 格式化浮点数
     *
     * @param float $param 浮点数值
     * @return string 格式化后的字符串
     */
    private function formatFloat(float $param): string
    {
        return sprintf(
            "%s %s",
            $this->format('reference', 'float'),
            $this->format('float', (string)$param)
        );
    }

    /**
     * 格式化字符串
     *
     * @param string $param 字符串值
     * @return string 格式化后的字符串
     */
    private function formatString(string $param): string
    {
        return sprintf(
            "%s '%s' (length=%d)",
            $this->format('reference', 'string'),
            $this->format('string', $param),
            strlen($param)
        );
    }

    /**
     * 格式化数组
     * 递归处理数组元素
     *
     * @param array $param 数组值
     * @param int $indentLevel 缩进级别
     * @return string 格式化后的字符串
     */
    private function formatArray(array $param, int $indentLevel): string
    {
        $len = count($param);
        $space = str_repeat("    ", $indentLevel);
        $output = sprintf(
            "%s (size=%d)\n",
            $this->format('array', 'array'),
            $len
        );

        if ($len === 0) {
            return $output . $space . "  " . $this->format('empty', 'empty') . "\n";
        }

        foreach ($param as $key => $val) {
            $output .= sprintf(
                "%s %s => ",
                $space,
                $this->format('array', (string)$key)
            );
            $output .= $this->dumpType($val, $indentLevel + 1) . "\n";
        }

        return $output;
    }

    /**
     * 格式化对象
     * 处理标准对象和自定义对象
     *
     * @param object $param 对象值
     * @param int $indentLevel 缩进级别
     * @return string 格式化后的字符串
     */
    private function formatObject(object $param, int $indentLevel): string
    {
        $className = get_class($param);
        if ($className === 'stdClass' && $result = json_encode($param)) {
            return $this->formatArray(json_decode($result, true), $indentLevel);
        }

        $output = sprintf(
            "%s %s",
            $this->format('reference', 'Object'),
            $this->format('object', $className)
        );

        self::$objId++;
        return $output . $this->formatProperties($param, self::$objId);
    }

    /**
     * 格式化对象属性
     *
     * @param object $obj 对象
     * @param int $num 对象ID
     * @return string 格式化后的字符串
     */
    private function formatProperties(object $obj, int $num): string
    {
        $prop = get_object_vars($obj);
        $len = count($prop);
        $output = sprintf(" (size=%d)", $len);

        self::$pads[] = "     ";
        foreach ($prop as $key => $value) {
            $output .= sprintf(
                "\n%s %s => ",
                implode('', self::$pads),
                $this->format('object', $key)
            );
            $output .= $this->dumpType($value, $num);
        }
        array_pop(self::$pads);

        return $output;
    }

    /**
     * 析构函数
     * 清理资源并重置状态
     */
    public function __destruct()
    {
        $this->vars = [];
        $this->output = '';
        $this->currentDepth = 0;
    }
}

<?php
declare(strict_types=1);

namespace nova\framework\log;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

class VarDump
{
    private static int $objId = 1;
    private static array $pads = [];
    private string $output = "";
    private array $vars = [];

    /**
     * 输出回调函数
     * @param callable $func
     * @return void
     */
    public function dumpCallback(callable $func): void
    {
        try {
            $refFunc = new ReflectionFunction($func);
            $start = $refFunc->getStartLine() - 1;
            $end = $refFunc->getEndLine() - 1;
            $filename = $refFunc->getFileName();
            $fun = implode("", array_slice(file($filename), $start, $end - $start + 1));
            $count = $end - $start;
        } catch (ReflectionException $e) {
            $fun = "Dump Error: " . $e->getMessage();
            $count = 0;
        }
        $str = sprintf(
            "<small style='color: #333;font-weight: bold'>callback</small> <i style='color:#cc0000'>'%s'</i> <i>(length=%d)</i>",
            htmlspecialchars($fun, ENT_QUOTES, 'UTF-8'),
            $count
        );
        $this->output .= $str;
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
     * @param mixed $param
     * @return array
     */
    public function dumpTypeToJson(mixed $param): array
    {
        $data = [];
        if (!is_iterable($param)) {
            return $data;
        }

        foreach ($param as $key => $value) {
            if (is_object($value)) {
                $hash = spl_object_hash($value);
                if (in_array($hash, $this->vars, true)) {
                    $data[$key] = "reference &" . get_class($value);
                } else {
                    $this->vars[] = $hash;
                    $className = get_class($value);
                    if ($className !== 'stdClass') {
                        try {
                            $reflect = new ReflectionClass($value);
                            $properties = $reflect->getProperties();
                            $array = ["Class [$className]"];
                            foreach ($properties as $property) {
                                $property->setAccessible(true);
                                $propertyName = $property->getName();
                                $array[$propertyName] = $this->dumpTypeToJson($property->getValue($value));
                            }
                            $data[$key] = $array;
                        } catch (ReflectionException $e) {
                            $data[$key] = $e->getMessage();
                        }
                    } else {
                        foreach ($value as $item => $v) {
                            $data[$key][$item] = $this->dumpTypeToJson($v);
                        }
                    }
                }
            } elseif (is_array($value)) {
                if (in_array($value, $this->vars, true)) {
                    $data[$key] = "reference &array";
                } else {
                    $this->vars[] = $value;
                    foreach ($value as $item => $v) {
                        $data[$key][$item] = $this->dumpTypeToJson($v);
                    }
                }
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function __destruct()
    {
        unset($this->vars);
    }

    /**
     * 输出对象
     * @param object $param
     * @param int $indentLevel
     * @return void
     */
    private function dumpObject(object $param, int $indentLevel = 0): void
    {
        $className = get_class($param);
        if ($className === 'stdClass' && $result = json_encode($param)) {
            $this->dumpArray(json_decode($result, true), $indentLevel);
            return;
        }

        $this->output .= "<b style='color: #333;'>Object</b> <i style='color: #333;'>$className</i>";
        self::$objId++;
        $this->dumpProperties($param, self::$objId);
    }

    /**
     * 输出数组
     * @param array $param
     * @param int $indentLevel
     * @return void
     */
    private function dumpArray(array $param, int $indentLevel = 0): void
    {
        $len = count($param);
        $space = str_repeat("    ", $indentLevel);
        $indentLevel++;
        $this->output .= "<b style='color: #333;'>array</b> <i style='color: #333;'>(size=$len)</i> \r\n";
        if ($len === 0) {
            $this->output .= $space . "  <i  style='color: #888a85;'>empty</i> \r\n";
        } else {
            foreach ($param as $key => $val) {
                $str = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
                $this->output .= $space . "<i style='color: #333;'> $str </i><i style='color: #888a85;'>=&gt;";
                $this->dumpType($val, $indentLevel);
                $this->output .= "</i> \r\n";
            }
        }
    }

    /**
     * 自动选择类型输出
     * @param mixed $param
     * @param int $indentLevel
     * @return string
     */
    public function dumpType(mixed $param, int $indentLevel = 0): string
    {
        if (is_object($param)) {
            $hash = spl_object_hash($param);
            if (!empty($param) && in_array($hash, $this->vars, true)) {
                $this->output .= '<small style="color: #333;font-weight: bold">reference</small> <span style="color:#75507b">&' . get_class($param) . "</span>";
                return $this->output;
            }
            $this->vars[] = $hash;
        } elseif (is_array($param)) {
            if (!empty($param) && in_array($param, $this->vars, true)) {
                $this->output .= '<small style="color: #333;font-weight: bold">reference</small> <span style="color:#75507b">&array</span>';
                return $this->output;
            }
            $this->vars[] = $param;
        }

        switch (gettype($param)) {
            case 'NULL':
                $this->output .= '<span style="color: #3465a4">null</span>';
                break;
            case 'boolean':
                $this->output .= '<small style="color: #333;font-weight: bold">boolean</small> <span style="color:#75507b">' . ($param ? 'true' : 'false') . "</span>";
                break;
            case 'integer':
                $this->output .= "<small style='color: #333;font-weight: bold'>int</small> <i style='color:#4e9a06'>$param</i>";
                break;
            case 'double':
                $this->output .= "<small style='color: #333;font-weight: bold'>float</small> <i style='color:#f57900'>$param</i>";
                break;
            case 'string':
                $this->dumpString($param);
                break;
            case 'array':
                $this->dumpArray($param, $indentLevel);
                break;
            case 'object':
                $this->dumpObject($param, $indentLevel);
                break;
            case 'resource':
                $this->output .= '<i style=\'color:#3465a4\'>resource</i>';
                break;
            default:
                $this->output .= '<i style=\'color:#3465a4\'>unknown type</i>';
                break;
        }

        return $this->output;
    }

    /**
     * 输出字符串
     * @param string $param
     * @return void
     */
    private function dumpString(string $param): void
    {
        $str = sprintf(
            "<small style='color: #333;font-weight: bold'>string</small> <i style='color:#cc0000'>'%s'</i> <i>(length=%d)</i>",
            htmlspecialchars($param, ENT_QUOTES, 'UTF-8'),
            strlen($param)
        );
        $this->output .= $str;
    }

    /**
     * 输出类对象属性
     * @param object $obj
     * @param int $num
     * @return void
     */
    private function dumpProperties(object $obj, int $num): void
    {
        $prop = get_object_vars($obj);
        $len = count($prop);
        $this->output .= "<i style='color: #333;'> (size=$len)</i>";
        self::$pads[] = "     ";
        foreach ($prop as $key => $value) {
            $this->output .= "\n" . implode('', self::$pads) . sprintf("<i style='color: #333;'> %s </i><i style='color:#888a85'>=&gt;&nbsp;", $key);
            $this->dumpType($value, $num);
        }
        array_pop(self::$pads);
    }
}
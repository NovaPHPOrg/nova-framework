<?php

declare(strict_types=1);

namespace nova\framework\route;

use nova\framework\core\Logger;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use ReflectionException;
use ReflectionMethod;

/**
 * 抽象路由对象
 *
 * 插件路由对象基类，支持自定义控制器命名空间
 *
 * 使用示例：
 * ```php
 * class PluginRouter extends AbstractRouteObject
 * {
 *     protected string $prefix = '/plugin';
 *
 *     public function getControllerClass(): string
 *     {
 *         return "plugin\\myplugin\\controller\\{$this->controller}";
 *     }
 *
 *     public function run(): void
 *     {
 *         // 插件自定义的 run 逻辑
 *     }
 * }
 * ```
 */
abstract class AbstractRouteObject
{
    /** @var string 模块名称 */
    public string $module = "";

    /** @var string 控制器名称 */
    public string $controller = "";

    /** @var string 动作方法名称 */
    public string $action = "";

    /** @var array 路由参数数组 */
    public array $params = [];

    /** @var string 控制器命名空间前缀，子类覆盖此值即可 */
    protected string $controllerNamespace = '';

    /**
     * 构造函数
     *
     * @param string $module     模块名称
     * @param string $controller 控制器名称
     * @param string $action     动作方法名称
     * @param array  $params     路由参数
     */
    public function __construct(string $module = "", string $controller = "", string $action = "", array $params = [])
    {
        $this->module = $module;
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }

    /**
     * 获取控制器完整类名
     *
     * 由 {@see $controllerNamespace} 前缀拼接控制器名得到。
     *
     * @return string 控制器完整类名
     */
    protected function getControllerClass(): string
    {
        return $this->controllerNamespace . ucfirst($this->controller);
    }

    /**
     * 更新路由参数
     */
    public function updateParams(array $params): void
    {
        $vars = get_object_vars($this);
        foreach ($params as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            foreach ($vars as $var => $val) {
                if ("{{$key}}" == $val) {
                    $this->$var = urldecode($value);
                }
            }
            if ($key != "module" && $key != "controller" && $key != "action") {
                $this->params[$key] = urldecode($value);
            }
        }
    }

    /**
     * 检查路由配置的有效性
     *
     * 插件可以覆盖此方法实现自定义检查逻辑
     */

    /**
     * 检查路由配置的有效性
     * 验证控制器类是否存在、是否继承自基础控制器类
     * 验证动作方法是否存在、返回类型是否正确、参数数量是否匹配
     *
     * @throws ControllerException 当控制器或动作方法配置无效时抛出异常
     */
    public function checkSelf(): void
    {
        $controllerClazz = $this->getControllerClass();

        // 检查控制器是否存在且继承自Controller
        if (!class_exists($controllerClazz)) {
            throw new ControllerException("Controller not found: $controllerClazz", $this);
        }
        if (!is_subclass_of($controllerClazz, Controller::class)) {
            throw new ControllerException("Controller must extends Controller: $controllerClazz", $this);
        }

        try {
            $reflection = new ReflectionMethod($controllerClazz, $this->action);

            // 检查返回类型
            $returnType = $reflection->getReturnType();
            if ($returnType == null || $returnType->getName() != Response::class) {
                throw new ControllerException("Action return type must be Response: $controllerClazz::{$this->action}", $this);
            }

            // 只检查参数数量
            $parameters = $reflection->getParameters();
            $requiredParamCount = 0;

            // 计算必需的参数数量(没有默认值的参数)
            foreach ($parameters as $param) {
                if (!$param->isOptional()) {
                    $requiredParamCount++;
                }
            }

            $paramCountReceived = count($this->params);
            $totalParamCount = count($parameters);

            // 检查参数数量:
            // 1. 接收的参数数量不能少于必需参数数量
            // 2. 接收的参数数量不能超过总参数数量
            if ($paramCountReceived < $requiredParamCount || $paramCountReceived > $totalParamCount) {
                throw new ControllerException(
                    "Parameter count mismatch: Need minimum $requiredParamCount params (total $totalParamCount), " .
                    "but got $paramCountReceived params for action: $controllerClazz::{$this->action}",
                    $this
                );
            }
        } catch (ReflectionException $e) {
            throw new ControllerException("Action not found: $controllerClazz::{$this->action}", $this);
        }

        Logger::debug(sprintf(
            'Controller check: %s::%s',
            $controllerClazz,
            $this->action,
        ));
    }

    /**
     * 将路由对象转换为字符串
     */
    public function __toString(): string
    {
        return $this->module . "/" . $this->controller . "/" . $this->action;
    }

    /**
     * 执行路由对应的控制器动作
     *
     * 默认实现：使用 getControllerClass() 获取控制器类并执行 action
     * 插件可以覆盖此方法实现自定义执行逻辑
     *
     * @throws AppExitException
     */
    public function run(): void
    {
        $controllerClazz = $this->getControllerClass();

        Logger::debug(sprintf(
            'Controller run: %s::%s params=%d',
            $controllerClazz,
            $this->action,
            count($this->params),
        ));

        $controller = new $controllerClazz();
        $init = $controller->init();
        if ($init instanceof Response) {
            Logger::debug(sprintf(
                'Controller init response: %s',
                $controllerClazz,
            ));
            throw new AppExitException($init);
        }
        $response = $controller->{$this->action}(...$this->params);
        Logger::debug(sprintf(
            'Controller done: %s::%s code=%d',
            $controllerClazz,
            $this->action,
            $response->code(),
        ));
        throw new AppExitException($response);
    }
}

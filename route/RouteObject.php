<?php
declare(strict_types=1);

namespace nova\framework\route;


use nova\framework\core\Logger;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use ReflectionException;
use ReflectionMethod;

/**
 * 路由对象类
 * 用于存储和处理单个路由的详细信息，包括模块、控制器、动作和参数
 */
class RouteObject
{
    /** @var string 模块名称 */
    public string $module;
    
    /** @var string 控制器名称 */
    public string $controller;
    
    /** @var string 动作方法名称 */
    public string $action;
    
    /** @var array 路由参数数组 */
    public array $params;

    /**
     * 构造函数
     * 
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param string $action 动作方法名称
     * @param array $params 路由参数
     */
    public function __construct(string $module = "", string $controller = "", string $action = "", array $params = [])
    {
        $this->module = $module;
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }

    /**
     * 更新路由参数
     * 支持使用占位符更新模块、控制器、动作名称，以及添加新的参数
     * 
     * @param array $params 要更新的参数数组
     */
    public function updateParams(array $params): void
    {
        $vars = get_object_vars($this);
        foreach ($params as $key => $value) {
            if(!is_string($key))continue;
          foreach ($vars as $var => $val) {
              if("{{$key}}" == $val){
                  $this->$var = $value;
              }
          }
          if ($key != "module" && $key != "controller" && $key != "action"){
              $this->params[$key] = $value;
          }
        }
    }

    /**
     * 检查路由配置的有效性
     * 验证控制器类是否存在、是否继承自基础控制器类
     * 验证动作方法是否存在、返回类型是否正确、参数数量是否匹配
     * 
     * @throws ControllerException 当控制器或动作方法配置无效时抛出异常
     */
    public function checkSelf(): void
    {
        $controllerName = ucfirst($this->controller);
        $controllerClazz = "app\\controller\\{$this->module}\\{$controllerName}";

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
            if ($returnType == null || $returnType->getName() != "nova\\framework\\request\\Response") {
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
    }

    /**
     * 将路由对象转换为字符串
     * 格式为：模块/控制器/动作
     * 
     * @return string 路由字符串表示
     */
    public function __toString(): string
    {
        return $this->module . "/" . $this->controller . "/" . $this->action;
    }

    /**
     * 执行路由对应的控制器动作
     * 创建控制器实例，执行初始化方法和目标动作方法
     *
     * @throws AppExitException 包含响应对象的异常，用于终止应用程序执行
     */
    public function run(): void
    {
        $controllerName = ucfirst( $this->controller);
        $controllerClazz = "app\\controller\\{$this->module}\\{$controllerName}";
        $controller = new $controllerClazz();
        $init = $controller->init();
        Logger::debug("Route Method: $controllerClazz::{$this->action}");
        if ($init instanceof Response){
            throw new AppExitException($init);
        }
        $response = $controller->{$this->action}(...$this->params);
        throw new AppExitException($response);
    }


}
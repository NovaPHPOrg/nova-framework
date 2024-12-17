<?php
declare(strict_types=1);

namespace nova\framework\request;


use nova\framework\exception\AppExitException;
use nova\framework\log\Logger;
use ReflectionException;
use ReflectionMethod;

class RouteObject
{
    public string $module;
    public string $controller;
    public string $action;
    public array $params;

    public function __construct($module = "", $controller = "", $action = "",$params = [])
    {
        $this->module = $module;
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }

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
     * @throws ControllerException
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

    public function __toString(): string
    {
        return $this->module . "/" . $this->controller . "/" . $this->action;
    }

    /**
     * @throws AppExitException
     */
    public function run(Request $request): void
    {
        $controllerName = ucfirst( $this->controller);
        $controllerClazz = "app\\controller\\{$this->module}\\{$controllerName}";
        $controller = new $controllerClazz($request);
        $init = $controller->init();
        Logger::info("Route Method: $controllerClazz::{$this->action}");
        if ($init instanceof Response){
            throw new AppExitException($init);
        }
        $response = $controller->{$this->action}(...$this->params);
        throw new AppExitException($response);
    }


}
<?php

namespace nova\framework\request;


use nova\framework\exception\AppExitException;

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
        $controllerName = ucfirst( $this->controller);
        $controllerClazz = "app\\controller\\{$this->module}\\{$controllerName}";
        //还要检查$controllerClazz是否为Controller的子类
        if (!class_exists($controllerClazz)) {
           throw new ControllerException("Controller not found: $controllerClazz", $this);
        }
        if (!is_subclass_of($controllerClazz, Controller::class)) {
            throw new ControllerException("Controller must extends Controller: $controllerClazz", $this);
        }
        if(!method_exists($controllerClazz, $this->action)){
            throw new ControllerException("Action not found: $controllerClazz::{$this->action}",$this);
        }
        //检查函数返回类型是否为Response
        try {
            $reflection = new \ReflectionMethod($controllerClazz, $this->action);
            $returnType = $reflection->getReturnType();
            if ($returnType == null || $returnType->getName() != "nova\\framework\\request\\Response") {
                throw new ControllerException("Action return type must be Response: $controllerClazz::{$this->action}",$this);
            }
        }catch (\ReflectionException $e){
            throw new ControllerException("Action not found: $controllerClazz::{$this->action}",$this);
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
        if ($init instanceof Response){
            throw new AppExitException($init);
        }
        $response = $controller->{$this->action}(...$this->params);
        throw new AppExitException($response);
    }


}
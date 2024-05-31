<?php

namespace nova\framework\request;

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

    public function __toString(): string
    {
        return $this->module . "/" . $this->controller . "/" . $this->action;
    }




}
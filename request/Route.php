<?php

namespace nova\framework\request;

use nova\framework\App;
use nova\framework\event\EventManager;
use nova\framework\log\Logger;
use function nova\framework\route;

class Route
{
    /**
     * @var array
     */
    private static array $routes = [];
    public static string $uri = "";

    public static string $root = "";

    static function get(string $uri,RouteObject $mapper): void
    {
        self::add($uri, $mapper,"GET");
    }

    static function post(string $uri, RouteObject $mapper): void
    {
        self::add($uri, $mapper,"POST", );
    }

    static function patch(string $uri, RouteObject $mapper): void
    {
        self::add($uri, $mapper,"PATCH");
    }

    static function options(string $uri, RouteObject $mapper): void
    {
        self::add($uri, $mapper,"OPTIONS");
    }

    static function head(string $uri, RouteObject $mapper): void
    {
        self::add($uri, $mapper,"HEAD");
    }
    static function put(string $uri, RouteObject $mapper): void
    {
        self::add($uri,  $mapper,"PUT",);
    }

    static function delete(string $uri, RouteObject $mapper): void
    {
        self::add($uri, $mapper,"DELETE");
    }

    static function add(string $uri, RouteObject $mapper, string $method = ""): void
    {
        if(!empty($method)){
            $uri = $method . " " . $uri;
        }

        self::$routes[$uri] = $mapper;
    }

    /**
     * @param string $uri
     * @param string $method
     * @return RouteObject
     * @throws ControllerException
     */
    public static function dispatch(string $uri,string $method): RouteObject
    {
       self::$uri = self::removeQueryStringVariables($uri);

       if (empty( self::$uri)) {
           self::$uri = '/';
       }

        EventManager::trigger("onBeforeRoute", self::$uri);

       $debug = $GLOBALS['__nova_app_config__']['debug']??false;

        $debug && Logger::info("Route dispatch: $method ".self::$uri);

        $routes = self::$routes;

        if($GLOBALS['__nova_app_config__']['default_route']??false){
            $routes = array_merge($routes, [
                "/{module}/{controller}/{action}" => route("{module}", "{controller}", "{action}"),
            ]);
        }

        $routeObj = null;

        foreach($routes as $key => $route){
            $key_method = explode(' ', $key);
            if(sizeof($key_method) == 2) {
                [$key_method, $key] = $key_method;
                if($method != $key_method) {
                    continue;
                }
            }


            $rule = '@^' . str_ireplace(
                    ['\\\\', '.', '/', '@number}', '@word}', '{', '}'],
                    ['', '\.', '\/',  '>\d+)','>\w+)','(?P<', '>.+?)'],
                    strtolower($key)
                ) . '$@ui';

            $debug &&  Logger::info("Route key: $key  rule: $rule  uri: ".self::$uri);

            if (preg_match($rule,  self::$uri, $matches)) {
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $_GET[$k] = $v;
                    }
                }
                $routeObj = $route;
                $routeObj->updateParams($matches);
                break;
            }
        }
        EventManager::trigger("onAfterRoute", $routeObj);
        if ($routeObj == null) {
            throw new ControllerException("Route not found: ".self::$uri);
        }

        return $routeObj;
    }

    /**
     * @param string $uri
     * @return string
     */
    private static function removeQueryStringVariables(string $uri): string
    {
        $raw = $uri;
        $parts = explode('?', $uri, 2);
        if (sizeof($parts) > 1) {
            $uri = $parts[0];
        }


        if(str_starts_with($uri,"/public")){
            $uri = substr($uri,7);
            Logger::warning("Don't use /public in uri: $uri, it's unsafe. Please use nginx or apache to set root path.");
        }
        if(str_starts_with($uri,"/index.php")){
            $uri = substr($uri,10);
        }
        self::$root = App::getInstance()->getReq()->getBasicAddress().str_replace($uri,"",$raw);
   /*     if(str_starts_with($uri,"/public/index.php")){
            $uri = substr($uri,17);
        }

        $uri =  str_replace(["/public/index.php", "/index.php"], "", $uri);*/
        Logger::info("Route removeQueryStringVariables: $uri");
        return $uri;
    }



}
<?php

namespace nova\framework\request;

use nova\framework\log\Logger;
use function nova\framework\route;

class Route
{
    /**
     * @var array
     */
    private static array $routes = [];

    static function get(string $uri,RouteObject $mapper): void
    {
        self::add($uri, $mapper,"GET");
    }

    static function post(string $uri, RouteObject $mapper): void
    {
        self::add($uri, $mapper,"POST", );
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
       $uri = self::removeQueryStringVariables($uri);

       if (empty($uri)) {
           $uri = '/';
       }

       $debug = $GLOBALS['__nova_app_config__']['debug']??false;

        $debug && Logger::info("Route dispatch: $uri method: $method");

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

            $debug &&  Logger::info("Route key: $key  rule: $rule  uri: $uri");

            if (preg_match($rule, $uri, $matches)) {
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

        if ($routeObj == null) {
            throw new ControllerException("Route not found: $uri");
        }

        return $routeObj;
    }

    /**
     * @param string $controller
     * @param string $method
     */
    private static function call(string $controller, string $method): void
    {
        $controller = "App\\Controllers\\$controller";
        $controller = new $controller;
        $controller->$method();
    }

    /**
     * @param string $uri
     * @return string
     */
    private static function removeQueryStringVariables(string $uri): string
    {
        $parts = explode('?', $uri, 2);
        if (sizeof($parts) > 1) {
            $uri = $parts[0];
        }
        $uri =  str_replace(["/public/index.php", "/index.php"], "", $uri);
        Logger::info("Route removeQueryStringVariables: $uri");
        return $uri;
    }

}
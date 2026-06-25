<?php

declare(strict_types=1);

namespace nova\framework\route;

/**
 * 通用插件路由对象
 *
 * 控制器命名空间在创建时注入，插件无需为每个模块单独定义 RouteObject 子类。
 * 配合 {@see RouteTrait::map()} 使用。
 *
 * @package nova\framework\route
 */
class PluginRouteObject extends AbstractRouteObject
{
    /**
     * 创建带指定控制器命名空间的路由对象
     *
     * @param  string $namespace  控制器命名空间前缀，如 'nova\\plugin\\ai\\controller\\'
     * @param  string $controller 控制器名称
     * @param  string $action     动作名称
     * @param  array  $params     路由参数
     * @return self
     */
    public static function create(string $namespace, string $controller, string $action, array $params = []): self
    {
        $route = new self('', $controller, $action, $params);
        $route->controllerNamespace = $namespace;
        return $route;
    }
}

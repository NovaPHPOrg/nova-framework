<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework;

use Error;
use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\NovaApp;
use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\exception\ErrorHandler;
use nova\framework\http\Response;
use nova\framework\route\ControllerException;
use nova\framework\route\Route;
use nova\framework\route\RouteObject;
use Throwable;

class App extends NovaApp
{
    /**
     * 性能监控阈值（毫秒）
     * 当应用执行时间超过此阈值时会触发性能警告
     */
    private const int PERFORMANCE_THRESHOLD = 50;

    /**
     * 错误页面模板路径配置
     * 包含404和500错误页面的HTML模板位置
     */
    private const array ERROR_TEMPLATES = [
        404 => '/nova/framework/error/404.html',
        500 => '/nova/framework/error/500.html'
    ];

    /**
     * 获取App实例
     * 如果存在自定义的Application类并实现了App接口，则返回自定义Application实例
     * 否则返回默认App实例
     *
     * @return App 返回应用程序实例
     */
    public static function getInstance(): App
    {
        return Context::instance()->getOrCreateInstance("App", function () {
            $applicationClazz = "app\\Application";
            if (class_exists($applicationClazz) && is_subclass_of($applicationClazz, App::class)) {
                return new $applicationClazz();
            }
            return new App();
        });
    }

    /**
     * 启动应用程序
     * 处理整个应用程序的生命周期，包括：
     * 1. 框架初始化
     * 2. 路由处理
     * 3. 请求处理
     * 4. 异常处理
     * 5. 应用程序终止
     */
    public function start(): void
    {
        try {
            $this->initializeFramework();
            $route = $this->handleRouting();
            $this->processRequest($route);
        } catch (AppExitException $exception) {
            $this->handleAppExit($exception);
        } catch (ControllerException $exception) {
            $this->handleControllerException($exception);
        } catch (Throwable|Error $exception) {
            $this->handleGeneralException($exception);
        } finally {
            $this->finalizeApplication();
        }
    }

    /**
     * 初始化框架
     */
    private function initializeFramework(): void
    {
        Logger::info("App Start");
        ErrorHandler::register();
        EventManager::register();
        $this->onFrameworkStart();
        EventManager::trigger("framework.start", $this);
    }

    /**
     * 处理路由
     * @throws ControllerException
     */
    private function handleRouting(): RouteObject
    {
        $route = Route::getInstance()->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        $this->onRoute($route);
        EventManager::trigger("route.handler", $route);
        $route->checkSelf();
        return $route;
    }

    /**
     * 处理请求
     * 设置路由信息到请求对象中，触发应用启动事件，并执行路由
     *
     * @param  RouteObject      $route 路由对象
     * @throws AppExitException
     */
    private function processRequest(RouteObject $route): void
    {
        $request = $this->context->request();
        $request->setRoute($route);
        $this->onAppStart();
        EventManager::trigger("app.start", $request);
        $route->run();
    }

    /**
     * 发送响应
     * 将响应发送给客户端，并记录相关日志
     *
     * @param Response|null $response 响应对象
     */
    private function sendResponse(?Response $response): void
    {
        try {
            if ($response) {
                $response->send();
                Logger::info("Response sent successfully");
            }
        } catch (Throwable|Error $e) {
            Logger::error("Response send error", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->printException($e);
        }
    }

    /**
     * 处理AppExit异常
     */
    private function handleAppExit(AppExitException $exception): void
    {
        Logger::info("App Exit Exception", ['message' => $exception->getMessage()]);
        $this->sendResponse($exception->response());
        $this->onAppEnd();
        EventManager::trigger("app.end", $this);
    }

    /**
     * 处理Controller异常
     */
    private function handleControllerException(ControllerException $exception): void
    {
        Logger::info("Controller Exception", ['message' => $exception->getMessage()]);
        $route = $exception->route();
        $response = $this->onRouteNotFound($route, $_SERVER['REQUEST_URI']);
        EventManager::trigger("route.not.found", $route);

        if (!$response) {
            $response = $this->createErrorResponse(404, $exception);
        }

        $this->sendResponse($response);
    }

    /**
     * 处理一般异常
     */
    private function handleGeneralException(Throwable|Error $exception): void
    {
        Logger::error("App Runtime Exception", [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $response = $this->onApplicationError($_SERVER['REQUEST_URI']);
        EventManager::trigger("app.error");

        if (!$response) {
            $response = $this->createErrorResponse(500, $exception);
        }

        $this->sendResponse($response);
    }

    /**
     * 创建错误响应
     * 根据是否处于调试模式返回不同的错误响应：
     * - 调试模式：返回详细的异常信息
     * - 生产模式：返回配置的错误页面
     *
     * @param  int      $statusCode HTTP状态码
     * @return Response 错误响应对象
     */
    private function createErrorResponse(int $statusCode, Throwable $exception): Response
    {
        if ($this->context->isDebug()) {
            return  ErrorHandler::getExceptionResponse($exception);
        }

        $errorTemplate = ROOT_PATH . self::ERROR_TEMPLATES[$statusCode];
        return Response::asHtml(
            file_get_contents($errorTemplate),
            [],
            $statusCode
        );
    }

    /**
     * 结束应用程序
     */
    private function finalizeApplication(): void
    {
        $this->onFrameworkEnd();
        EventManager::trigger("framework.end", $this);

        $executionTime = runtime("App Session");
        if ($executionTime > self::PERFORMANCE_THRESHOLD) {
            Logger::warning("Performance warning", [
                'runtime' => $executionTime,
                'threshold' => self::PERFORMANCE_THRESHOLD,
                'message' => "App execution exceeded recommended time"
            ]);
        }

        Logger::info("App end");
        Response::finish();
    }

    /**
     * 框架启动时的钩子方法
     */
    protected function onFrameworkStart()
    {
    }

    /**
     * 路由解析完成时的钩子方法
     * @param RouteObject|null $route 路由对象
     */
    protected function onRoute(?RouteObject $route)
    {
    }

    /**
     * 应用启动时的钩子方法
     */
    protected function onAppStart()
    {
    }

    /**
     * 路由未找到时的钩子方法
     * @param  RouteObject|null $route 路由对象
     * @param  string           $uri   请求URI
     * @return Response|null    自定义的错误响应
     */
    protected function onRouteNotFound(?RouteObject $route, string $uri): ?Response
    {
        return null;
    }

    /**
     * 框架结束时的钩子方法
     */
    protected function onFrameworkEnd()
    {
    }

    /**
     * 应用结束时的钩子方法
     */
    protected function onAppEnd()
    {
    }

    /**
     * 应用发生错误时的钩子方法
     * @param  string        $uri 请求URI
     * @return Response|null 自定义的错误响应
     */
    protected function onApplicationError(string $uri): ?Response
    {
        return null;
    }

    private function printException(\Exception $e): void
    {
        if ($this->context->isDebug()) {
            echo "<pre>";
            echo "<h2>Exception: " . $e->getMessage() . "</h2><br>";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
            echo "Trace: <br>";
            echo $e->getTraceAsString();
            echo "</pre>";
        } else {
            echo "<h2>An error occurred, please try again later.</h2>";
        }
    }
}

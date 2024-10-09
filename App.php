<?php

namespace nova\framework;

use Exception;
use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\exception\ErrorHandler;
use nova\framework\log\Logger;
use nova\framework\request\ControllerException;
use nova\framework\request\Request;
use nova\framework\request\Response;
use nova\framework\request\ResponseType;
use nova\framework\request\Route;
use nova\framework\request\RouteObject;

class App
{
    /**
     * @var bool|mixed
     */
    public bool $debug = false;//是否调试模式

    public function __construct()
    {
        $this->debug = config('debug') ?? false;
    }

    private static ?App $instance = null;

    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private Request $request;


    private ?iApplication $application = null;

    private ?RouteObject $route = null;


    function start(): void
    {
        $this->request= new Request();
        try {
            Logger::info("App start");
            ErrorHandler::register();
            //初始化Application
            $applicationClazz = "app\\Application";

            if (class_exists($applicationClazz) && ($imp = class_implements($applicationClazz)) && in_array(iApplication::class, $imp)) {
                $this->application = new $applicationClazz();
                $this->application->onFrameworkStart();
            }

            EventManager::trigger("onFrameworkStart", $this);

            $this->route = Route::dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

            if ($this->application != null) {
                $this->application->onRoute($this->route);
            }
            EventManager::trigger("onRoute", $this->route);

            $this->route->checkSelf();

            $this->request->route =  $this->route;

            if ($this->application != null) {
                $this->application->onAppStart();
            }
            EventManager::trigger("onAppStart", $this->request);

            $this->route->run($this->request);


        } catch (AppExitException $exception) {
            Logger::info("App Exit Exception: " . $exception->getMessage());
            //获取渲染引擎
            $response = $exception->response();
            $response->send();
            if ($this->application != null) {
                $this->application->onAppEnd();
            }
            EventManager::trigger("onAppEnd", $this);
        } catch (ControllerException $exception) {
            Logger::info("Controller Exception: " . $exception->getMessage());
            $response = null;
            $route = $exception->route();
            if ($this->application != null) {
                $response = $this->application->onRouteNotFound($route, $_SERVER['REQUEST_URI']);
            }
            EventManager::trigger("onRouteNotFound", $route);
            if ($response == null) {
                if ($this->debug) {
                    $response = ErrorHandler::getExceptionResponse($exception);
                }
                if ($response == null) {
                    $response = new Response(
                        file_get_contents(ROOT_PATH . "/nova/framework/error/404.html"),
                        404,
                        ResponseType::HTML
                    );
                }
            }
            $response->send();
        } catch (Exception $exception) {
            Logger::info("App Runtime Exception: " . $exception->getMessage());
            $response = null;
            if ($this->application != null) {
                $response = $this->application->onApplicationError($this->route, $_SERVER['REQUEST_URI']);
            }


            EventManager::trigger("onApplicationError", $this->route);

            if ($response == null) {
                if ($this->debug) {
                    $response = ErrorHandler::getExceptionResponse($exception);
                }
                if ($response == null) {
                    $response = new Response(
                        file_get_contents(ROOT_PATH . "/nova/framework/error/500.html"),
                        500,
                        ResponseType::HTML
                    );

                }
            }
            $response->send();
        } finally {
            if ($this->application != null) {
                $this->application->onFrameworkEnd();
            }
            EventManager::trigger("onFrameworkEnd", $this);
            $t = runtime("App Session");
            if ($t > 50) {
                Logger::warning("App run too slow: $t ms, please check your code. The best runtime is 50ms");
            }
            Logger::info("App end");
        }
    }

    function getReq(): Request
    {
        return $this->request;
    }

}
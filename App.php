<?php

namespace nova\framework;

class App
{
    public  bool $debug = false;//是否调试模式
    public function __construct()
    {
        $this->debug = $GLOBALS['__nova_app_config__']['debug'];
    }
    private static App $instance;
    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new App();
        }
        return self::$instance;
    }

    function start(): void
    {
        try {
            if (self::$cli) {
                EngineManager::setDefaultEngine(new CliEngine());
            }
            if (self::$debug) {
                if (self::$cli)
                    Log::record("Request", "命令行启动框架", Log::TYPE_WARNING);
                else {
                    Log::record("Request", "收到请求：".$_SERVER["REQUEST_METHOD"] . " " . $_SERVER["REQUEST_URI"]);
                }
            }

            Error::register();// 注册错误和异常处理机制
            //Application实例化
            $app = "\app\\Application"; //入口初始化
            if (class_exists($app) && ($imp = class_implements($app)) && in_array(MainApp::class, $imp)) {
                self::$app = new $app();
                self::$app->onFrameworkStart();
            }
            EventManager::trigger("__frame_init__");//框架初始化
            Async::register();//异步任务注册
            $t = round(((microtime(true) - Variables::get("__frame_start__", 0)) * 1000),4);
            self::$frame = $t;
            App::$debug && Log::record("Frame", "框架基础环境加载完毕，耗时：".$t."ms", Log::TYPE_WARNING);
            //路由
            [$__module, $__controller, $__action] = Route::rewrite();
            //模块检查
            Variables::set("__request_module__", $__module);
            Variables::set("__request_controller__", $__controller);
            Variables::set("__request_action__", $__action);
            //通过路由检测后才认为是请求到达
            self::$app && self::$app->onRequestArrive();
            EventManager::trigger("__application_init__");//框架初始化
            if (!is_dir(Variables::getControllerPath($__module))) {
                EngineManager::getEngine()->onNotFound("模块 '$__module' 不存在!");
            }
            // 控制器检查
            if (strtolower($__controller) === 'basecontroller'){
                Error::err("基类 'BaseController' 不允许被访问！", [], "Controller");
            }

            $controller_name = ucfirst($__controller);

            $controller_class = 'app\\controller\\' . $__module . '\\' . $controller_name;

            if (!class_exists($controller_class, true)) {
                $data = [$__module, $__controller, $__action, $controller_class];
                EventManager::trigger("__not_render__", $data);
                EngineManager::getEngine()->onNotFound("模块 ( $__module ) => 控制器 ( $controller_name ) 不存在!");
            }


            $method = method_exists($controller_class, $__action);
            if (!$method) {
                $data = [$__module, $__controller, $__action, $controller_class];
                EventManager::trigger("__not_render__", $data);
                EngineManager::getEngine()->onNotFound("模块 ( $__module ) => 控制器 ( $controller_name ) 中的方法 ( $__action ) 不存在!");
            }

            if (!in_array_case($__action, get_class_methods($controller_class)) || $__action === '__init') {
                Error::err("模块 ( $__module ) => 控制器 ( $controller_name ) 中的方法 ( $__action ) 为私有方法，禁止访问!", [], "Action");
            }

            $app_start = microtime(true);

            /**
             * @var $controller_obj Controller
             */

            $controller_obj = new $controller_class();

            $result = $controller_obj->$__action();

            App::$app_ = round((microtime(true) - $app_start) * 1000,4);

            $engine = EngineManager::getEngine();
            if ($result !== null){
                (new Response())
                    ->render($result)
                    ->setHeaders($engine->getHeaders())
                    ->contentType($engine->getContentType())
                    ->code($engine->getCode())
                    ->send();
            } else {
                $data = [$__module, $__controller, $__action, $controller_class];
                EventManager::trigger("__not_render__", $data);
                $engine->onNotFound("No data.",$controller_obj);
            }

        } catch (ExitApp $exit_app) {//执行退出
            App::$debug && Log::record("Frame", sprintf("框架执行退出: %s", $exit_app->getMessage()));
        } catch (Throwable $exception) {
            Error::err("Exception: ".get_class($exception)."\r\n\r\n".$exception->getMessage(), $exception->getTrace());
        } finally {
            self::$app && self::$app->onRequestEnd();
            if (App::$debug) {
                Log::record("Frame", "框架响应结束...");
                $t = round((microtime(true) - Variables::get("__frame_start__", 0)) * 1000,4);
                Log::record("Frame", sprintf("会话运行时间：%s ms，App运行时间：%s ms", $t,App::$app_), Log::TYPE_WARNING);
                $memory = round((memory_get_usage() - $GLOBALS['__memory_start__'])/ 1024 / 1024,2);
                Log::record("Frame", sprintf("会话运行占用内存：%s MB", $memory), Log::TYPE_WARNING);
                if (App::$app_ > 50) {
                    Log::record("Frame", sprintf("优化提醒：您的当前应用处理用时（%s毫秒）超过 50 毫秒，建议对代码进行优化以获得更好的使用体验。", $t), Log::TYPE_WARNING);
                }
            }
        }
    }
}
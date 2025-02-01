<?php
declare(strict_types=1);
namespace nova\framework;
use nova\framework\core\Context;
use nova\framework\core\Loader;

/**
 * 设置错误报告
 * 开发环境下显示所有错误
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * 框架初始化流程
 * 1. 加载自动加载器
 * 2. 初始化上下文环境
 * 3. 加载助手函数
 * 4. 启动应用程序
 */

// 加载框架核心的自动加载器
include_once "core/Loader.php";
$loader = new Loader();

// 初始化应用程序上下文
$context = new Context($loader);

// 加载助手函数
include_once "helper.php";

// 获取应用程序实例并启动
App::getInstance()->start();

// 清理上下文对象
$context = null;
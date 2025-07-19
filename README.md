# Nova Framework

Nova是一个轻量级、高性能的PHP Web应用框架，采用MVC架构模式，提供完整的Web开发解决方案。

## 框架特性

- **轻量级**: 核心框架精简高效，启动速度快
- **高性能**: 优化的路由系统和缓存机制
- **易扩展**: 模块化设计，支持插件扩展
- **现代化**: 支持PHP 8.0+，采用严格类型声明
- **开发友好**: 完善的调试工具和错误处理

## 目录结构

```
framework/
├── App.php              # 应用程序主类
├── bootstrap.php        # 框架启动文件
├── helper.php          # 助手函数库
├── core/               # 核心组件
│   ├── Context.php     # 应用上下文管理
│   ├── Config.php      # 配置管理
│   ├── Loader.php      # 自动加载器
│   ├── Logger.php      # 日志系统
│   └── ...
├── route/              # 路由系统
│   ├── Route.php       # 路由管理器
│   ├── RouteObject.php # 路由对象
│   └── Controller.php  # 控制器基类
├── http/               # HTTP处理
│   ├── Request.php     # 请求处理
│   ├── Response.php    # 响应处理
│   └── Arguments.php   # 参数处理
├── cache/              # 缓存系统
│   ├── Cache.php       # 缓存管理器
│   └── FileCacheDriver.php # 文件缓存驱动
├── event/              # 事件系统
│   └── EventManager.php # 事件管理器
├── exception/          # 异常处理
│   ├── ErrorHandler.php # 错误处理器
│   └── AppExitException.php # 应用退出异常
├── json/               # JSON处理
│   ├── Json.php        # JSON工具类
│   └── ...
└── error/              # 错误页面
    ├── 404.html        # 404错误页面
    └── 500.html        # 500错误页面
```

## 核心组件

### 1. 应用上下文 (Context)
- 管理应用生命周期
- 提供依赖注入容器
- 处理配置和运行时状态
- 管理请求响应对象

### 2. 路由系统 (Route)
- 支持多种HTTP方法 (GET, POST, PUT, DELETE, PATCH, OPTIONS)
- 参数化路由支持 (`/users/{id}`)
- 路由缓存优化
- 自动路由分发

### 3. HTTP处理
- **Request**: 封装HTTP请求信息，提供参数获取、文件上传等功能
- **Response**: 处理HTTP响应，支持多种响应类型 (JSON, HTML, File等)
- **Arguments**: 参数验证和处理

### 4. 缓存系统
- 支持多种缓存驱动
- 文件缓存驱动
- 缓存接口标准化
- 性能优化

### 5. 事件系统
- 事件注册和触发
- 支持事件监听器
- 框架生命周期事件

### 6. 异常处理
- 统一异常处理机制
- 错误页面支持
- 调试模式错误显示

## 使用示例

### 基本应用启动

```php
<?php
// public/index.php
require_once '../src/nova/framework/bootstrap.php';

// 应用会自动启动
```

### 路由注册

```php
<?php
// 在应用启动前注册路由
Route::getInstance()
    ->get('/', route('index', 'main', 'index'))
    ->get('/users/{id}', route('user', 'main', 'show'))
    ->post('/users', route('user', 'main', 'create'));
```

### 控制器示例

```php
<?php
namespace app\controller\user;

use nova\framework\route\Controller;
use nova\framework\http\Response;

class Main extends Controller
{
    public function index(): Response
    {
        return $this->json(['message' => 'Hello Nova!']);
    }
    
    public function show(int $id): Response
    {
        return $this->json(['id' => $id]);
    }
}
```

### 配置管理

```php
<?php
// 获取配置
$debug = config('debug');
$timezone = config('timezone', 'Asia/Shanghai');

// 设置配置
config('custom.setting', 'value');
```

### 助手函数

```php
<?php
// 调试输出
dump($variable);

// 性能监控
$time = runtime('operation');

// 文件类型检测
$mime = file_type('image.jpg');

// 生成UUID
$uuid = uuid();
```

## 日志系统

框架提供完整的日志记录功能：

- **INFO**: 信息类型，不影响框架正常运行
- **WARNING**: 警告类型，框架可能不会按预期运行但可继续执行  
- **ERROR**: 错误类型，框架无法继续执行
- **DEBUG**: 调试信息，仅在调试模式下显示

## 性能特性

- 路由缓存机制
- 自动加载优化
- 内存使用优化
- 响应时间监控
- 性能阈值警告

## 开发模式

框架支持开发和生产两种模式：

- **开发模式**: 显示详细错误信息，启用调试功能
- **生产模式**: 隐藏错误详情，优化性能

## 版本信息

- 当前版本: 5.0.1
- 最低PHP版本: 8.0
- 许可证: MIT

## 贡献指南

欢迎提交Issue和Pull Request来改进框架。在提交代码前，请确保：

1. 代码符合PSR-12编码规范
2. 添加适当的注释和文档
3. 包含必要的测试用例
4. 遵循框架的架构设计原则
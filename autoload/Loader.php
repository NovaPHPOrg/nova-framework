<?php
declare(strict_types=1);
namespace nova\framework\autoload;


use nova\framework\log\File;
use nova\framework\log\Logger;
use Throwable;
use function nova\framework\config;


class Loader
{

    private array $autoloadFilesCache = [];
    private string $file = '';

    public function __construct()
    {
        $this->autoloadFilesCache =  [];
        $this->file = File::path("runtime", "autoload.php");
        if (file_exists($this->file)) {
            try {
                $this->autoloadFilesCache = require $this->file;
            } catch (Throwable $e) {
                $this->autoloadFilesCache = [];
            }
        }
    }

    public function __destruct()
    {
        file_put_contents($this->file, "<?php\nreturn " . var_export($this->autoloadFilesCache, true) . ";");
    }

    /**
     * 注册自动加载
     */
    public function register(): void
    {
        spl_autoload_register(function () {
            $this->autoload(...func_get_args());
        }, true, true);
    }

    /**
     * 框架本身的自动加载
     *
     * @param string $raw
     */
    public function autoload(string $raw): void
    {
        if (array_key_exists($raw, $this->autoloadFilesCache)) {
            $this->load($this->autoloadFilesCache[$raw]);
            return;
        }

        $namespace = config("namespace") ?? [];
        $namespace += [
            'nova\\' => 'nova'.DS,
        ];

        foreach ($namespace as $prefix => $replace) {
            $realClass = str_replace("\\", DS, str_replace($prefix, $replace, $raw)) . ".php";
            $file = File::path($realClass);
            Logger::info("AutoLoader => $file ");
            if (File::exists($file)) {
                $this->autoloadFilesCache[$raw] = $file;
                $this->load($file);
                return;
            }
        }
    }

    private function load($file): void
    {
        if (function_exists('opcache_compile_file')) {
            opcache_compile_file($file);
        }
        require $file;
    }
}

<?php
namespace nova\framework\autoload;


use nova\framework\cache\ApcuCacheDriver;
use nova\framework\cache\Cache;


class Loader
{
    private array $autoloadFiles;
    private string $cacheKey = 'autoload_files_cache';

    private Cache $cache;

    public function __construct()
    {
        $this->cache = new Cache(false,ApcuCacheDriver::class);

        $this->autoloadFiles =  $this->cache->get($this->cacheKey,[]);
    }

    public function __destruct()
    {
        $this->cache->set($this->cacheKey, $this->autoloadFiles);
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
        if (array_key_exists($raw, $this->autoloadFiles)) {
            $this->load($this->autoloadFiles[$raw]);
            return;
        }

        $prefixes = [
            'nova\\' => 'nova'.DS,
        ];

        foreach ($prefixes as $prefix => $replace) {
            $realClass = str_replace("\\", DS, str_replace($prefix, $replace, $raw)) . ".php";
            $file = ROOT_PATH . DS . $realClass;
            if (file_exists($file)) {
                $this->autoloadFiles[$raw] = $file;
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

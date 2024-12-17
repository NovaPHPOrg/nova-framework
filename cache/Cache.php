<?php
declare(strict_types=1);

namespace nova\framework\cache;

use function nova\framework\config;

class Cache {
    private iCacheDriver $driver;
    public function __construct($shared = false,$driverClazz = null){
        $driver = config('cache_driver');
        if($driverClazz != null){
            $driver = $driverClazz;
        }
        try {
            if (!class_exists($driver) || !in_array('nova\framework\cache\iCacheDriver', class_implements($driver))) {
                throw new CacheException("Cache driver {$driver} not found");
            }
            $this->driver = new $driver($shared);
        } catch (CacheException $e) {
            $this->driver = new FileCacheDriver($shared);
        }
    }

    public function set($key, $value, $ttl = 0): void
    {
         $this->driver->set($key, $value, $ttl);
    }

    public function get($key,$default = null): mixed{
        return $this->driver->get($key, $default);
    }

    public function delete($key): void
    {
         $this->driver->delete($key);
    }


    public function deleteKeyStartWith($key): void
    {
         $this->driver->deleteKeyStartWith($key);
    }


    public function clear(): void
    {
         $this->driver->clear();
    }

    public function getTtl($key): int
    {
        return $this->driver->getTtl($key);
    }
}

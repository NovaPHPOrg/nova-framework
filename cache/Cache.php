<?php

namespace nova\framework\cache;

use cache\FileCacheDriver;

class Cache {
    private iCacheDriver $driver;
    public function __construct($shared = false,$driverClazz = null){
        $driver = $GLOBALS['__nova_app_config__']['cache_driver'];
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

    public function get($key, $default = null) {
        return $this->driver->get($key, $default);
    }

    public function delete($key): void
    {
         $this->driver->delete($key);
    }

    public function clear(): void
    {
         $this->driver->clear();
    }
}

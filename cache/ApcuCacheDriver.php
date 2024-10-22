<?php

namespace nova\framework\cache;



use Exception;

class ApcuCacheDriver implements iCacheDriver
{
    private string $prefix;


    /**
     * @throws Exception
     */
    public function __construct($shared = false) {
        if (!extension_loaded('apcu')) { //如果没有apcu扩展，则使用文件缓存
            throw new CacheException('APCu extension is not loaded');
        }
        if($shared) {
            $this->prefix = 'nova_';
            return;
        }
        $this->prefix = hash('sha256', ROOT_PATH) . '_nova_';
    }

    public function set($key, $value, $expire = 0): void
    {
         apcu_store($this->prefix . $key, $value, $expire);
    }

    public function get($key, $default = null): mixed
    {
        $result = apcu_fetch($this->prefix . $key, $success);
        if ($success === false) {
            return $default;
        }
        return $result;
    }

    public function delete($key): void
    {
         apcu_delete($this->prefix . $key);
    }

    public function clear(): void
    {
         apcu_clear_cache();
    }

    public function deleteKeyStartWith($key): void
    {
        $info = apcu_cache_info();
        $cache = $info['cache_list'];
        foreach ($cache as $item) {
            if (str_starts_with($item['info'], $this->prefix . $key)) {
                apcu_delete($item['info']);
            }
        }
    }

    public function getTtl($key): int
    {
        $info = apcu_key_info($this->prefix . $key);
        if ($info === false) {
            return 0;
        }
        return $info['ttl'] ?? 0;
    }
}

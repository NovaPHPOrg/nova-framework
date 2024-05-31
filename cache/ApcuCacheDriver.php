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
            throw new Exception('APCu extension is not loaded');
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
        $result = apcu_fetch($this->prefix . $key,$success);
        if($success === false) {
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

}
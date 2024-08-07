<?php
namespace nova\framework\cache;
interface iCacheDriver
{
    public function __construct($shared = false);
    public function get($key,$default = null): mixed;
    public function set($key, $value, $expire);
    public function delete($key);
    public function deleteKeyStartWith($key);
    public function clear();

}
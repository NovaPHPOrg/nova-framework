<?php

namespace nova\framework\core;

use nova\plugin\orm\object\Dao;

class Instance
{
    public static function getInstance(...$args): static
    {
        $cls = get_called_class();
        return Context::instance()->getOrCreateInstance($cls,new $cls(...$args));
    }
}
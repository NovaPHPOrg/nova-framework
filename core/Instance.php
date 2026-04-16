<?php

namespace nova\framework\core;

class Instance
{
    public static function getInstance(...$args): static
    {
        $instanceKey = static::class . md5(serialize($args));
        return Context::instance()->getOrCreateInstance($instanceKey, function () use ($args) {
            return new static(...$args);
        });
    }
}
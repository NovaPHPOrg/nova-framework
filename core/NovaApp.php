<?php
declare(strict_types=1);
namespace nova\framework\core;

class NovaApp
{
    protected Context $context;
    public function __construct()
    {
        $this->context = Context::instance();
    }

}
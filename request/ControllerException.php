<?php

namespace nova\framework\request;

use nova\framework\log\Logger;
use Throwable;

class ControllerException extends \Exception
{
    private ?RouteObject $route;
    public function __construct(string $message = "", RouteObject $route = null)
    {
        parent::__construct($message, 0, null);
        Logger::info($message);
        $this->route = $route;
    }

    public function route(): ?RouteObject
    {
        return $this->route;
    }
}
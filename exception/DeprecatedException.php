<?php

namespace nova\framework\exception;

use Exception;
use nova\framework\log\Logger;
use Throwable;

class DeprecatedException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::error($message);
    }
}
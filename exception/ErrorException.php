<?php

namespace nova\framework\exception;

use nova\framework\log\Logger;

class ErrorException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::warning($message);
    }
}
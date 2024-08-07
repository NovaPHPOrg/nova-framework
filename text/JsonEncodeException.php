<?php

namespace nova\framework\text;

use Exception;
use nova\framework\log\Logger;
use Throwable;

class JsonEncodeException extends Exception
{
    public function __construct(string $message = "", $json = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::error("json encode error =>".print_r($json,true));
        Logger::error($message);
    }
}
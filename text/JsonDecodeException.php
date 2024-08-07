<?php

namespace nova\framework\text;

use Exception;
use nova\framework\log\Logger;
use Throwable;

class JsonDecodeException extends Exception
{
    public function __construct(string $message = "", $json = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::error("json decode error => ".$json);
        Logger::error($message);
    }
}
<?php

namespace nova\framework\cache;

use Exception;
use nova\framework\log\Logger;
use Throwable;

class CacheException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::warning($message);
    }
}
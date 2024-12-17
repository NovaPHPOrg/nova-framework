<?php
declare(strict_types=1);

namespace nova\framework\exception;

use Exception;
use nova\framework\request\Response;
use function nova\framework\config;

class AppExitException extends Exception
{
    private Response $response;
    public function __construct($response,$message = "App Exit")
    {
        if (config("debug")) {
            $message .= "( Called By {$this->getPreviousFunction()} )";
        }
        parent::__construct($message, 0, null);
        $this->response = $response;
    }

    public function response(): Response
    {
        return $this->response;
    }
    function getPreviousFunction() {
        $backtrace = debug_backtrace();
        return $backtrace[2]['function'] ?? null;
    }
}
<?php

namespace nova\framework\exception;

use Exception;
use nova\framework\request\Response;

class AppExitException extends Exception
{
    private Response $response;
    public function __construct($response)
    {
        parent::__construct("", 0, null);
        $this->response = $response;
    }

    public function response(): Response
    {
        return $this->response;
    }
}
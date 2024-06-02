<?php

namespace nova\framework\request;

class Controller
{
    protected Request $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
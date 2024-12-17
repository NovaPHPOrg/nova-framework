<?php
declare(strict_types=1);

namespace nova\framework\request;

class Controller
{
    protected Request $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    /**
     * @return Response|null
     */
    public function init():?Response
    {
        return null;
    }
}
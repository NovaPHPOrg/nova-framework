<?php

namespace nova\framework\render;

use nova\framework\text\Json;
use nova\framework\text\JsonEncodeException;

class JsonViewRender extends BaseViewRender
{

    /**
     * @inheritDoc
     */
    function render(...$data): string
    {
        try {
            return Json::encode($data);
        } catch (JsonEncodeException $e) {
            return "";
        }
    }
}
<?php

namespace nova\framework\request;

enum ResponseType{
    case JSON;
    case HTML;
    case XML;
    case TEXT;
    case FILE;
    case STATIC;
    case SSE;

    case REDIRECT;
}
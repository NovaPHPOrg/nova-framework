<?php
namespace nova\framework;
use nova\framework\request\Response;
use nova\framework\request\RouteObject;

interface iApplication
{
    function onAppStart();
    function onAppEnd();
    function onRouteNotFound(?RouteObject $route,string $uri):?Response;
    function onApplicationError(?RouteObject $route,string $uri):?Response;
    function onRoute(RouteObject $route);
    function onFrameworkStart();
    function onFrameworkEnd();
}
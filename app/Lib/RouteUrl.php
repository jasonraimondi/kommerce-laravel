<?php
namespace App\Lib;

use inklabs\KommerceTemplates\Lib\RouteUrlInterface;

class RouteUrl implements RouteUrlInterface
{
    /**
     * Generate a URL to a named route.
     *
     * @param  string $name
     * @param  array $parameters
     * @param  bool $absolute
     * @return string
     */
    function getRoute($name, $parameters = [], $absolute = true)
    {
        return route($name, $parameters, $absolute);
    }
}

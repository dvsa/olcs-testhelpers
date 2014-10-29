<?php

namespace Olcs\TestHelpers;

use Mockery as m;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;

/**
 * Class ControllerRouteMatchHelper
 * @author Ian Lindsay <ian@hemera-business-services.co.uk>
 */
class ControllerRouteMatchHelper
{
    /**
     * @param array $params
     * @param array $routerConfig
     * @return \Zend\Mvc\MvcEvent
     */
    public function getMockRouteMatch($params = array(), $routerConfig = array())
    {
        $routeMatch = new RouteMatch($params);
        $event      = new MvcEvent();
        $router = HttpRouter::factory($routerConfig);

        $event->setRouter($router);
        $event->setRouteMatch($routeMatch);

        return $event;
    }
}
<?php

namespace Olcs\TestHelpers;

use Mockery as m;
use Zend\View\HelperPluginManager;
use Olcs\TestHelpers\ControllerPluginManagerHelper;
use Olcs\TestHelpers\ControllerRouteMatchHelper;
use Zend\View\Helper\Placeholder;

/**
 * When a controller use parent::detailsAction it will often be possible to reuse the service and plugin managers
 * provided here
 * @author Ian Lindsay <ian@hemera-business-services.co.uk>
 */
class ControllerDetailsActionHelper
{
    /**
     * Gets a mock service manager
     *
     * @param array $expectedResult
     * @param array $mockRestData
     * @param string $placeholderName
     *
     * @return m\MockInterface
     */
    public function getServiceManager($expectedResult, $mockRestData, $placeholderName)
    {
        //placeholders
        $placeholder = new \Zend\View\Helper\Placeholder();
        $placeholder->getContainer($placeholderName)->set($expectedResult);

        //add placeholders to view helper
        $mockViewHelperManager = new \Zend\View\HelperPluginManager();
        $mockViewHelperManager->setService('placeholder', $placeholder);

        //rest call to return prohibition data
        $mockRestHelper = m::mock('RestHelper');
        $mockRestHelper->shouldReceive('makeRestCall')->withAnyArgs()->andReturn($mockRestData);

        //mock service manager
        $mockServiceManager = m::mock('\Zend\ServiceManager\ServiceManager');
        $mockServiceManager->shouldReceive('get')->with('Helper\Rest')->andReturn($mockRestHelper);
        $mockServiceManager->shouldReceive('get')->with('viewHelperManager')->andReturn($mockViewHelperManager);

        return $mockServiceManager;
    }

    /**
     * @param array $routeVars
     * @return m\MockInterface|\Zend\Mvc\Controller\PluginManager
     */
    public function getPluginManager($routeVars)
    {
        $pluginHelper = new ControllerPluginManagerHelper();
        $mockPluginManager = $pluginHelper->getMockPluginManager(['params' => 'Params']);

        //route params
        $mockParams = $mockPluginManager->get('params', '');

        foreach ($routeVars as $var => $value) {
            $mockParams->shouldReceive('fromRoute')->with($var)->andReturn($value);
        }

        return $mockPluginManager;
    }

    public function getNotFoundEvent()
    {
        $routeMatchHelper = new ControllerRouteMatchHelper();
        return $routeMatchHelper->getMockRouteMatch(array('action' => 'not-found'));
    }

}
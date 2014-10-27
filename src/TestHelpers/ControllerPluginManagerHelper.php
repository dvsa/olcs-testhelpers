<?php

namespace Olcs\TestHelpers;

use Mockery as m;

class ControllerPluginManagerHelper
{
    /**
     * @param $class
     * @return m\MockInterface
     */
    public function getMockPlugin($class)
    {
        if (strpos($class, '\\') === false) {
            $class = 'Zend\Mvc\Controller\Plugin\\' . $class;
        }

        $mockPlugin = m::mock($class);
        $mockPlugin->shouldReceive('__invoke')->andReturnSelf();
        return $mockPlugin;
    }

    /**
     * @param $plugins
     * @return m\MockInterface|\Zend\Mvc\Controller\PluginManager
     */
    public function getMockPluginManager($plugins)
    {
        $mockPluginManager = m::mock('Zend\Mvc\Controller\PluginManager');
        $mockPluginManager->shouldReceive('setController');

        foreach ($plugins as $name => $class) {
            $mockPlugin = $this->getMockPlugin($class);
            $mockPluginManager->shouldReceive('get')->with($name, '')->andReturn($mockPlugin);
        }

        return $mockPluginManager;
    }
}
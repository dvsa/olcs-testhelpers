<?php

namespace Olcs\TestHelpers;

use Mockery as m;
use Zend\View\HelperPluginManager;
use Olcs\TestHelpers\ControllerPluginManagerHelper;
use Zend\View\Helper\Placeholder;
use Zend\Form\Form;
use Common\Form\Annotation\CustomAnnotationBuilder;

/**
 * When a controller use parent::addAction or parent::editAction,
 * it will often be possible to reuse the service and plugin managers provided here
 * @author Ian Lindsay <ian@hemera-business-services.co.uk>
 */
class ControllerAddEditHelper
{
    protected $form;

    public function getForm()
    {
        return $this->form;
    }

    public function setForm($form)
    {
        $this->form = $form;
        return $this;
    }

    /**
     * Gets a mock service manager
     *
     * @param string $formAction
     * @param array $mockResult
     * @return m\MockInterface
     */
    public function getServiceManager($formAction, $mockResult, $formName)
    {
        $form = new Form();
        $form->setAttribute('action', $formAction);
        $this->setForm($form);

        $mockRestHelper = m::mock('RestHelper');
        $mockRestHelper->shouldReceive('makeRestCall')->withAnyArgs()->andReturn($mockResult);

        // tons of legacy tests relied on this helper being mocked incorrectly...
        $stringHelper = new \Common\Service\Helper\StringHelperService();
        // ... meaning they never actually relied on the return value, and thus
        // got away with formName being incorrectly all lowercased
        $formName = ucfirst($formName);

        $formAnnotationBuilder = new CustomAnnotationBuilder();

        $olcsCustomForm = m::mock('\Common\Service\Helper\FormHelperService');
        $olcsCustomForm->shouldReceive('createForm')->with($formName)->andReturn($form);

        $placeholder = new Placeholder();

        $mockViewHelperManager = new HelperPluginManager();
        $mockViewHelperManager->setService('placeholder', $placeholder);

        $mockServiceManager = m::mock('\Zend\ServiceManager\ServiceManager');
        $mockServiceManager->shouldReceive('get')->with('FormAnnotationBuilder')->andReturn($formAnnotationBuilder);
        $mockServiceManager->shouldReceive('get')->with('Helper\Form')->andReturn($olcsCustomForm);
        $mockServiceManager->shouldReceive('get')->with('Helper\Rest')->andReturn($mockRestHelper);
        $mockServiceManager->shouldReceive('get')->with('Helper\String')->andReturn($stringHelper);
        $mockServiceManager->shouldReceive('get')->with('viewHelperManager')->andReturn($mockViewHelperManager);

        return $mockServiceManager;
    }

    /**
     * @param string $action
     * @param int $caseId
     * @param int $licence
     * @return m\MockInterface|\Zend\Mvc\Controller\PluginManager
     */
    public function getPluginManager($action, $caseId, $licence, $identifierName, $identifierId)
    {
        $pluginHelper = new ControllerPluginManagerHelper();
        $mockPluginManager = $pluginHelper->getMockPluginManager(['params' => 'Params']);

        $mockParams = $mockPluginManager->get('params', '');
        $mockParams->shouldReceive('fromRoute')->with('action')->andReturn($action);
        $mockParams->shouldReceive('fromRoute')->with($identifierName)->andReturn($identifierId);
        $mockParams->shouldReceive('fromQuery')->with('licence', '')->andReturn($licence);
        $mockParams->shouldReceive('fromRoute')->with('licence', '')->andReturn($licence);

        if ($action == 'add') {
            $mockParams->shouldReceive('fromQuery')->with('case', '')->andReturn($caseId);
        }

        $mockParams->shouldReceive('fromQuery')->with('application', '')->andReturn('application');
        $mockParams->shouldReceive('fromRoute')->with('application', '')->andReturn('application');
        $mockParams->shouldReceive('fromQuery')->with('transportManager', '')->andReturn('transportManager');
        $mockParams->shouldReceive('fromRoute')->with('transportManager', '')->andReturn('transportManager');

        return $mockPluginManager;
    }
}

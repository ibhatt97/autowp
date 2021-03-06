<?php

namespace ApplicationTest\Frontend\Controller;

use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Api\NewController;

class NewControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/new', 'GET');

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(NewController::class);
        $this->assertMatchedRouteName('api/new/get');
        $this->assertActionName('index');
    }
}

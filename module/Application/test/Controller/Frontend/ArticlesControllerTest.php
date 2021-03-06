<?php

namespace ApplicationTest\Controller\Frontend;

use Zend\Http\Request;
use Application\Test\AbstractHttpControllerTestCase;

use Application\Controller\Api\ArticleController;

class ArticlesControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    public function testIndex()
    {
        $this->dispatch('https://www.autowp.ru/api/article', Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(ArticleController::class);
        $this->assertMatchedRouteName('api/article/get');
        $this->assertActionName('index');
    }
}

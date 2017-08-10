<?php

namespace ApplicationTest\Frontend\Controller;

use Zend\Http\Request;
use Zend\Http\Header\Cookie;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\ItemParentController;
use Application\Controller\FactoriesController;
use Application\Test\AbstractHttpControllerTestCase;

class FactoryControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function createItem($params)
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('https://www.autowp.ru/api/item', Request::METHOD_POST, $params);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemController::class);
        $this->assertMatchedRouteName('api/item/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $itemId = $parts[count($parts) - 1];

        return $itemId;
    }

    private function addItemParent($itemId, $parentId, array $params = [])
    {
        $this->reset();

        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch(
            'https://www.autowp.ru/api/item-parent',
            Request::METHOD_POST,
            array_replace([
                'item_id'   => $itemId,
                'parent_id' => $parentId
            ], $params)
        );

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(ItemParentController::class);
        $this->assertMatchedRouteName('api/item-parent/post');
        $this->assertActionName('post');
    }

    public function testIndex()
    {
        $factoryId = $this->createItem([
            'item_type_id' => 6,
            'name'         => 'Factory'
        ]);

        $vehcileId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Vehicle on factory'
        ]);

        $this->addItemParent($vehcileId, $factoryId);

        $this->reset();
        $this->dispatch('https://www.autowp.ru/factory/factory/id/' . $factoryId, Request::METHOD_GET);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(FactoriesController::class);
        $this->assertMatchedRouteName('factories/factory');
        $this->assertActionName('factory');
    }
}

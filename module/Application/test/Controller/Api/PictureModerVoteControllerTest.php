<?php

namespace ApplicationTest\Api\Controller;

use Zend\Http\Header\Cookie;
use Zend\Http\Request;

use Application\Controller\Api\ItemController;
use Application\Controller\Api\PictureController;
use Application\Controller\Api\PictureModerVoteController;
use Application\Test\AbstractHttpControllerTestCase;

class PictureModerControllerTest extends AbstractHttpControllerTestCase
{
    protected $applicationConfigPath = __DIR__ . '/../../../../../config/application.config.php';

    private function mockDuplicateFinder()
    {
        $serviceManager = $this->getApplicationServiceLocator();

        $tables = $serviceManager->get('TableManager');

        $mock = $this->getMockBuilder(\Application\DuplicateFinder::class)
            ->setMethods(['indexImage'])
            ->setConstructorArgs([
                $tables->get('df_hash'),
                $tables->get('df_distance')
            ])
            ->getMock();

        $mock->method('indexImage')->willReturn(true);

        $serviceManager->setService(\Application\DuplicateFinder::class, $mock);
    }

    private function addPictureToItem($vehicleId)
    {
        $this->reset();

        $this->mockDuplicateFinder();

        $request = $this->getRequest();
        $request->getHeaders()
            ->addHeader(Cookie::fromString('Cookie: remember=admin-token'))
            ->addHeaderLine('Content-Type', 'multipart/form-data');
        $request->getServer()->set('REMOTE_ADDR', '127.0.0.1');

        $file = tempnam(sys_get_temp_dir(), 'upl');
        $filename = 'test.jpg';
        copy(__DIR__ . '/../../_files/' . $filename, $file);

        $request->getFiles()->fromArray([
            'file' => [
                'tmp_name' => $file,
                'name'     => $filename,
                'error'    => UPLOAD_ERR_OK,
                'type'     => 'image/jpeg'
            ]
        ]);

        $this->dispatch('https://www.autowp.ru/api/picture', Request::METHOD_POST, [
            'item_id' => $vehicleId
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureController::class);
        $this->assertMatchedRouteName('api/picture/post');
        $this->assertActionName('post');

        $headers = $this->getResponse()->getHeaders();
        $uri = $headers->get('Location')->uri();
        $parts = explode('/', $uri->getPath());
        $pictureId = $parts[count($parts) - 1];

        return $pictureId;
    }

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

    public function testVote()
    {
        $itemId = $this->createItem([
            'item_type_id' => 1,
            'name'         => 'Peugeot 404 Coupe "Voted"',
            'begin_year'   => 1980,
            'end_year'     => 1990
        ]);
        $pictureId = $this->addPictureToItem($itemId);


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture-moder-vote/' . $pictureId, Request::METHOD_PUT, [
            'vote'   => 1,
            'reason' => 'Good pic',
            'save'   => 1
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureModerVoteController::class);
        $this->assertMatchedRouteName('api/picture-moder-vote');


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture-moder-vote/' . $pictureId, Request::METHOD_DELETE);

        $this->assertResponseStatusCode(204);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureModerVoteController::class);
        $this->assertMatchedRouteName('api/picture-moder-vote');


        $this->reset();
        $this->getRequest()->getHeaders()->addHeader(Cookie::fromString('Cookie: remember=admin-token'));
        $this->dispatch('http://www.autowp.ru/api/picture-moder-vote/' . $pictureId, Request::METHOD_PUT, [
            'vote'   => -1,
            'reason' => 'Poor pic',
            'save'   => 0
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertModuleName('application');
        $this->assertControllerName(PictureModerVoteController::class);
        $this->assertMatchedRouteName('api/picture-moder-vote');
    }
}

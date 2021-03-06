<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CategoryController as Controller;

class CategoryControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('longCache'),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\Model\Categories::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\Picture::class)
        );
    }
}

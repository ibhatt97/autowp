<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureItemControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');

        return new PictureItemController(
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\Model\Log::class),
            $hydrators->get(\Application\Hydrator\Api\PictureItemHydrator::class),
            $filters->get('api_picture_item_list'),
            $filters->get('api_picture_item_item'),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Picture::class)
        );
    }
}

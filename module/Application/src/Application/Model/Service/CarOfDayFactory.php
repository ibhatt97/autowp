<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CarOfDayFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new \Application\Model\CarOfDay(
            $tables->get('of_day'),
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Autowp\Image\Storage::class),
            $container->get(\Application\Model\Catalogue::class),
            $container->get('HttpRouter'),
            $container->get('MvcTranslator'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\ItemParent::class),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Twins::class),
            $container->get(\Application\PictureNameFormatter::class)
        );
    }
}

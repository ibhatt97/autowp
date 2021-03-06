<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserItemSubscribeFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new UserItemSubscribe(
            $tables->get('user_item_subscribe'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}

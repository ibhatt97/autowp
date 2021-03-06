<?php

namespace Autowp\Message\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Message\MessageService;

/**
 * @todo Unlink from Telegram
 */
class MessageServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new MessageService(
            $container->get(\Application\Service\TelegramService::class),
            $tables->get('personal_messages'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}

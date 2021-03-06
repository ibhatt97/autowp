<?php

namespace Autowp\Forums;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ForumsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Forums(
            $container->get(\Autowp\Comments\CommentsService::class),
            $tables->get('forums_themes'),
            $tables->get('forums_topics'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}

<?php

declare(strict_types=1);

namespace Autowp\Traffic;

use Zend\Authentication\AuthenticationService;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

use Autowp\User\Model\User;

class TrafficRouteListener extends AbstractListenerAggregate
{
    private $whitelist = [
        '/api/forum',
        '/api/user',
        '/api/account',
        '/api/acl',
        '/api/article',
        '/api/attr',
        '/api/chart',
        '/api/comment',
        '/api/contacts',
        '/api/donate',
        '/api/feedback',
        '/api/hotlinks',
        '/api/ip',
        '/api/item-link',
        '/api/language',
        '/api/log',
        '/api/login',
        '/api/signin',
        '/api/map',
        '/api/message',
        '/api/mosts',
        '/api/page',
        '/api/picture-moder-vote',
        '/api/pulse',
        '/api/rating',
        '/api/recaptcha',
        '/api/restore-password',
        '/api/text',
        '/api/timezone',
        '/api/traffic',
        '/api/spec',
        '/api/stat',
        '/api/vehicle-types',
        '/api/perspective',
        '/api/perspective-page',
        '/api/picture-moder-vote-template',
        '/api/voting',
        '/ng/',
        '/comments',
        '/donate',
        '/factory',
        '/login',
        '/telegram'
    ];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -625);
    }

    private function matchWhitelist(string $url): bool
    {
        foreach ($this->whitelist as $prefix) {
            if (strncasecmp($prefix, $url, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  MvcEvent $e
     * @return null
     */
    public function onRoute(MvcEvent $e)
    {
        $request = $e->getRequest();

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            if ($this->matchWhitelist($request->getUri()->getPath())) {
                return;
            }

            $serviceManager = $e->getApplication()->getServiceManager();

            $auth = new AuthenticationService();

            $unlimitedTraffic = false;
            if ($auth->hasIdentity()) {
                $userModel = $serviceManager->get(User::class);
                $user = $userModel->getRow(['id' => (int)$auth->getIdentity()]);

                if ($user) {
                    $acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
                    $unlimitedTraffic = $acl->isAllowed($user['role'], 'website', 'unlimited-traffic');
                }
            }

            $ip = $request->getServer('REMOTE_ADDR');

            $service = $serviceManager->get(TrafficControl::class);

            $banInfo = $service->getBanInfo($ip);
            if ($banInfo) {
                $response = $e->getResponse();
                $response->setStatusCode(403);
                $response->setContent('Access denied: ' . $banInfo['reason']);

                return $response;
            }

            if (! $unlimitedTraffic) { //  && ! $service->inWhiteList($ip)
                $service->pushHit($ip);
            }
        }
    }
}

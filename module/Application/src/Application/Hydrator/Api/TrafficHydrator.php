<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

use Autowp\User\Model\User;

class TrafficHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    protected $userId = null;

    private $acl;

    private $router;

    /**
     * @var User
     */
    private $userModel;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->router = $serviceManager->get('HttpRouter');
        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('up_to', $strategy);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('ban_user', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        return $this;
    }

    public function extract($object)
    {
        /*$row['users'] = $users->fetchAll([
            'last_ip = inet_aton(inet6_ntoa(?))' => $row['ip']
        ]);*/

        if ($object['ban']) {
            $date = \Autowp\Commons\Db\Table\Row::getDateTimeByColumnType('timestamp', $object['ban']['up_to']);
            $object['ban']['up_to'] = $this->extractValue('up_to', $date);
            $object['ban']['user'] = null;
            if ($object['ban']['by_user_id']) {
                $user = $this->userModel->getRow((int) $object['ban']['by_user_id']);
                if ($user) {
                    $object['ban']['user'] = $this->extractValue('ban_user', $user);
                }
            }
        }

        $object['whois_url'] = 'http://nic.ru/whois/?query='.urlencode($object['ip']);
        unset($object['ban']['ip']);


        return $object;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    public function setUserId($userId)
    {
        if ($this->userId != $userId) {
            $this->userId = $userId;
        }

        return $this;
    }
}

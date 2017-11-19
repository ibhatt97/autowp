<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Db\TableGateway\TableGateway;
use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;

class ArticleHydrator extends RestHydrator
{
    /**
     * @var User
     */
    private $userModel;

    /**
     * @var TableGateway
     */
    private $htmlTable;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->userModel = $serviceManager->get(User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('author', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('date', $strategy);

        $tables = $serviceManager->get('TableManager');
        $this->htmlTable = $tables->get('htmls');
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

        return $this;
    }

    public function extract($object)
    {
        $previewUrl = null;
        if ($object['preview_filename']) {
            $previewUrl = \Application\Controller\Api\ArticleController::PREVIEW_CAT_PATH . $object['preview_filename'];
        }

        $result = [
            'id'          => (int)$object['id'],
            'author_id'   => (int)$object['author_id'],
            'catname'     => $object['catname'],
            'preview_url' => $previewUrl,
            'name'        => $object['name'],
            'date'        => $this->extractValue('date', Row::getDateTimeByColumnType('timestamp', $object['first_enabled_datetime'])),
        ];

        if ($this->filterComposite->filter('author')) {
            $user = $this->userModel->getRow((int)$object['author_id']);

            $result['author'] = $user ? $this->extractValue('author', $user) : null;
        }

        if ($this->filterComposite->filter('description')) {
            $result['description'] = $object['description'];
        }

        if ($this->filterComposite->filter('html')) {
            $htmlRow = $this->htmlTable->select([
                'id' => (int)$object['html_id']
            ])->current();
            $result['html'] = $htmlRow ? $htmlRow['html'] : null;
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}

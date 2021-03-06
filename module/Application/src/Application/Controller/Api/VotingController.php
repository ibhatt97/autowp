<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Votings;

use Application\Hydrator\Api\RestHydrator;

class VotingController extends AbstractRestfulController
{
    /**
     * @var Votings\Votings
     */
    private $service;

    /**
     * @var InputFilter
     */
    private $variantVoteInputFilter;

    /**
     * @var RestHydrator
     */
    private $variantVoteHydrator;

    public function __construct(
        Votings\Votings $service,
        InputFilter $variantVoteInputFilter,
        RestHydrator $variantVoteHydrator
    ) {
        $this->service = $service;
        $this->variantVoteInputFilter = $variantVoteInputFilter;
        $this->variantVoteHydrator = $variantVoteHydrator;
    }

    public function getItemAction()
    {
        $id = (int)$this->params('id');
        $filter = (int)$this->params()->fromQuery('filter');

        $user = $this->user()->get();

        $data = $this->service->getVoting($id, $filter, $user ? (int)$user['id'] : 0);

        if (! $data) {
            return $this->notFoundAction();
        }

        $strategy = new DateTimeFormatterStrategy();

        $data['begin_date'] = $strategy->extract($data['begin_date']);
        $data['end_date'] = $strategy->extract($data['end_date']);

        return new JsonModel($data);
    }

    public function getVoteListAction()
    {
        $this->variantVoteInputFilter->setData($this->params()->fromQuery());

        if (! $this->variantVoteInputFilter->isValid()) {
            return $this->inputFilterResponse($this->variantVoteInputFilter);
        }

        $values = $this->variantVoteInputFilter->getValues();

        $rows = $this->service->getVotes($this->params('id'));

        $this->variantVoteHydrator->setOptions([
            'language' => $this->language(),
            'fields'   => isset($values['fields']) ? $values['fields'] : []
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->variantVoteHydrator->extract($row);
        }

        return new JsonModel([
            'items' => $result
        ]);
    }

    public function patchItemAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $id = (int)$this->params('id');

        $data = $this->processBodyContent($this->getRequest());
        $variant = isset($data['vote']) ? (array)$data['vote'] : [];

        $success = $this->service->vote(
            $id,
            $variant,
            $user['id']
        );
        if (! $success) {
            return $this->notFoundAction();
        }

        return $this->getResponse()->setStatusCode(200);
    }
}

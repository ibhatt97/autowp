<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use Autowp\Message\MessageService;
use Autowp\User\Model\User;

use Application\DuplicateFinder;
use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\CarOfDay;
use Application\Model\Item;
use Application\Model\Log;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use Application\Service\PictureService;
use Application\Service\TelegramService;

class PictureController extends AbstractRestfulController
{
    /**
     * @var CarOfDay
     */
    private $carOfDay;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var DuplicateFinder
     */
    private $duplicateFinder;

    /**
     * @var UserPicture
     */
    private $userPicture;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var InputFilter
     */
    private $itemInputFilter;

    /**
     * @var InputFilter
     */
    private $postInputFilter;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var InputFilter
     */
    private $publicListInputFilter;

    /**
     * @var InputFilter
     */
    private $editInputFilter;

    private $textStorage;

    /**
     * @var \Autowp\Comments\CommentsService
     */
    private $comments;

    /**
     * @var PictureModerVote
     */
    private $pictureModerVote;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var PictureService
     */
    private $pictureService;

    public function __construct(
        RestHydrator $hydrator,
        PictureItem $pictureItem,
        DuplicateFinder $duplicateFinder,
        UserPicture $userPicture,
        Log $log,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        CarOfDay $carOfDay,
        InputFilter $itemInputFilter,
        InputFilter $postInputFilter,
        InputFilter $listInputFilter,
        InputFilter $publicListInputFilter,
        InputFilter $editInputFilter,
        $textStorage,
        \Autowp\Comments\CommentsService $comments,
        PictureModerVote $pictureModerVote,
        Item $item,
        Picture $picture,
        User $userModel,
        PictureService $pictureService
    ) {
        $this->carOfDay = $carOfDay;

        $this->hydrator = $hydrator;
        $this->pictureItem = $pictureItem;
        $this->duplicateFinder = $duplicateFinder;
        $this->userPicture = $userPicture;
        $this->log = $log;
        $this->hostManager = $hostManager;
        $this->telegram = $telegram;
        $this->message = $message;
        $this->itemInputFilter = $itemInputFilter;
        $this->postInputFilter = $postInputFilter;
        $this->listInputFilter = $listInputFilter;
        $this->publicListInputFilter = $publicListInputFilter;
        $this->editInputFilter = $editInputFilter;
        $this->textStorage = $textStorage;
        $this->comments = $comments;
        $this->pictureModerVote = $pictureModerVote;
        $this->picture = $picture;
        $this->item = $item;
        $this->userModel = $userModel;
        $this->pictureService = $pictureService;
    }

    public function randomPictureAction()
    {
        $pictureRow = $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'order'  => 'random'
        ]);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow['image_id']);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow['identity'], true)
            ];
        }

        return new JsonModel($result);
    }


    public function newPictureAction()
    {
        $pictureRow = $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'order'  => 'accept_datetime_desc'
        ]);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow['image_id']);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow['identity'], true)
            ];
        }

        return new JsonModel($result);
    }


    public function carOfDayPictureAction()
    {
        $itemOfDay = $this->carOfDay->getCurrent();

        $pictureRow = null;

        if ($itemOfDay) {
            $carRow = $this->item->getRow(['id' => (int)$itemOfDay['item_id']]);
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $filter = [
                        'status' => Picture::STATUS_ACCEPTED,
                        'item'   => [
                            'ancestor_or_self' => $carRow['id']
                        ],
                        'order'  => 'resolution_desc'
                    ];

                    if ($groupId) {
                        $filter['item']['perspective'] = [
                            'group' => $groupId
                        ];
                        $filter['order'] = 'perspective_group';
                    }

                    $pictureRow = $this->picture->getRow($filter);
                    if ($pictureRow) {
                        break;
                    }
                }
            }
        }

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow['image_id']);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow['identity'], true)
            ];
        }

        return new JsonModel($result);
    }

    public function indexAction()
    {
        $isModer = $this->user()->inheritsRole('moder');

        $user = $this->user()->get();

        $inputFilter = $isModer ? $this->listInputFilter : $this->publicListInputFilter;
        $inputFilter->setData($this->params()->fromQuery());

        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $data = $inputFilter->getValues();

        if ($data['status'] == 'inbox' && ! $user) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'item_id' => [
                            'invalid' => 'inbox not allowed anonymously'
                        ]
                    ]
                ])
            );
        }

        if (! $isModer) {
            if (! $data['item_id'] && ! $data['owner_id'] && ! $data['status']) {
                return new ApiProblemResponse(
                    new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                        'invalid_params' => [
                            'item_id' => [
                                'invalid' => 'item_id or owner_id is required'
                            ]
                        ]
                    ])
                );
            }
        }

        $filter = [
            'timezone' => $this->user()->timezone()
        ];

        if ($data['item_id']) {
            $filter['item']['ancestor_or_self']['id'] = $data['item_id'];
        }

        if ($data['owner_id']) {
            $filter['user'] = $data['owner_id'];
        }

        $orders = [
            1 => 'add_date_desc',
            2 => 'add_date_asc',
            3 => 'resolution_desc',
            4 => 'resolution_asc',
            5 => 'filesize_desc',
            6 => 'filesize_asc',
            7 => 'comments',
            8 => 'views',
            9 => 'moder_votes',
            10 => 'similarity',
            11 => 'removing_date',
            12 => 'likes',
            13 => 'dislikes',
            14 => 'status',
            15 => 'accept_datetime_desc'
        ];

        switch ($data['order']) {
            case 13:
                $filter['has_dislikes'] = true;
                break;
        }

        if ($data['order']) {
            $filter['order'] = $orders[$data['order']];
        } else {
            $filter['order'] = $orders[1];
        }

        if (strlen($data['status'])) {
            switch ($data['status']) {
                case Picture::STATUS_INBOX:
                case Picture::STATUS_ACCEPTED:
                case Picture::STATUS_REMOVING:
                    $filter['status'] = $data['status'];
                    break;
                case 'custom1':
                    $filter['status'] = [
                    Picture::STATUS_INBOX,
                    Picture::STATUS_ACCEPTED
                    ];
                    break;
            }
        }

        if (strlen($data['add_date'])) {
            $filter['add_date'] = $data['add_date'];
        }

        if (strlen($data['accept_date'])) {
            $filter['accept_date'] = $data['accept_date'];
        }

        if ($data['perspective_id']) {
            if ($data['perspective_id'] == 'null') {
                $filter['item']['perspective_is_null'] = true;
            } else {
                $filter['item']['perspective'] = $data['perspective_id'];
            }
        }

        if ($data['exact_item_id']) {
            $filter['item']['id'] = $data['exact_item_id'];
        }

        if ($data['exact_item_link_type']) {
            $filter['item']['link_type'] = $data['exact_item_link_type'];
        }

        if ($isModer) {
            if (strlen($data['comments'])) {
                if ($data['comments'] == '1') {
                    $filter['has_comments'] = true;
                } elseif ($data['comments'] == '0') {
                    $filter['has_comments'] = false;
                }
            }

            if ($data['car_type_id']) {
                $filter['item']['vehicle_type'] = $data['car_type_id'];
            }

            if ($data['special_name']) {
                $filter['has_special_name'] = true;
            }

            if ($data['similar']) {
                $filter['has_similar'] = true;
                $data['order'] = 10;
            }

            if (strlen($data['requests'])) {
                switch ($data['requests']) {
                    case '0':
                        $filter['has_moder_votes'] = false;
                        break;

                    case '1':
                        $filter['has_accept_votes'] = true;
                        break;

                    case '2':
                        $filter['has_delete_votes'] = true;
                        break;

                    case '3':
                        $filter['has_moder_votes'] = true;
                        break;
                }
            }

            if (strlen($data['replace'])) {
                if ($data['replace'] == '1') {
                    $filter['is_replace'] = true;
                } elseif ($data['replace'] == '0') {
                    $filter['is_replace'] = false;
                }
            }

            if ($data['lost']) {
                $filter['is_lost'] = true;
            }

            if ($data['gps']) {
                $filter['has_point'] = true;
            }

            if ($data['added_from']) {
                $filter['added_from'] = $data['added_from'];
            }

            if ($data['exclude_item_id']) {
                $filter['item']['exclude_ancestor_or_self']['id'] = $data['exclude_item_id'];
            }
        }

        $paginator = $this->picture->getPaginator($filter);

        if (strlen($data['limit']) > 0) {
            $limit = (int)$data['limit'];
            $limit = $limit >= 0 ? $limit : 0;
        } else {
            $limit = 1;
        }

        $paginator
            ->setItemCountPerPage($limit ? $limit : 1)
            ->setCurrentPageNumber($data['page']);

        $result = [
            'paginator' => get_object_vars($paginator->getPages())
        ];

        if ($limit > 0) {
            $this->hydrator->setOptions([
                'language' => $this->language(),
                'user_id'  => $user ? $user['id'] : null,
                'fields'   => $data['fields'],
            ]);

            $pictures = [];
            foreach ($paginator->getCurrentItems() as $pictureRow) {
                $pictures[] = $this->hydrator->extract($pictureRow);
            }
            $result['pictures'] = $pictures;
        }

        return new JsonModel($result);
    }

    private function canAccept($picture)
    {
        return $this->picture->canAccept($picture)
            && $this->user()->isAllowed('picture', 'accept');
    }

    /**
     * @param array|\ArrayObject $user
     * @param bool $full
     * @param \Zend\Uri\Uri $uri
     * @return string
     */
    private function userModerUrl($user, $full = false, $uri = null)
    {
        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);
    }

    private function pictureUrl($picture, $forceCanonical = false, $uri = null)
    {
        return $this->url()->fromRoute('index', [], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]) . 'ng/moder/pictures/' . $picture['id'];
    }

    public function postAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $data = array_merge(
            $this->params()->fromPost(),
            $this->getRequest()->getFiles()->toArray()
        );

        $this->postInputFilter->setData($data);
        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $values = $this->postInputFilter->getValues();

        $itemId = (int)$values['item_id'];
        $replacePictureId = (int)$values['replace_picture_id'];
        $perspectiveId = (int)$values['perspective_id'];

        if (! $itemId && ! $replacePictureId) {
            return new ApiProblemResponse(
                new ApiProblem(400, 'Data is invalid. Check `detail`.', null, 'Validation error', [
                    'invalid_params' => [
                        'item_id' => [
                            'invalid' => 'item_id or replace_picture_id is required'
                        ]
                    ]
                ])
            );
        }

        $picture = $this->pictureService->addPictureFromFile(
            $values['file']['tmp_name'],
            $user['id'],
            $this->getRequest()->getServer('REMOTE_ADDR'),
            $itemId,
            $perspectiveId,
            $replacePictureId,
            (string)$values['comment']
        );

        $url = $this->url()->fromRoute('api/picture/picture/item', [
            'id' => $picture['id']
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        return $this->getResponse()->setStatusCode(201);
    }

    public function updateAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int)$this->params('id')]);

        if (! $picture) {
            return $this->notFoundAction();
        }

        $data = (array)$this->processBodyContent($this->getRequest());
        $validationGroup = array_keys($data); // TODO: intersect with real keys
        if (! $validationGroup) {
            return $this->forbiddenAction();
        }
        $this->editInputFilter->setValidationGroup($validationGroup);
        $this->editInputFilter->setData($data);

        if (! $this->editInputFilter->isValid()) {
            return $this->inputFilterResponse($this->editInputFilter);
        }

        $data = $this->editInputFilter->getValues();

        $isModer = $this->user()->inheritsRole('moder');

        $set = [];

        if (isset($data['crop'])) {
            $canCrop = $this->user()->isAllowed('picture', 'crop')
                    || ($picture['owner_id'] == $user['id']) && ($picture['status'] == Picture::STATUS_INBOX);

            if (! $canCrop) {
                return $this->forbiddenAction();
            }

            $left = round($data['crop']['left']);
            $top = round($data['crop']['top']);
            $width = round($data['crop']['width']);
            $height = round($data['crop']['height']);

            $left = max(0, $left);
            $left = min($picture['width'], $left);
            $width = max(1, $width);
            $width = min($picture['width'], $width);

            $top = max(0, $top);
            $top = min($picture['height'], $top);
            $height = max(1, $height);
            $height = min($picture['height'], $height);

            $crop = null;
            if ($left > 0 || $top > 0 || $width < $picture['width'] || $height < $picture['height']) {
                $crop = [
                    'left'   => $left,
                    'top'    => $top,
                    'width'  => $width,
                    'height' => $height
                ];
            }

            $this->imageStorage()->setImageCrop($picture['image_id'], $crop);

            $this->log(sprintf(
                'Выделение области на картинке %s',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id']
            ]);
        }

        if ($isModer) {
            if (array_key_exists('replace_picture_id', $data)) {
                if ($picture['replace_picture_id'] && ! $data['replace_picture_id']) {
                    $replacePicture = $this->picture->getRow(['id' => (int)$picture['replace_picture_id']]);
                    if (! $replacePicture) {
                        return $this->notFoundAction();
                    }

                    if (! $this->user()->isAllowed('picture', 'move')) {
                        return $this->forbiddenAction();
                    }

                    $set['replace_picture_id'] = null;

                    // log
                    $this->log(sprintf(
                        'Замена %s на %s отклонена',
                        htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => [$picture['id'], $replacePicture['id']]
                    ]);
                }
            }

            if (isset($data['special_name'])) {
                $set['name'] = $data['special_name'];
            }

            if (isset($data['copyrights'])) {
                $text = $data['copyrights'];

                $user = $this->user()->get();

                if ($picture['copyrights_text_id']) {
                    $this->textStorage->setText($picture['copyrights_text_id'], $text, $user['id']);
                } elseif ($text) {
                    $textId = $this->textStorage->createText($text, $user['id']);
                    $set['copyrights_text_id'] = $textId;
                }

                $this->log(sprintf(
                    'Редактирование текста копирайтов изображения %s',
                    htmlspecialchars($this->pic()->name($picture, $this->language()))
                ), [
                    'pictures' => $picture['id']
                ]);

                if ($picture['copyrights_text_id']) {
                    $userIds = $this->textStorage->getTextUserIds($picture['copyrights_text_id']);

                    foreach ($userIds as $userId) {
                        if ($userId != $user['id']) {
                            $userRow = $this->userModel->getRow((int)$userId);
                            if ($userRow) {
                                $uri = $this->hostManager->getUriByLanguage($userRow['language']);

                                $message = sprintf(
                                    $this->translate(
                                        'pm/user-%s-edited-picture-copyrights-%s-%s',
                                        'default',
                                        $userRow['language']
                                    ),
                                    $this->userModerUrl($user, true, $uri),
                                    $this->pic()->name($picture, $userRow['language']),
                                    $this->pictureUrl($picture, true, $uri)
                                );

                                $this->message->send(null, $userRow['id'], $message);
                            }
                        }
                    }
                }
            }

            if (isset($data['status'])) {
                $user = $this->user()->get();
                $previousStatusUserId = $picture['change_status_user_id'];

                if ($data['status'] == Picture::STATUS_ACCEPTED) {
                    $canAccept = $this->canAccept($picture);

                    if (! $canAccept) {
                        return $this->forbiddenAction();
                    }

                    $success = $this->picture->accept($picture['id'], $user['id'], $isFirstTimeAccepted);
                    if ($success) {
                        $owner = $this->userModel->getRow((int)$picture['owner_id']);

                        if ($owner) {
                            $this->userPicture->refreshPicturesCount($owner['id']);
                        }

                        if ($isFirstTimeAccepted) {
                            if ($owner && ($owner['id'] != $user['id'])) {
                                $uri = $this->hostManager->getUriByLanguage($owner['language']);

                                $message = sprintf(
                                    $this->translate('pm/your-picture-accepted-%s', 'default', $owner['language']),
                                    $this->pic()->url($picture['identity'], true, $uri)
                                );

                                $this->message->send(null, $owner['id'], $message);
                            }

                            $this->telegram->notifyPicture($picture['id']);
                        }
                    }


                    if ($previousStatusUserId != $user['id']) {
                        $prevUser = $this->userModel->getRow((int)$previousStatusUserId);
                        if ($prevUser) {
                            $message = sprintf(
                                'Принята картинка %s',
                                $this->pic()->url($picture['identity'], true)
                            );
                            $this->message->send(null, $prevUser['id'], $message);
                        }
                    }

                    $this->log(sprintf(
                        'Картинка %s принята',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => $picture['id']
                    ]);
                }

                if ($data['status'] == Picture::STATUS_INBOX) {
                    if ($picture['status'] == Picture::STATUS_REMOVING) {
                        $canRestore = $this->user()->isAllowed('picture', 'restore');
                        if (! $canRestore) {
                            return $this->forbiddenAction();
                        }

                        $set = array_replace($set, [
                            'status'                => Picture::STATUS_INBOX,
                            'change_status_user_id' => $user['id']
                        ]);

                        $this->log(sprintf(
                            'Картинки `%s` восстановлена из очереди удаления',
                            htmlspecialchars($this->pic()->name($picture, $this->language()))
                        ), [
                            'pictures' => $picture['id']
                        ]);
                    } elseif ($picture['status'] == Picture::STATUS_ACCEPTED) {
                        $canUnaccept = $this->user()->isAllowed('picture', 'unaccept');
                        if (! $canUnaccept) {
                            return $this->forbiddenAction();
                        }

                        $this->picture->getTable()->update([
                            'status'                => Picture::STATUS_INBOX,
                            'change_status_user_id' => $user['id']
                        ], [
                            'id' => $picture['id']
                        ]);

                        if ($picture['owner_id']) {
                            $this->userPicture->refreshPicturesCount($picture['owner_id']);
                        }

                        $this->log(sprintf(
                            'С картинки %s снят статус "принято"',
                            htmlspecialchars($this->pic()->name($picture, $this->language()))
                        ), [
                            'pictures' => $picture['id']
                        ]);


                        $pictureUrl = $this->pic()->url($picture['identity'], true);
                        if ($previousStatusUserId != $user['id']) {
                            $prevUser = $this->userModel->getRow((int)$previousStatusUserId);
                            if ($prevUser) {
                                $message = sprintf(
                                    'С картинки %s снят статус "принято"',
                                    $pictureUrl
                                );
                                $this->message->send(null, $prevUser['id'], $message);
                            }
                        }
                    }
                }

                if ($data['status'] == Picture::STATUS_REMOVING) {
                    $canDelete = $this->pictureCanDelete($picture);
                    if (! $canDelete) {
                        return $this->forbiddenAction();
                    }

                    $user = $this->user()->get();
                    $set = array_replace($set, [
                        'status'                => Picture::STATUS_REMOVING,
                        'removing_date'         => new Sql\Expression('CURDATE()'),
                        'change_status_user_id' => $user['id']
                    ]);

                    $owner = $this->userModel->getRow((int)$picture['owner_id']);
                    if ($owner && $owner['id'] != $user['id']) {
                        $uri = $this->hostManager->getUriByLanguage($owner['language']);

                        $deleteRequests = $this->pictureModerVote->getNegativeVotes($picture['id']);

                        $reasons = [];
                        foreach ($deleteRequests as $request) {
                            $user = $this->userModel->getRow((int)$request['user_id']);
                            if ($user) {
                                $reasons[] = $this->userModerUrl($user, true, $uri) . ' : ' . $request['reason'];
                            }
                        }

                        $message = sprintf(
                            $this->translate('pm/your-picture-%s-enqueued-to-remove-%s', 'default', $owner['language']),
                            $this->pic()->url($picture['identity'], true, $uri),
                            implode("\n", $reasons)
                        );

                        $this->message->send(null, $owner['id'], $message);
                    }

                    $this->log(sprintf(
                        'Картинка %s поставлена в очередь на удаление',
                        htmlspecialchars($this->pic()->name($picture, $this->language()))
                    ), [
                        'pictures' => $picture['id']
                    ]);
                }
            }
        }

        if ($set) {
            $this->picture->getTable()->update($set, [
                'id' => $picture['id']
            ]);
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function itemAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => $data['fields']
        ]);

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->hydrator->extract($row));
    }

    private function pictureCanDelete($picture)
    {
        if (! $this->picture->canDelete($picture)) {
            return false;
        }

        $canDelete = false;
        $user = $this->user()->get();
        if ($this->user()->isAllowed('picture', 'remove')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $canDelete = true;
            }
        } elseif ($this->user()->isAllowed('picture', 'remove_by_vote')) {
            if ($this->pictureModerVote->hasVote($picture['id'], $user['id'])) {
                $acceptVotes = $this->pictureModerVote->getPositiveVotesCount($picture['id']);
                $deleteVotes = $this->pictureModerVote->getNegativeVotesCount($picture['id']);

                $canDelete = ($deleteVotes > $acceptVotes);
            }
        }

        return $canDelete;
    }

    public function normalizeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        $canNormalize = $row['status'] == Picture::STATUS_INBOX
                     && $this->user()->isAllowed('picture', 'normalize');

        if (! $canNormalize) {
            return $this->forbiddenAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->normalize($row['image_id']);
        }

        $this->log(sprintf(
            'К картинке %s применён normalize',
            htmlspecialchars($this->pic()->name($row, $this->language()))
        ), [
            'pictures' => $row['id']
        ]);

        return $this->getResponse()->setStatusCode(200);
    }

    public function flopAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        $canFlop = $row['status'] == Picture::STATUS_INBOX
                && $this->user()->isAllowed('picture', 'flop');

        if (! $canFlop) {
            return $this->forbiddenAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->flop($row['image_id']);
        }

        $this->log(sprintf(
            'К картинке %s применён flop',
            htmlspecialchars($this->pic()->name($row, $this->language()))
        ), [
            'pictures' => $row['id']
        ]);

        return $this->getResponse()->setStatusCode(200);
    }

    public function repairAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->flush([
                'image' => $row['image_id']
            ]);
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function correctFileNamesAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $row = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $row) {
            return $this->notFoundAction();
        }

        if ($row['image_id']) {
            $this->imageStorage()->changeImageName($row['image_id'], [
                'pattern' => $this->picture->getFileNamePattern($row['id'])
            ]);
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function deleteSimilarAction()
    {
        $srcPicture = $this->picture->getRow(['id' => (int)$this->params('id')]);
        $dstPicture = $this->picture->getRow(['id' => (int)$this->params('similar_picture_id')]);

        if (! $srcPicture || ! $dstPicture) {
            return $this->notFoundAction();
        }

        $this->duplicateFinder->hideSimilar($srcPicture['id'], $dstPicture['id']);

        $this->log('Отменёно предупреждение о повторе', [
            'pictures' => [$srcPicture['id'], $dstPicture['id']]
        ]);

        return $this->getResponse()->setStatusCode(204);
    }

    private function canReplace($picture, $replacedPicture)
    {
        $can1 = false;
        switch ($picture['status']) {
            case Picture::STATUS_ACCEPTED:
                $can1 = true;
                break;

            case Picture::STATUS_INBOX:
                $can1 = $this->user()->isAllowed('picture', 'accept');
                break;
        }

        $can2 = false;
        switch ($replacedPicture['status']) {
            case Picture::STATUS_ACCEPTED:
                $can2 = $this->user()->isAllowed('picture', 'unaccept')
                     && $this->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_INBOX:
                $can2 = $this->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_REMOVING:
            case Picture::STATUS_REMOVED:
                $can2 = true;
                break;
        }

        return $can1 && $can2 && $this->user()->isAllowed('picture', 'move');
    }

    public function acceptReplaceAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $picture = $this->picture->getRow(['id' => (int)$this->params('id')]);
        if (! $picture) {
            return $this->notFoundAction();
        }

        if (! $picture['replace_picture_id']) {
            return $this->notFoundAction();
        }

        $replacePicture = $this->picture->getRow(['id' => (int)$picture['replace_picture_id']]);
        if (! $replacePicture) {
            return $this->notFoundAction();
        }

        if (! $this->canReplace($picture, $replacePicture)) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        // statuses
        if ($picture['status'] != Picture::STATUS_ACCEPTED) {
            $set = [
                'status'                => Picture::STATUS_ACCEPTED,
                'change_status_user_id' => $user['id']
            ];
            if (! $picture['accept_datetime']) {
                $set['accept_datetime'] = new Sql\Expression('NOW()');
            }

            $this->picture->getTable()->update($set, [
                'id' => $picture['id']
            ]);

            if ($picture['owner_id']) {
                $this->userPicture->refreshPicturesCount($picture['owner_id']);
            }
        }

        if (! in_array($replacePicture['status'], [Picture::STATUS_REMOVING, Picture::STATUS_REMOVED])) {
            $this->picture->getTable()->update([
                'status'                => Picture::STATUS_REMOVING,
                'removing_date'         => new Sql\Expression('now()'),
                'change_status_user_id' => $user['id']
            ], [
                'id' => $replacePicture['id']
            ]);
            if ($replacePicture['owner_id']) {
                $this->userPicture->refreshPicturesCount($replacePicture['owner_id']);
            }
        }

        // comments
        $this->comments->moveMessages(
            \Application\Comments::PICTURES_TYPE_ID,
            $replacePicture['id'],
            \Application\Comments::PICTURES_TYPE_ID,
            $picture['id']
        );

        // pms
        $owner = $this->userModel->getRow((int)$picture['owner_id']);
        $replaceOwner = $this->userModel->getRow((int)$replacePicture['owner_id']);
        $recepients = [];
        if ($owner) {
            $recepients[$owner['id']] = $owner;
        }
        if ($replaceOwner) {
            $recepients[$replaceOwner['id']] = $replaceOwner;
        }
        unset($recepients[$user['id']]);
        if ($recepients) {
            foreach ($recepients as $recepient) {
                $uri = $this->hostManager->getUriByLanguage($recepient['language']);

                $url = $this->pic()->url($picture['identity'], true, $uri);
                $replaceUrl = $this->pic()->url($replacePicture['identity'], true, $uri);

                $moderUrl = $this->url()->fromRoute('ng', ['path' => ''], [
                    'force_canonical' => true,
                    'uri'             => $uri
                ]) . 'users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']);

                $message = sprintf(
                    $this->translate('pm/user-%s-accept-replace-%s-%s', 'default', $recepient['language']),
                    $moderUrl,
                    $replaceUrl,
                    $url
                );

                $this->message->send(null, $recepient['id'], $message);
            }
        }

        // log
        $this->log(sprintf(
            'Замена %s на %s',
            htmlspecialchars($this->pic()->name($replacePicture, $this->language())),
            htmlspecialchars($this->pic()->name($picture, $this->language()))
        ), [
            'pictures' => [$picture['id'], $replacePicture['id']]
        ]);

        return $this->getResponse()->setStatusCode(200);
    }

    public function userSummaryAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $acceptedCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'user'   => $user['id']
        ]);

        $inboxCount = $this->picture->getCount([
            'status' => Picture::STATUS_INBOX,
            'user'   => $user['id']
        ]);

        return new JsonModel([
            'inboxCount'    => $inboxCount,
            'acceptedCount' => $acceptedCount
        ]);
    }
}

<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Model\DbTable\BrandLanguage;
use Application\Model\DbTable\BrandLink;
use Application\Model\DbTable\BrandRow;
use Application\Model\DbTable\Vehicle;
use Application\Model\Message;

class BrandsController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var Form
     */
    private $brandForm;

    /**
     * @var Form
     */
    private $logoForm;

    /**
     * @var Form
     */
    private $descForm;

    /**
     * @var Form
     */
    private $editForm;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var Message
     */
    private $message;

    public function __construct(
        HostManager $hostManager,
        $textStorage,
        Form $brandForm,
        Form $logoForm,
        Form $descForm,
        Form $editForm,
        Message $message
    ) {

        $this->hostManager = $hostManager;
        $this->textStorage = $textStorage;
        $this->brandForm = $brandForm;
        $this->logoForm = $logoForm;
        $this->descForm = $descForm;
        $this->editForm = $editForm;
        $this->message = $message;
    }

    private function getLanguages()
    {
        return [
            'ru', 'en', 'fr', 'zh'
        ];
    }

    public function indexAction()
    {
        return $this->notFoundAction();
    }

    /**
     * @param BrandRow $car
     * @param bool $forceCanonical
     * @param \Zend\Uri\Uri
     * @return string
     */
    private function brandModerUrl(BrandRow $brand, $forceCanonical, $uri = null)
    {
        return $this->url()->fromRoute('moder/brands/params', [
            'action'   => 'brand',
            'brand_id' => $brand->id
        ], [
            'force_canonical' => $forceCanonical,
            'uri'             => $uri
        ]);
    }

    public function brandAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $brand = $this->catalogue()->getBrandTable()->find($this->params('brand_id'))->current();
        if (! $brand) {
            return $this->notFoundAction();
        }

        $canEdit = $this->user()->isAllowed('brand', 'edit');
        $canLogo = $this->user()->isAllowed('brand', 'logo');
        $canDeleteModel = $this->user()->isAllowed('model', 'delete');

        $picture = null;
        $formBrandEdit = null;
        $formLogo = null;

        $request = $this->getRequest();

        if ($canEdit) {
            $form = new \Application\Form\Moder\Brand\Edit(null, [
                'languages' => $this->getLanguages()
            ]);
            $form->setAttribute('action', $this->url()->fromRoute('moder/brands/params', [
                'action'   => 'brand',
                'brand_id' => $brand->id,
                'form'     => 'edit'
            ]));



            $values = [
                'name'      => $brand->name,
                'full_name' => $brand->full_name,
            ];

            $brandLangTable = new BrandLanguage();
            foreach ($this->getLanguages() as $language) {
                $brandLangRow = $brandLangTable->fetchRow([
                    'brand_id = ?' => $brand->id,
                    'language = ?' => $language
                ]);
                if ($brandLangRow) {
                    $values['name' . $language] = $brandLangRow->name;
                }
            }

            $form->setData($values);

            if ($request->isPost() && $this->params('form') == 'edit') {
                $form->setData($this->params()->fromPost());
                if ($form->isValid()) {
                    $values = $form->getData();

                    $brand->setFromArray([
                        'full_name' => $values['full_name'],
                    ]);

                    $brand->save();

                    foreach ($this->getLanguages() as $language) {
                        $value = $values['name' . $language];
                        $brandLangRow = $brandLangTable->fetchRow([
                            'brand_id = ?' => $brand->id,
                            'language = ?' => $language
                        ]);
                        if ($value) {
                            if (! $brandLangRow) {
                                $brandLangRow = $brandLangTable->fetchNew();
                                $brandLangRow->setFromArray([
                                    'brand_id' => $brand->id,
                                    'language' => $language
                                ]);
                            }
                            $brandLangRow->name = $value;
                            $brandLangRow->save();
                        } else {
                            if ($brandLangRow) {
                                $brandLangRow->delete();
                            }
                        }
                    }

                    $this->log(sprintf(
                        'Редактирование информации о %s',
                        $brand->name
                    ), $brand);

                    return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
                }
            }

            $formBrandEdit = $form;
        }

        if ($canLogo) {
            $this->logoForm->setAttribute('action', $this->url()->fromRoute('moder/brands/params', [
                'action'   => 'brand',
                'brand_id' => $brand->id,
                'form'     => 'logo'
            ]));

            if ($request->isPost() && $this->params('form') == 'logo') {
                $data = array_merge_recursive(
                    $this->getRequest()->getPost()->toArray(),
                    $this->getRequest()->getFiles()->toArray()
                );
                $this->logoForm->setData($data);
                if ($this->logoForm->isValid()) {
                    $tempFilepath = $data['logo']['tmp_name'];

                    $imageStorage = $this->imageStorage();

                    $oldImageId = $brand->img;

                    $newImageId = $imageStorage->addImageFromFile($tempFilepath, 'brand');
                    $brand->img = $newImageId;
                    $brand->save();

                    if ($oldImageId) {
                        $imageStorage->removeImage($oldImageId);
                    }

                    $this->log(sprintf(
                        'Закачен логотип %s',
                        htmlspecialchars($brand->name)
                    ), $brand);

                    $this->flashMessenger()->addSuccessMessage($this->translate('moder/brands/logo/saved'));

                    return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
                }
            }
        }

        $carTable = new Vehicle();
        $cars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->order('cars.name')
        );

        $this->descForm->setAttribute('action', $this->url()->fromRoute('moder/brands/params', [
            'action'   => 'save-description',
            'brand_id' => $brand['id']
        ]));

        if ($brand->text_id) {
            $textStorage = $this->textStorage;
            $description = $textStorage->getText($brand->text_id);
            if ($canEdit) {
                $this->descForm->setData([
                    'markdown' => $description
                ]);
            }
        } else {
            $description = '';
        }

        $linkTable = new BrandLink();
        $linkRows = $linkTable->fetchAll([
            'brandId = ?' => $brand->id
        ]);

        $links = [];
        foreach ($linkRows as $link) {
            $links[] = [
                'id'   => $link->id,
                'name' => $link->name,
                'url'  => $link->url,
                'type' => $link->type
            ];
        }

        return [
            'brand'                 => $brand,
            'canEdit'               => $canEdit,
            'canDeleteModel'        => $canDeleteModel,
            'canLogo'               => $canLogo,
            'description'           => $description,
            'descriptionForm'       => $this->descForm,
            'links'                 => $links,
            'formBrandEdit'         => $formBrandEdit,
            'formLogo'              => $this->logoForm,
            'cars'                  => $cars
        ];
    }

    public function saveLinksAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $brand = $this->catalogue()->getBrandTable()->find($this->params('brand_id'))->current();
        if (! $brand) {
            return $this->notFoundAction();
        }

        $canEdit = $this->user()->isAllowed('brand', 'edit');

        $links = new BrandLink();

        foreach ($this->params()->fromPost('link') as $id => $link) {
            $row = $links->find($id)->current();
            if ($row) {
                if (strlen($link['url'])) {
                    $row->name = $link['name'];
                    $row->url = $link['url'];
                    $row->type = $link['type'];

                    $row->save();
                } else {
                    $row->delete();
                }
            }
        }

        if ($new = $this->params()->fromPost('new')) {
            if (strlen($new['url'])) {
                $row = $links->fetchNew();
                $row->brandId = $brand->id;
                $row->name = $new['name'];
                $row->url = $new['url'];
                $row->type = $new['type'];

                $row->save();
            }
        }

        return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
    }

    public function saveDescriptionAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $canEdit = $this->user()->isAllowed('brand', 'edit');
        if (! $canEdit) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->forbiddenAction();
        }

        $brand = $this->catalogue()->getBrandTable()->find($this->params('brand_id'))->current();
        if (! $brand) {
            return $this->notFoundAction();
        }

        $this->descForm->setData($this->params()->fromPost());
        if ($this->descForm->isValid()) {
            $values = $this->descForm->getData();

            $text = $values['markdown'];

            $textStorage = $this->textStorage;

            if ($brand->text_id) {
                $textStorage->setText($brand->text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $textStorage->createText($text, $user->id);
                $brand->text_id = $textId;
                $brand->save();
            }


            $this->log(sprintf(
                'Редактирование описания бренда %s',
                $brand->name
            ), $brand);

            if ($brand->text_id) {
                $userIds = $textStorage->getTextUserIds($brand->text_id);

                $userTable = new User();

                foreach ($userTable->find($userIds) as $userRow) {
                    if ($userRow->id != $user->id) {
                        $uri = $this->hostManager->getUriByLanguage($userRow->language);

                        $userUrl = $this->url()->fromRoute('users/user', [
                            'user_id' => $user->identity ? $user->identity : 'user' . $user->id
                        ], [
                            'force_canonical' => true,
                            'uri'             => $uri
                        ]);

                        $message = sprintf(
                            $this->translate(
                                'pm/user-%s-edited-brand-description-%s-%s',
                                'default',
                                $userRow->language
                            ),
                            $userUrl,
                            $brand->name, //TODO: translate brand name
                            $this->brandModerUrl($brand, true, $uri)
                        );

                        $this->message->send(null, $userRow->id, $message);
                    }
                }
            }
        }

        return $this->redirect()->toUrl($this->brandModerUrl($brand, true));
    }
}

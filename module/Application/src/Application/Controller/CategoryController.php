<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\Categories;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Service\SpecificationsService;

class CategoryController extends AbstractActionController
{
    private $cache;

    private $textStorage;

    /**
     * @var Categories
     */
    private $categories;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        $cache,
        $textStorage,
        Categories $categories,
        SpecificationsService $specsService,
        Perspective $perspective,
        Item $itemModel,
        ItemParent $itemParent,
        Picture $picture
    ) {
        $this->cache = $cache;
        $this->textStorage = $textStorage;
        $this->categories = $categories;
        $this->specsService = $specsService;
        $this->perspective = $perspective;
        $this->itemModel = $itemModel;
        $this->itemParent = $itemParent;
        $this->picture = $picture;
    }

    public function indexAction()
    {
        $language = $this->language();

        $key = 'CATEGORY_INDEX48_' . $language;

        $categories = $this->cache->getItem($key, $success);
        if (! $success) {
            $categories = [];

            $rows = $this->itemModel->getRows([
                'item_type_id' => Item::CATEGORY,
                'no_parents'   => true,
                'order'        => 'name'
            ]);

            foreach ($rows as $row) {
                $langName = $this->itemModel->getName($row['id'], $language);
                $carsCount = $this->itemModel->getVehiclesAndEnginesCount($row['id']);

                $categories[] = [
                    'id'             => $row['id'],
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row['catname'],
                    ]),
                    'name'           => $langName ? $langName : $row['name'],
                    'short_name'     => $langName ? $langName : $row['name'],
                    'cars_count'     => $carsCount,
                    'new_cars_count' => $carsCount //$row->getWeekCarsCount(),
                ];
            }

            $this->cache->setItem($key, $categories);
        }

        foreach ($categories as &$category) {
            $picture = $this->picture->getRow([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'ancestor_or_self' => $category['id']
                ],
                'order'  => 'front_angle'
            ]);

            $image = null;
            if ($picture) {
                $image = $this->imageStorage()->getFormatedImage(
                    $picture['image_id'],
                    'picture-thumb'
                );
            }

            $category['top_picture'] = [
                'image' => $image
            ];
        }

        return [
            'categories' => $categories
        ];
    }

    private function categoriesMenuActive(&$menu, $categoryParentIds, $isOther)
    {
        $activeFound = false;
        foreach ($menu as &$item) {
            $item['active'] = false;

            if (($item['isOther'] ? $isOther : ! $isOther) && in_array($item['id'], $categoryParentIds)) {
                $activeFound = true;
                $item['active'] = true;
            }
            if ($this->categoriesMenuActive($item['categories'], $categoryParentIds, $isOther)) {
                $activeFound = true;
                $item['active'] = true;
            }
        }

        return $activeFound;
    }

    private function categoriesMenu($parent, $language, $maxDeep)
    {
        $categories = [];

        $otherCategoriesName = $this->translate('categories/other');

        if ($maxDeep > 0) {
            $categories = $this->categories->getCategoriesList($parent['id'], $language, null, 'name');

            foreach ($categories as &$category) {
                $category['categories'] = $this->categoriesMenu($category, $language, $maxDeep - 1);
                $category['isOther'] = false;
            }
            unset($category); // prevent bugs

            if ($parent && count($categories)) {
                $ownCarsCount = $this->itemModel->getCount([
                    'item_type_id' => [Item::ENGINE, Item::VEHICLE],
                    'is_group'     => false,
                    'parent'       => $parent['id']
                ]);
                if ($ownCarsCount > 0) {
                    $categories[] = [
                        'id'             => $parent['id'],
                        'url'            => $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $parent['catname'],
                            'other'            => true,
                            'page'             => null
                        ]),
                        'short_name'     => $otherCategoriesName,
                        'cars_count'     => $ownCarsCount,
                        'new_cars_count' => 0, //$parent->getWeekOwnCarsCount(),
                        'isOther'        => true,
                        'categories'     => []
                    ];
                }
            }
        }

        usort($categories, function ($a, $b) use ($otherCategoriesName) {
            if ($a["short_name"] == $otherCategoriesName) {
                return 1;
            }
            if ($b["short_name"] == $otherCategoriesName) {
                return -1;
            }
            return strcmp($a["short_name"], $b["short_name"]);
        });

        return $categories;
    }

    private function doCategoryAction($callback)
    {
        $language = $this->language();

        $currentCategory = $this->itemModel->getRow([
            'catname' => (string)$this->params('category_catname')
        ]);
        $isOther = (bool)$this->params('other');

        if (! $currentCategory) {
            return $this->notFoundAction();
        }

        $langName = $this->itemModel->getName($currentCategory['id'], $language);

        $breadcrumbs = [[
            'name' => $langName ? $langName : $currentCategory['name'],
            'url'  => $this->url()->fromRoute('categories', [
                'action'           => 'category',
                'category_catname' => $currentCategory['catname'],
                'other'            => false,
                'path'             => [],
                'page'             => 1
            ])
        ]];

        $topCategory = $currentCategory;

        while (true) {
            $parentCategory = $this->itemModel->getRow([
                'child' => $topCategory['id']
            ]);

            if (! $parentCategory) {
                break;
            }

            $topCategory = $parentCategory;

            $cLangName = $this->itemModel->getName($parentCategory['id'], $language);

            array_unshift($breadcrumbs, [
                'name' => $cLangName ? $cLangName : $parentCategory['name'],
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $parentCategory['catname'],
                    'other'            => false,
                    'path'             => [],
                    'page'             => 1
                ])
            ]);
        }

        $path = $this->params('path');
        $path = $path ? (array)$path : [];

        $currentCar = $currentCategory;

        $breadcrumbsPath = [];

        foreach ($path as $pathNode) {
            $childCar = $this->itemModel->getRow([
                'parent' => [
                    'id'           => $currentCar['id'],
                    'link_catname' => $pathNode
                ]
            ]);

            if (! $childCar) {
                return $this->notFoundAction();
            }

            $breadcrumbsPath[] = $pathNode;

            $breadcrumbs[] = [
                'name' => $this->car()->formatName($childCar, $language),
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $currentCategory['catname'],
                    'other'            => false,
                    'path'             => $breadcrumbsPath,
                    'page'             => 1
                ])
            ];

            $currentCar = $childCar;
        }

        $key = 'CATEGORY_MENU344_' . $topCategory['id'] . '_' . $language;

        $menu = $this->cache->getItem($key, $success);
        if (! $success) {
            $menu = $this->categoriesMenu($topCategory, $language, 2);

            $this->cache->setItem($key, $menu);
        }

        $categoryParentIds = $this->itemModel->getIds([
            'descendant_or_self' => $currentCategory['id']
        ]);

        $this->categoriesMenuActive($menu, $categoryParentIds, $isOther);

        $sideBarModel = new ViewModel([
            'categories' => $menu,
            'category'   => $currentCategory,
            'isOther'    => $isOther,
            'deep'       => 1
        ]);
        $sideBarModel->setTemplate('application/category/menu');
        $this->layout()->addChild($sideBarModel, 'sidebar');

        $currentItem = $currentCar ? $currentCar : $currentCategory;
        $currentItemNameData = $this->itemModel->getNameData($currentItem, $language);

        $data = [
            'category'            => $currentCategory,
            'categoryName'        => $langName ? $langName : $currentCategory['name'],
            'isOther'             => $isOther,
            'currentItem'         => $currentItem,
            'currentItemNameData' => $currentItemNameData
        ];


        $result = $callback(
            $currentCategory,
            $currentCar,
            $isOther,
            $path,
            $breadcrumbs,
            $langName ? $langName : $currentCategory['name']
        );

        if (is_array($result)) {
            return array_replace($data, $result);
        }

        return $result;
    }

    public function categoryAction()
    {
        return $this->doCategoryAction(function (
            $currentCategory,
            $currentCar,
            $isOther,
            $path,
            $breadcrumbs,
            $currentCategoryName
        ) {

            $language = $this->language();

            $haveSubcategories = $this->itemModel->isExists([
                'item_type_id' => Item::CATEGORY,
                'parent'       => $currentCategory['id']
            ]);

            $paginator = $this->itemModel->getPaginator([
                'parent'               => $currentCar ? $currentCar['id'] : $currentCategory['id'],
                'order'                => $this->catalogue()->itemOrdering(),
                'item_type_id'         => (! $isOther) && $haveSubcategories ? Item::CATEGORY : null,
                'item_type_id_exclude' => $isOther ? Item::CATEGORY : null
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            $contributors = [];
            /*$contributors = $users->fetchAll(
                $users->select(true)
                    ->join('category_item', 'users.id = category_item.user_id', [])
                    ->join('category_parent', 'category_item.category_id = category_parent.category_id', [])
                    ->where('category_parent.parent_id = ?', $currentCategory['id'])
                    ->where('not users.deleted')
                    ->group('users.id')
            );*/

            $title = '';
            if ($currentCar) {
                $title = $this->car()->formatName($currentCar, $language);
            } else {
                $title = $currentCategoryName;
            }

            $listBuilder = new \Application\Model\Item\ListBuilder\Category([
                'catalogue'    => $this->catalogue(),
                'router'       => $this->getEvent()->getRouter(),
                'picHelper'    => $this->getPluginManager()->get('pic'),
                'itemParent'   => $this->itemParent,
                'currentItem'  => $currentCar,
                'category'     => $currentCategory,
                'isOther'      => $isOther,
                'path'         => $path,
                'specsService' => $this->specsService
            ]);

            if ($currentCar && $paginator->getTotalItemCount() <= 0) {
                if ($path) {
                    $paginator = $this->itemModel->getPaginator([
                        'id' => $currentCar['id'],
                    ]);

                    $cPath = $path;
                    $catname = array_pop($cPath);

                    $parentItemRow = $this->itemModel->getRow([
                        'child'        => $currentCar['id'],
                        'link_catname' => $catname
                    ]);

                    $listBuilder
                        ->setPath($cPath)
                        ->setCurrentItem($parentItemRow);
                }
            }

            $listData = $this->car()->listData($paginator->getCurrentItems(), [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureModel' => $this->picture,
                    'itemModel'    => $this->itemModel,
                    'perspective'  => $this->perspective
                ]),
                'useFrontPictures' => $haveSubcategories,
                'disableLargePictures' => true,
                'picturesDateSort' => true,
                'listBuilder' => $listBuilder
            ]);

            $description = $this->itemModel->getTextOfItem($currentCategory['id'], $this->language());

            $otherPictures = [];
            $otherItemsCount = 0;
            $isLastPage = $paginator->getCurrentPageNumber() == $paginator->count();
            $isCategory = $currentCar['item_type_id'] == Item::CATEGORY;

            if ($haveSubcategories && $isLastPage && $isCategory && ! $isOther) {
                $otherPaginator = $this->itemModel->getPaginator([
                    'item_type_id' => [Item::ENGINE, Item::VEHICLE],
                    'parent'       => $currentCategory['id']
                ]);

                $otherItemsCount = $otherPaginator->getTotalItemCount();

                $pictureRows = $this->picture->getRows([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item'   => [
                        'item_type_id' => [Item::ENGINE, Item::VEHICLE],
                        'parent'       => $currentCategory['id']
                    ],
                    'limit'  => 4,
                    'order'  => 'resolution_desc'
                ]);

                $imageStorage = $this->imageStorage();
                foreach ($pictureRows as $pictureRow) {
                    $imageInfo = $imageStorage->getFormatedImage(
                        $pictureRow['image_id'],
                        'picture-thumb'
                    );

                    $otherPictures[] = [
                        'name' => $this->pic()->name($pictureRow, $language),
                        'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                        'url'  => $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $currentCategory['catname'],
                            'other'            => true,
                            'picture_id'       => $pictureRow['identity']
                        ], [], true)
                    ];
                }
            }

            return [
                'title'            => $title,
                'breadcrumbs'      => $breadcrumbs,
                'paginator'        => $paginator,
                'contributors'     => $contributors,
                'listData'         => $listData,
                'urlParams'        => [
                    'action'           => 'category',
                    'category_catname' => $currentCategory['catname'],
                    'other'            => $isOther,
                    'path'             => $path
                ],
                'description'     => $description,
                'otherItemsCount' => $otherItemsCount,
                'otherPictures'   => $otherPictures,
                'otherCategoryName' => $this->translate('categories/other')
            ];
        });
    }

    public function categoryPicturesAction()
    {
        return $this->doCategoryAction(function (
            $currentCategory,
            $currentCar,
            $isOther,
            $path,
            $breadcrumbs
        ) {

            $paginator = $this->picture->getPaginator([
                'status' => Picture::STATUS_ACCEPTED,
                'order'  => 'resolution_desc',
                'item'   => [
                    'ancestor_or_self' => $currentCar ? $currentCar['id'] : $currentCategory['id']
                ]
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
                ->setCurrentPageNumber($this->params('page'));

            $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
                'width' => 4,
                'url'   => function ($picture) use ($currentCategory, $isOther, $path) {
                    return $this->url()->fromRoute('categories', [
                        'action'           => 'category-picture',
                        'category_catname' => $currentCategory['catname'],
                        'other'            => $isOther,
                        'path'             => $path,
                        'picture_id'       => $picture['identity']
                    ]);
                }
            ]);

            return [
                'breadcrumbs'  => $breadcrumbs,
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ];
        });
    }

    public function categoryPictureAction()
    {
        return $this->doCategoryAction(function (
            $currentCategory,
            $currentCar,
            $isOther,
            $path,
            $breadcrumbs
        ) {

            $filter = [
                'item'   => [
                    'ancestor_or_self' => $currentCar ? $currentCar['id'] : $currentCategory['id']
                ],
                'status' => Picture::STATUS_ACCEPTED,
                'order'  => 'resolution_desc'
            ];

            $pictureFilter = $filter;
            $pictureFilter['identity'] = (string)$this->params('picture_id');

            $picture = $this->picture->getRow($pictureFilter);

            if (! $picture) {
                return $this->notFoundAction();
            }

            return [
                'breadcrumbs' => $breadcrumbs,
                'picture'     => array_replace(
                    $this->pic()->picPageData($picture, $filter, []),
                    [
                        'galleryUrl' => $this->url()->fromRoute('categories', [
                            'action'           => 'category-picture-gallery',
                            'category_catname' => $currentCategory['catname'],
                            'other'            => $isOther,
                            'path'             => $path,
                            'picture_id'       => $picture['identity']
                        ])
                    ]
                )
            ];
        });
    }

    public function categoryPictureGalleryAction()
    {

        return $this->doCategoryAction(function ($currentCategory, $currentCar) {

            $filter = [
                'item'   => [
                    'ancestor_or_self' => $currentCar ? $currentCar['id'] : $currentCategory['id']
                ],
                'status' => Picture::STATUS_ACCEPTED,
                'order'  => 'resolution_desc'
            ];

            $pictureFilter = $filter;
            $pictureId = (string)$this->params('picture_id');
            $pictureFilter['identity'] = $pictureId;

            $picture = $this->picture->getRow($pictureFilter);

            if (! $picture) {
                return $this->notFoundAction();
            }

            return new JsonModel($this->pic()->gallery2($filter, [
                'page'        => $this->params()->fromQuery('page'),
                'pictureId'   => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams'   => [
                    'action' => 'category-picture'
                ]
            ]));
        });
    }

    public function newcarsAction()
    {
        $category = $this->itemModel->getRow([
            'item_type_id' => Item::CATEGORY,
            'id'           => (int)$this->params('item_id')
        ]);
        if (! $category) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'item_type_id' => [Item::VEHICLE, Item::ENGINE],
            'order' => 'ip1.timestamp DESC',
            'parent' => [
                'item_type_id'     => Item::CATEGORY,
                'ancestor_or_self' => $category['id'],
                'linked_in_days'   => Categories::NEW_DAYS
            ],
            'limit' => 20
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->itemModel->getNameData($row, $language);
        }

        $viewModel = new ViewModel([
            'items' => $items
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}

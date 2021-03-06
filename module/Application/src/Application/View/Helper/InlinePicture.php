<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Model\Picture;

class InlinePicture extends AbstractHelper
{
    /**
     * @var Picture
     */
    private $picture;

    public function __construct(Picture $picture)
    {
        $this->picture = $picture;
    }

    public function __invoke($picture)
    {
        $view = $this->view;

        $url = $view->pic($picture)->url();

        $name = $view->pic()->name($picture, $this->view->language());

        $imageHtml = $view->img($picture['image_id'], [
            'format'  => 'picture-thumb',
            'alt'     => $name,
            'title'   => $name,
            'shuffle' => true,
            'class'  => 'rounded border border-light'
        ]);

        return $view->htmlA([
            'href'  => $url,
            'class' => 'd-inline-block rounded'
        ], $imageHtml, false);
    }
}

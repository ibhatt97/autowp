<?php
class Project_View_Helper_Img extends Zend_View_Helper_HtmlElement
{
    private $_attribs;

    /**
     * @var Autowp_Service_ImageStorage
     */
    protected $_imageStorage = null;

    protected function _getImageStorage()
    {
        if (null === $this->_imageStorage) {
            $this->_imageStorage = Zend_Controller_Front::getInstance()
                ->getParam('bootstrap')->getResource('imagestorage');
        }

        return $this->_imageStorage;
    }

    /**
     * @param int $imageId
     * @param array $attribs
     * @return Project_View_Helper_Img
     */
    public function img($imageId, array $attribs = array())
    {
        $this->_attribs = array();
        $format = null;
        if (array_key_exists('format', $attribs)) {
            $format = $attribs['format'];
            unset($attribs['format']);
        }

        if (!$imageId) {
            return $this;
        }

        $storage = $this->_getImageStorage();

        if ($format) {
            $imageInfo = $storage->getFormatedImage($imageId, $format);
        } else {
            $imageInfo = $storage->getImage($imageId);
        }

        if ($imageInfo) {
            $attribs['src'] = $imageInfo->getSrc();
            $this->_attribs = $attribs;
        }

        return $this;
    }

    public function src()
    {
        return isset($this->_attribs['src']) ? $this->_attribs['src'] : '';
    }

    public function __toString()
    {
        try {

            if (isset($this->_attribs['src'])) {
                return $this->view->htmlImg($this->_attribs);
            }

        } catch (Exception $e) {

            print $e->getMessage();

        }

        return '';
    }

    public function exists()
    {
        return isset($this->_attribs['src']) && $this->_attribs['src'];
    }
}

<?php

namespace Application\Hydrator\Api\Filter;

use Zend\Hydrator\Filter\FilterInterface;

class PropertyFilter implements FilterInterface
{
    private $properties = [];

    public function __construct(array $properties)
    {
        $this->setProperties($properties);
    }

    public function setProperties(array $properties)
    {
        $this->properties = $properties;
    }

    public function filter($property)
    {
        return in_array($property, $this->properties);
    }
}

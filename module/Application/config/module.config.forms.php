<?php

namespace Application;

use Zend\Form\ElementFactory;

return [
    'form_elements' => [
        'aliases' => [
            'year' => Form\Element\Year::class,
            'Year' => Form\Element\Year::class,
        ],
        'factories' => [
            Form\Element\Year::class => ElementFactory::class,
        ]
    ]
];

<?php

return [
    'pagination' => [
        'with_pages' => false,
    ],
    'paths' => [
        'models' => 'App\\Models\\',
        'transformers' => 'App\\Transformers\\',
        'repositories' => 'App\\Repositories\\',
        'controllers' => 'App\\Http\\Controllers\\',
        'requests' => 'App\\Http\\Requests\\',
        'authorisation' => 'App\\Services\\Authorisation\\',
        'filter_services' => 'App\\Services\\Filters\\',
    ],

    /**
     * It will automatically use select statement based on the transformer
     */
    'auto_select' => true,

    /**
     * It will eager load the data when using the filtering service
     */
    'auto_eager_load' => true,

    'transformers' => [
        'prefixes' => [
            'hidden_attributes' => '!',
            'custom_attributes' => '@',
            'accessor_attributes' => '~',
            'unmerged_format' => '_',
        ],
    ],
];

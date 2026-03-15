<?php

return [
    'pagination' => [
        'with_pages' => false,
    ],
    'paths' => [
        'models' => 'App\\Models\\',
        'transformers' => 'App\\Transformers\\',
        'repositories' => 'App\\Repositories\\',
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
            'unmerged_format' => '_',
        ],
    ],
];

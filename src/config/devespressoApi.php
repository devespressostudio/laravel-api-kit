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

    'versioning' => [
        /**
         * Enable transformer versioning. When true, getFormatting() builds the
         * format by applying the version chain on top of baseFormat().
         */
        'enabled' => false,

        /**
         * How the active version is detected from the incoming request.
         * Supported: 'route_prefix' | 'header'
         */
        'driver' => 'route_prefix',

        /**
         * Header name used when driver = 'header'.
         */
        'header' => 'X-Api-Version',

        /**
         * Ordered list of known versions. The order determines the cumulative
         * chain — v3 builds on v2, which builds on the base.
         */
        'versions' => [],
    ],
];

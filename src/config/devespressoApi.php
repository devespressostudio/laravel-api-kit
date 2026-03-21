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
     * When true, the filter service will only dispatch request data keys that are
     * explicitly listed in the $explicitFilters param passed to filter(). Any key
     * not in the list is silently ignored, regardless of whether a matching method
     * exists on the service. sort and search are always exempt.
     *
     * Useful when you want to be explicit about what can be filtered per request,
     * rather than relying solely on guarded/role method lists.
     */
    'enable_explicit_filtering' => false,

    /**
     * Ordered list of roles from lowest to highest privilege.
     * A user with a higher role automatically inherits access to all methods
     * available to lower roles in $roleMethods on the filter service.
     *
     * Example: ['moderator', 'editor', 'admin']
     * An 'admin' user can trigger methods listed under 'admin', 'editor', and 'moderator'.
     *
     * Not needed when numeric_roles is true.
     */
    'roles' => [],

    /**
     * Set to true when roles are numeric (e.g. 1, 2, 3 or 10, 20, 30).
     * The hierarchy is derived automatically from the $roleMethods keys —
     * a user with role 3 can trigger methods listed under 3, 2, and 1.
     * No roles list is required when this is enabled.
     */
    'numeric_roles' => false,

    /**
     * Invokable class string that receives the authenticated user and returns their
     * single role as a string or integer. Return null for unauthenticated or roleless users.
     * Must be an invokable class — closures are not supported as config cannot be cached.
     *
     * Example: App\Support\RoleResolver::class
     */
    'role_resolver' => null,

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

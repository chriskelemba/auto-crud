<?php

return [
    'controller_paths' => [],
    'response_formatter' => \AutoCrud\Support\ResponseFormatter::class,
    'api' => [
        'enabled' => env('AUTOCRUD_API_ENABLED', true),
        'prefix' => env('AUTOCRUD_API_PREFIX', 'api'),
        'middleware' => ['api'],
        'route_name_prefix' => env('AUTOCRUD_API_ROUTE_PREFIX', ''),
        'per_page' => (int) env('AUTOCRUD_API_PER_PAGE', 10),
        'allowed_sorts' => [],
        'allowed_filters' => [],
        'allowed_includes' => [],
        'allowed_fields' => [],
    ],
    'web' => [
        'enabled' => env('AUTOCRUD_WEB_ENABLED', false),
        'prefix' => env('AUTOCRUD_WEB_PREFIX', ''),
        'middleware' => ['web'],
        'route_name_prefix' => env('AUTOCRUD_WEB_ROUTE_PREFIX', 'web.'),
    ],
];

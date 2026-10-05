<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    'api_path' => 'api/v1',
    'api_domain' => null,
    'export_path' => 'api.json',
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],
    'info' => [
        'version' => env('API_VERSION', '0.0.1'),
        'description' => 'The Iskenda Academy API provides a backend for the student learning platform.

Currently available: authentication module (register, verify email with OTP, login, and logout).

All endpoints are prefixed with `/api/v1` and return JSON responses.',
    ],
    'ui' => [
        'title' => 'Iskenda Academy API',
    ],
    'renderer' => 'elements',
    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],
    'servers' => null,
    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],
    'extensions' => [],
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for external API integrations used by VerifySource
    | for content verification, fact-checking, and historical analysis.
    |
    */

    'wayback_machine' => [
        'base_url' => 'https://web.archive.org',
        'availability_api' => 'https://archive.org/wayback/available',
        'cdx_api' => 'https://web.archive.org/cdx/search/cdx',
        'timeout' => 30,
        'retry_attempts' => 3,
        'rate_limit' => [
            'requests_per_minute' => 100,
            'requests_per_hour' => 1000,
        ],
    ],

    'news_apis' => [
        'newsapi' => [
            'base_url' => 'https://newsapi.org/v2',
            'api_key' => env('NEWSAPI_KEY'),
            'timeout' => 15,
            'rate_limit' => [
                'requests_per_day' => env('NEWSAPI_REQUESTS_PER_DAY', 500),
            ],
        ],
        'guardian' => [
            'base_url' => 'https://content.guardianapis.com',
            'api_key' => env('GUARDIAN_API_KEY'),
            'timeout' => 15,
            'rate_limit' => [
                'requests_per_day' => env('GUARDIAN_REQUESTS_PER_DAY', 5000),
            ],
        ],
    ],

    'fact_check_apis' => [
        'google_factcheck' => [
            'base_url' => 'https://factchecktools.googleapis.com/v1alpha1/claims:search',
            'api_key' => env('GOOGLE_FACTCHECK_API_KEY'),
            'timeout' => 10,
            'rate_limit' => [
                'requests_per_day' => env('GOOGLE_FACTCHECK_REQUESTS_PER_DAY', 1000),
            ],
        ],
        'factcheck_org' => [
            'base_url' => 'https://www.factcheck.org/api',
            'timeout' => 15,
            'rate_limit' => [
                'requests_per_minute' => 30,
            ],
        ],
    ],

    'url_validation' => [
        'virustotal' => [
            'base_url' => 'https://www.virustotal.com/vtapi/v2',
            'api_key' => env('VIRUSTOTAL_API_KEY'),
            'timeout' => 20,
            'rate_limit' => [
                'requests_per_minute' => 4, // Free tier limit
            ],
        ],
        'urlvoid' => [
            'base_url' => 'https://api.urlvoid.com/1000',
            'api_key' => env('URLVOID_API_KEY'),
            'timeout' => 15,
            'rate_limit' => [
                'requests_per_day' => 1000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Settings
    |--------------------------------------------------------------------------
    */
    
    'global' => [
        'cache_duration' => 3600, // Cache API responses for 1 hour
        'max_retries' => 3,
        'retry_delay' => 2, // seconds
        'user_agent' => 'VerifySource/1.0 (https://github.com/verifySource; content verification bot)',
        'timeout' => 30,
        'enable_caching' => env('EXTERNAL_API_CACHING', true),
        'enable_rate_limiting' => env('EXTERNAL_API_RATE_LIMITING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    */
    
    'features' => [
        'wayback_machine' => env('ENABLE_WAYBACK_MACHINE', true),
        'news_apis' => env('ENABLE_NEWS_APIS', true),
        'fact_check_apis' => env('ENABLE_FACT_CHECK_APIS', false),
        'url_validation' => env('ENABLE_URL_VALIDATION', true),
    ],
];
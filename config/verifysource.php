<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Scraper Settings
    |--------------------------------------------------------------------------
    */
    'scraper' => [
        'timeout' => env('SCRAPER_TIMEOUT', 30),
        'retries' => env('SCRAPER_RETRIES', 3),
        'delay_between_requests' => env('SCRAPER_DELAY', 1), // seconds
        'max_content_length' => env('SCRAPER_MAX_CONTENT', 10485760), // 10MB
        'respect_robots_txt' => env('SCRAPER_RESPECT_ROBOTS', true),
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Crawl Job Settings
    |--------------------------------------------------------------------------
    */
    'crawl_jobs' => [
        'max_retries' => env('CRAWL_MAX_RETRIES', 3),
        'default_priority' => env('CRAWL_DEFAULT_PRIORITY', 0),
        'cleanup_days' => env('CRAWL_CLEANUP_DAYS', 30),
        'batch_size' => env('CRAWL_BATCH_SIZE', 50),
        'concurrent_jobs' => env('CRAWL_CONCURRENT_JOBS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Processing Settings
    |--------------------------------------------------------------------------
    */
    'content' => [
        'min_content_length' => env('CONTENT_MIN_LENGTH', 100),
        'max_content_length' => env('CONTENT_MAX_LENGTH', 1048576), // 1MB
        'excerpt_length' => env('CONTENT_EXCERPT_LENGTH', 160),
        'duplicate_threshold' => env('CONTENT_DUPLICATE_THRESHOLD', 0.8),
        'quality_threshold' => env('CONTENT_QUALITY_THRESHOLD', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Crawler Settings
    |--------------------------------------------------------------------------
    */
    'python' => [
        'executable' => env('PYTHON_EXECUTABLE', 'python3'),
        'enabled' => env('PYTHON_CRAWLER_ENABLED', true),
        'timeout' => env('PYTHON_CRAWLER_TIMEOUT', 300),
        'fallback_to_php' => env('PYTHON_FALLBACK_TO_PHP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Crawler Settings (for backward compatibility)
    |--------------------------------------------------------------------------
    */
    'crawler' => [
        'max_pages_per_domain' => env('CRAWLER_MAX_PAGES_PER_DOMAIN', 100),
        'delay_between_requests' => env('CRAWLER_DELAY_BETWEEN_REQUESTS', 1),
        'user_agent' => env('CRAWLER_USER_AGENT', 'VerifySource Bot 1.0'),
        'timeout' => 30,
        'max_retries' => 3,
    ],

    'search' => [
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
            'key' => env('MEILISEARCH_KEY', ''),
            'index_prefix' => 'verifysource_',
        ],
        'qdrant' => [
            'host' => env('QDRANT_HOST', 'http://127.0.0.1:6333'),
            'api_key' => env('QDRANT_API_KEY', ''),
            'collection_name' => 'verifysource_articles',
        ],
    ],

    'verification' => [
        'similarity_threshold' => 0.8,
        'credibility_threshold' => 0.5,
        'max_results' => 10,
        'cache_duration' => 3600,
    ],

    'apis' => [
        'wayback_machine' => [
            'url' => env('WAYBACK_MACHINE_API_URL', 'https://web.archive.org/cdx/search/cdx'),
            'timeout' => 10,
        ],
        'duckduckgo' => [
            'url' => env('DUCKDUCKGO_API_URL', 'https://api.duckduckgo.com/'),
            'timeout' => 10,
        ],
    ],

    'scoring' => [
        'credibility_weights' => [
            'domain_authority' => 0.4,
            'content_freshness' => 0.2,
            'source_verification' => 0.2,
            'user_feedback' => 0.2,
        ],
        'similarity_weights' => [
            'exact_match' => 1.0,
            'semantic_similarity' => 0.8,
            'partial_match' => 0.6,
        ],
    ],
];
